<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\Services\WorkflowEngine\Conditions\CoreConditions;
use Cake\I18n\DateTime;

/**
 * Safe expression evaluator for workflow templates, date arithmetic, conditionals, and math.
 *
 * Supports context path resolution, string template interpolation, date arithmetic,
 * ternary conditionals, basic arithmetic, and PHP-style string concatenation.
 * All parsing is regex-based — no eval() or dynamic code execution.
 */
class ExpressionEvaluator
{
    /**
     * Evaluate an expression string and return the computed result.
     *
     * Detects expression type automatically and delegates to the appropriate evaluator.
     *
     * @param string $expression Raw expression (without leading '=' prefix)
     * @param array $context Current workflow context
     * @return mixed Evaluated result
     */
    public function evaluate(string $expression, array $context): mixed
    {
        $expression = trim($expression);

        if ($expression === '') {
            return null;
        }

        // String template: contains {{...}} interpolation markers
        if (str_contains($expression, '{{') && str_contains($expression, '}}')) {
            return $this->evaluateTemplate($expression, $context);
        }

        // Ternary conditional: contains ? and : with a comparison operator
        if (preg_match('/\?.*:/', $expression) && preg_match('/[><=!]/', $expression)) {
            $result = $this->evaluateConditional($expression, $context);
            if ($result !== null) {
                return $result;
            }
        }

        // Date arithmetic: "now" keyword or context path + date offset
        if ($this->looksLikeDateExpression($expression)) {
            $result = $this->evaluateDateExpression($expression, $context);
            if ($result !== null) {
                return $result;
            }
        }

        // String concatenation with PHP-style dot operator
        if ($this->looksLikeConcatenation($expression)) {
            return $this->evaluateConcatenation($expression, $context);
        }

        // Basic arithmetic: +, -, *, / between values (not date expressions)
        if ($this->looksLikeArithmetic($expression)) {
            return $this->evaluateArithmetic($expression, $context);
        }

        // Simple context path resolution
        if (str_starts_with($expression, '$.')) {
            return CoreConditions::resolveFieldPath($context, $expression);
        }

        // Literal value fallback
        return $expression;
    }

    /**
     * Interpolate {{$.path}} placeholders in a string template.
     *
     * @param string $template Template string with {{$.path}} markers
     * @param array $context Current workflow context
     * @return string Interpolated string
     */
    public function evaluateTemplate(string $template, array $context): string
    {
        return (string)preg_replace_callback(
            '/\{\{(.*?)\}\}/',
            function (array $matches) use ($context): string {
                $path = trim($matches[1]);
                $value = CoreConditions::resolveFieldPath($context, $path);

                if ($value === null) {
                    return '';
                }

                if (is_scalar($value)) {
                    return (string)$value;
                }

                return '';
            },
            $template,
        );
    }

    /**
     * Evaluate a date arithmetic expression.
     *
     * Supports: "now", "now + 30 days", "$.start_on + 1 year",
     * "$.start_on + $.term_length days"
     *
     * @param string $expression Date expression
     * @param array $context Current workflow context
     * @return \DateTimeInterface|null Resulting date or null if not parseable
     */
    public function evaluateDateExpression(string $expression, array $context): ?\DateTimeInterface
    {
        $expression = trim($expression);

        // "now" keyword
        if (strtolower($expression) === 'now') {
            return new DateTime();
        }

        // Pattern: base +/- amount unit
        // base: "now" or a context path
        // amount: number or context path
        // unit: day(s), month(s), year(s), week(s), hour(s), minute(s)
        $pattern = '/^(.+?)\s*([+-])\s*(.+?)\s+(days?|months?|years?|weeks?|hours?|minutes?)$/i';

        if (!preg_match($pattern, $expression, $matches)) {
            return null;
        }

        $basePart = trim($matches[1]);
        $operator = $matches[2];
        $amountPart = trim($matches[3]);
        $unit = strtolower($matches[4]);

        // Normalize unit to plural
        if (!str_ends_with($unit, 's')) {
            $unit .= 's';
        }

        // Resolve base date
        $baseDate = $this->resolveDate($basePart, $context);
        if ($baseDate === null) {
            return null;
        }

        // Resolve amount (numeric literal or context path)
        $amount = $this->resolveNumericValue($amountPart, $context);
        if ($amount === null) {
            return null;
        }

        $amount = (int)$amount;

        // Apply arithmetic
        $dateTime = new DateTime($baseDate->format('Y-m-d H:i:s'));
        $sign = $operator === '-' ? '-' : '+';
        $dateTime = $dateTime->modify("{$sign}{$amount} {$unit}");

        return $dateTime ?: null;
    }

