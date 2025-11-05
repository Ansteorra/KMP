<turbo-frame id="boardView-frame" data-turbo='true'>
    <div class="row">
        <div class="col-12 text-end">
            <?php
            if ($hiddenStatesStr != ""):
                if (!$showHidden): ?>

                    <?= $this->Html->link(
                        "Show $hiddenStatesStr In last $range days",
                        [$view, '?' => ['showHidden' => 'true']],
                        ['class' => 'btn btn-primary btn-sm end m-3']
                    ) ?>
                <?php else : ?>
                    <?= $this->Html->link(
                        "Hide $hiddenStatesStr In last $range days",
                        [$view],
                        ['class' => 'btn btn-primary btn-sm end m-3']
                    ) ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="overflow-x-auto table-responsive" data-controller='recommendation-kanban'
            data-recommendation-kanban-kanban-outlet=".rec-kanban">
            <script type="application/json" data-awards-rec-edit-target="stateRulesBlock" class="d-none">
                <?= json_encode($rules) ?>
            </script>
            <table class="table table-striped-columns rec-kanban" width="100%" style="min-width:1020px"
                data-controller="kanban" data-kanban-csrf-token-value="<?= $this->request->getAttribute('csrfToken') ?>"
                data-kanban-url-value="<?= $this->Url->build(['action' => 'kanbanUpdate']) ?>">
                <thead>
                    <tr>
                        <?php foreach ($states as $state => $recommendations) : ?>
                            <th scope="col" width="14.28%"><?= h($state) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        foreach ($states as $state => $recommendations) : ?>
                            <td class="sortable" width="14.28%" data-kanban-target="column" data-col="<?= h($state) ?>"
                                data-action="dragstart->kanban#grabCard dragover->kanban#cardDrag drop->kanban#dropCard">

                                <?php
                                if (is_array($recommendations)) :
                                    foreach ($recommendations as $recommendation) : ?>
                                        <div class="card m-1" style="cursor: pointer;" draggable="true"
                                            data-stack-rank="<?= $recommendation->stack_rank ?>"
                                            data-rec-id="<?= $recommendation->id ?>" id="card_<?= $recommendation->id ?>"
                                            data-kanban-target="card">
                                            <div class="card-body">
                                                <div class="card-title"> <?php
                                                                            $awardTitle = $recommendation->award->abbreviation;
                                                                            if ($recommendation->specialty != null && $recommendation->specialty != "" && $recommendation->specialty != "No Specialty Selected") :
                                                                                $awardTitle = $awardTitle . " (" . $recommendation->specialty . ")";
                                                                            endif;
                                                                            ?>
                                                    <?= $this->Html->link($awardTitle, ['action' => 'view', $recommendation->id]) ?>
                                                    <button type="button" class="btn btn-primary btn-sm float-end edit-rec"
                                                        data-bs-toggle="modal" data-bs-target="#boardEditModal"
                                                        data-controller="outlet-btn" data-action="click->outlet-btn#fireNotice"
                                                        data-outlet-btn-btn-data-value='{ "id":<?= $recommendation->id ?>}'>
                                                        Edit</button>
                                                </div>
                                                <h6 class="card-subtitle mb-2 text-body-secondary">
                                                    <?= $recommendation->member_sca_name ?>
                                                </h6>
                                                <p class="card-text"><?= $this->Text->autoParagraph(
                                                                            h($this->Text->truncate($recommendation->reason, 100)),
                                                                        ) ?></p>
                                                <b>Last Modified: </b><?= $recommendation->modified ? $this->Timezone->format($recommendation->modified, 'm/d/Y', true) : '-' ?> by
                                                <?= $recommendation->ModifiedByMembers['sca_name'] ?>
                                            </div>
                                        </div>
                                <?php endforeach;
                                endif; ?>
                            </td>
                        <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->element('recommendationQuickEditModal', ['modalId' => 'boardEditModal']) ?>
</turbo-frame>