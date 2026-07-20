<?php
declare(strict_types=1);

namespace App\Services\ActionItems;

/**
 * Structured metadata for a plugin-provided action item completion form.
 */
class ActionItemCompletionForm
{
    /**
     * @param string $provider Provider key.
     * @param string $title Form title.
     * @param string $description Form description.
     * @param array<int, array<string, mixed>> $fields Field metadata.
     * @param array<string, mixed> $payload Additional payload for JavaScript/controllers.
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $title,
        public readonly string $description,
        public readonly array $fields,
        public readonly array $payload = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'title' => $this->title,
            'description' => $this->description,
            'fields' => $this->fields,
            'payload' => $this->payload,
        ];
    }
}
