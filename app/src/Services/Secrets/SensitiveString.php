<?php
declare(strict_types=1);

namespace App\Services\Secrets;

use JsonSerializable;
use LogicException;

/**
 * Holds plaintext secret material while redacting common debug surfaces.
 */
final class SensitiveString implements JsonSerializable
{
    /**
     * Constructor.
     *
     * @param string $value Plaintext secret value
     */
    public function __construct(private readonly string $value)
    {
    }

    /**
     * Reveal the plaintext secret value.
     *
     * @return string
     */
    public function reveal(): string
    {
        return $this->value;
    }

    /**
     * Check whether the secret value is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    /**
     * Return the plaintext length without revealing the value.
     *
     * @return int
     */
    public function length(): int
    {
        return strlen($this->value);
    }

    /**
     * Prevent implicit string conversion that could send redacted placeholders downstream.
     *
     * @return string
     */
    public function __toString(): string
    {
        throw new LogicException('SensitiveString cannot be converted to string; call reveal() explicitly.');
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'value' => '***',
            'length' => $this->length(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function __serialize(): array
    {
        return ['value' => '***'];
    }

    /**
     * @param array<string, mixed> $data Serialized data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        throw new LogicException('SensitiveString values cannot be unserialized.');
    }

    /**
     * Return a redacted JSON representation.
     *
     * @return string
     */
    public function jsonSerialize(): string
    {
        return '***';
    }
}
