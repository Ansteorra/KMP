<?php

namespace App\Controller;

use Cake\Http\ServerRequest;
use Cake\Http\Response;

class SessionsController extends AppController
{
    public function keepalive()
    {

        // $session = $this->request->getSession();
        // if ($session->check('count')) {
        //     $session->write(['count' => $session->read('count') + 1]);
        // } else {
        //     $session->write(['count' => 1]);
        // }
        $response = $this->response;
        $response = $response->withStringBody('My Body');
        $response = $response->withType('application/json')
            ->withStringBody(json_encode(['response' => 'Session extended']));
        return $response;
    }
}
