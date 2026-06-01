<?php

/**
 * @deprecated Use element/turbo_close_modal via TurboResponseTrait::renderTurboCloseModal()
 */

echo $this->element('turbo_close_modal', [
    'refreshFrame' => $refreshFrame ?? 'app-settings-grid-table',
    'refreshUrl' => $refreshUrl ?? $this->Url->build(['action' => 'gridData']),
    'flashMessages' => $flashMessages ?? [],
]);
