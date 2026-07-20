<?php
declare(strict_types=1);

namespace GitHubIssueSubmitter\Controller;

use App\KMP\StaticHelpers;
use App\Services\Security\RequestRateLimiter;
use Cake\Event\EventInterface;
use Cake\Log\Log;

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
            'Content-type: application/json',
            'Authorization: token ' . $token,
        ];
        $postData = json_encode([
            'title' => $title,
            'body' => $body,
            'labels' => ['web', $category],
        ]);
        $ch = curl_init();
        if ($ch === false) {
            Log::warning('GitHub issue submission failed: unable to initialize curl.');
            $this->viewBuilder()->setClassName('Ajax');
            $this->response = $this->response
                ->withStatus(502)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'message' => 'Feedback submission is temporarily unavailable. Please try again later.',
                ]));

            return $this->response;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_USERAGENT => 'PHP',
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseJson = [];
        $status = 200;
        if ($response === false) {
            Log::warning(sprintf(
                'GitHub issue submission failed: curl error %d: %s',
                $curlErrno,
                $curlError,
            ));
            $responseJson['message'] = 'Feedback submission is temporarily unavailable. Please try again later.';
            $status = 502;
        } else {
            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                Log::warning('GitHub issue submission failed: invalid JSON response from GitHub.');
                $responseJson['message'] = 'Feedback submission is temporarily unavailable. Please try again later.';
                $status = 502;
            } elseif (isset($decoded['message'])) {
                Log::warning(sprintf(
                    'GitHub issue submission failed: GitHub returned HTTP %d: %s',
                    $httpStatus,
                    (string)$decoded['message'],
                ));
                $responseJson['message'] = $decoded['message'];
                $status = $httpStatus >= 400 ? $httpStatus : 502;
            } elseif (!isset($decoded['html_url'], $decoded['number'])) {
                Log::warning('GitHub issue submission failed: response omitted issue URL or number.');
                $responseJson['message'] = 'Feedback submission is temporarily unavailable. Please try again later.';
                $status = 502;
            } else {
                $responseJson = ['url' => $decoded['html_url'], 'number' => $decoded['number']];
            }
        }

        //set to ajax response
        $this->viewBuilder()->setClassName('Ajax');
        $this->response = $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode($responseJson));

        return $this->response;
    }
}
