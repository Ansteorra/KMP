<?php

namespace App\KMP;


interface KMPPluginInterface
{
    public function getMigrationOrder(): int;
}