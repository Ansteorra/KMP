<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Services\UiUpdateProviderInterface;
use App\Services\UiUpdateRegistry;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;

/** Invalidation metadata is resolved against the active tenant at use time. */
final class UiUpdateProvider implements UiUpdateProviderInterface
{
    /** @inheritDoc */
    public static function definitions(): array
    {
        return [
            'Members' => ['invalidatesScreens' => [
                'Awards.Recommendations', 'Awards.Bestowals', 'Awards.CourtAgendas',
            ]],
            'Branches' => ['invalidatesScreens' => ['Awards.Awards', 'Awards.Recommendations', 'Awards.Bestowals']],
            'Gatherings' => ['invalidatesScreens' => [
                'Awards.Recommendations', 'Awards.Bestowals', 'Awards.CourtAgendas',
            ]],
            'Awards.Awards' => ['controller' => 'Awards', 'invalidatesScreens' => [
                'Members', 'Gatherings', 'Awards.Recommendations', 'Awards.Bestowals', 'Awards.CourtAgendas',
            ]],
            'Awards.Domains' => ['controller' => 'Domains',
                'invalidatesScreens' => ['Awards.Awards', 'Awards.Recommendations', 'Awards.Bestowals']],
            'Awards.Levels' => ['controller' => 'Levels',
                'invalidatesScreens' => ['Awards.Awards', 'Awards.Recommendations', 'Awards.Bestowals']],
            'Awards.ApprovalProcesses' => ['controller' => 'ApprovalProcesses'],
            'Awards.BestowalTodoTemplates' => ['controller' => 'BestowalTodoTemplates'],
            'Awards.Recommendations' => [
                'controller' => 'Recommendations',
                'notesPrivate' => 'entity',
                'parents' => ['gathering_id' => 'Gatherings', 'member_id' => 'Members'],
                'changes' => ['awards'],
            ],
            'Awards.Bestowals' => [
                'controller' => 'Bestowals',
                'notesPrivate' => false,
                'parents' => ['gathering_id' => 'Gatherings', 'member_id' => 'Members'],
                'changes' => ['awards'],
            ],
            'Awards.CourtAgendas' => ['controller' => 'CourtAgendas', 'parents' => ['gathering_id' => 'Gatherings'],
                'changes' => ['agenda'], 'contexts' => ['gathering' => [self::class, 'gatheringContext']]],
            'Awards.CourtAgendaSegments' => [
                'parents' => ['court_agenda_id' => 'Awards.CourtAgendas'],
                'changes' => ['agenda'],
            ],
            'Awards.CourtAgendaItems' => ['parents' => ['court_agenda_segment_id' => 'Awards.CourtAgendaSegments',
                'bestowal_id' => 'Awards.Bestowals'], 'changes' => ['agenda']],
            'Awards.RecommendationFeedbackRequests' => [
                'parents' => ['recommendation_id' => 'Awards.Recommendations'],
                'changes' => ['approvals'],
            ],
            'Awards.RecommendationFeedbackRequestRecipients' => [
                'parents' => ['feedback_request_id' => 'Awards.RecommendationFeedbackRequests'],
                'changes' => ['approvals'],
            ],
        ];
    }

    /** @param array<string, mixed> $params Parsed server route. @return array<string, mixed> */
    public static function gatheringContext(ServerRequest $request, array $params): array
    {
        $gathering = TableRegistry::getTableLocator()->get('Gatherings')->get($params['pass'][0] ?? null);
        $agenda = TableRegistry::getTableLocator()->get('Awards.CourtAgendas')->newEmptyEntity();
        $agenda->gathering_id = $gathering->id;
        $agenda->gathering = $gathering;

        return [
            'resource' => $agenda, 'action' => 'gathering',
            'topic' => UiUpdateRegistry::topic('Gatherings', $gathering->id),
        ];
    }
}
