<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Table\MembersTable;
use App\Services\MemberExpirationImportService;
use App\Test\TestCase\BaseTestCase;
use Laminas\Diactoros\UploadedFile;
use RuntimeException;

class MemberExpirationImportServiceTest extends BaseTestCase
{
    private MembersTable $Members;

    private MemberExpirationImportService $service;

    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->service = new MemberExpirationImportService($this->Members);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function testImportsMembershipExpirationDates(): void
    {
        $result = $this->service->import(
            $this->uploadedCsv("Member Number,Expiration Date\n1111111,2031-04-15\n"),
            MemberExpirationImportService::TYPE_MEMBERSHIP,
        );

        $this->assertTrue($result->success, $result->reason ?? 'Import failed');
        $this->assertSame('membership_expires_on', $result->data['field']);
        $this->assertSame(1, $result->data['updatedCount']);
        $this->assertSame(0, $result->data['notFoundCount']);
        $this->assertSame('2031-04-15', $this->memberDate(self::TEST_MEMBER_AGATHA_ID, 'membership_expires_on'));
        $this->assertNull($this->Members->get(self::TEST_MEMBER_AGATHA_ID)->background_check_expires_on);
    }

    public function testImportsBackgroundCheckExpirationDatesWithoutChangingMembershipDate(): void
    {
        $originalMembershipDate = $this->memberDate(
            self::TEST_MEMBER_BRYCE_ID,
            'membership_expires_on',
        );

        $result = $this->service->import(
            $this->uploadedCsv(
                "Member Number,Background Check Expiration Date\n222222222,2032-05-16\n",
            ),
            MemberExpirationImportService::TYPE_BACKGROUND_CHECK,
        );

        $this->assertTrue($result->success, $result->reason ?? 'Import failed');
        $this->assertSame('background_check_expires_on', $result->data['field']);
        $this->assertSame(1, $result->data['updatedCount']);
        $this->assertSame('2032-05-16', $this->memberDate(
            self::TEST_MEMBER_BRYCE_ID,
            'background_check_expires_on',
        ));
        $this->assertSame(
            $originalMembershipDate,
            $this->memberDate(self::TEST_MEMBER_BRYCE_ID, 'membership_expires_on'),
        );
    }

    public function testRejectsInvalidHeaders(): void
    {
        $invalidCsvFiles = [
            'wrong member-number heading' => "Membership Number,Expiration Date\n1111111,2031-04-15\n",
            'wrong expiration heading' => "Member Number,Expires On\n1111111,2031-04-15\n",
            'extra heading' => "Member Number,Expiration Date,Notes\n1111111,2031-04-15,test\n",
        ];

        foreach ($invalidCsvFiles as $description => $csv) {
            $result = $this->service->import(
                $this->uploadedCsv($csv),
                MemberExpirationImportService::TYPE_MEMBERSHIP,
            );

            $this->assertFalse($result->success, $description);
            $this->assertStringContainsString('CSV header', (string)$result->reason, $description);
        }
    }

    public function testRejectsTypeSpecificHeaderThatDoesNotMatchSelection(): void
    {
        $membershipDate = $this->memberDate(self::TEST_MEMBER_AGATHA_ID, 'membership_expires_on');
        $backgroundCheckDate = $this->memberDate(
            self::TEST_MEMBER_BRYCE_ID,
            'background_check_expires_on',
        );
        $cases = [
            [
                MemberExpirationImportService::TYPE_MEMBERSHIP,
                "Member Number,Background Check Expiration Date\n1111111,2035-08-19\n",
            ],
            [
                MemberExpirationImportService::TYPE_BACKGROUND_CHECK,
                "Member Number,Membership Expiration Date\n222222222,2035-08-20\n",
            ],
        ];

        foreach ($cases as [$importType, $csv]) {
            $result = $this->service->import($this->uploadedCsv($csv), $importType);

            $this->assertFalse($result->success);
            $this->assertStringContainsString('selected expiration date type', (string)$result->reason);
        }

        $this->assertSame(
            $membershipDate,
            $this->memberDate(self::TEST_MEMBER_AGATHA_ID, 'membership_expires_on'),
        );
        $this->assertSame(
            $backgroundCheckDate,
            $this->memberDate(self::TEST_MEMBER_BRYCE_ID, 'background_check_expires_on'),
        );
    }

