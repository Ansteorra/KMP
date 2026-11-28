<?php
declare(strict_types=1);

namespace Waivers\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;
use App\KMP\GridColumns\GatheringsGridColumns;

/** Dataverse filters for the clerk's month view. */
final class WaiverCalendarGridColumns extends BaseGridColumns
{
    /** @inheritDoc */
    public static function getColumns(): array
    {
        $columns = array_intersect_key(
            GatheringsGridColumns::getColumns(),
            array_flip(['name', 'branch_id', 'gathering_type_id', 'start_date', 'location', 'activity_filter']),
        );
        $columns['branch_id']['label'] = __('Hosting group');
        // The controller supplies options from the clerk's authorized branches.
        unset($columns['branch_id']['filterOptionsSource']);
        $columns['gathering_type_id']['label'] = __('Gathering type');
        $columns['gathering_type_id']['filterQueryField'] = 'Gatherings.gathering_type_id';
        $columns['start_date']['filterable'] = false;
        $columns['status'] = [
            'key' => 'status',
            'label' => __('Waiver status'),
            'type' => 'string',
            'filterable' => true,
            'filterType' => 'dropdown',
            'filterOnly' => true,
            // Status depends on the required, uploaded, exempted, and lost waiver types.
            'skipAutoFilter' => true,
            'filterOptions' => [
                ['value' => 'missing', 'label' => __('Missing')],
                ['value' => 'partial', 'label' => __('Partial')],
                ['value' => 'complete', 'label' => __('Complete')],
                ['value' => 'ready_to_close', 'label' => __('Ready to close')],
                ['value' => 'closed', 'label' => __('Closed')],
            ],
        ];

        return $columns;
    }
}
