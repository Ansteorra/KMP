<?php

declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.1.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App\Mailer;

use App\View\Helper\KmpHelper;
use Cake\Core\App;
use Cake\Mailer\Exception\MissingMailerException;
use Cake\Mailer\Mailer;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\TableRegistry;
use App\KMP\StaticHelpers;
use Throwable;

/**
 * Provides functionality for loading mailer classes
 * onto properties of the host object.
 *
 * Example users of this trait are Cake\Controller\Controller and
 * Cake\Console\Command.
 */
trait QueuedMailerAwareTrait
{
    use MailerAwareTrait;

    protected Mailer $mailer;
    /**
     * Returns a mailer instance.
     *
     * @param string $name Mailer's name.
     * @param array<string, mixed>|string|null $config Array of configs, or profile name string.
     * @return \Cake\Mailer\Mailer
     * @throws \Cake\Mailer\Exception\MissingMailerException if undefined mailer class.
     */
    protected function queueMail(string $name, $action, $to, $vars): void
    {
        $className = App::className($name, 'Mailer', 'Mailer');
        if ($className === null) {
            throw new MissingMailerException(compact('name'));
        }
        $vars['to'] = $to;
        $data = [
            'class' => $className,
            'action' => $action,
            'vars' => $vars,
        ];
        $useEmailQueue = (StaticHelpers::getAppSetting("Email.UseQueue", "no", null, true) === "yes");
        if (!$useEmailQueue) {
            $this->sendMailNow($data);
        } else {
            $this->queueMailJob($data);
        }
    }
    /**
     * Returns a mailer instance.
     *
     * @param string $name Mailer's name.
     * @return \Cake\Mailer\Mailer
     * @throws \Cake\Mailer\Exception\MissingMailerException if undefined mailer class.
     */
    protected function sendMailNow(array $data): void
    {
        $this->mailer = $this->getMailer($data['class']);

        try {
            $this->mailer->setTransport($data['transport'] ?? 'default');
            $result = $this->mailer->send($data['action'], $data['vars'] ?? []);
        } catch (Throwable $e) {
            throw $e;
        }
    }
    /**
     * Queues a mail job to be processed later.
     *
     * @param array $data Data to be passed to the mailer.
     * @return void
     */
    protected function queueMailJob(array $data): void
    {
        $queuedJobsTable = TableRegistry::getTableLocator()->get('Queue.QueuedJobs');
        $queuedJobsTable->createJob('Queue.Mailer', $data);
    }
}