<?php
declare(strict_types=1);

namespace App\Services\Escrow;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Text;
use DateTimeZone;

class PlatformEscrowVerificationRecorder implements EscrowVerificationRecorderInterface
{
    /**
     * Constructor.
     *
     * @param \Cake\Database\Connection|null $connection Optional platform connection
     * @param \App\Services\Escrow\EscrowSensitiveValueRedactor|null $redactor Optional redactor
     */
    public function __construct(
        private readonly ?Connection $connection = null,
        private readonly ?EscrowSensitiveValueRedactor $redactor = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function recordVerification(EscrowVerificationRequest $request): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $record = [
            'id' => Text::uuid(),
            'escrow_ceremony_id' => $request->escrowCeremonyId,
            'tenant_id' => $request->tenantId,
            'key_name' => $request->keyName,
            'key_version' => $request->keyVersion,
            'threshold' => $request->threshold,
            'share_count' => $request->shareCount,
            'verified_at' => $request->verifiedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'verified_by_platform_user_id' => $request->verifiedByPlatformUserId,
            'status' => $request->status,
            'metadata' => json_encode($this->redactor()->redactMetadata($request->metadata), JSON_THROW_ON_ERROR),
            'notes' => $this->redactor()->redactNotes($request->notes),
            'created_at' => $now,
        ];

        $this->connection()->insert('escrow_verifications', $record);

        return $record;
    }

    /**
     * Resolve the platform connection.
     *
     * @return \Cake\Database\Connection
     */
    private function connection(): Connection
    {
        return $this->connection ?? ConnectionManager::get('platform');
    }

    /**
     * Resolve the metadata redactor.
     *
     * @return \App\Services\Escrow\EscrowSensitiveValueRedactor
     */
    private function redactor(): EscrowSensitiveValueRedactor
    {
        return $this->redactor ?? new EscrowSensitiveValueRedactor();
    }
}