    public function testRejectsInvalidDatesAndDoesNotWriteEarlierRows(): void
    {
        $originalDate = $this->memberDate(self::TEST_MEMBER_AGATHA_ID, 'membership_expires_on');
        $invalidDates = ['04/15/2031', '2031-4-15', '2031-02-29'];

        foreach ($invalidDates as $invalidDate) {
            $result = $this->service->import(
                $this->uploadedCsv(
                    "Member Number,Expiration Date\n"
                    . "1111111,2031-04-15\n"
                    . "222222222,{$invalidDate}\n",
                ),
                MemberExpirationImportService::TYPE_MEMBERSHIP,
            );

            $this->assertFalse($result->success, $invalidDate);
            $this->assertStringContainsString('invalid expiration date', (string)$result->reason);
            $this->assertSame(
                $originalDate,
                $this->memberDate(self::TEST_MEMBER_AGATHA_ID, 'membership_expires_on'),
                "The valid row before {$invalidDate} must not be written.",
            );
        }
    }

    public function testReportsUnknownMembershipNumbersAndImportsKnownMembers(): void
    {
        $result = $this->service->import(
            $this->uploadedCsv(
                "Member Number,Membership Expiration Date\n"
                . "1111111,2033-06-17\n"
                . "UNKNOWN-123,2033-06-18\n",
            ),
            MemberExpirationImportService::TYPE_MEMBERSHIP,
        );

        $this->assertTrue($result->success, $result->reason ?? 'Import failed');
        $this->assertSame(1, $result->data['updatedCount']);
        $this->assertSame(1, $result->data['notFoundCount']);
        $this->assertSame(['UNKNOWN-123'], $result->data['notFoundMembershipNumbers']);
        $this->assertSame('2033-06-17', $this->memberDate(
            self::TEST_MEMBER_AGATHA_ID,
            'membership_expires_on',
        ));
    }

    public function testRejectsDuplicateMembershipNumbersInMemberRecords(): void
    {
        $agathaOriginalDate = $this->memberDate(self::TEST_MEMBER_AGATHA_ID, 'membership_expires_on');
        $bryceOriginalDate = $this->memberDate(self::TEST_MEMBER_BRYCE_ID, 'membership_expires_on');
        $bryce = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $bryce->membership_number = '1111111';
        $this->Members->saveOrFail($bryce);

        $result = $this->service->import(
            $this->uploadedCsv("Member Number,Expiration Date\n1111111,2034-07-18\n"),
            MemberExpirationImportService::TYPE_MEMBERSHIP,
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Multiple member records', (string)$result->reason);
        $this->assertSame(
            $agathaOriginalDate,
            $this->memberDate(self::TEST_MEMBER_AGATHA_ID, 'membership_expires_on'),
        );
        $this->assertSame(
            $bryceOriginalDate,
            $this->memberDate(self::TEST_MEMBER_BRYCE_ID, 'membership_expires_on'),
        );
    }

    private function uploadedCsv(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'kmp-expiration-import-');
        if ($path === false || file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to create the temporary CSV fixture.');
        }
        $this->temporaryFiles[] = $path;

        return new UploadedFile(
            $path,
            strlen($contents),
            UPLOAD_ERR_OK,
            'expiration-dates.csv',
            'text/csv',
        );
    }

    private function memberDate(int $memberId, string $field): ?string
    {
        $value = $this->Members->get($memberId)->get($field);

        return $value?->format('Y-m-d');
    }
}
