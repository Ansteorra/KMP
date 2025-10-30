#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__FILE__) . '/vendor/autoload.php';
require dirname(__FILE__) . '/config/bootstrap.php';

use Cake\ORM\TableRegistry;

$templates = TableRegistry::getTableLocator()->get('EmailTemplates');
$template = $templates->find()->where(['action_method' => 'resetPassword'])->first();

if ($template) {
    echo "Found template:\n";
    echo "  ID: " . $template->id . "\n";
    echo "  Mailer: " . $template->mailer_class . "\n";
    echo "  Active: " . ($template->is_active ? 'YES' : 'NO') . "\n";
    echo "  Has HTML: " . (!empty($template->html_template) ? 'YES (' . strlen($template->html_template) . ' chars)' : 'NO') . "\n";
    echo "  Has Text: " . (!empty($template->text_template) ? 'YES (' . strlen($template->text_template) . ' chars)' : 'NO') . "\n";
    echo "  Variables: " . count($template->available_vars) . "\n";
    echo "\n";

    if (!empty($template->html_template)) {
        echo "HTML Template (first 200 chars):\n";
        echo substr($template->html_template, 0, 200) . "...\n\n";
    }

    if (!empty($template->text_template)) {
        echo "Text Template (first 200 chars):\n";
        echo substr($template->text_template, 0, 200) . "...\n\n";
    }

    echo "Available vars:\n";
    print_r($template->available_vars);
} else {
    echo "No template found for resetPassword\n";
    echo "\nAll templates:\n";
    $all = $templates->find()->select(['id', 'mailer_class', 'action_method', 'is_active'])->all();
    foreach ($all as $t) {
        echo "  - {$t->action_method} ({$t->mailer_class}) - " . ($t->is_active ? 'Active' : 'Inactive') . "\n";
    }
}
