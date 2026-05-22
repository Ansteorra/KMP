<?php
declare(strict_types=1);

namespace App\Services\Secrets;

interface WritableSecretStoreInterface extends SecretStoreInterface
{
    /**
     * Store or replace a secret value.
     *
     * @param string $name Secret name
     * @param \App\Services\Secrets\SensitiveString $value Secret value
     * @return void
     */
    public function put(string $name, SensitiveString $value): void;

    /**
     * Delete a secret value.
     *
     * @param string $name Secret name
     * @return void
     */
    public function delete(string $name): void;
}
