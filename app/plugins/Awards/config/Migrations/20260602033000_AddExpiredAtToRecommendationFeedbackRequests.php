<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddExpiredAtToRecommendationFeedbackRequests extends BaseMigration
{
    /**
     * Add explicit expiration timestamps for missed feedback deadlines.
     */
    public function change(): void
    {
        $requests = $this->table('awards_recommendation_feedback_requests');
        if (!$requests->hasColumn('expired_at')) {
            $requests->addColumn('expired_at', 'datetime', [
                'after' => 'retracted_at',
                'null' => true,
            ])->update();
        }

        $recipients = $this->table('awards_recommendation_feedback_request_recipients');
        if (!$recipients->hasColumn('expired_at')) {
            $recipients->addColumn('expired_at', 'datetime', [
                'after' => 'retracted_at',
                'null' => true,
            ])->update();
        }
    }
}
