<?php

namespace App\Controller;

use Cake\Http\ServerRequest;
use Cake\Http\Response;

class SessionsController extends AppController
{
    public function keepalive()
    {
        $this->Authorization->skipAuthorization();
        $response = $this->response;
        $response = $response->withStringBody('My Body');
        $response = $response->withType('application/json')
            ->withStringBody(json_encode(['response' => 'Session extended']));
        return $response;
    }
}