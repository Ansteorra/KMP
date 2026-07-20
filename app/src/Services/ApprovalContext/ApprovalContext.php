<?php

declare(strict_types=1);

namespace App\Services\ApprovalContext;

/**
 * Value object representing display context for a pending approval.
 *
 * Carries the title, description, structured fields, and metadata
 * needed to render an approval card in the unified approvals UI.
 */
class ApprovalContext
{
    /**
     * @param string $title Summary line, e.g. "Authorization Request: Heavy Fighting"
     * @param string $description Longer explanation of what is being approved
     * @param array<int, array{label: string, value: string}> $fields Key/value display pairs
     * @param string|null $entityUrl Link back to the entity under review
     * @param string $icon Bootstrap icon class, e.g. 'bi-shield-check'
     * @param string|null $requester Display name of the person who initiated the request
     */
    public function __construct(
        private readonly string $title,
        private readonly string $description,
        private readonly array $fields = [],
        private readonly ?string $entityUrl = null,
        private readonly string $icon = 'bi-question-circle',
        private readonly ?string $requester = null,
    ) {
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return string|null
     */
    public function getEntityUrl(): ?string
    {
        return $this->entityUrl;
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * @return string|null
     */
    public function getRequester(): ?string
    {
        return $this->requester;
    }

    /**
     * Serialize to a plain array for JSON responses or template consumption.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'fields' => $this->fields,
            'entityUrl' => $this->entityUrl,
            'icon' => $this->icon,
            'requester' => $this->requester,
        ];
    }
}
