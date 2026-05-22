<?php
declare(strict_types=1);

namespace App\Services\Escrow;

use App\Services\Secrets\SensitiveString;
use RuntimeException;

class NonProductionDeterministicKekEscrowSplitter implements KekEscrowSplitterInterface
{
    /**
     * Constructor.
     *
     * @param string $environment Current runtime environment
     */
    public function __construct(private readonly string $environment)
    {
    }

    /**
     * @inheritDoc
     */
    public function split(SensitiveString $kek, int $threshold, int $shareCount): array
    {
        $this->assertNonProduction();
        if ($threshold < 2 || $shareCount < $threshold) {
            throw new RuntimeException('Escrow threshold must be at least 2 and no greater than share count.');
        }

        $fingerprint = hash('sha256', $kek->reveal());
        $shares = [];
        for ($index = 1; $index <= $shareCount; $index++) {
            $shares[] = new SensitiveString(sprintf(
                'placeholder-share-%d-of-%d:%s',
                $index,
                $shareCount,
                $fingerprint,
            ));
        }

        return $shares;
    }

    /**
     * @inheritDoc
     */
    public function reassemble(array $shares): SensitiveString
    {
        $this->assertNonProduction();

        throw new RuntimeException(
            'Placeholder escrow shares cannot reassemble a KEK. Use a vetted Shamir implementation.',
        );
    }

    /**
     * Fail closed when the placeholder splitter is accidentally used in production.
     *
     * @return void
     */
    private function assertNonProduction(): void
    {
        if ($this->environment === 'production') {
            throw new RuntimeException('Placeholder KEK escrow splitter is forbidden in production.');
        }
    }
}
