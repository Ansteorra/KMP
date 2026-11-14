<?php
declare(strict_types=1);

use Awards\Model\Entity\BestowalTodoTemplateItem;

/** @var \App\Model\Entity\ActionItem $item */
$outcomes = BestowalTodoTemplateItem::terminalOutcomesFor($item);
if ($item->is_terminal && $outcomes !== []) {
    echo $this->Form->control('terminal_status', [
        'type' => 'select',
        'id' => 'terminal-outcome-' . (int)$item->id,
        'label' => __('Completion outcome'),
        'options' => $outcomes,
        'empty' => __('Choose a completion outcome'),
        'required' => true,
        'data-action' => 'change->awards-bestowal-todos#selectTerminalOutcome',
    ]);
}
