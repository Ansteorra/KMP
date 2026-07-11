<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\KMP\MissingTenantContextException;
use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\Platform\TenantQueueDrainService;
use Cake\Console\CommandInterface;
use Cake\Core\ContainerInterface;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use Queue\Queue\Processor;
use RuntimeException;

class TenantQueueDrainServiceTest extends TestCase
{
    public function testDrainPassesBoundsAndReturnsProcessedCount(): void
    {
        $processor = new class extends Processor {
            /**
             * @var array<string, mixed>
             */
            public array $receivedArguments = [];

            public function __construct()
            {
            }

            public function run(array $args): int
            {
                $this->receivedArguments = $args;

                return CommandInterface::CODE_SUCCESS;
            }

            public function getProcessedJobs(): int
            {
                return 3;
            }
        };
        $service = new TenantQueueDrainService(
            $this->createStub(ContainerInterface::class),
            static fn(): Processor => $processor,
        );

        $processed = TenantContext::with(
            $this->tenant(),
            static fn(): int => $service->drain(7, 11),
        );

        $this->assertSame(3, $processed);
        $this->assertSame([
            'max-jobs' => 7,
            'max-runtime' => 11,
            'exit-when-empty' => true,
            'quiet' => true,
        ], $processor->receivedArguments);
    }

    public function testDrainRequiresTenantContext(): void
    {
        $service = new TenantQueueDrainService($this->createStub(ContainerInterface::class));

        $this->expectException(MissingTenantContextException::class);

        $service->drain();
    }

    public function testDrainRejectsUnboundedJobLimit(): void
    {
        $service = new TenantQueueDrainService($this->createStub(ContainerInterface::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('max jobs must be between');

        TenantContext::with($this->tenant(), static fn(): int => $service->drain(101));
    }

    public function testDrainSurfacesProcessorFailure(): void
    {
        $processor = new class extends Processor {
            public function __construct()
            {
            }

            public function run(array $args): int
            {
                return CommandInterface::CODE_ERROR;
            }
        };
        $service = new TenantQueueDrainService(
            $this->createStub(ContainerInterface::class),
            static fn(): Processor => $processor,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('failed for tenant "alpha"');

        TenantContext::with($this->tenant(), static fn(): int => $service->drain());
    }

    private function tenant(): TenantMetadata
    {
        return new TenantMetadata(
            '11111111-1111-4111-8111-111111111111',
            'alpha',
            'Alpha',
            'active',
            'db',
            'alpha',
            'alpha',
        );
    }
}
