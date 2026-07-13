<?php
declare(strict_types=1);

namespace Awards\Controller;

use Awards\Model\Entity\CourtAgenda;
use Awards\Services\CourtAgendaService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;

/**
 * Visual and printable court agenda management for gathering bestowals.
 *
 * @property \Awards\Model\Table\CourtAgendasTable $CourtAgendas
 */
class CourtAgendasController extends AppController
{
    /**
     * Visual agenda board for a gathering.
     *
     * @param \Awards\Services\CourtAgendaService $agendaService Court agenda service.
     * @param string|null $gatheringId Gathering ID.
     * @return \Cake\Http\Response|null|void
     */
    public function gathering(CourtAgendaService $agendaService, ?string $gatheringId = null): ?Response
    {
        $gatheringIdInt = $this->requirePositiveInt($gatheringId, __('Gathering ID is required.'));
        $gathering = TableRegistry::getTableLocator()->get('Gatherings')->get($gatheringIdInt);
        $placeholder = $this->CourtAgendas->newEntity([
            'gathering_id' => $gatheringIdInt,
            'name' => (string)$gathering->name . ' Court Agenda',
            'gathering' => $gathering,
        ]);
        $this->Authorization->authorize($placeholder, 'gathering');

        $user = $this->request->getAttribute('identity');
        $agenda = $agendaService->getOrCreateDefaultAgenda($gatheringIdInt, (int)$user->id);
        $canManage = $user->checkCan('edit', $agenda);
        $agendaService->ensureEligibleCourtSegments((int)$agenda->id, (int)$user->id);

        $viewModel = $agendaService->buildAgendaViewModel((int)$agenda->id);
        $selectedSegmentId = $this->selectedSegmentId(
            $viewModel['segments'],
            $this->request->getQuery('segment_id'),
        );
        $this->set([
            'agenda' => $viewModel['agenda'],
            'segments' => $viewModel['segments'],
            'selectedSegmentId' => $selectedSegmentId,
            'totalMinutes' => $viewModel['totalMinutes'],
            'totalWarning' => $viewModel['totalWarning'],
            'unscheduledBestowals' => $viewModel['unscheduledBestowals'],
            'scheduledActivityOptions' => $viewModel['scheduledActivityOptions'],
            'segmentOptions' => $viewModel['segmentOptions'],
            'canManage' => $canManage,
        ]);

        return null;
    }

    /**
     * Printer-ready court agenda.
     *
     * @param \Awards\Services\CourtAgendaService $agendaService Court agenda service.
     * @param string|null $agendaId Agenda ID.
     * @return \Cake\Http\Response|null|void
     */
    public function printAgenda(CourtAgendaService $agendaService, ?string $agendaId = null): ?Response
    {
        $agendaIdInt = $this->requirePositiveInt($agendaId, __('Court agenda ID is required.'));
        $agenda = $this->CourtAgendas->get($agendaIdInt, contain: ['Gatherings']);
        $this->Authorization->authorize($agenda, 'printAgenda');

        $viewModel = $agendaService->buildAgendaViewModel($agendaIdInt);
        $this->set([
            'agenda' => $viewModel['agenda'],
            'segments' => $viewModel['segments'],
            'totalMinutes' => $viewModel['totalMinutes'],
            'totalWarning' => $viewModel['totalWarning'],
        ]);
        $this->viewBuilder()->disableAutoLayout();

        return null;
    }

    /**
     * Import current gathering bestowals into the agenda.
     *
     * @param \Awards\Services\CourtAgendaService $agendaService Court agenda service.
     * @param string|null $agendaId Agenda ID.
     * @return \Cake\Http\Response|null
     */
    public function import(CourtAgendaService $agendaService, ?string $agendaId = null): ?Response
    {
        $this->request->allowMethod(['post']);
        $agenda = $this->loadAgenda($agendaId);
        $this->Authorization->authorize($agenda, 'import');
        $createdSegments = $agendaService->ensureEligibleCourtSegments(
            (int)$agenda->id,
            (int)$this->request->getAttribute('identity')->id,
        );
        $count = $agendaService->importGatheringBestowals(
            (int)$agenda->id,
            (int)$this->request->getAttribute('identity')->id,
        );
        $this->Flash->success(__(
            '{0} court segment(s) created and {1} bestowal(s) imported into the court agenda.',
            $createdSegments,
            $count,
        ));

        return $this->redirect(['action' => 'gathering', $agenda->gathering_id]);
    }

