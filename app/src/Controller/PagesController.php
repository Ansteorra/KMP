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
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App\Controller;

use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;
use Parsedown;

/**
 * Static content controller
 *
 * This controller will render views from templates/Pages/
 *
 * @link https://book.cakephp.org/4/en/controllers/pages-controller.html
 */
class PagesController extends AppController
{
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        $this->Authentication->allowUnauthenticated([
            'display',
            'webmanifest',
            'changelog',
        ]);
    }

    /**
     * Displays a view
     *
     * @param string ...$path Path segments.
     * @return \Cake\Http\Response|null
     * @throws \Cake\Http\Exception\ForbiddenException When a directory traversal attempt.
     * @throws \Cake\View\Exception\MissingTemplateException When the view file could not
     *   be found and in debug mode.
     * @throws \Cake\Http\Exception\NotFoundException When the view file could not
     *   be found and not in debug mode.
     * @throws \Cake\View\Exception\MissingTemplateException In debug mode.
     */
    public function display(string ...$path): ?Response
    {
        $this->Authorization->skipAuthorization();
        if (!$path) {
            return $this->redirect('/');
        }
        if (in_array('..', $path, true) || in_array('.', $path, true)) {
            throw new ForbiddenException();
        }
        $page = $subpage = null;

        if (!empty($path[0])) {
            $page = $path[0];
        }
        if (!empty($path[1])) {
            $subpage = $path[1];
        }
        $this->set(compact('page', 'subpage'));

        try {
            return $this->render(implode('/', $path));
        } catch (MissingTemplateException $exception) {
            //get current user
            $user = $this->Authentication->getIdentity();
            if ($user) {
                return $this->redirect(['controller' => 'members', 'action' => 'view', $user['id']]);
            }
            //if (Configure::read("debug")) {
            //    throw $exception;
            //}
            return $this->redirect(['controller' => 'members', 'action' => 'login']);
        }
    }

    public function webmanifest($id = null)
    {
        $this->Authorization->skipAuthorization();
        $path = $this->request->getPath();
        if ($id) {
            $mobile_token = $id;
        } else {
            $mobile_token = $this->request->getParam('mobile_token');
        }
        if (!$mobile_token) {
            $current_user = $this->Authentication->getIdentity();
            $mobile_token = $current_user->mobile_card_token;
        }
        if (!$mobile_token) {
            throw new NotFoundException();
        }
        $this->viewBuilder()->setLayout('ajax');
        $this->response = $this->response->withType('application/manifest+json');
        $this->set(compact('mobile_token'));
    }

    /**
     * Display the changelog page
     *
     * Reads the CHANGELOG.md file from the project root and renders it as HTML.
     *
     * @return \Cake\Http\Response|null
     */
    public function changelog(): ?Response
    {
        $this->Authorization->skipAuthorization();

        $changelogPath = ROOT . DS . 'CHANGELOG.md';
        $changelogContent = '';
        $lastSyncedDate = null;

        if (file_exists($changelogPath)) {
            $rawContent = file_get_contents($changelogPath);

            // Handle file read failure
            if ($rawContent === false) {
                $this->set(compact('changelogContent', 'lastSyncedDate'));

                return null;
            }

            // Extract last synced date from the marker
            if (preg_match('/<!-- LAST_SYNCED_DATE: ([^\s]+) -->/', $rawContent, $matches)) {
                $lastSyncedDate = $matches[1] !== 'none' ? $matches[1] : null;
            }

            // Remove the sync markers from displayed content
            $cleanContent = preg_replace('/<!-- CHANGELOG_SYNC_MARKER:.*?-->\n?/', '', $rawContent);
            $cleanContent = preg_replace('/<!-- LAST_SYNCED_COMMIT:.*?-->\n?/', '', $cleanContent);
            $cleanContent = preg_replace('/<!-- LAST_SYNCED_DATE:.*?-->\n?/', '', $cleanContent);

            // Convert markdown to HTML using Parsedown
            $parsedown = new Parsedown();
            $parsedown->setSafeMode(true);
            $changelogContent = $parsedown->text($cleanContent);
        }

        $this->set(compact('changelogContent', 'lastSyncedDate'));

        return null;
    }
}
