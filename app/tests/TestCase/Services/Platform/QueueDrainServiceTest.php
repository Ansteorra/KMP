<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\KMP\MissingTenantContextException;
use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\Platform\QueueDrainService;
use Cake\Console\CommandInterface;
use Cake\Core\ContainerInterface;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use Queue\Queue\Processor;
use RuntimeException;

class QueueDrainServiceTest extends TestCase
{
    public function testDrainDefaultPassesBoundsAndReturnsProcessedCount(): void
    {
        $processor = $this->processor(CommandInterface::CODE_SUCCESS, 3);
        $service = new QueueDrainService(
            $this->createStub(ContainerInterface::class),
            static fn(): Processor => $processor,
        );

        $processed = $service->drainDefault(7, 11);

        $this->assertSame(3, $processed);
        $this->assertSame([
            'max-jobs' => 7,
            'max-runtime' => 11,
            'exit-when-empty' => true,
            'quiet' => true,
        ], $processor->receivedArguments);
    }

    public function testDrainTenantUsesActiveTenantContext(): void
    {
        $processor = $this->processor(CommandInterface::CODE_SUCCESS, 2);
        $service = new QueueDrainService(
            $this->createStub(ContainerInterface::class),
            static fn(): Processor => $processor,
        );

        $processed = TenantContext::with(
            $this->tenant(),
            static fn(): int => $service->drainTenant(),
        );

        $this->assertSame(2, $processed);
    }

    public function testDrainTenantRequiresTenantContext(): void
    {
        $service = new QueueDrainService($this->createStub(ContainerInterface::class));

        $this->expectException(MissingTenantContextException::class);

        $service->drainTenant();
    }

    public function testDrainRejectsUnboundedJobLimit(): void
    {
        $service = new QueueDrainService($this->createStub(ContainerInterface::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('max jobs must be between');

        $service->drainDefault(101);
    }

    public function testDrainSurfacesProcessorFailureWithQueueName(): void
    {
        $processor = $this->processor(CommandInterface::CODE_ERROR, 0);
        $service = new QueueDrainService(
            $this->createStub(ContainerInterface::class),
            static fn(): Processor => $processor,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('failed for default datasource');

        $service->drainDefault();
    }

    private function processor(int $exitCode, int $processedJobs): Processor
    {
        return new class ($exitCode, $processedJobs) extends Processor {
            /**
             * @var array<string, mixed>
             */
            public array $receivedArguments = [];

            public function __construct(
                private readonly int $exitCode,
                int $processedJobs,
            ) {
                $this->processedJobs = $processedJobs;
            }

            public function run(array $args): int
            {
                $this->receivedArguments = $args;

                return $this->exitCode;
            }
        };
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
