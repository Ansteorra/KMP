<?php
declare(strict_types=1);

namespace App\Services;

/** Plugin-owned metadata; implementations must not retain tenant tables. */
interface UiUpdateProviderInterface
{
    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array;
}
