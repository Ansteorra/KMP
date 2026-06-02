<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\TimezoneHelper;
use App\Model\Entity\EmailTemplate;
use Cake\Log\Log;
use DateTimeInterface;
use Parsedown;
use RuntimeException;

/**
 * Service for rendering email templates with variable substitution
 *
 * HTML templates are stored as Markdown and converted to HTML during rendering
 */
class EmailTemplateRendererService
{
    /**
     * @var \Parsedown
     */
    protected $parsedown;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->parsedown = new Parsedown();
        $this->parsedown->setSafeMode(false); // Allow HTML in markdown
    }

    /**
     * Render a template by replacing variables with values
     *
     * Processes conditional blocks first, then substitutes variables.
     *
     * @param string $template Template string with {{variable}} placeholders
     * @param array $vars Array of variable name => value pairs
     * @return string Rendered template
     */
    public function renderTemplate(string $template, array $vars): string
    {
        // Process conditional blocks before variable substitution
        $rendered = $this->processConditionals($template, $vars);

        foreach ($vars as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $rendered = str_replace($placeholder, $this->formatValue($value), $rendered);
        }

        // Also support ${variable} syntax for compatibility
        foreach ($vars as $key => $value) {
            $placeholder = '${' . $key . '}';
            $rendered = str_replace($placeholder, $this->formatValue($value), $rendered);
        }

        return $rendered;
    }

    /**
     * Render subject template
     *
     * @param \App\Model\Entity\EmailTemplate $emailTemplate
     * @param array $vars
     * @return string
     */
    public function renderSubject(EmailTemplate $emailTemplate, array $vars): string
    {
        $vars = $this->normalizeTemplateVars($emailTemplate, $vars);

        return $this->renderTemplate($emailTemplate->subject_template, $vars);
    }

    /**
     * Render HTML template
     *
     * The html_template field stores Markdown, which is converted to HTML during rendering.
     * Variables are substituted BEFORE markdown conversion to allow variables in links, etc.
     *
     * @param \App\Model\Entity\EmailTemplate $emailTemplate
     * @param array $vars
     * @return string|null
     */
    public function renderHtml(EmailTemplate $template, array $vars = []): ?string
    {
        if (empty($template->html_template)) {
            Log::debug('No HTML template to render');

            return null;
        }
        $vars = $this->normalizeTemplateVars($template, $vars);

        // Step 1: Replace variables in the markdown template
        $markdown = $this->renderTemplate($template->html_template, $vars);

        // Step 2: Convert markdown to HTML
        $htmlBody = $this->parsedown->text($markdown);

        // Step 3: Wrap in email-friendly HTML structure
        $html = $this->wrapInEmailHtml($htmlBody);

        Log::debug('Rendered HTML template from Markdown', [
            'template_id' => $template->id,
            'markdown_length' => strlen($template->html_template),
            'vars_count' => count($vars),
            'html_body_length' => strlen($htmlBody),
            'final_html_length' => strlen($html),
            'html_preview' => substr($htmlBody, 0, 200),
        ]);

        return $html;
    }

    /**
     * Render HTML body only (without wrapper)
     *
     * Used when you need just the content without the HTML structure wrapper.
     *
     * @param \App\Model\Entity\EmailTemplate $emailTemplate
     * @param array $vars
     * @return string|null
     */
    public function renderHtmlBody(EmailTemplate $template, array $vars = []): ?string
    {
        if (empty($template->html_template)) {
            return null;
        }
        $vars = $this->normalizeTemplateVars($template, $vars);

        // Replace variables in the markdown template
        $markdown = $this->renderTemplate($template->html_template, $vars);

        // Convert markdown to HTML (body only, no wrapper)
        return $this->parsedown->text($markdown);
    }

    /**
     * Render text template
     *
     * @param \App\Model\Entity\EmailTemplate $emailTemplate
     * @param array $vars
     * @return string|null
     */
    public function renderText(EmailTemplate $emailTemplate, array $vars): ?string
    {
        if (empty($emailTemplate->text_template)) {
            return null;
        }
        $vars = $this->normalizeTemplateVars($emailTemplate, $vars);

        return $this->renderTemplate($emailTemplate->text_template, $vars);
    }

    /**
     * Normalize send-time variables using the template contract.
     *
     * @param \App\Model\Entity\EmailTemplate $template
     * @param array $vars Variable name => value pairs
     * @return array
     */
    protected function normalizeTemplateVars(EmailTemplate $template, array $vars): array
    {
        foreach ($template->variables_schema as $entry) {
            if (!isset($entry['name'], $entry['type']) || !is_string($entry['name'])) {
                continue;
            }
            $name = $entry['name'];
            if (!array_key_exists($name, $vars)) {
                continue;
            }

            $type = strtolower((string)$entry['type']);
            if (in_array($type, ['date_time', 'datetime', 'date-time', 'timestamp'], true)) {
                $vars[$name] = $this->formatDateTimeValue($vars[$name]);
            } elseif ($type === 'date') {
                $vars[$name] = $this->formatDateValue($vars[$name]);
            }
        }

        return $vars;
    }

    /**
     * Process conditional blocks in template before variable substitution.
     *
     * Parses {{#if condition}}...{{/if}} blocks as a safe DSL.
     * Supports ==, !=, bare variable presence, || (OR), and && (AND) operators.
     *
     * Example: {{#if awardReason}}...{{/if}}
     * Example: {{#if status == "Approved" || status == "Revoked"}}...{{/if}}
     *
     * @param string $template Template with conditional blocks
     * @param array $vars Variable values for condition evaluation
     * @return string Template with conditionals resolved
     */
    protected function processConditionals(string $template, array $vars): string
    {
        $pattern = '/\{\{#if\s+(.+?)\}\}(.*?)\{\{\/if\}\}/s';

        // Loop to resolve innermost {{#if}} blocks first when nested
        $maxIterations = 10;
        $iteration = 0;
        while (preg_match($pattern, $template) && $iteration < $maxIterations) {
            $iteration++;
            $result = preg_replace_callback($pattern, function ($matches) use ($vars) {
                $condition = trim($matches[1]);
                $content = $matches[2];

                if (str_contains($content, '{{#if')) {
                    Log::warning(
                        'EmailTemplateRendererService: nested '
                        . '{{#if}} blocks detected — innermost '
                        . 'resolved first',
                    );
                }

                if ($this->evaluateCondition($condition, $vars)) {
                    return $content;
                }

                return '';
            }, $template);

            if ($result === null) {
                Log::error(
                    'EmailTemplateRendererService: '
                    . 'preg_replace_callback returned null '
                    . '(PCRE error) in processConditionals',
                );

                return $template;
            }
            $template = $result;
        }

        return $template;
    }

    /**
     * Evaluate a conditional expression safely.
     *
     * Splits by || first (lower precedence), then && (higher precedence),
     * then evaluates individual comparisons.
     *
     * @param string $condition Expression like 'var == "value" || var == "other"'
     * @param array $vars Available variable values
     * @return bool
     */
    protected function evaluateCondition(string $condition, array $vars): bool
    {
        // OR: split by || outside of quotes — any part true means true
        $orParts = $this->splitOutsideQuotes($condition, '||');
        if (count($orParts) > 1) {
            foreach ($orParts as $part) {
                if ($this->evaluateCondition(trim($part), $vars)) {
                    return true;
                }
            }

            return false;
        }

        // AND: split by && outside of quotes — all parts must be true
        $andParts = $this->splitOutsideQuotes($condition, '&&');
        if (count($andParts) > 1) {
            foreach ($andParts as $part) {
                if (!$this->evaluateCondition(trim($part), $vars)) {
                    return false;
                }
            }

            return true;
        }

        // Single comparison
        return $this->evaluateComparison(trim($condition), $vars);
    }

    /**
     * Evaluate a single comparison or bare variable presence check.
     *
     * Supports both == (equality) and != (not-equal) operators.
     * Variable names do not use a $ prefix in the {{#if}} syntax.
     *
     * @param string $comparison Single comparison expression
     * @param array $vars Available variable values
     * @return bool
     */
    protected function evaluateComparison(string $comparison, array $vars): bool
    {
        // Match varName == "value" or varName != "value" (with optional $ prefix for compat)
        $pattern = '/^\$?(\w+)\s*(==|!=)\s*["\']([^"\']*)["\']$/';

        if (preg_match($pattern, $comparison, $matches)) {
            $varName = $matches[1];
            $operator = $matches[2];
            $expectedValue = $matches[3];
            $actualValue = $this->formatValue($vars[$varName] ?? null);

            if ($operator === '!=') {
                return $actualValue !== $expectedValue;
            }

            return $actualValue === $expectedValue;
        }

        if (preg_match('/^\$?(\w+)$/', $comparison, $matches)) {
            return $this->hasUsefulValue($vars[$matches[1]] ?? null);
        }

        Log::warning('EmailTemplateRendererService: unsupported conditional expression: ' . $comparison);

        return false;
    }

    /**
     * Determine whether a value is useful enough to render a bare {{#if variable}} block.
     *
     * @param mixed $value Value to test
     * @return bool
     */
    protected function hasUsefulValue(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }

        if (is_array($value) && $value === []) {
            return false;
        }

        return trim($this->formatValue($value)) !== '';
    }

    /**
     * Split a condition string by a logical operator, but only when the operator
     * appears outside of quoted strings.
     *
     * @param string $condition The condition string to split
     * @param string $operator The operator to split on ('||' or '&&')
     * @return array Parts of the condition (single-element array if operator not found outside quotes)
     */
    protected function splitOutsideQuotes(string $condition, string $operator): array
    {
        $parts = [];
        $current = '';
        $inQuote = false;
        $quoteChar = '';
        $len = strlen($condition);
        $opLen = strlen($operator);

        for ($i = 0; $i < $len; $i++) {
            $char = $condition[$i];

            if ($inQuote) {
                $current .= $char;
                if ($char === $quoteChar) {
                    $inQuote = false;
                }
            } elseif ($char === '"' || $char === "'") {
                $inQuote = true;
                $quoteChar = $char;
                $current .= $char;
            } elseif (substr($condition, $i, $opLen) === $operator) {
                $parts[] = $current;
                $current = '';
                $i += $opLen - 1;
            } else {
                $current .= $char;
            }
        }
        $parts[] = $current;

        return $parts;
    }

    /**
     * Format a value for display in email
     *
     * @param mixed $value
     * @return string
     */
    protected function formatValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        if ($value instanceof DateTimeInterface) {
            return $this->formatDateTimeValue($value);
        }

        if (is_object($value)) {
            if (method_exists($value, 'format')) {
                return $this->formatDateTimeValue($value);
            }
            if (method_exists($value, '__toString')) {
                return (string)$value;
            }

            return '[Object]';
        }

        return (string)$value;
    }

    /**
     * Get list of variables used in a template
     *
     * Finds {{variable}}, ${variable}, and variable references in {{#if}} conditionals.
     *
     * @param string $template
     * @return array List of variable names
     */
    public function extractVariables(string $template): array
    {
        $variables = [];

        // Find {{variable}} style — but exclude {{#if ...}} and {{/if}} control tags
        preg_match_all('/\{\{(?!#if\s|\/if\})([^}]+)\}\}/', $template, $matches);
        if (!empty($matches[1])) {
            $variables = array_merge($variables, $matches[1]);
        }

        // Find ${variable} style
        preg_match_all('/\$\{([^}]+)\}/', $template, $matches);
        if (!empty($matches[1])) {
            $variables = array_merge($variables, $matches[1]);
        }

        // Find variable references in {{#if condition}} expressions
        preg_match_all('/\{\{#if\s+(.+?)\}\}/s', $template, $condMatches);
        if (!empty($condMatches[1])) {
            foreach ($condMatches[1] as $condition) {
                $conditionWithoutStrings = preg_replace('/["\'][^"\']*["\']/', '', $condition) ?? $condition;
                preg_match_all('/\$?\b(\w+)\b/', $conditionWithoutStrings, $varMatches);
                if (!empty($varMatches[1])) {
                    $variables = array_merge($variables, $varMatches[1]);
                }
            }
        }

        return array_unique($variables);
    }

    /**
     * Format a date/time value in the kingdom default timezone.
     *
     * @param mixed $value Date/time value
     * @return string
     */
    protected function formatDateTimeValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return TimezoneHelper::formatForDisplay($value, null, TimezoneHelper::DISPLAY_DATETIME_FORMAT, true);
    }

    /**
     * Format a date value in the kingdom default timezone.
     *
     * @param mixed $value Date value
     * @return string
     */
    protected function formatDateValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return TimezoneHelper::formatForDisplay($value, null, TimezoneHelper::DISPLAY_DATE_FORMAT);
    }

    /**
     * Validate that all required variables are provided
     *
     * @param string $template Template string
     * @param array $vars Variables provided
     * @return array Missing variable names
     */
    public function getMissingVariables(string $template, array $vars): array
    {
        $required = $this->extractVariables($template);
        $provided = array_keys($vars);

        return array_diff($required, $provided);
    }

    /**
     * Preview rendered template with sample data
     *
     * @param \App\Model\Entity\EmailTemplate $emailTemplate
     * @param array $sampleVars Sample variable values
     * @return array Preview of subject, html, and text
     */
    public function preview(EmailTemplate $emailTemplate, array $sampleVars = []): array
    {
        // If no sample vars provided, use placeholders
        if (empty($sampleVars)) {
            $availableVars = $emailTemplate->available_vars;
            foreach ($availableVars as $var) {
                $sampleVars[$var['name']] = '[' . $var['name'] . ']';
            }
        }

        return [
            'subject' => $this->renderSubject($emailTemplate, $sampleVars),
            'html' => $this->renderHtml($emailTemplate, $sampleVars),
            'text' => $this->renderText($emailTemplate, $sampleVars),
        ];
    }

    /**
     * Validate a template against provided send-time variables.
     *
     * Performs a three-way comparison:
     *  1. Placeholders extracted from subject + html + text vs $vars provided → errors for any
     *     placeholder that has no value (it would render as a raw {{...}} token).
     *  2. variables_schema required entries vs $vars → errors for any required schema var absent.
     *  3. Placeholders used in template vs variables_schema declared names → warnings for any
     *     placeholder not declared in the schema (undocumented variable drift).
     *
     * Returns ['errors' => string[], 'warnings' => string[]].
     * Errors are blocking (use assertValidForSend to throw); warnings are advisory.
     *
     * @param \App\Model\Entity\EmailTemplate $template
     * @param array $vars Variable name => value pairs to be used at send time
     * @return array{errors: string[], warnings: string[]}
     */
    public function validateForSend(EmailTemplate $template, array $vars): array
    {
        $errors = [];
        $warnings = [];
        $providedKeys = array_keys($vars);

        // Distinguish between rendered placeholders and conditional vars.
        $renderedPlaceholders = $this->extractRenderedPlaceholders($template);
        $allPlaceholders = $this->extractAllPlaceholders($template);

        // 1. Rendered placeholders in template content but no value in $vars
        $unresolved = array_diff($renderedPlaceholders, $providedKeys);
        foreach ($unresolved as $placeholder) {
            $errors[] = "Placeholder '{{{{$placeholder}}}}' is used in the template but no value was provided; " .
                'it will render as a literal token.';
        }

        // 2. Schema required vars missing from $vars
        $schema = $template->variables_schema;
        $schemaDeclaredNames = [];
        foreach ($schema as $entry) {
            if (!isset($entry['name'])) {
                continue;
            }
            $name = $entry['name'];
            $schemaDeclaredNames[] = $name;
            $required = isset($entry['required']) && $entry['required'] === true;
            if ($required && !array_key_exists($name, $vars)) {
                $errors[] = "Required variable '{$name}' declared in variables_schema was not provided.";
            }
        }

        // 3. Template placeholders not declared in schema (advisory drift warning)
        if (!empty($schemaDeclaredNames)) {
            foreach ($allPlaceholders as $placeholder) {
                if (!in_array($placeholder, $schemaDeclaredNames, true)) {
                    $warnings[] = "Placeholder '{{{{$placeholder}}}}' is used in the template but not declared " .
                        'in variables_schema.';
                }
            }
        }

        return ['errors' => array_values($errors), 'warnings' => array_values($warnings)];
    }

    /**
     * Like validateForSend() but throws a RuntimeException when there are any errors.
     *
     * @param \App\Model\Entity\EmailTemplate $template
     * @param array $vars Variable name => value pairs
     * @return void
     * @throws \RuntimeException
     */
    public function assertValidForSend(EmailTemplate $template, array $vars): void
    {
        $result = $this->validateForSend($template, $vars);
        if (!empty($result['errors'])) {
            $slug = $template->slug ?? $template->display_name;
            throw new RuntimeException(
                "Email template '{$slug}' failed send-time validation: "
                . implode(' | ', $result['errors']),
            );
        }
    }

    /**
     * Extract all unique placeholder names from every content field of a template.
     *
     * Combines subject_template, html_template, and text_template so callers
     * get a unified view of what the template actually needs.
     *
     * @param \App\Model\Entity\EmailTemplate $template
     * @return array<string> Unique placeholder names
     */
    public function extractAllPlaceholders(EmailTemplate $template): array
    {
        $all = [];
        foreach (['subject_template', 'html_template', 'text_template'] as $field) {
            if (!empty($template->$field)) {
                $all = array_merge($all, $this->extractVariables($template->$field));
            }
        }

        return array_values(array_unique($all));
    }

    /**
     * Extract placeholders that render literal output tokens.
     *
     * Unlike extractAllPlaceholders(), this excludes variables referenced only
     * inside conditional expressions because missing condition vars safely
     * evaluate false and do not render literal tokens.
     *
     * @param \App\Model\Entity\EmailTemplate $template
     * @return array<string>
     */
    protected function extractRenderedPlaceholders(EmailTemplate $template): array
    {
        $all = [];
        foreach (['subject_template', 'html_template', 'text_template'] as $field) {
            if (empty($template->$field)) {
                continue;
            }

            preg_match_all('/\{\{(?!#if\s|\/if\})([^}]+)\}\}/', $template->$field, $matches);
            if (!empty($matches[1])) {
                $all = array_merge($all, $matches[1]);
            }

            preg_match_all('/\$\{([^}]+)\}/', $template->$field, $matches);
            if (!empty($matches[1])) {
                $all = array_merge($all, $matches[1]);
            }
        }

        return array_values(array_unique($all));
    }

    /**
     * Validate that a template's variables_schema is consistent with its placeholders.
     *
     * A schema-consistency check for template authoring time (not send time).
     * Returns ['errors' => [], 'warnings' => []] where:
     *  - warnings include schema vars not referenced by any template placeholder.
     *
     * @param \App\Model\Entity\EmailTemplate $template
     * @return array{errors: string[], warnings: string[]}
     */
    public function validateSchemaConsistency(EmailTemplate $template): array
    {
        $warnings = [];
        $allPlaceholders = $this->extractAllPlaceholders($template);
        $schema = $template->variables_schema;

        foreach ($schema as $entry) {
            if (!isset($entry['name'])) {
                continue;
            }
            $name = $entry['name'];
            if (!in_array($name, $allPlaceholders, true)) {
                $warnings[] = "Schema variable '{$name}' is declared in variables_schema but not used in any " .
                    'template content.';
            }
        }

        return ['errors' => [], 'warnings' => array_values($warnings)];
    }

    /**
     * Wrap HTML body content in email-friendly HTML structure
     *
     * @param string $htmlBody HTML body content
     * @return string Complete HTML email
     */
    protected function wrapInEmailHtml(string $htmlBody): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        a {
            color: #0066cc;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: "Courier New", monospace;
        }
        pre {
            background-color: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        blockquote {
            border-left: 4px solid #ddd;
            margin: 0;
            padding-left: 16px;
            color: #666;
        }
        hr {
            border: none;
            border-top: 1px solid #ddd;
            margin: 20px 0;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
    </style>
</head>
<body>
    ' . $htmlBody . '
</body>
</html>';
    }

    /**
     * Convert plain text to HTML (simple conversion)
     *
     * @param string $text Plain text
     * @return string HTML
     */
    public function textToHtml(string $text): string
    {
        $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $html = nl2br($html);

        // Wrap in basic HTML structure for email
        return $this->wrapInEmailHtml($html);
    }

    /**
     * Convert HTML to plain text (simple conversion)
     *
     * @param string $html HTML
     * @return string Plain text
     */
    public function htmlToText(string $html): string
    {
        // Remove HTML tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Clean up excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return $text;
    }
}
