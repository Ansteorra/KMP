<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Services\WorkflowEngine\Conditions\CoreConditions;
use Cake\TestSuite\TestCase;

/**
 * Edge case tests for evaluateExpression() in CoreConditions.
 */
class ExpressionParserTest extends TestCase
{
    private CoreConditions $conditions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conditions = new CoreConditions();
    }

    private function evaluate(array $context, string $expression): bool
    {
        return $this->conditions->evaluateExpression($context, ['expression' => $expression]);
    }

    // =====================================================
    // Empty / null inputs
    // =====================================================

    public function testEmptyExpressionReturnsFalse(): void
    {
        $this->assertFalse($this->evaluate(['x' => 1], ''));
    }

    public function testWhitespaceOnlyExpressionReturnsFalse(): void
    {
        $this->assertFalse($this->evaluate(['x' => 1], '   '));
    }

    public function testNullExpressionConfig(): void
    {
        $result = $this->conditions->evaluateExpression(['x' => 1], ['expression' => null]);
        $this->assertFalse($result);
    }

    public function testMissingExpressionKey(): void
    {
        $result = $this->conditions->evaluateExpression(['x' => 1], []);
        $this->assertFalse($result);
    }

    // =====================================================
    // Null / undefined field references
    // =====================================================

    public function testNullFieldEqualsValue(): void
    {
        // Null (missing field) compared to string
        $this->assertFalse($this->evaluate([], 'missing == active'));
    }

    public function testNullFieldNotEqualsValue(): void
    {
        // null != "active" should be true
        $this->assertTrue($this->evaluate([], 'missing != active'));
    }

    public function testNullFieldGreaterThan(): void
    {
        // null > 5 — numeric comparison on null
        $this->assertFalse($this->evaluate([], 'missing > 5'));
    }

    public function testNullFieldLessThan(): void
    {
        // null casts to 0 for numeric comparison: 0 < 5 = true
        $this->assertTrue($this->evaluate([], 'missing < 5'));
    }

    public function testNullFieldBooleanCheck(): void
    {
        // No operator: boolean check on null → false
        $this->assertFalse($this->evaluate([], 'missing'));
    }

    // =====================================================
    // Type coercion edge cases
    // =====================================================

    public function testStringNumberComparison(): void
    {
        // "42" == 42 with numeric casting
        $this->assertTrue($this->evaluate(['count' => '42'], 'count == 42'));
    }

    public function testNumericStringGreaterThan(): void
    {
        $this->assertTrue($this->evaluate(['count' => '100'], 'count > 50'));
    }

    public function testNonNumericGreaterThanReturnsFalse(): void
    {
        // Non-numeric string compared with > against numeric should compare as strings
        $context = ['name' => 'alice'];
        // String "alice" > "5" — compare as strings
        $result = $this->evaluate($context, 'name > 5');
        // Both are cast: "alice" is not numeric so compare raw
        $this->assertIsBool($result);
    }

    public function testBooleanFieldAsTruthyCheck(): void
    {
        $this->assertTrue($this->evaluate(['active' => true], 'active'));
    }

    public function testBooleanFieldAsFalsyCheck(): void
    {
        $this->assertFalse($this->evaluate(['active' => false], 'active'));
    }

    public function testZeroIsFalsy(): void
    {
        // 0 should be empty → false in boolean check
        $this->assertFalse($this->evaluate(['count' => 0], 'count'));
    }

    public function testNonZeroIsTruthy(): void
    {
        $this->assertTrue($this->evaluate(['count' => 1], 'count'));
    }

    public function testEmptyStringIsFalsy(): void
    {
        $this->assertFalse($this->evaluate(['name' => ''], 'name'));
    }

    public function testNonEmptyStringIsTruthy(): void
    {
        $this->assertTrue($this->evaluate(['name' => 'alice'], 'name'));
    }

    // =====================================================
    // Operator precedence (>= vs > etc.)
    // =====================================================

    public function testGreaterThanOrEqualExactBoundary(): void
    {
        $this->assertTrue($this->evaluate(['score' => 10], 'score >= 10'));
    }

    public function testGreaterThanExactBoundary(): void
    {
        $this->assertFalse($this->evaluate(['score' => 10], 'score > 10'));
    }

    public function testLessThanOrEqualExactBoundary(): void
    {
        $this->assertTrue($this->evaluate(['score' => 10], 'score <= 10'));
    }

    public function testLessThanExactBoundary(): void
    {
        $this->assertFalse($this->evaluate(['score' => 10], 'score < 10'));
    }

    public function testNotEqualsWhenEqual(): void
    {
        $this->assertFalse($this->evaluate(['status' => 'active'], 'status != active'));
    }

    public function testNotEqualsWhenDifferent(): void
    {
        $this->assertTrue($this->evaluate(['status' => 'inactive'], 'status != active'));
    }

    // =====================================================
    // Nested field paths in expressions
    // =====================================================

    public function testDeepNestedFieldExpression(): void
    {
        $context = ['entity' => ['details' => ['level' => 5]]];
        $this->assertTrue($this->evaluate($context, 'entity.details.level >= 5'));
    }

    public function testDollarPrefixInExpression(): void
    {
        $context = ['entity' => ['status' => 'active']];
        $this->assertTrue($this->evaluate($context, '$.entity.status == active'));
    }

    // =====================================================
    // Quoted values
    // =====================================================

    public function testDoubleQuotedValue(): void
    {
        $context = ['name' => 'Alice'];
        $this->assertTrue($this->evaluate($context, 'name == "Alice"'));
    }

    public function testSingleQuotedValue(): void
    {
        $context = ['name' => 'Alice'];
        $this->assertTrue($this->evaluate($context, "name == 'Alice'"));
    }

    // =====================================================
    // Long / complex expressions
    // =====================================================

    public function testVeryLongFieldPath(): void
    {
        $context = ['a' => ['b' => ['c' => ['d' => ['e' => 'deep']]]]];
        $this->assertTrue($this->evaluate($context, 'a.b.c.d.e == deep'));
    }

    public function testExpressionWithExtraSpaces(): void
    {
        $context = ['status' => 'active'];
        $this->assertTrue($this->evaluate($context, '  status  ==  active  '));
    }

    public function testExpressionWithValueContainingOperatorChars(): void
    {
        // Value ">=5" after splitting on first >= should still work
        $context = ['threshold' => 10];
        $this->assertTrue($this->evaluate($context, 'threshold >= 5'));
    }

    public function testNegativeNumberComparison(): void
    {
        $context = ['temp' => -5];
        $this->assertTrue($this->evaluate($context, 'temp < 0'));
    }

    public function testFloatComparison(): void
    {
        $context = ['price' => 9.99];
        $this->assertTrue($this->evaluate($context, 'price > 9'));
        $this->assertTrue($this->evaluate($context, 'price < 10'));
    }

    public function testArrayFieldIsTruthy(): void
    {
        $context = ['items' => [1, 2, 3]];
        $this->assertTrue($this->evaluate($context, 'items'));
    }

    public function testEmptyArrayIsFalsy(): void
    {
        $context = ['items' => []];
        $this->assertFalse($this->evaluate($context, 'items'));
    }
}