    /**
     * Evaluate a simple ternary conditional expression.
     *
     * Format: "condition ? trueValue : falseValue"
     * Condition supports: ==, !=, >, <, >=, <=
     *
     * @param string $expression Ternary expression
     * @param array $context Current workflow context
     * @return mixed Result of the selected branch, or null if not parseable
     */
    public function evaluateConditional(string $expression, array $context): mixed
    {
        // Split on ? and :
        // Pattern: condition ? trueValue : falseValue
        $questionPos = strpos($expression, '?');
        if ($questionPos === false) {
            return null;
        }

        $condition = trim(substr($expression, 0, $questionPos));
        $remainder = substr($expression, $questionPos + 1);

        // Find the colon that separates true/false values
        // Handle quoted strings that may contain colons
        $colonPos = $this->findTernaryColon($remainder);
        if ($colonPos === false) {
            return null;
        }

        $trueValue = trim(substr($remainder, 0, $colonPos));
        $falseValue = trim(substr($remainder, $colonPos + 1));

        // Evaluate condition
        $conditionResult = $this->evaluateConditionExpression($condition, $context);

        // Return the appropriate branch
        $resultExpr = $conditionResult ? $trueValue : $falseValue;

        return $this->resolveExpressionValue($resultExpr, $context);
    }

    /**
     * Evaluate a basic arithmetic expression: +, -, *, /
     *
     * @param string $expression Arithmetic expression
     * @param array $context Current workflow context
     * @return float|int Computed result
     */
    public function evaluateArithmetic(string $expression, array $context): float|int
    {
        // Match: operand operator operand
        $pattern = '/^(.+?)\s*([+\-*\/])\s*(.+)$/';

        if (!preg_match($pattern, $expression, $matches)) {
            // Try to resolve as a single value
            $val = $this->resolveNumericValue($expression, $context);

            return $val ?? 0;
        }

        $left = $this->resolveNumericValue(trim($matches[1]), $context);
        $operator = $matches[2];
        $right = $this->resolveNumericValue(trim($matches[3]), $context);

        if ($left === null) {
            $left = 0;
        }
        if ($right === null) {
            $right = 0;
        }

        $result = match ($operator) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right != 0 ? $left / $right : 0,
            default => 0,
        };

        // Return int if the result is a whole number
        if (is_float($result) && floor($result) == $result && abs($result) < PHP_INT_MAX) {
            return (int)$result;
        }

