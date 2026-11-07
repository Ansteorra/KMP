<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Test\TestCase\BaseTestCase;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;

class SeedPublicCalendarCommandTest extends BaseTestCase
{
    use ConsoleIntegrationTestTrait;

    public function testPocSeedingRequiresStagingAdministrativeOptIn(): void
    {
        $names = ['KMP_ENV', 'KMP_ADMIN_JOB', 'KMP_ALLOW_POC_CALENDAR_SEED'];
        $original = array_map('getenv', $names);
        try {
            foreach ([['production', 'true', 'true'], ['staging', 'false', 'true'], ['staging', 'true', 'false']] as $values) {
                foreach ($names as $index => $name) {
                    putenv($name . '=' . $values[$index]);
                }
                $this->exec('seed_public_calendar --poc --host example.org');
                $this->assertExitCode(1);
                $this->assertErrorContains('explicitly enabled staging administrative job');
            }
        } finally {
            foreach ($names as $index => $name) {
                putenv($original[$index] === false ? $name : $name . '=' . $original[$index]);
            }
        }
    }

    public function testRefusesNonDevelopmentAndRemoteHostsBeforeResolvingTenants(): void
    {
        $previous = Configure::read('debug');
        try {
            Configure::write('debug', false);
            $this->exec('seed_public_calendar --refresh-activity-colors');
            $this->assertExitCode(1);
            $this->assertErrorContains('Calendar demos require debug mode');
            Configure::write('debug', true);
            $this->exec('seed_public_calendar --host example.com');
            $this->assertExitCode(1);
            $this->assertErrorContains('local tenant hostname');
        } finally {
            Configure::write('debug', $previous);
        }
    }
}
