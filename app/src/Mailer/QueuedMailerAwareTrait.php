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

use Cake\Core\App;
use Cake\Mailer\Exception\MissingMailerException;
use Cake\ORM\TableRegistry;

/**
 * Provides functionality for loading mailer classes
 * onto properties of the host object.
 *
 * Example users of this trait are Cake\Controller\Controller and
 * Cake\Console\Command.
 */
trait QueuedMailerAwareTrait
{
    /**
     * Returns a mailer instance.
     *
     * @param string $name Mailer's name.
     * @param array<string, mixed>|string|null $config Array of configs, or profile name string.
     * @return \Cake\Mailer\Mailer
     * @throws \Cake\Mailer\Exception\MissingMailerException if undefined mailer class.
     */
    protected function queueMail(string $name, $action, $to, $vars)
    {
        $className = App::className($name, 'Mailer', 'Mailer');
        if ($className === null) {
            throw new MissingMailerException(compact('name'));
        }
        $vars['to'] = $to;
        $data = [
            'class' => $className,
            'action' => $action,
            'vars' => $vars
        ];
        $queuedJobsTable = TableRegistry::getTableLocator()->get('Queue.QueuedJobs');
        $queuedJobsTable->createJob('Queue.Mailer', $data);
    }
}