<?php
declare(strict_types=1);

namespace App\KMP;

interface KMPPluginInterface
{
    public function getMigrationOrder(): int;
}
