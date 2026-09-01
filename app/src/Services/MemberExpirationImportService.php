<?php
declare(strict_types=1);

namespace App\Services;

use App\Model\Table\MembersTable;
use Cake\I18n\Date;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

/**
 * Imports member expiration dates from the shared two-column CSV format.
 */
class MemberExpirationImportService
{
    public const TYPE_MEMBERSHIP = 'membership';
    public const TYPE_BACKGROUND_CHECK = 'background_check';

    private const IMPORT_FIELDS = [
        self::TYPE_MEMBERSHIP => 'membership_expires_on',
        self::TYPE_BACKGROUND_CHECK => 'background_check_expires_on',
    ];

    private const IMPORT_HEADERS = [
        self::TYPE_MEMBERSHIP => [
            'expiration date',
            'membership expiration date',
        ],
        self::TYPE_BACKGROUND_CHECK => [
            'expiration date',
            'background check expiration date',
        ],
    ];

    private MembersTable $Members;

    /**
     * @param \App\Model\Table\MembersTable|null $members Optional table override for tests
     */
    public function __construct(?MembersTable $members = null)
    {
        /** @var \App\Model\Table\MembersTable $membersTable */
        $membersTable = $members ?? TableRegistry::getTableLocator()->get('Members');
        $this->Members = $membersTable;
    }

    /**
     * Return the supported import types for form options.
     *
     * @return array<string, string>
     */
    public static function getImportTypeOptions(): array
    {
        return [
            self::TYPE_MEMBERSHIP => (string)__('Membership expiration dates'),
            self::TYPE_BACKGROUND_CHECK => (string)__('Background check expiration dates'),
        ];
    }

