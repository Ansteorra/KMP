<?php
declare(strict_types=1);

namespace App\Services;

use Cake\Http\Session;
use Cake\Utility\Hash;

/** Request-local session for scheduled grid reads; never touches the browser session. */
final class GridReportSession extends Session
{
    private array $values = [];

    /** Keep all state in memory without starting a PHP session. */
    public function __construct(array $config = [])
    {
    }

    /** @inheritDoc */
    public function read(?string $name = null, mixed $default = null): mixed
    {
        return $name === null ? $this->values : Hash::get($this->values, $name, $default);
    }

    /** @inheritDoc */
    public function write(array|string $name, mixed $value = null): void
    {
        foreach (is_array($name) ? $name : [$name => $value] as $key => $item) {
            $this->values = Hash::insert($this->values, $key, $item);
        }
    }

    /** @inheritDoc */
    public function check(?string $name = null): bool
    {
        return $name === null ? $this->values !== [] : $this->read($name) !== null;
    }

    /** @inheritDoc */
    public function delete(string $name): void
    {
        $this->values = Hash::remove($this->values, $name);
    }

    /** @inheritDoc */
    public function start(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function started(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function close(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function id(?string $id = null): string
    {
        return '';
    }

    /** @inheritDoc */
    public function destroy(): void
    {
        $this->values = [];
    }

    /** @inheritDoc */
    public function clear(bool $renew = false): void
    {
        $this->values = [];
    }

    /** @inheritDoc */
    public function renew(): void
    {
    }
}
