<?php
declare(strict_types=1);

namespace GitHubIssueSubmitter\Controller;

use App\KMP\StaticHelpers;
use App\Services\Security\RequestRateLimiter;
use Cake\Event\EventInterface;

/**
 * Issues Controller - GitHub Issue Submission
 *
 * Handles anonymous feedback submission to GitHub Issues API.
 * Processes feedback forms and creates GitHub issues with proper labeling.
 *
 * @package GitHubIssueSubmitter\Controller
 */
class IssuesController extends AppController
{
    /**
     * Configure anonymous access for feedback submission.
     *
     * @param \Cake\Event\EventInterface $event The beforeFilter event
     * @return void
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated([
            'submit',
        ]);
    }

    /**
     * Process anonymous feedback and create GitHub issues.
     *
     * Collects feedback data, sanitizes input, and submits to GitHub API.
     * Returns JSON response with issue URL or error message.
     *
     * @return \Cake\Http\Response JSON response with issue data or error
     */
    public function submit(RequestRateLimiter $rateLimiter)
    {
        $this->Authorization->skipAuthorization();
        $clientIp = $this->request->clientIp() ?? 'unknown';
        $rate = $rateLimiter->attempt(RequestRateLimiter::BUCKET_GITHUB_ISSUE, $clientIp);
        if (!$rate->allowed) {
            $this->viewBuilder()->setClassName('Ajax');
            $this->response = $this->response
                ->withStatus(429)
                ->withType('application/json')
                ->withHeader('Retry-After', (string)$rate->retryAfterSeconds)
                ->withStringBody(json_encode(['message' => 'Too many requests. Please try again later.']));

            return $this->response;
        }
        $owner = StaticHelpers::getAppSetting('KMP.GitHub.Owner');
        $repo = StaticHelpers::getAppSetting('KMP.GitHub.Project');
        $token = StaticHelpers::getAppSetting('KMP.GitHub', '')['Token'];
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
            $responseJson['message'] = $decoded['message'];
        } else {
            $responseJson = ['url' => $decoded['html_url'], 'number' => $decoded['number']];
        }
        //set to ajax response
        $this->viewBuilder()->setClassName('Ajax');
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($responseJson));

        return $this->response;
    }
}
