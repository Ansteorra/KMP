<?php

/**
 * Dataverse Table Cell Action Element
 * 
 * Wraps cell content with appropriate action handler based on clickAction type.
 * 
 * Supported clickAction formats:
 * - navigate:<url> - Direct navigation (e.g., "navigate:/members/view/:id")
 * - toggleSubRow:<name> - Toggle sub-row expansion
 * - openModal:<name> - Open a modal
 * - callable - Custom function that returns HTML
 * 
 * @var string $content The rendered cell content
 * @var string|callable $clickAction The action specification
 * @var array $row The data row
 * @var string $primaryKey Primary key field name
 * @var string $columnKey Column key
 */

// Handle callable actions
if (is_callable($clickAction)) {
    echo $clickAction($content, $row, $this);
    return;
}

// Parse string-based actions
if (!is_string($clickAction)) {
    echo $content;
    return;
}

// Extract action type and parameters
$actionParts = explode(':', $clickAction, 2);
$actionType = $actionParts[0];
$actionParam = $actionParts[1] ?? '';

// Replace placeholders in action parameter
$actionParam = str_replace(':id', $row[$primaryKey], $actionParam);
$actionParam = preg_replace_callback('/:(\w+)/', function ($matches) use ($row) {
    return $row[$matches[1]] ?? $matches[0];
}, $actionParam);

switch ($actionType) {
    case 'navigate':
        // Direct navigation link
        echo $this->Html->link(
            $content,
            $actionParam,
            [
                'escape' => false,
                'data-turbo-frame' => '_top',
                'class' => 'text-decoration-none',
            ]
        );
        break;

    case 'toggleSubRow':
        // Toggle sub-row (for future implementation)
?>
        <a href="#"
            class="text-decoration-none d-flex align-items-center"
            data-action="click->grid-view#toggleSubRow"
            data-row-id="<?= h($row[$primaryKey]) ?>"
            data-subrow-type="<?= h($actionParam) ?>"
            onclick="event.preventDefault();">
            <i class="bi bi-chevron-right toggle-icon me-1" style="font-size: 0.75rem;"></i>
            <span><?= $content ?></span>
        </a>
    <?php
        break;

    case 'openModal':
        // Open modal (for future implementation)
    ?>
        <a href="#"
            class="text-decoration-none"
            data-action="click->grid-view#openModal"
            data-row-id="<?= h($row[$primaryKey]) ?>"
            data-modal-type="<?= h($actionParam) ?>"
            onclick="event.preventDefault();">
            <?= $content ?>
        </a>
<?php
        break;

    case 'link':
        // Simple link without Turbo frame breaking
        echo $this->Html->link(
            $content,
            $actionParam,
            [
                'escape' => false,
                'class' => 'text-decoration-none',
            ]
        );
        break;

    default:
        // Unknown action type, just display content
        echo $content;
        break;
}
