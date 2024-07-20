<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup[]|\Cake\Collection\CollectionInterface $activityGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Award Recommendations';
$this->KMP->endBlock(); ?>
<h3>
    Award Recommendations
</h3>
<div class="overflow-x-auto table-responsive">
    <table class="table table-striped-columns" width="100%" style="min-width:1020px">
        <thead>
            <tr>
                <?php foreach ($statuses as $statusName => $status) : ?>
                <th scope="col" width="14.28%"><?= h($statusName) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php
                foreach ($statuses as $statusName => $status) : ?>
                <td class="sortable" width="14.28%" data-status="<?= h($statusName) ?>">

                    <?php
                        if (is_array($status)) :
                            foreach ($status as $recommendation) : ?>
                    <div class="card m-1" style="cursor: pointer;" draggable="true"
                        data-stackRank="<?= $recommendation->stack_rank ?>" data-recId="<?= $recommendation->id ?>"
                        id="card_<?= $recommendation->id ?>">
                        <div class="card-body">
                            <div class="card-title">
                                <?= $this->Html->link($recommendation->award->name, ['action' => 'view', $recommendation->id]) ?>
                                <button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal"
                                    data-bs-target="#editModal"
                                    onclick="loadRec(<?= $recommendation->id ?>, '<?= $currentUrl ?>')">Edit</button>
                            </div>
                            <h6 class="card-subtitle mb-2 text-body-secondary"><?= $recommendation->member_sca_name ?>
                            </h6>
                            <p class="card-text"><?= $this->Text->autoParagraph(
                                                                    h($this->Text->truncate($recommendation->reason, 100)),
                                                                ) ?></p>
                        </div>
                    </div>
                    <?php endforeach;
                        endif; ?>
                </td>
                <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $this->KMP->startBlock("script") ?>
<script>
<?= sprintf('var csrfToken = %s;', json_encode($this->request->getAttribute('csrfToken'))) ?>
class recommendationsIndex {
    constructor() {
        this.draggedItem = null;
        this.ac = null;
    };
    startDragListen(event, me) {
        var target = event.target;
        while (!target.classList.contains('card')) {
            if (target.tagName == 'BODY') {
                return;
            }
            target = target.parentElement;
        }
        $(target).addClass("opacity-25");
        me.draggedItem = target;
    }
    processDrag(event, me, isDrop) {
        //console.log(event);
        var targetCol = event.target;
        var entityId = me.draggedItem.getAttribute('data-recId');
        var targetStackRank = null;
        while (!targetCol.classList.contains('sortable')) {
            if (targetCol.tagName == 'BODY') {
                return;
            }
            targetCol = targetCol.parentElement;
        }
        var targetBefore = event.target;
        var foundBefore = true;
        while (!targetBefore.classList.contains('card')) {
            if (targetBefore.tagName == 'TD') {
                foundBefore = false;
                break;
            }
            targetBefore = targetBefore.parentElement;
        }
        if (foundBefore) {
            targetStackRank = targetBefore.getAttribute('data-stackRank');
        }
        if (targetCol.classList.contains('sortable')) {
            const data = event.dataTransfer.getData('Text');
            if (foundBefore) {
                targetCol.insertBefore(me.draggedItem, targetBefore);
            } else {
                targetCol.appendChild(me.draggedItem);
            }
            if (isDrop) {
                //in the targetCol get the card before the draggedItem
                var palaceAfter = -1;
                var palaceBefore = -1;
                var previousSibling = $(me.draggedItem).prev()
                if (previousSibling) {
                    palaceAfter = previousSibling.attr('data-recId');
                } else {
                    palaceAfter = -1;
                }
                var nextSibling = $(me.draggedItem).next()
                if (nextSibling) {
                    palaceBefore = nextSibling.attr('data-recId');
                } else {
                    palaceBefore = -1;
                }

                $.ajax({
                    url: "<?= $this->Url->build(['action' => 'kanbanUpdate']) ?>/" + entityId,
                    type: "POST",
                    data: {
                        _csrfToken: csrfToken,
                        status: targetCol.getAttribute('data-status'),
                        placeAfter: palaceAfter,
                        placeBefore: palaceBefore
                    }
                });
            }
        }
    }
    run() {
        var me = this;
        document.addEventListener('dragstart', event => {
            me.startDragListen(event, me);
        });
        document.addEventListener('dragover', event => {
            event.preventDefault();
            me.processDrag(event, me, false);
        });
        document.addEventListener('drop', event => {
            event.preventDefault();
            me.processDrag(event, me, true);
            $(me.draggedItem).removeClass("opacity-25");
            me.draggedItem = null;
        });
    };
}
window.addEventListener('DOMContentLoaded', function() {
    var ri = new recommendationsIndex();
    ri.run();
});
</script>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock("modals"); ?>

<?php
echo $this->Form->create($recommendation, [
    "id" => "recommendation_form",
    "url" => [
        "controller" => "Recommendations",
        "action" => "edit",
    ],
]);
echo $this->Form->control(
    "current_page",
    [
        "type" => "hidden",
        "id" => "recommendation__current_page",
        "value" => $currentUrl,
    ]

);
echo $this->Modal->create("Edit Recommendation", [
    "id" => "editModal",
    "close" => true,
]);
?>
<turbo-frame id="editRecommendation">
    loading
</turbo-frame>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "recommendation_submit"
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]);

echo $this->Form->end();
?>

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>
<?= $this->element('recommendationEditScript') ?>
<?php echo $this->KMP->startBlock("script"); ?>
<script>
loadRec = function(id, returnUrl) {
    formSrc =
        "<?= $this->URL->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'edit']) ?>" +
        "/" + id;
    src =
        "<?= $this->URL->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'TurboEditForm']) ?>" +
        "/" + id;
    $("#recommendation_form").attr("action", formSrc);
    $("#recommendation__current_page").val(returnUrl);
    $("#editRecommendation").attr("src", src);
}
window.addEventListener('DOMContentLoaded', function() {
    $("#editRecommendation").on("turbo:frame-load", function() {
        var recAdd = new recommendationsAdd();
        recAdd.run();
    });
});
</script>
<?php echo $this->KMP->endBlock(); ?>