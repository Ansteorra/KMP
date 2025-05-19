<?php
declare(strict_types=1);

namespace App\Controller;

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
