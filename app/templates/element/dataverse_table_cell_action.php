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
 * @var string|callable|array|null $clickActionPermission Optional permission configuration
 * @var array<int|string, mixed>|object $row The data row
 * @var array<int, mixed>|string $clickActionPermissionArgs Optional additional arguments for permission check
 * @var \Authorization\IdentityInterface|null $user Current user identity for permission checks
 * @var string $primaryKey Primary key field name
 * @var string $columnKey Column key
 */

$clickActionPermission = $clickActionPermission ?? null;
$clickActionPermissionArgs = $clickActionPermissionArgs ?? [];
$identity = $user ?? $this->getRequest()->getAttribute('identity');

$resolveNestedValue = static function ($path, $data) {
    $segments = explode('.', (string)$path);
    $current = $data;

    foreach ($segments as $segment) {
        if ($segment === '' || $current === null) {
            return null;
        }

        if (is_array($current)) {
            if (!array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
            continue;
        }

        if (is_object($current)) {
            if (method_exists($current, 'get')) {
                $value = $current->get($segment);
                if ($value !== null) {
                    $current = $value;
                    continue;
                }
            }

            if (isset($current->{$segment})) {
                $current = $current->{$segment};
                continue;
            }

            return null;
        }

        return null;
    }

    return $current;
};

$requiresPermission = $clickActionPermission !== null;

if ($requiresPermission) {
    $isAllowed = false;

    try {
        if (is_callable($clickActionPermission)) {
            $isAllowed = (bool)call_user_func($clickActionPermission, $row, $identity, $this, $columnKey);
        } elseif (is_string($clickActionPermission) && $identity && method_exists($identity, 'checkCan')) {
            $args = is_array($clickActionPermissionArgs) ? $clickActionPermissionArgs : [$clickActionPermissionArgs];
            $isAllowed = $identity->checkCan($clickActionPermission, $row, ...$args);
        } elseif (is_array($clickActionPermission)) {
            $ability = $clickActionPermission['ability']
                ?? $clickActionPermission['permission']
                ?? null;

            $args = $clickActionPermission['args'] ?? $clickActionPermissionArgs;
            if (!is_array($args)) {
                $args = [$args];
            }

            $subject = $row;

            if (array_key_exists('subjectResolver', $clickActionPermission) && is_callable($clickActionPermission['subjectResolver'])) {
                $subject = $clickActionPermission['subjectResolver']($row, $identity, $this, $columnKey);
            } elseif (array_key_exists('subjectField', $clickActionPermission)) {
                $subject = $resolveNestedValue($clickActionPermission['subjectField'], $row);
            } elseif (array_key_exists('subject', $clickActionPermission)) {
                $subject = $clickActionPermission['subject'];
            } elseif (array_key_exists('resource', $clickActionPermission)) {
                $subject = $clickActionPermission['resource'];
            }

            if ($ability !== null && $identity && method_exists($identity, 'checkCan')) {
                $isAllowed = $identity->checkCan($ability, $subject, ...$args);
            } elseif (array_key_exists('callable', $clickActionPermission) && is_callable($clickActionPermission['callable'])) {
                $isAllowed = (bool)$clickActionPermission['callable']($row, $identity, $this, $columnKey);
            }
        }
    } catch (\Throwable $exception) {
        $isAllowed = false;
    }

    if (!$isAllowed) {
        echo $content;
        return;
    }
}

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
