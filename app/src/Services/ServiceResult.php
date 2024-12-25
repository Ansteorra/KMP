<?php

declare(strict_types=1);

namespace App\Services;

class ServiceResult
{
    public bool $status;
    public ?string $reason = null;
    public $data = null;

    public function __construct(bool $success, ?string $reason = null, $data = null)
    {
        $this->success = $success;
        if ($reason !== null) {
            $this->reason = $reason;
        }
        if ($data !== null) {
            $this->data = $data;
        }
    }
}