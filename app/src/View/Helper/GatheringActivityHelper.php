<?php
declare(strict_types=1);

namespace App\View\Helper;

use App\Model\Entity\GatheringActivity;
use App\Services\GatheringActivityPresentationService;
use App\Services\IconCatalog;
use Cake\View\Helper;

/** Renders the same activity identity in compact calendars and detailed views. */
class GatheringActivityHelper extends Helper
{
    protected array $helpers = ['Html'];

    /**
     * @param \App\Model\Entity\GatheringActivity $activity Activity
     * @param bool $compact Use the uploaded alpha channel as a solid foreground icon
     * @return string
     */
    public function badge(GatheringActivity $activity, bool $compact = false): string
    {
        $data = GatheringActivityPresentationService::appearanceData($activity);
        $icon = IconCatalog::render($data['icon_name'], ['size' => $compact ? 16 : 24]);
        if ($data['icon_url']) {
            $icon = $compact
                ? $this->Html->tag('span', '', [
                    'class' => 'gathering-activity-mask',
                    'style' => 'mask-image:url("' . $data['icon_url'] . '");'
                        . '-webkit-mask-image:url("' . $data['icon_url'] . '");',
                    'aria-hidden' => 'true',
                ])
                : $this->Html->image($data['icon_url'], [
                    'class' => 'gathering-activity-image', 'alt' => '', 'aria-hidden' => 'true',
                    'width' => 32, 'height' => 32,
                ]);
        }

        return $this->Html->tag('span', $icon . ($compact ? '' : ' ' . h($activity->name)), [
            'class' => 'badge gathering-activity-badge',
            'style' => 'color:' . $data['foreground_color'] . ';background-color:' . $data['background_color'] . ';',
            'title' => $activity->name,
            'role' => $compact ? 'img' : null,
            'aria-label' => $compact ? $activity->name : null,
            'data-activity-badge' => $activity->id,
        ]);
    }
}
