<?php
declare(strict_types=1);

namespace Awards\KMP\GridColumns;

/** Dataverse columns for the recommendation detail tab. */
class SimilarRecommendationsGridColumns extends RecommendationsGridColumns
{
    public const GRID_KEY = 'Awards.Recommendations.similar';
    public const VIEW = 'sys-recs-like-this';

    /**
     * @inheritDoc
     */
    public static function getColumns(): array
    {
        $columns = array_intersect_key(parent::getColumns(), array_flip([
            'id', 'created', 'requester_sca_name', 'reason', 'state', 'state_date',
            'close_reason', 'given', 'assigned_gathering',
        ]));

        return $columns + [
            'grouping_status' => [
                'key' => 'grouping_status',
                'label' => __('Grouping'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getSystemViews(array $options = []): array
    {
        return [
            self::VIEW => [
                'id' => self::VIEW,
                'name' => __('Recs. Like This'),
                'canManage' => false,
                'config' => [
                    'columns' => [
                        'created', 'requester_sca_name', 'state', 'state_date', 'close_reason',
                        'grouping_status', 'reason',
                    ],
                ],
            ],
        ];
    }
}
