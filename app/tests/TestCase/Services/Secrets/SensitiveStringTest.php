<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Secrets;

use App\Services\Secrets\SensitiveString;
use Cake\TestSuite\TestCase;
use LogicException;

class SensitiveStringTest extends TestCase
{
    public function testRevealReturnsPlaintextOnlyThroughExplicitMethod(): void
    {
        $secret = new SensitiveString('top-secret');

        $this->assertSame('top-secret', $secret->reveal());
        $this->assertSame(10, $secret->length());
        $this->assertFalse($secret->isEmpty());
    }

    public function testStringConversionThrowsInsteadOfReturningRedactedPlaceholder(): void
    {
        $secret = new SensitiveString('top-secret');

        $this->expectException(LogicException::class);
        (string)$secret;
    }

    public function testCommonDebugSurfacesAreRedacted(): void
    {
        $secret = new SensitiveString('top-secret');

        $this->assertSame('"***"', json_encode($secret));
        $this->assertStringNotContainsString('top-secret', print_r($secret, true));
        $this->assertStringNotContainsString('top-secret', serialize($secret));

        ob_start();
        var_dump($secret);
        $dump = (string)ob_get_clean();
        $this->assertStringNotContainsString('top-secret', $dump);
        $this->assertStringContainsString('***', $dump);
    }
}
