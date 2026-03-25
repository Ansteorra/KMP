<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Event\EventInterface;

/**
 * NavBarController Controller
 */
class NavBarController extends AppController
{
    /**
     * Run before controller action execution.
     *
     * @param \Cake\Event\EventInterface $event
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated([
            'recordExpand',
            'recordCollapse',
        ]);
    }

    /**
     * Record expand.
     *
     * @param mixed $menu
     */
    public function recordExpand($menu = null)
    {
        $navbarState = $this->request->getSession()->read('navbarState');
        if (!$navbarState) {
            $navbarState = [];
        }
        $navbarState[$menu] = true;
        $this->request->getSession()->write('navbarState', $navbarState);

        $this->Authorization->skipAuthorization();
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode('recordedExpand'));

        return $this->response;
    }

    /**
     * Record collapse.
     *
     * @param mixed $menu
     */
    public function recordCollapse($menu = null)
    {
        $navbarState = $this->request->getSession()->read('navbarState');
        if (!$navbarState) {
            $navbarState = [];
        }
        $navbarState[$menu] = false;
        $this->request->getSession()->write('navbarState', $navbarState);

        $this->Authorization->skipAuthorization();
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode('recordedCollapse'));

        return $this->response;
    }
}