    /**
     * Add an eligible gathering bestowal to an agenda segment.
     *
     * @param \Awards\Services\CourtAgendaService $agendaService Court agenda service.
     * @return \Cake\Http\Response|null
     */
    public function addBestowal(CourtAgendaService $agendaService): ?Response
    {
        $this->request->allowMethod(['post']);
        $agenda = $this->loadAgenda((string)$this->request->getData('court_agenda_id'));
        $this->Authorization->authorize($agenda, 'addBestowal');
        $bestowalId = $this->requirePositiveInt($this->request->getData('bestowal_id'), __('Bestowal ID is required.'));
        $segmentId = $this->requirePositiveInt(
            $this->request->getData('court_agenda_segment_id'),
            __('Agenda segment ID is required.'),
        );
        $agendaService->addBestowalToSegment(
            (int)$agenda->id,
            $bestowalId,
            $segmentId,
            (int)$this->request->getAttribute('identity')->id,
        );
        $this->Flash->success(__('Bestowal added to the court agenda.'));

        return $this->redirectToGatheringSegment($agenda, $segmentId);
    }

    /**
     * Move a bestowal or agenda item to roaming court.
     *
     * @param \Awards\Services\CourtAgendaService $agendaService Court agenda service.
     * @return \Cake\Http\Response|null
     */
    public function moveToRoaming(CourtAgendaService $agendaService): ?Response
    {
        $this->request->allowMethod(['post']);
        $agenda = $this->loadAgenda((string)$this->request->getData('court_agenda_id'));
        $this->Authorization->authorize($agenda, 'moveToRoaming');
        $itemId = $this->optionalPositiveInt($this->request->getData('item_id'));
        $bestowalId = $this->optionalPositiveInt($this->request->getData('bestowal_id'));
        $agendaService->moveToRoamingCourt(
            (int)$agenda->id,
            $itemId,
            $bestowalId,
            (int)$this->request->getAttribute('identity')->id,
        );
        $this->Flash->success(__('Bestowal moved to roaming court.'));

        return $this->redirectToGatheringSegment($agenda, $this->request->getData('return_segment_id'));
    }

    /**
     * Add a court, break, or business segment.
     *
     * @param \Awards\Services\CourtAgendaService $agendaService Court agenda service.
     * @return \Cake\Http\Response|null
     */
    public function addSegment(CourtAgendaService $agendaService): ?Response
    {
        $this->request->allowMethod(['post']);
        $agenda = $this->loadAgenda((string)$this->request->getData('court_agenda_id'));
        $this->Authorization->authorize($agenda, 'addSegment');
        $agendaService->addSegment($this->request->getData(), (int)$this->request->getAttribute('identity')->id);
        $this->Flash->success(__('Court agenda segment added.'));

        return $this->redirectToGatheringSegment($agenda, $this->request->getData('return_segment_id'));
    }

    /**
     * Add a manual timing block.
     *
     * @param \Awards\Services\CourtAgendaService $agendaService Court agenda service.
     * @return \Cake\Http\Response|null
     */
    public function addBlock(CourtAgendaService $agendaService): ?Response
    {
        $this->request->allowMethod(['post']);
        $agenda = $this->loadAgendaForSegment((string)$this->request->getData('court_agenda_segment_id'));
        $this->Authorization->authorize($agenda, 'addBlock');
        $agendaService->addBlock($this->request->getData(), (int)$this->request->getAttribute('identity')->id);
        $this->Flash->success(__('Agenda block added.'));

        return $this->redirectToGatheringSegment($agenda, $this->request->getData('court_agenda_segment_id'));
    }

    /**
     * Update agenda item notes and duration.
     *
     * @param \Awards\Services\CourtAgendaService $agendaService Court agenda service.
     * @param string|null $itemId Item ID.
     * @return \Cake\Http\Response|null
     */
    public function updateItem(CourtAgendaService $agendaService, ?string $itemId = null): ?Response
    {
        $this->request->allowMethod(['post', 'patch', 'put']);
        $itemIdInt = $this->requirePositiveInt($itemId, __('Agenda item ID is required.'));
        $agenda = $this->loadAgendaForItem($itemIdInt);
        $this->Authorization->authorize($agenda, 'updateItem');
        $agendaService->updateItem(
            $itemIdInt,
            $this->request->getData(),
            (int)$this->request->getAttribute('identity')->id,
        );
        $this->Flash->success(__('Agenda item updated.'));

        return $this->redirectToGatheringSegment($agenda, $this->request->getData('return_segment_id'));
    }

