<?php

/**
 * Dataverse Table Row Actions Element
 * 
 * Renders action buttons for a table row based on configuration and user permissions.
 * Supports modal buttons, post links, and regular links with conditions and data attributes.
 * 
 * @var \App\View\AppView $this
 * @var array $actions Action configurations from GridColumns::getRowActions()
 * @var array|object $row The current row data
 * @var \Authorization\Identity|null $user Current user for permission checks
 */

use App\KMP\StaticHelpers;

$user = $user ?? null;

/**
 * Get a nested value from an array/object using dot notation
 */
$getNestedValue = function ($path, $data) {
    $parts = explode('.', $path);
    $value = $data;
    foreach ($parts as $part) {
        if (is_array($value) && isset($value[$part])) {
            $value = $value[$part];
        } elseif (is_object($value) && isset($value->{$part})) {
            $value = $value->{$part};
        } else {
            return null;
        }
    }
    return $value;
};

/**
 * Process template strings like {{member.sca_name}}
 */
$processTemplate = function ($template, $data) use ($getNestedValue) {
    return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($data, $getNestedValue) {
        return h($getNestedValue($matches[1], $data) ?? '');
    }, $template);
};

foreach ($actions as $action):
    // Check status filter (only show action for certain statuses)
    if (!empty($action['statusFilter'])) {
        $rowStatus = $getNestedValue('status', $row);
        if (!in_array($rowStatus, $action['statusFilter'])) {
            continue;
        }
    }

    // Check conditions
    if (!empty($action['condition'])) {
        $skip = false;
        foreach ($action['condition'] as $field => $expectedValue) {
            $actualValue = $getNestedValue($field, $row);
            // Handle boolean comparisons
            if (is_bool($expectedValue)) {
                if ((bool)$actualValue !== $expectedValue) {
                    $skip = true;
                    break;
                }
            } else {
                if ($actualValue != $expectedValue) {
                    $skip = true;
                    break;
                }
            }
        }
        if ($skip) {
            continue;
        }
    }

    // Check permission
    if (!empty($action['permission']) && $user) {
        if (!$user->checkCan($action['permission'], $row)) {
            continue;
        }
    }

    // Build button label
    $label = '';
    if (!empty($action['icon'])) {
        $label .= '<i class="bi ' . h($action['icon']) . '"></i>';
        if (!empty($action['label'])) {
            $label .= ' ';
        }
    }
    if (!empty($action['label'])) {
        $label .= h(__($action['label']));
    }

    // Build button class
    $buttonClass = $action['class'] ?? 'btn btn-sm btn-secondary';

    // Render based on action type
    switch ($action['type']):
        case 'modal':
            // Build data attributes for modal buttons
            $dataAttrs = [];
            $dataAttrs['data-bs-toggle'] = 'modal';
            $dataAttrs['data-bs-target'] = $action['modalTarget'];

            if (!empty($action['dataAttributes'])) {
                foreach ($action['dataAttributes'] as $attrKey => $attrValue) {
                    if (is_array($attrValue)) {
                        // Build JSON object with row data
                        $jsonData = [];
                        foreach ($attrValue as $jsonKey => $fieldPath) {
                            $jsonData[$jsonKey] = $getNestedValue($fieldPath, $row);
                        }
                        // Store as JSON for manual rendering
                        $dataAttrs['data-' . $attrKey] = $jsonData;
                    } else {
                        $dataAttrs['data-' . $attrKey] = $processTemplate($attrValue, $row);
                    }
                }
            }

            // Build button HTML manually to properly escape JSON in attributes
            $attrParts = [];
            $attrParts[] = 'type="button"';
            $attrParts[] = 'class="' . h($buttonClass) . '"';
            foreach ($dataAttrs as $attrName => $attrValue) {
                if (is_array($attrValue)) {
                    // JSON encode and HTML escape for attribute
                    $jsonStr = json_encode($attrValue);
                    $attrParts[] = $attrName . "='" . h($jsonStr) . "'";
                } else {
                    $attrParts[] = $attrName . '="' . h($attrValue) . '"';
                }
            }
            echo '<button ' . implode(' ', $attrParts) . '>' . $label . '</button> ';
            break;

        case 'postLink':
            // Build URL for post link
            $url = $action['url'];
            $urlParams = [
                'plugin' => $url['plugin'] ?? null,
                'controller' => $url['controller'],
                'action' => $url['action'],
            ];

            // Add the ID from the row
            if (!empty($url['idField'])) {
                $urlParams[] = $getNestedValue($url['idField'], $row);
            }

            // Build confirm message
            $confirmMessage = null;
            if (!empty($action['confirmMessage'])) {
                $confirmMessage = $processTemplate($action['confirmMessage'], $row);
            }

            echo $this->Form->postLink($label, $urlParams, [
                'escape' => false,
                'class' => $buttonClass,
                'confirm' => $confirmMessage,
                'data-turbo-frame' => '_top',
            ]);
            echo ' ';
            break;

        case 'link':
            // Build URL for regular link
            $url = $action['url'];
            $urlParams = [
                'plugin' => $url['plugin'] ?? null,
                'controller' => $url['controller'],
                'action' => $url['action'],
            ];

            // Add the ID from the row
            if (!empty($url['idField'])) {
                $urlParams[] = $getNestedValue($url['idField'], $row);
            }

            echo $this->Html->link($label, $urlParams, [
                'escape' => false,
                'class' => $buttonClass,
                'data-turbo-frame' => '_top',
            ]);
            echo ' ';
            break;
    endswitch;
endforeach;
