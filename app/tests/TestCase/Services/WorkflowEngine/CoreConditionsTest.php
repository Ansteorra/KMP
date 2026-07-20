<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Services\WorkflowEngine\Conditions\CoreConditions;
use Cake\TestSuite\TestCase;

/**
 * Unit tests for CoreConditions: field resolution, comparisons, and expression evaluation.
 */
class CoreConditionsTest extends TestCase
{
    private CoreConditions $conditions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conditions = new CoreConditions();
    }

    // =====================================================
    // resolveFieldPath()
    // =====================================================

    public function testResolveFieldPathSimpleKey(): void
    {
        $context = ['name' => 'Alice'];
        $this->assertSame('Alice', CoreConditions::resolveFieldPath($context, 'name'));
    }

    public function testResolveFieldPathDotNotation(): void
    {
        $context = ['entity' => ['status' => 'active']];
        $this->assertSame('active', CoreConditions::resolveFieldPath($context, 'entity.status'));
    }

    public function testResolveFieldPathStripsDollarPrefix(): void
    {
        $context = ['entity' => ['id' => 42]];
        $this->assertSame(42, CoreConditions::resolveFieldPath($context, '$.entity.id'));
    }

    public function testResolveFieldPathDeepNesting(): void
    {
        $context = ['a' => ['b' => ['c' => ['d' => 'deep']]]];
        $this->assertSame('deep', CoreConditions::resolveFieldPath($context, 'a.b.c.d'));
    }

    public function testResolveFieldPathMissingFieldReturnsNull(): void
    {
        $context = ['entity' => ['name' => 'Bob']];
        $this->assertNull(CoreConditions::resolveFieldPath($context, 'entity.missing'));
    }

    public function testResolveFieldPathMissingIntermediateReturnsNull(): void
    {
        $context = ['entity' => ['name' => 'Bob']];
        $this->assertNull(CoreConditions::resolveFieldPath($context, 'missing.field.path'));
    }

    public function testResolveFieldPathEmptyContext(): void
    {
        $this->assertNull(CoreConditions::resolveFieldPath([], 'anything'));
    }

    public function testResolveFieldPathReturnsArray(): void
    {
        $context = ['items' => ['list' => [1, 2, 3]]];
        $this->assertSame([1, 2, 3], CoreConditions::resolveFieldPath($context, 'items.list'));
    }

    public function testResolveFieldPathWithObjectProperty(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';
        $context = ['entity' => $obj];
        $this->assertSame('test', CoreConditions::resolveFieldPath($context, 'entity.name'));
    }

    public function testResolveFieldPathReturnsFalseValue(): void
    {
        $context = ['flag' => false];
        $this->assertFalse(CoreConditions::resolveFieldPath($context, 'flag'));
    }

    public function testResolveFieldPathReturnsZero(): void
    {
        $context = ['count' => 0];
        $this->assertSame(0, CoreConditions::resolveFieldPath($context, 'count'));
    }

    public function testResolveFieldPathReturnsEmptyString(): void
    {
        $context = ['value' => ''];
        $this->assertSame('', CoreConditions::resolveFieldPath($context, 'value'));
    }

    // =====================================================
    // fieldEquals()
    // =====================================================

    public function testFieldEqualsStringMatch(): void
    {
        $context = ['status' => 'active'];
        $this->assertTrue($this->conditions->fieldEquals($context, ['field' => 'status', 'value' => 'active']));
    }

    public function testFieldEqualsStringMismatch(): void
    {
        $context = ['status' => 'active'];
        $this->assertFalse($this->conditions->fieldEquals($context, ['field' => 'status', 'value' => 'inactive']));
    }

    public function testFieldEqualsIntegerMatch(): void
    {
        $context = ['count' => 5];
        $this->assertTrue($this->conditions->fieldEquals($context, ['field' => 'count', 'value' => 5]));
    }

    public function testFieldEqualsLooseTypeComparison(): void
    {
        // Loose comparison: int 5 == string "5"
        $context = ['count' => 5];
        $this->assertTrue($this->conditions->fieldEquals($context, ['field' => 'count', 'value' => '5']));
    }

    public function testFieldEqualsBooleanTrue(): void
    {
        $context = ['active' => true];
        $this->assertTrue($this->conditions->fieldEquals($context, ['field' => 'active', 'value' => true]));
    }

    public function testFieldEqualsNestedField(): void
    {
        $context = ['entity' => ['type' => 'warrant']];
        $this->assertTrue($this->conditions->fieldEquals($context, ['field' => 'entity.type', 'value' => 'warrant']));
    }

    public function testFieldEqualsMissingFieldIsNull(): void
    {
        $context = ['name' => 'Alice'];
        $this->assertTrue($this->conditions->fieldEquals($context, ['field' => 'missing', 'value' => null]));
    }

    public function testFieldEqualsNullNotEqualToString(): void
    {
        $context = ['name' => 'Alice'];
        $this->assertFalse($this->conditions->fieldEquals($context, ['field' => 'missing', 'value' => 'something']));
    }

    // =====================================================
    // fieldNotEmpty()
    // =====================================================

    public function testFieldNotEmptyWithValue(): void
    {
        $context = ['name' => 'Alice'];
        $this->assertTrue($this->conditions->fieldNotEmpty($context, ['field' => 'name']));
    }

    public function testFieldNotEmptyWithEmptyString(): void
    {
        $context = ['name' => ''];
        $this->assertFalse($this->conditions->fieldNotEmpty($context, ['field' => 'name']));
    }

    public function testFieldNotEmptyWithNull(): void
    {
        $context = ['name' => null];
        $this->assertFalse($this->conditions->fieldNotEmpty($context, ['field' => 'name']));
    }

    public function testFieldNotEmptyWithMissingField(): void
    {
        $this->assertFalse($this->conditions->fieldNotEmpty([], ['field' => 'missing']));
    }

    public function testFieldNotEmptyWithZero(): void
    {
        // PHP empty() considers 0 as empty
        $context = ['count' => 0];
        $this->assertFalse($this->conditions->fieldNotEmpty($context, ['field' => 'count']));
    }

    public function testFieldNotEmptyWithNonEmptyArray(): void
    {
        $context = ['items' => [1, 2]];
        $this->assertTrue($this->conditions->fieldNotEmpty($context, ['field' => 'items']));
    }

    public function testFieldNotEmptyWithEmptyArray(): void
    {
        $context = ['items' => []];
        $this->assertFalse($this->conditions->fieldNotEmpty($context, ['field' => 'items']));
    }

    // =====================================================
    // fieldGreaterThan()
    // =====================================================

    public function testFieldGreaterThanTrue(): void
    {
        $context = ['score' => 10];
        $this->assertTrue($this->conditions->fieldGreaterThan($context, ['field' => 'score', 'value' => 5]));
    }

    public function testFieldGreaterThanFalseWhenEqual(): void
    {
        $context = ['score' => 5];
        $this->assertFalse($this->conditions->fieldGreaterThan($context, ['field' => 'score', 'value' => 5]));
    }

    public function testFieldGreaterThanFalseWhenLess(): void
    {
        $context = ['score' => 3];
        $this->assertFalse($this->conditions->fieldGreaterThan($context, ['field' => 'score', 'value' => 5]));
    }

    public function testFieldGreaterThanWithStringNumbers(): void
    {
        $context = ['score' => '10'];
        $this->assertTrue($this->conditions->fieldGreaterThan($context, ['field' => 'score', 'value' => '5']));
    }

    public function testFieldGreaterThanWithFloats(): void
    {
        $context = ['score' => 3.14];
        $this->assertTrue($this->conditions->fieldGreaterThan($context, ['field' => 'score', 'value' => 2.71]));
    }

    public function testFieldGreaterThanNonNumericFieldReturnsFalse(): void
    {
        $context = ['name' => 'Alice'];
        $this->assertFalse($this->conditions->fieldGreaterThan($context, ['field' => 'name', 'value' => 5]));
    }

    public function testFieldGreaterThanNonNumericValueReturnsFalse(): void
    {
        $context = ['score' => 10];
        $this->assertFalse($this->conditions->fieldGreaterThan($context, ['field' => 'score', 'value' => 'abc']));
    }

    public function testFieldGreaterThanMissingFieldReturnsFalse(): void
    {
        $this->assertFalse($this->conditions->fieldGreaterThan([], ['field' => 'missing', 'value' => 5]));
    }

    public function testFieldGreaterThanNegativeNumbers(): void
    {
        $context = ['temp' => -5];
        $this->assertTrue($this->conditions->fieldGreaterThan($context, ['field' => 'temp', 'value' => -10]));
    }

    // =====================================================
    // evaluateExpression()
    // =====================================================

    public function testEvaluateExpressionEquals(): void
    {
        $context = ['status' => 'active'];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'status == active']));
    }

    public function testEvaluateExpressionNotEquals(): void
    {
        $context = ['status' => 'active'];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'status != inactive']));
    }

    public function testEvaluateExpressionGreaterThan(): void
    {
        $context = ['score' => 10];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'score > 5']));
    }

    public function testEvaluateExpressionLessThan(): void
    {
        $context = ['score' => 3];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'score < 5']));
    }

    public function testEvaluateExpressionGreaterThanOrEqual(): void
    {
        $context = ['score' => 5];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'score >= 5']));
    }

    public function testEvaluateExpressionLessThanOrEqual(): void
    {
        $context = ['score' => 5];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'score <= 5']));
    }

    public function testEvaluateExpressionEqualsFalse(): void
    {
        $context = ['status' => 'active'];
        $this->assertFalse($this->conditions->evaluateExpression($context, ['expression' => 'status == inactive']));
    }

    public function testEvaluateExpressionGreaterThanFalse(): void
    {
        $context = ['score' => 3];
        $this->assertFalse($this->conditions->evaluateExpression($context, ['expression' => 'score > 5']));
    }

    public function testEvaluateExpressionEmptyReturnsFalse(): void
    {
        $this->assertFalse($this->conditions->evaluateExpression([], ['expression' => '']));
    }

    public function testEvaluateExpressionNullExpressionReturnsFalse(): void
    {
        $this->assertFalse($this->conditions->evaluateExpression([], ['expression' => null]));
    }

    public function testEvaluateExpressionWhitespaceOnlyReturnsFalse(): void
    {
        $this->assertFalse($this->conditions->evaluateExpression([], ['expression' => '   ']));
    }

    public function testEvaluateExpressionNumericCasting(): void
    {
        $context = ['count' => '10'];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'count > 5']));
    }

    public function testEvaluateExpressionMissingFieldComparison(): void
    {
        $context = ['name' => 'Alice'];
        // Missing field resolves to null, null == "value" is false
        $this->assertFalse($this->conditions->evaluateExpression($context, ['expression' => 'missing == value']));
    }

    public function testEvaluateExpressionNestedFieldPath(): void
    {
        $context = ['entity' => ['status' => 'approved']];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'entity.status == approved']));
    }

    public function testEvaluateExpressionBooleanFieldCheck(): void
    {
        // No operator → treated as boolean field check
        $context = ['is_active' => true];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'is_active']));
    }

    public function testEvaluateExpressionBooleanFieldCheckFalse(): void
    {
        $context = ['is_active' => false];
        $this->assertFalse($this->conditions->evaluateExpression($context, ['expression' => 'is_active']));
    }

    public function testEvaluateExpressionBooleanFieldCheckMissing(): void
    {
        $this->assertFalse($this->conditions->evaluateExpression([], ['expression' => 'nonexistent']));
    }

    public function testEvaluateExpressionWithQuotedValue(): void
    {
        $context = ['name' => 'Alice'];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'name == "Alice"']));
    }

    public function testEvaluateExpressionWithSingleQuotedValue(): void
    {
        $context = ['name' => 'Alice'];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => "name == 'Alice'"]));
    }

    public function testEvaluateExpressionNotEqualsWithDifferentTypes(): void
    {
        $context = ['count' => 0];
        // 0 != 1 → true
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'count != 1']));
    }

    public function testEvaluateExpressionLessThanOrEqualWhenLess(): void
    {
        $context = ['score' => 3];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'score <= 5']));
    }

    public function testEvaluateExpressionGreaterThanOrEqualWhenGreater(): void
    {
        $context = ['score' => 10];
        $this->assertTrue($this->conditions->evaluateExpression($context, ['expression' => 'score >= 5']));
    }
}