    /**
     * Move an agenda item inside or across segments.
     *
     * @param \Awards\Services\CourtAgendaService $agendaService Court agenda service.
     * @return \Cake\Http\Response|null
     */
    public function moveItem(CourtAgendaService $agendaService): ?Response
    {
        $this->request->allowMethod(['post']);
        $itemId = $this->requirePositiveInt($this->request->getData('item_id'), __('Agenda item ID is required.'));
        $segmentId = $this->requirePositiveInt(
            $this->request->getData('court_agenda_segment_id'),
            __('Agenda segment ID is required.'),
        );
        $sortOrder = (int)$this->request->getData('sort_order', 999999);
        $agenda = $this->loadAgendaForItem($itemId);
        $this->Authorization->authorize($agenda, 'moveItem');
        $agendaService->moveItem($itemId, $segmentId, $sortOrder, (int)$this->request->getAttribute('identity')->id);

        if ($this->request->accepts('application/json')) {
            return $this->response
                ->withType('application/json')
                ->withStringBody((string)json_encode(['success' => true]));
        }

        return $this->redirectToGatheringSegment($agenda, $segmentId);
    }

    /**
     * Remove an item from the court agenda.
     *
     * @param \Awards\Services\CourtAgendaService $agendaService Court agenda service.
     * @return \Cake\Http\Response|null
     */
    public function removeItem(CourtAgendaService $agendaService): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $itemId = $this->requirePositiveInt($this->request->getData('item_id'), __('Agenda item ID is required.'));
        $agenda = $this->loadAgendaForItem($itemId);
        $this->Authorization->authorize($agenda, 'removeItem');
        $agendaService->removeItem($itemId, (int)$this->request->getAttribute('identity')->id);
        $this->Flash->success(__('Agenda item removed.'));

        return $this->redirectToGatheringSegment($agenda, $this->request->getData('return_segment_id'));
    }

    /**
     * @param string|null $agendaId Agenda ID.
     * @return \Awards\Model\Entity\CourtAgenda
     */
    private function loadAgenda(?string $agendaId): CourtAgenda
    {
        $agendaIdInt = $this->requirePositiveInt($agendaId, __('Court agenda ID is required.'));

        return $this->CourtAgendas->get($agendaIdInt, contain: ['Gatherings']);
    }

    /**
     * @param string|null $segmentId Segment ID.
     * @return \Awards\Model\Entity\CourtAgenda
     */
    private function loadAgendaForSegment(?string $segmentId): CourtAgenda
    {
        $segmentIdInt = $this->requirePositiveInt($segmentId, __('Agenda segment ID is required.'));
        $segment = TableRegistry::getTableLocator()->get('Awards.CourtAgendaSegments')
            ->get($segmentIdInt, contain: ['CourtAgendas' => ['Gatherings']]);

        return $segment->court_agenda;
    }

    /**
     * @param int $itemId Item ID.
     * @return \Awards\Model\Entity\CourtAgenda
     */
    private function loadAgendaForItem(int $itemId): CourtAgenda
    {
        $item = TableRegistry::getTableLocator()->get('Awards.CourtAgendaItems')
            ->get($itemId, contain: ['CourtAgendaSegments' => ['CourtAgendas' => ['Gatherings']]]);

        return $item->court_agenda_segment->court_agenda;
    }

    /**
     * @param mixed $value Value to validate.
     * @param string $message Error message.
     * @return int
     */
    private function requirePositiveInt(mixed $value, string $message): int
    {
        if (!is_numeric((string)$value) || (int)$value <= 0) {
            throw new BadRequestException($message);
        }

        return (int)$value;
    }

    /**
     * @param array<int, array<string, mixed>> $segments Agenda segments.
     * @param mixed $requestedId Requested selected segment ID.
     * @return int|null
     */
    private function selectedSegmentId(array $segments, mixed $requestedId): ?int
    {
        $firstSegmentId = null;
        foreach ($segments as $segmentData) {
            $segment = $segmentData['entity'];
            $segmentId = (int)$segment->id;
            $firstSegmentId ??= $segmentId;
            if ((string)$requestedId !== '' && (int)$requestedId === $segmentId) {
                return $segmentId;
            }
        }

        return $firstSegmentId;
    }

    /**
     * @param \Awards\Model\Entity\CourtAgenda $agenda Agenda.
     * @param mixed $segmentId Segment ID.
     * @return \Cake\Http\Response|null
     */
    private function redirectToGatheringSegment(CourtAgenda $agenda, mixed $segmentId): ?Response
    {
        $query = [];
        $segmentId = $this->optionalPositiveInt($segmentId);
        if ($segmentId !== null) {
            $query['segment_id'] = $segmentId;
        }

        return $this->redirect(['action' => 'gathering', $agenda->gathering_id, '?' => $query]);
    }

    /**
     * @param mixed $value Value to validate.
     * @return int|null
     */
    private function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric((string)$value) || (int)$value <= 0) {
            throw new BadRequestException(__('Invalid identifier.'));
        }

        return (int)$value;
    }
}
