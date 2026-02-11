---
name: "safe-template-dsl"
description: "Pattern for adding safe conditional logic to user-editable templates without eval()"
domain: "security-and-templating"
confidence: "low"
source: "earned"
---

## Context
When templates are stored in a database and editable by users/admins, you cannot use `eval()` or any PHP code execution to process logic embedded in those templates. Instead, parse the template syntax as a safe DSL using regex, supporting only the specific operations you need.

## Patterns

### Safe Conditional Parsing
Parse `{{#if var == "value"}}...{{/if}}` blocks using regex, not `eval()`:

```php
// Regex to find conditional blocks (s flag for multiline content)
$pattern = '/\{\{#if\s+(.+?)\}\}(.*?)\{\{\/if\}\}/s';

preg_replace_callback($pattern, function ($matches) use ($vars) {
    $condition = trim($matches[1]);
    $content = $matches[2];
    return $this->evaluateCondition($condition, $vars) ? $content : '';
}, $template);
```

### Operator Precedence via Recursive Splitting
Handle `||` (OR) and `&&` (AND) with correct precedence by splitting `||` first:

```php
// Split by || first → gives && higher precedence (correct)
if (str_contains($condition, '||')) {
    foreach (explode('||', $condition) as $part) {
        if ($this->evaluateCondition(trim($part), $vars)) return true;
    }
    return false;
}
if (str_contains($condition, '&&')) {
    foreach (explode('&&', $condition) as $part) {
        if (!$this->evaluateCondition(trim($part), $vars)) return false;
    }
    return true;
}
return $this->evaluateComparison($condition, $vars);
```

### Evaluation Order
Process conditionals BEFORE variable substitution. Conditionals need raw values to decide which blocks to keep; the kept blocks then get their `{{variables}}` substituted.

### Fail-Safe: Unknown Expressions
Any expression that doesn't match the supported pattern should log a warning and evaluate to `false`. Never silently succeed on unrecognized input.

## Anti-Patterns
- **Using `eval()` on user-editable content** — arbitrary code execution vulnerability
- **Substituting variables before conditionals** — conditionals can't evaluate if values are already string-replaced
- **Silent failures** — unsupported expressions should warn, not silently pass/fail

## When to Use
- User/admin-editable templates need conditional logic
- Template syntax must be compatible with an existing server-side template engine (e.g., PHP, Jinja)
- You need a safe subset of the full language's conditional capabilities
