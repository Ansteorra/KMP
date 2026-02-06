<?php

declare(strict_types=1);

namespace App\Model\Entity;

use App\KMP\TimezoneHelper;
use Cake\Utility\Security;

/**
 * ServicePrincipalToken Entity - API Authentication Tokens
 *
 * Supports multiple tokens per service principal for rotation.
 *
 * @property int $id
 * @property int $service_principal_id
 * @property string $token_hash
 * @property string|null $name
 * @property \Cake\I18n\DateTime|null $expires_at
 * @property \Cake\I18n\DateTime|null $last_used_at
 * @property int|null $created_by
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\ServicePrincipal $service_principal
 * @property \App\Model\Entity\Member|null $creator
 */
class ServicePrincipalToken extends BaseEntity
{
    /**
     * Fields accessible for mass assignment.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'service_principal_id' => true,
        'name' => true,
        'expires_at' => true,
        'last_used_at' => true,
        'created_by' => true,
    ];

    /** @var array<string> Fields hidden from serialization */
    protected array $_hidden = [
        'token_hash',
    ];

    /**
     * Generate a new token (plain text - hash before storing).
     *
     * @return string 32-byte base64-encoded token
     */
    public static function generateToken(): string
    {
        return base64_encode(Security::randomBytes(32));
    }

    /**
     * Hash a token for storage.
     *
     * @param string $token Plain text token
     * @return string Hashed token (SHA-256)
     */
    public static function hashToken(string $token): string
    {
        // Use SHA-256 for tokens (faster than bcrypt, secure for random tokens)
        return hash('sha256', $token);
    }

    /**
     * Check if the token has expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Get formatted expiration date for display.
     *
     * @return string
     */
    protected function _getExpiresAtToString(): string
    {
        if ($this->expires_at === null) {
            return 'Never';
        }

        return TimezoneHelper::formatDateTime($this->expires_at);
    }

    /**
     * Get formatted last used date for display.
     *
     * @return string
     */
    protected function _getLastUsedAtToString(): string
    {
        if ($this->last_used_at === null) {
            return 'Never';
        }

        return TimezoneHelper::formatDateTime($this->last_used_at);
    }

    /**
     * Get masked token hash for display (first/last 8 chars).
     *
     * @return string
     */
    protected function _getMaskedHash(): string
    {
        if (empty($this->token_hash)) {
            return '';
        }

        $first = substr($this->token_hash, 0, 8);
        $last = substr($this->token_hash, -8);

        return $first . '...' . $last;
    }
}
