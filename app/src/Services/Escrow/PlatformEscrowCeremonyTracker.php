<?php
declare(strict_types=1);

namespace App\Services\Escrow;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Text;

class PlatformEscrowCeremonyTracker implements EscrowCeremonyTrackerInterface
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
    public function recordCeremony(EscrowCeremonyRequest $request): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $ceremony = [
            'id' => Text::uuid(),
            'tenant_id' => $request->tenantId,
            'key_name' => $request->keyName,
            'key_version' => $request->keyVersion,
            'threshold' => $request->threshold,
            'share_count' => $request->shareCount,
            'status' => $request->status,
            'metadata' => json_encode($this->redactor()->redactMetadata($request->metadata), JSON_THROW_ON_ERROR),
            'notes' => $this->redactor()->redactNotes($request->notes),
            'created_by_platform_user_id' => $request->createdByPlatformUserId,
            'completed_at' => null,
            'created_at' => $now,
        ];

        $this->connection()->transactional(function () use ($ceremony, $request, $now): void {
            $this->connection()->insert('escrow_ceremonies', $ceremony);
            foreach ($request->shareEnvelopes as $offset => $envelope) {
                $this->connection()->insert('escrow_share_envelopes', [
                    'id' => Text::uuid(),
                    'escrow_ceremony_id' => $ceremony['id'],
                    'share_index' => (int)($envelope['share_index'] ?? $offset + 1),
                    'custodian_label_hash' => $this->hashLabel((string)($envelope['custodian_label'] ?? '')),
                    'envelope_label_hash' => $this->hashLabel((string)($envelope['envelope_label'] ?? '')),
                    'status' => (string)($envelope['status'] ?? 'sealed'),
                    'verified_at' => null,
                    'metadata' => json_encode(
                        $this->redactor()->redactMetadata((array)($envelope['metadata'] ?? [])),
                        JSON_THROW_ON_ERROR,
                    ),
                    'created_at' => $now,
                ]);
            }
        });

        return $ceremony;
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

    /**
     * Hash a sealed-envelope label before persistence.
     *
     * @param string $label Envelope or custodian label
     * @return string
     */
    private function hashLabel(string $label): string
    {
        return hash('sha256', $label);
    }
}
