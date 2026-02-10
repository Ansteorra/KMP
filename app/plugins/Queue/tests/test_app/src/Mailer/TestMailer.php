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
        ob_start();
        var_dump($debugData);
        $output = ob_get_clean();

        $this->debug = [
            'message' => $output,
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