        return $result;
    }

    /**
     * Evaluate PHP-style string concatenation with dot operator.
     *
     * Format: "$.first_name . ' ' . $.last_name"
     *
     * @param string $expression Concatenation expression
     * @param array $context Current workflow context
     * @return string Concatenated result
     */
    private function evaluateConcatenation(string $expression, array $context): string
    {
        // Split on dot-concat operator (dot surrounded by spaces, not within a context path)
        $parts = preg_split('/\s+\.\s+/', $expression);
        if ($parts === false || count($parts) < 2) {
            return $expression;
        }

        $result = '';
        foreach ($parts as $part) {
            $part = trim($part);
            $result .= (string)$this->resolveExpressionValue($part, $context);
        }

        return $result;
    }

    /**
     * Check if an expression looks like date arithmetic.
     */
    private function looksLikeDateExpression(string $expression): bool
    {
        if (strtolower(trim($expression)) === 'now') {
            return true;
        }

        return (bool)preg_match('/\b(days?|months?|years?|weeks?|hours?|minutes?)\b/i', $expression);
    }

    /**
     * Check if an expression looks like basic arithmetic (not date arithmetic).
     */
    private function looksLikeArithmetic(string $expression): bool
    {
        // Must contain an arithmetic operator surrounded by whitespace or between operands
        if (!preg_match('/\S\s*[+\-*\/]\s*\S/', $expression)) {
            return false;
        }

        // Exclude date-like expressions
        if (preg_match('/\b(days?|months?|years?|weeks?|hours?|minutes?)\b/i', $expression)) {
            return false;
        }

        // Require at least one side to be numeric or a context path
        if (preg_match('/^(.+?)\s*[+\-*\/]\s*(.+)$/', $expression, $m)) {
            $left = trim($m[1]);
            $right = trim($m[2]);

            $isLeftOperand = is_numeric($left) || str_starts_with($left, '$.');
            $isRightOperand = is_numeric($right) || str_starts_with($right, '$.');

            return $isLeftOperand || $isRightOperand;
        }

        return false;
    }

    /**
     * Check if an expression looks like PHP-style string concatenation.
     */
    private function looksLikeConcatenation(string $expression): bool
    {
        // Dot operator surrounded by whitespace (not part of context path)
        return (bool)preg_match('/\s+\.\s+/', $expression);
    }

    /**
     * Resolve a string to a date value.
     */
    private function resolveDate(string $value, array $context): ?\DateTimeInterface
    {
        if (strtolower($value) === 'now') {
            return new DateTime();
        }

        // Context path
        if (str_starts_with($value, '$.')) {
            $resolved = CoreConditions::resolveFieldPath($context, $value);

            if ($resolved instanceof \DateTimeInterface) {
                return $resolved;
            }

            if (is_string($resolved) && !empty($resolved)) {
                try {
                    return new DateTime($resolved);
                } catch (\Exception) {
                    return null;
                }
            }

            return null;
        }

        // Try parsing as a date string
        try {
            return new DateTime($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Resolve a value to a numeric type.
     */
    private function resolveNumericValue(string $value, array $context): float|int|null
    {
        $value = trim($value);

        // Context path
        if (str_starts_with($value, '$.')) {
            $resolved = CoreConditions::resolveFieldPath($context, $value);

            if (is_numeric($resolved)) {
                return str_contains((string)$resolved, '.') ? (float)$resolved : (int)$resolved;
            }

            return null;
        }

        // Numeric literal
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }

        return null;
    }

    /**
     * Evaluate a comparison condition (left operator right).
     */
    private function evaluateConditionExpression(string $condition, array $context): bool
    {
        $operators = ['>=', '<=', '!=', '==', '>', '<'];

        foreach ($operators as $op) {
            $parts = explode($op, $condition, 2);
            if (count($parts) === 2) {
                $left = $this->resolveExpressionValue(trim($parts[0]), $context);
                $right = $this->resolveExpressionValue(trim($parts[1]), $context);

                // Cast to numeric if both sides look numeric
                if (is_numeric($left) && is_numeric($right)) {
                    $left = (float)$left;
                    $right = (float)$right;
                }

                return match ($op) {
                    '==' => $left == $right,
                    '!=' => $left != $right,
                    '>' => $left > $right,
                    '<' => $left < $right,
                    '>=' => $left >= $right,
                    '<=' => $left <= $right,
                    default => false,
                };
            }
        }

        // Treat as truthy check
        $val = $this->resolveExpressionValue($condition, $context);

        return !empty($val);
    }

    /**
     * Resolve a single value token: context path, quoted string, numeric literal, or bare string.
     */
    private function resolveExpressionValue(string $value, array $context): mixed
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // Context path
        if (str_starts_with($value, '$.')) {
            return CoreConditions::resolveFieldPath($context, $value);
        }

        // Single or double quoted string
        if (
            (str_starts_with($value, "'") && str_ends_with($value, "'")) ||
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
        ) {
            return substr($value, 1, -1);
        }

        // Numeric literal
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }

        // Bare string
        return $value;
    }

    /**
     * Find the colon separating true/false branches in a ternary, skipping colons inside quotes.
     */
    private function findTernaryColon(string $remainder): int|false
    {
        $inSingle = false;
        $inDouble = false;

        for ($i = 0, $len = strlen($remainder); $i < $len; $i++) {
            $ch = $remainder[$i];

            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
            } elseif ($ch === ':' && !$inSingle && !$inDouble) {
                return $i;
            }
        }

        return false;
    }
}
