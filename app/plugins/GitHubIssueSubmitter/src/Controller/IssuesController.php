<?php

namespace GitHubIssueSubmitter\Controller;

use GitHubIssueSubmitter\Controller\AppController;
use \App\KMP\StaticHelpers;

class IssuesController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated([
            "submit",
        ]);
    }


    public function submit()
    {
        $this->Authorization->skipAuthorization();
        $owner = StaticHelpers::getAppSetting("KMP.GitHub.Owner");
        $repo = StaticHelpers::getAppSetting("KMP.GitHub.Project");
        $token = StaticHelpers::getAppSetting("KMP.GitHub", "")["Token"];
        $body = $this->request->getData('body');
        $title = $this->request->getData('title');
        $category = $this->request->getData('feedbackType');
        $url = "https://api.github.com/repos/$owner/$repo/issues";

        $title = htmlspecialchars(stripslashes($title), ENT_QUOTES);
        $body = htmlspecialchars(stripslashes($body), ENT_QUOTES);

        $header = [
            'Content-type: application/x-www-form-urlencoded',
            'Authorization: token ' . $token,
        ];
        $postData = json_encode([
            'title' => $title,
            'body' => $body,
            'labels' => ['web', $category],
        ]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if (isset($decoded['message'])) {
            //    throw new Exception("Github return an error: {$decoded['message']}. Check your token permission or repository owner and name");
        }
        $responseJson = [];
        if (isset($decoded['message'])) {
            $responseJson["message"] = $decoded['message'];
        } else {
            $responseJson = ["url" => $decoded["html_url"], "number" => $decoded["number"]];
        }
        //set to ajax response
        $this->viewBuilder()->setClassName("Ajax");
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($responseJson));

        return $this->response;
    }
}
