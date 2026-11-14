<?php
declare(strict_types=1);

namespace Awards\KMP\GridColumns;

/** Text-search-only metadata for the Submitted By Me grid. */
class MemberSubmittedRecommendationsGridColumns extends RecommendationsGridColumns
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getColumns(): array
    {
        $columns = parent::getColumns();
        foreach ($columns as &$column) {
            $column['filterable'] = false;
        }

        return $columns;
    }
}
