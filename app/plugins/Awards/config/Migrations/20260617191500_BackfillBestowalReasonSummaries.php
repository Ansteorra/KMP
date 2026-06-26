<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Backfill bestowal reason summaries from linked recommendations.
 */
class BackfillBestowalReasonSummaries extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (
            !$this->hasTable('awards_bestowals')
            || !$this->table('awards_bestowals')->hasColumn('reason_summary')
            || !$this->hasTable('awards_bestowal_recommendations')
            || !$this->hasTable('awards_recommendations')
        ) {
            return;
        }

        $rows = $this->fetchAll(
            "SELECT
                b.id AS bestowal_id,
                r.reason,
                COALESCE(r.requester_sca_name, requester.sca_name, '') AS submitter_name
            FROM awards_bestowals b
            INNER JOIN awards_bestowal_recommendations br ON br.bestowal_id = b.id
            INNER JOIN awards_recommendations r ON r.id = br.recommendation_id
            LEFT JOIN members requester ON requester.id = r.requester_id
            WHERE b.reason_summary IS NULL OR b.reason_summary = ''
            ORDER BY b.id ASC, br.id ASC",
        );

        $summaries = [];
        foreach ($rows as $row) {
            $reason = trim((string)($row['reason'] ?? ''));
            if ($reason === '') {
                continue;
            }

            $submitter = trim((string)($row['submitter_name'] ?? ''));
            if ($submitter === '') {
                $submitter = 'Unknown submitter';
            }

            $bestowalId = (int)$row['bestowal_id'];
            $summaries[$bestowalId][] = 'Submitted by ' . $submitter . ":\n" . $reason;
        }

        foreach ($summaries as $bestowalId => $sections) {
            $summary = implode("\n\n", $sections);
            $this->execute(
                'UPDATE awards_bestowals SET reason_summary = ' . $this->quoteString($summary)
                    . ' WHERE id = ' . $bestowalId,
            );
        }
    }

    /**
     * Do not clear user-edited summaries on rollback.
     *
     * @return void
     */
    public function down(): void
    {
    }

    /**
     * Quote a string literal for static migration SQL.
     *
     * @param string $value Value to quote.
     * @return string SQL string literal.
     */
    private function quoteString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
