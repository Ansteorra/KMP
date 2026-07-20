<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Services\WorkflowEngine\ExpressionEvaluator;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;

/**
 * Unit tests for ExpressionEvaluator: templates, dates, conditionals, arithmetic, and paths.
 */
class ExpressionEvaluatorTest extends TestCase
{
    private ExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new ExpressionEvaluator();
    }

    // =====================================================
    // evaluateTemplate() — string interpolation
    // =====================================================

    public function testTemplateSimpleInterpolation(): void
    {
        $context = ['member' => ['name' => 'Alice']];
        $result = $this->evaluator->evaluateTemplate('Hello {{$.member.name}}', $context);
        $this->assertSame('Hello Alice', $result);
    }

    public function testTemplateMultiplePlaceholders(): void
    {
        $context = [
            'member' => ['sca_name' => 'Sir Test'],
            'branch' => ['name' => 'Lochac'],
        ];
        $result = $this->evaluator->evaluateTemplate(
            'Warrant for {{$.member.sca_name}} at {{$.branch.name}}',
            $context,
        );
        $this->assertSame('Warrant for Sir Test at Lochac', $result);
    }

    public function testTemplateNestedPath(): void
    {
        $context = ['a' => ['b' => ['c' => 'deep']]];
        $result = $this->evaluator->evaluateTemplate('Value: {{$.a.b.c}}', $context);
        $this->assertSame('Value: deep', $result);
    }

    public function testTemplateMissingVariableReplacedWithEmpty(): void
    {
        $context = ['member' => ['name' => 'Alice']];
        $result = $this->evaluator->evaluateTemplate('Hi {{$.member.missing}}!', $context);
        $this->assertSame('Hi !', $result);
    }

    public function testTemplateNoPlaceholdersReturnedAsIs(): void
    {
        $result = $this->evaluator->evaluateTemplate('Plain text', []);
        $this->assertSame('Plain text', $result);
    }

    public function testTemplateNumericValueInterpolation(): void
    {
        $context = ['count' => 42];
        $result = $this->evaluator->evaluateTemplate('Count: {{$.count}}', $context);
        $this->assertSame('Count: 42', $result);
    }

    // =====================================================
    // evaluateDateExpression() — date arithmetic
    // =====================================================

    public function testDateNow(): void
    {
        $result = $this->evaluator->evaluateDateExpression('now', []);
        $this->assertInstanceOf(\DateTimeInterface::class, $result);

        $diff = abs(time() - $result->getTimestamp());
        $this->assertLessThan(5, $diff);
    }

    public function testDateNowPlusDays(): void
    {
        $result = $this->evaluator->evaluateDateExpression('now + 30 days', []);
        $this->assertInstanceOf(\DateTimeInterface::class, $result);

        $expected = (new DateTime())->modify('+30 days');
        $diff = abs($expected->getTimestamp() - $result->getTimestamp());
        $this->assertLessThan(5, $diff);
    }

    public function testDateContextPathPlusDays(): void
    {
        $startDate = '2025-01-15';
        $context = ['start_on' => $startDate];
        $result = $this->evaluator->evaluateDateExpression('$.start_on + 365 days', $context);

        $this->assertInstanceOf(\DateTimeInterface::class, $result);
        $this->assertSame('2026-01-15', $result->format('Y-m-d'));
    }

    public function testDateAddMonths(): void
    {
        $context = ['start_on' => '2025-03-01'];
        $result = $this->evaluator->evaluateDateExpression('$.start_on + 6 months', $context);

        $this->assertInstanceOf(\DateTimeInterface::class, $result);
        $this->assertSame('2025-09-01', $result->format('Y-m-d'));
    }

    public function testDateAddYears(): void
    {
        $context = ['start_on' => '2025-01-01'];
        $result = $this->evaluator->evaluateDateExpression('$.start_on + 1 year', $context);

        $this->assertInstanceOf(\DateTimeInterface::class, $result);
        $this->assertSame('2026-01-01', $result->format('Y-m-d'));
    }

    public function testDateSubtractDays(): void
    {
        $context = ['end_on' => '2025-06-15'];
        $result = $this->evaluator->evaluateDateExpression('$.end_on - 30 days', $context);

        $this->assertInstanceOf(\DateTimeInterface::class, $result);
        $this->assertSame('2025-05-16', $result->format('Y-m-d'));
    }

    public function testDateWithContextAmountPath(): void
    {
        $context = [
            'start_on' => '2025-01-01',
            'activity' => ['term_length' => 90],
        ];
        $result = $this->evaluator->evaluateDateExpression(
            '$.start_on + $.activity.term_length days',
            $context,
        );

        $this->assertInstanceOf(\DateTimeInterface::class, $result);
        $this->assertSame('2025-04-01', $result->format('Y-m-d'));
    }

    public function testDateWithDateTimeObjectInContext(): void
    {
        $context = ['start_on' => new DateTime('2025-03-15')];
        $result = $this->evaluator->evaluateDateExpression('$.start_on + 10 days', $context);

        $this->assertInstanceOf(\DateTimeInterface::class, $result);
        $this->assertSame('2025-03-25', $result->format('Y-m-d'));
    }

    public function testDateInvalidExpressionReturnsNull(): void
    {
        $result = $this->evaluator->evaluateDateExpression('not a date', []);
        $this->assertNull($result);
    }

    // =====================================================
    // evaluateConditional() — ternary expressions
    // =====================================================

    public function testConditionalTruePath(): void
    {
        $context = ['member' => ['age' => 25]];
        $result = $this->evaluator->evaluateConditional(
            "$.member.age >= 18 ? 'adult' : 'minor'",
            $context,
        );
        $this->assertSame('adult', $result);
    }

    public function testConditionalFalsePath(): void
    {
        $context = ['member' => ['age' => 12]];
        $result = $this->evaluator->evaluateConditional(
            "$.member.age >= 18 ? 'adult' : 'minor'",
            $context,
        );
        $this->assertSame('minor', $result);
    }

    public function testConditionalEquality(): void
    {
        $context = ['status' => 'active'];
        $result = $this->evaluator->evaluateConditional(
            "$.status == 'active' ? 'yes' : 'no'",
            $context,
        );
        $this->assertSame('yes', $result);
    }

    public function testConditionalNotEquals(): void
    {
        $context = ['status' => 'pending'];
        $result = $this->evaluator->evaluateConditional(
            "$.status != 'active' ? 'inactive' : 'active'",
            $context,
        );
        $this->assertSame('inactive', $result);
    }

    public function testConditionalWithContextPathResult(): void
    {
        $context = ['member' => ['age' => 25, 'name' => 'Alice']];
        $result = $this->evaluator->evaluateConditional(
            "$.member.age >= 18 ? $.member.name : 'underage'",
            $context,
        );
        $this->assertSame('Alice', $result);
    }

    // =====================================================
    // evaluateArithmetic() — basic math
    // =====================================================

    public function testArithmeticAddition(): void
    {
        $context = ['count' => 5];
        $result = $this->evaluator->evaluateArithmetic('$.count + 1', $context);
        $this->assertSame(6, $result);
    }

    public function testArithmeticSubtraction(): void
    {
        $context = ['total' => 100, 'discount' => 15];
        $result = $this->evaluator->evaluateArithmetic('$.total - $.discount', $context);
        $this->assertSame(85, $result);
    }

    public function testArithmeticMultiplication(): void
    {
        $context = ['price' => 10, 'quantity' => 3];
        $result = $this->evaluator->evaluateArithmetic('$.price * $.quantity', $context);
        $this->assertSame(30, $result);
    }

    public function testArithmeticDivision(): void
    {
        $context = ['total' => 100];
        $result = $this->evaluator->evaluateArithmetic('$.total / 4', $context);
        $this->assertSame(25, $result);
    }

    public function testArithmeticDivisionByZero(): void
    {
        $context = ['total' => 100];
        $result = $this->evaluator->evaluateArithmetic('$.total / 0', $context);
        $this->assertSame(0, $result);
    }

    public function testArithmeticWithFloats(): void
    {
        $context = ['total' => 100];
        $result = $this->evaluator->evaluateArithmetic('$.total * 0.1', $context);
        $this->assertEquals(10.0, $result);
    }

    // =====================================================
    // evaluate() — main entry point / context path resolution
    // =====================================================

    public function testEvaluateContextPath(): void
    {
        $context = ['member' => ['email' => 'test@example.com']];
        $result = $this->evaluator->evaluate('$.member.email', $context);
        $this->assertSame('test@example.com', $result);
    }

    public function testEvaluateDeepNesting(): void
    {
        $context = ['a' => ['b' => ['c' => ['d' => 'found']]]];
        $result = $this->evaluator->evaluate('$.a.b.c.d', $context);
        $this->assertSame('found', $result);
    }

    public function testEvaluateMissingPathReturnsNull(): void
    {
        $context = ['member' => ['name' => 'Alice']];
        $result = $this->evaluator->evaluate('$.member.nonexistent', $context);
        $this->assertNull($result);
    }

    public function testEvaluateEmptyStringReturnsNull(): void
    {
        $result = $this->evaluator->evaluate('', []);
        $this->assertNull($result);
    }

    public function testEvaluateLiteralString(): void
    {
        $result = $this->evaluator->evaluate('plain-text', []);
        $this->assertSame('plain-text', $result);
    }

    // =====================================================
    // Edge cases
    // =====================================================

    public function testEvaluateNullContextValueInTemplate(): void
    {
        $context = ['val' => null];
        $result = $this->evaluator->evaluateTemplate('Result: {{$.val}}', $context);
        $this->assertSame('Result: ', $result);
    }

    public function testArithmeticWithMissingContextReturnsZero(): void
    {
        $result = $this->evaluator->evaluateArithmetic('$.missing + 5', []);
        $this->assertSame(5, $result);
    }

    public function testDateMissingContextReturnsNull(): void
    {
        $result = $this->evaluator->evaluateDateExpression('$.missing + 30 days', []);
        $this->assertNull($result);
    }

    public function testStringConcatenation(): void
    {
        $context = ['first' => 'John', 'last' => 'Doe'];
        $result = $this->evaluator->evaluate("$.first . ' ' . $.last", $context);
        $this->assertSame('John Doe', $result);
    }

    public function testConditionalInvalidExpressionReturnsNull(): void
    {
        $result = $this->evaluator->evaluateConditional('no question mark here', []);
        $this->assertNull($result);
    }
}
