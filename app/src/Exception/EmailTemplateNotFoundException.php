<?php
declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Thrown when an email template cannot be resolved by slug.
 */
class EmailTemplateNotFoundException extends RuntimeException
{
    /**
     * @param string $slug Template slug
     * @return self
     */
    public static function forSlug(string $slug): self
    {
        return new self("No active email template found for slug '{$slug}'.");
    }
}
