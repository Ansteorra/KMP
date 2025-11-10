<?php
declare(strict_types=1);

/**
 * Test fixture DTO for Queue plugin tests
 */

namespace TestApp\Dto;

/**
 * Simple DTO class for testing data serialization in Queue jobs
 */
class MyTaskDto
{
    /**
     * @var array<string, mixed>
     */
    protected array $data;

    /**
     * @param array<string, mixed> $data Data to store
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Convert DTO to array for storage
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
