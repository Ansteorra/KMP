<?php
declare(strict_types=1);

/**
 * Test fixture Mailer for Queue plugin tests
 */

namespace TestApp\Mailer;

use Cake\Mailer\Mailer;

/**
 * Simple test mailer for testing MailerTask
 */
class TestMailer extends Mailer
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $debug = null;

    /**
     * Test action that receives debug data
     *
     * @param mixed $debugData Debug data to store
     * @return void
     */
    public function testAction($debugData): void
    {
        $this->debug = [
            'message' => var_export($debugData, true),
        ];
    }

    /**
     * Get debug information
     *
     * @return array<string, mixed>|null
     */
    public function getDebug(): ?array
    {
        return $this->debug;
    }
}
