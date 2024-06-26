<?php

declare(strict_types=1);

namespace App\Controller;

/**
 * NavBarController Controller
 *
 */
class NavBarController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated([
            "recordExpand",
            "recordCollapse"
        ]);
    }

    public function recordExpand($menu = null)
    {
        $navbarState = $this->request->getSession()->read("navbarState");
        if (!$navbarState) {
            $navbarState = [];
        }
        $navbarState[$menu] = true;
        $this->request->getSession()->write("navbarState", $navbarState);

        $this->Authorization->skipAuthorization();
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode("recordedExpand"));
        return $this->response;
    }

    public function recordCollapse($menu = null)
    {
        $navbarState = $this->request->getSession()->read("navbarState");
        if (!$navbarState) {
            $navbarState = [];
        }
        $navbarState[$menu] = false;
        $this->request->getSession()->write("navbarState", $navbarState);

        $this->Authorization->skipAuthorization();
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode("recordedCollapse"));
        return $this->response;
    }
}