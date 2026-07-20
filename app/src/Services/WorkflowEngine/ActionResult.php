<?php
declare(strict_types=1);

namespace App\Services\WorkflowEngine;

/**
 * Standard workflow action result envelope.
 */
final class ActionResult
{
    /**
     * @param bool $success Whether the action succeeded
     * @param array $data Public action output data
     * @param string|null $error Failure reason
     */
    private function __construct(
        private bool $success,
        private array $data = [],
        private ?string $error = null,
    ) {
    }

    /**
     * Create a successful action result.
     *
     * @param array $data Action output data
     * @return self
     */
    public static function success(array $data = []): self
    {
        return new self(true, $data);
    }

    /**
     * Create a failed action result.
     *
     * @param string $error Failure reason
     * @param array $data Optional action output data
     * @return self
     */
    public static function failure(string $error, array $data = []): self
    {
        return new self(false, $data, $error);
    }

    /**
     * Convert the result to the workflow context payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
        ];

        return $result + $this->data;
    }
}