    /**
     * Import one expiration-date type from an uploaded CSV file.
     */
    public function import(UploadedFileInterface $file, string $importType): ServiceResult
    {
        $field = self::IMPORT_FIELDS[$importType] ?? null;
        if ($field === null) {
            return new ServiceResult(false, (string)__('Choose a valid expiration date type.'));
        }

        if ($file->getError() !== UPLOAD_ERR_OK || $file->getSize() <= 0) {
            return new ServiceResult(false, (string)__('Choose a non-empty CSV file to upload.'));
        }

        $clientFilename = trim((string)$file->getClientFilename());
        if ($clientFilename !== '' && strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION)) !== 'csv') {
            return new ServiceResult(false, (string)__('The uploaded file must use the .csv extension.'));
        }

        $uri = $file->getStream()->getMetadata('uri');
        if (!is_string($uri) || $uri === '') {
            return new ServiceResult(false, (string)__('The uploaded CSV file could not be read.'));
        }

        $handle = fopen($uri, 'rb');
        if ($handle === false) {
            return new ServiceResult(false, (string)__('The uploaded CSV file could not be read.'));
        }

        try {
            $parsedRows = $this->parseCsv($handle, $importType);
            if ($parsedRows instanceof ServiceResult) {
                return $parsedRows;
            }
        } finally {
            fclose($handle);
        }

        $membershipNumbers = array_keys($parsedRows);
        $members = $this->Members->find()
            ->where(['membership_number IN' => $membershipNumbers])
            ->all();
        $membersByNumber = [];
        $duplicateMembershipNumbers = [];
        foreach ($members as $member) {
            $membershipNumber = (string)$member->membership_number;
            if (isset($membersByNumber[$membershipNumber])) {
                $duplicateMembershipNumbers[] = $membershipNumber;
                continue;
            }
            $membersByNumber[$membershipNumber] = $member;
        }
        if ($duplicateMembershipNumbers !== []) {
            $duplicateMembershipNumbers = array_values(array_unique($duplicateMembershipNumbers));
            sort($duplicateMembershipNumbers);

            return new ServiceResult(false, (string)__(
                'Multiple member records use these membership numbers: {0}. '
                . 'Resolve the duplicate records before importing.',
                implode(', ', $duplicateMembershipNumbers),
            ));
        }

        $updatedCount = 0;
        $notFoundMembershipNumbers = [];
        $connection = $this->Members->getConnection();
        $connection->enableSavePoints();
        $connection->begin();

        try {
            foreach ($parsedRows as $membershipNumber => $expirationDate) {
                $member = $membersByNumber[$membershipNumber] ?? null;
                if ($member === null) {
                    $notFoundMembershipNumbers[] = $membershipNumber;
                    continue;
                }

                $member->set($field, $expirationDate);
                $member->setDirty($field, true);
                if (!$this->Members->save($member)) {
                    throw new RuntimeException((string)__(
                        'The expiration date for member number {0} could not be saved.',
                        $membershipNumber,
                    ));
                }
                $updatedCount++;
            }

            $connection->commit();
        } catch (Throwable $e) {
            if ($connection->inTransaction()) {
                $connection->rollback();
            }
            Log::error('Member expiration CSV import failed.', [
                'import_type' => $importType,
                'error' => $e->getMessage(),
            ]);

            return new ServiceResult(
                false,
                (string)__('No expiration dates were imported. Please try again.'),
            );
        }

        return new ServiceResult(true, null, [
            'importType' => $importType,
            'field' => $field,
            'updatedCount' => $updatedCount,
            'notFoundCount' => count($notFoundMembershipNumbers),
            'notFoundMembershipNumbers' => $notFoundMembershipNumbers,
        ]);
    }

    /**
     * Parse and validate the shared Member Number / Expiration Date CSV format.
     *
     * @param resource $handle Readable CSV stream
     * @param string $importType Selected expiration-date type
     * @return \App\Services\ServiceResult|array<string, \Cake\I18n\Date>
     */
    private function parseCsv($handle, string $importType): array|ServiceResult
    {
        $header = fgetcsv($handle, null, ',', '"', '');
        if ($header === false) {
            return new ServiceResult(false, (string)__('The CSV file is empty.'));
        }

        $firstHeader = preg_replace('/^\xEF\xBB\xBF/', '', trim((string)($header[0] ?? '')));
        $secondHeader = trim((string)($header[1] ?? ''));
        $validExpirationHeaders = self::IMPORT_HEADERS[$importType];
        if (
            count($header) !== 2
            || strtolower((string)$firstHeader) !== 'member number'
            || !in_array(strtolower($secondHeader), $validExpirationHeaders, true)
        ) {
            return new ServiceResult(
                false,
                (string)__(
                    'The CSV header must begin with Member Number and match the selected expiration date type.',
                ),
            );
        }

        $rows = [];
        $lineNumber = 1;
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $lineNumber++;
            if ($this->isBlankRow($row)) {
                continue;
            }

            if (count($row) !== 2) {
                return new ServiceResult(false, (string)__(
                    'CSV line {0} must contain exactly two columns.',
                    $lineNumber,
                ));
            }

            $membershipNumber = trim((string)($row[0] ?? ''));
            $dateValue = trim((string)($row[1] ?? ''));
            if ($membershipNumber === '' || $dateValue === '') {
                return new ServiceResult(false, (string)__(
                    'CSV line {0} must include both a member number and an expiration date.',
                    $lineNumber,
                ));
            }
            if (isset($rows[$membershipNumber])) {
                return new ServiceResult(false, (string)__(
                    'CSV line {0} repeats member number {1}.',
                    $lineNumber,
                    $membershipNumber,
                ));
            }

            $dateParts = explode('-', $dateValue);
            if (
                count($dateParts) !== 3
                || !ctype_digit($dateParts[0])
                || strlen($dateParts[0]) !== 4
                || !ctype_digit($dateParts[1])
                || strlen($dateParts[1]) !== 2
                || !ctype_digit($dateParts[2])
                || strlen($dateParts[2]) !== 2
                || !checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])
            ) {
                return new ServiceResult(false, (string)__(
                    'CSV line {0} has an invalid expiration date. Use YYYY-MM-DD.',
                    $lineNumber,
                ));
            }

            $rows[$membershipNumber] = new Date($dateValue);
        }

        if ($rows === []) {
            return new ServiceResult(false, (string)__('The CSV file does not contain any data rows.'));
        }

        return $rows;
    }

    /**
     * @param array<int, mixed> $row Parsed CSV row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }

        return true;
    }
}
