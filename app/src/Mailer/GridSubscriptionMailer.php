<?php
declare(strict_types=1);

namespace App\Mailer;

use App\KMP\StaticHelpers;
use RuntimeException;

class GridSubscriptionMailer extends KMPMailer
{
    /** Both samples and scheduled summaries use the tenant's selected editable template. */
    public function summary(string $email, string $name, array $report): void
    {
        $slug = trim((string)StaticHelpers::getAppSetting(
            'Email.GridSubscriptionTemplate',
            'grid-email-summary',
            'string',
            true,
        ));
        if ($slug === '' || is_numeric($slug)) {
            throw new RuntimeException('Email.GridSubscriptionTemplate must name an active email template slug.');
        }
        $sample = !empty($report['sample']);
        $table = '';
        $text = '';
        if ($report['rows'] !== []) {
            $table = $this->markdownRow($report['headers']) . "\n"
                . '| ' . implode(' | ', array_fill(0, count($report['headers']), '---')) . " |\n";
            foreach ($report['rows'] as $row) {
                $table .= $this->markdownRow($row) . "\n";
                foreach ($row as $index => $value) {
                    $text .= ($report['headers'][$index] ?? '') . ': ' . $value . "\n";
                }
                $text .= "\n";
            }
        }
        $this->sendFromTemplate(
            $email,
            $slug,
            null,
            siteTitle: StaticHelpers::getAppSetting('KMP.ShortSiteTitle'),
            summaryName: $name,
            gridLabel: $report['label'],
            viewUrl: $report['url'],
            manageUrl: $report['manageUrl'],
            rowCount: count($report['rows']),
            rowLimit: 50,
            isSample: $sample,
            hasRows: $report['rows'] !== [],
            isEmpty: $report['rows'] === [],
            resultsText: $text,
            resultsTable: $table,
        );
    }

    /** Escape Markdown syntax so grid display text cannot create Markdown links, images, or extra cells. */
    private function markdownRow(array $values): string
    {
        $cells = array_map(static function ($value): string {
            $value = str_replace(["\r", "\n", "\t"], ' ', (string)$value);

            return strtr($value, array_combine(
                str_split('\\`*_{}[]()#+-!|'),
                array_map(static fn($char) => '\\' . $char, str_split('\\`*_{}[]()#+-!|')),
            ));
        }, $values);

        return '| ' . implode(' | ', $cells) . ' |';
    }
}
