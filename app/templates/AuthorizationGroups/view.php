<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationGroup $authorizationGroup
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<div class="authorizationGroups view large-9 medium-8 columns content">
   <div class="row align-items-start">
        <div class="col">
            <h3>
                <?= $this->Html->link('', ['action' => 'index'], ['class' => 'bi bi-arrow-left-circle']) ?>
                <?= h($authorizationGroup->name) ?> 
            </h3>
        </div>
        <div class="col text-end">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
            <?php 
                if(empty($authorizationGroup -> authorization_types)){
                    echo $this->Form->postLink(__('Delete'), ['action' => 'delete', $authorizationGroup->id], ['confirm' => __('Are you sure you want to delete {0}?', $authorizationGroup->name), 'title' => __('Delete'), 'class' => 'btn btn-danger btn-sm']);
                }
            ?> 
        </div>
    </div>
    <div class="related">
        <h4><?= __('Related Authorization Types') ?> </h4>
        <?php if (!empty($authorizationGroup -> authorization_types)) { ?> 
            <div class="table-responsive">
                <table class="table table-striped">
                <thead>
                <tr>
                    <th scope="col"><?= h('name') ?></th>
                    <th scope="col" class="text-center"><?= h('Duration (years)') ?></th>
                    <th scope="col" class="text-center"><?= h('minimum_age') ?></th>
                    <th scope="col" class="text-center"><?= h('maximum_age') ?></th>
                    <th scope="col" class="text-center"><?= h('# of Approvers') ?></th>
                </tr>
                </thead>
                <tbody>
                    <?php foreach ($authorizationGroup -> authorization_types as $authorizationType) : ?>
                        <tr>
                            <td><?= h($authorizationType->name) ?></td>
                            <td class="text-center"><?= $this->Number->format($authorizationType->length) ?></td>
                            <td class="text-center"><?= $authorizationType->minimum_age === null ? '' : $this->Number->format($authorizationType->minimum_age) ?></td>
                            <td class="text-center"><?= $authorizationType->maximum_age === null ? '' : $this->Number->format($authorizationType->maximum_age) ?></td>
                            <td class="text-center"><?= $this->Number->format($authorizationType->num_required_authorizors) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
<?php 
    $this->start('modals');
    echo $this->Modal->create("Edit Authoriztion Group", ['id' => 'editModal', 'close' => true]) ;
?>
    <fieldset>
        <?php
         echo $this->Form->create($authorizationGroup, ['id' => 'edit_entity', 'url' => ['controller' => 'AuthorizationGroups', 'action' => 'edit', $authorizationGroup->id]]);
         echo $this->Form->control('name');
         echo $this->Form->end()
                ?>
    </fieldset>
<?php
    echo $this->Modal->end([
        $this->Form->button('Submit',['class' => 'btn btn-primary', 'id' => 'edit_entity__submit', 'onclick' => '$("#edit_entity").submit();']),
        $this->Form->button('Close', ['data-bs-dismiss' => 'modal'])
    ]);
?>

<?php    
//finish writing to modal block in layout
    $this->end(); ?>
