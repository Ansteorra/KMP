<?php
declare(strict_types=1);

namespace App\Services\ActionItems;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\ActionItem;
use App\Services\ServiceResult;

/**
 * Plugin extension point for specialized ActionItem completion forms.
 */
interface ActionItemCompletionFormProviderInterface
{
    /**
     * Whether this provider owns completion UI/validation for the item.
     *
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @return bool
     */
    public function canHandle(ActionItem $item): bool;

    /**
     * Build structured form metadata for My To-Dos.
     *
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @param \App\KMP\KmpIdentityInterface $user Current user.
     * @return \App\Services\ActionItems\ActionItemCompletionForm|null
     */
    public function buildForm(ActionItem $item, KmpIdentityInterface $user): ?ActionItemCompletionForm;

    /**
     * Apply submitted special-form values before the item is completed.
     *
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @param array<string, mixed> $data Submitted request data.
     * @param int $actorId Acting member id.
     * @param \App\KMP\KmpIdentityInterface $user Current user.
     * @return \App\Services\ServiceResult
     */
    public function applySubmission(
        ActionItem $item,
        array $data,
        int $actorId,
        KmpIdentityInterface $user,
    ): ServiceResult;

    /**
     * Validate completion requirements after any submitted values are applied.
     *
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @return \App\Services\ServiceResult
     */
    public function validateCompletion(ActionItem $item): ServiceResult;
}
