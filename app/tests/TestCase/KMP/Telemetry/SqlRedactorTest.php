<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP\Telemetry;

use App\KMP\Telemetry\SqlRedactor;
use Cake\TestSuite\TestCase;

class SqlRedactorTest extends TestCase
{
    public function testRedactsSingleQuotedStringLiterals(): void
    {
        $sql = "SELECT * FROM members WHERE sca_name = 'Iago of Smithfield'";
        $this->assertSame(
            "SELECT * FROM members WHERE sca_name = '?'",
            SqlRedactor::redact($sql),
        );
    }

    public function testRedactsNumericLiterals(): void
    {
        $sql = 'SELECT * FROM members WHERE id = 42 AND age >= 18';
        $this->assertSame(
            'SELECT * FROM members WHERE id = ? AND age >= ?',
            SqlRedactor::redact($sql),
        );
    }

    public function testRedactsEmailsAddresses(): void
    {
        $sql = "INSERT INTO members (email_address) VALUES ('iago@example.com')";
        $redacted = SqlRedactor::redact($sql);
        $this->assertStringNotContainsString('iago@example.com', $redacted);
        $this->assertStringContainsString('?', $redacted);
    }

    public function testRedactsIpAddresses(): void
    {
        $sql = 'INSERT INTO audit_log (ip) VALUES (192.168.1.42)';
        $redacted = SqlRedactor::redact($sql);
        $this->assertStringNotContainsString('192.168.1.42', $redacted);
    }

    public function testRedactsLongHexTokens(): void
    {
        $token = str_repeat('a1b2c3d4', 4);
        $sql = "UPDATE sessions SET token = '$token' WHERE id = 1";
        $redacted = SqlRedactor::redact($sql);
        $this->assertStringNotContainsString($token, $redacted);
    }

    public function testRedactsBoundParamsDump(): void
    {
        $sql = "SELECT * FROM members WHERE id = :c0 params=[c0 => 'sensitive']";
        $redacted = SqlRedactor::redact($sql);
        $this->assertStringContainsString('params=<redacted>', $redacted);
        $this->assertStringNotContainsString('sensitive', $redacted);
    }

    public function testIsIdempotent(): void
    {
        $sql = "SELECT * FROM members WHERE id = 42 AND name = 'test'";
        $once = SqlRedactor::redact($sql);
        $twice = SqlRedactor::redact($once);
        $this->assertSame($once, $twice);
    }

    public function testPreservesStructure(): void
    {
        $sql = "SELECT m.id, m.sca_name FROM members m INNER JOIN branches b ON b.id = m.branch_id WHERE m.email_address = 'a@b.com' AND m.id = 7";
        $redacted = SqlRedactor::redact($sql);
        $this->assertStringContainsString('FROM members m', $redacted);
        $this->assertStringContainsString('INNER JOIN branches b', $redacted);
        $this->assertStringNotContainsString('a@b.com', $redacted);
        $this->assertStringNotContainsString(' 7', $redacted);
    }
}
