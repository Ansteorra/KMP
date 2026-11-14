<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/** Restrict tenant mail overrides to mail credentials and provider destinations. */
final class TenantMailConfigGuard
{
    private const SECRET_NAMES = [
        'smtp' => ['smtp-password', 'email.smtp_password'],
        'sendgrid' => ['email-api-key', 'sendgrid-key', 'email.sendgrid.api_key'],
        'resend' => ['email-api-key', 'resend-key', 'email.resend.api_key'],
        'azure' => ['acs-connection', 'email.azure.connection_string'],
    ];

    private const API_ENDPOINTS = [
        'sendgrid' => ['https://api.sendgrid.com/v3/mail/send', 'https://api.eu.sendgrid.com/v3/mail/send'],
        'resend' => ['https://api.resend.com/emails'],
    ];

    /**
     * @param string $slug Tenant slug
     * @param array<string, mixed> $email Tenant email configuration
     */
    public function assertAllowed(string $slug, array $email): void
    {
        $mode = strtolower(trim((string)($email['mode'] ?? 'default')));
        if (!isset(self::SECRET_NAMES[$mode])) {
            return;
        }

        $field = match ($mode) {
            'smtp' => 'smtp_password_secret_ref',
            'azure' => 'connection_string_secret_ref',
            default => 'api_secret_ref',
        };
        $reference = trim((string)($email[$field] ?? ''));
        if ($reference !== '') {
            foreach (['secret://', 'db://'] as $scheme) {
                if (str_starts_with($reference, $scheme)) {
                    $reference = substr($reference, strlen($scheme));
                    break;
                }
            }
            $allowed = array_map(
                static fn(string $name): string => 'tenant.' . $slug . '.' . $name,
                self::SECRET_NAMES[$mode],
            );
            if (!in_array($reference, $allowed, true)) {
                throw new InvalidArgumentException(
                    'Use an approved email secret reference for this tenant and provider.',
                );
            }
        }

        $endpoint = trim((string)($email['endpoint_url'] ?? ''));
        if (
            isset(self::API_ENDPOINTS[$mode]) && $endpoint !== ''
            && !in_array($endpoint, self::API_ENDPOINTS[$mode], true)
        ) {
            throw new InvalidArgumentException(
                'Use the official SendGrid or Resend mail endpoint for the selected provider.',
            );
        }
    }
}
