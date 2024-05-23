<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Branch $branch
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<div class="branches view large-9 medium-8 columns content">
<div class="row align-items-start">
        <div class="col">
            <h3>
                <?= $this->Html->link('', ['action' => 'index'], ['class' => 'bi bi-arrow-left-circle']) ?>
                <?= h($branch->name) ?> 
            </h3>
        </div>
        <div class="col text-end">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
            <?php 
                if(empty($branch -> children) && empty($branch -> members)){
                    echo $this->Form->postLink(__('Delete'), ['action' => 'delete', $branch->id], ['confirm' => __('Are you sure you want to delete {0}?', $branch->name), 'title' => __('Delete'), 'class' => 'btn btn-danger btn-sm']);
                }
            ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped">
            <trscope="row">
                <th class="col"><?= __('Location') ?></th>
                <td class="col-10"><?= h($branch->location) ?></td>
            </tr>
            <tr scope="row">
                <th class="col"><?= __('Parent') ?></th>
                <td class="col-10"><?= $branch->parent === null ? 'Root' : $this->Html->link(__($branch->parent->name), ['action' => 'view', $branch->parent_id], ['title' => __('View')]) ?></td>
            </tr>
            </tr>
        </table>
    </div>
    <div class="related">
        <h4><?= __('Children') ?></h4>
        <?php if (!empty($branch->children)) : ?> 
        <div class="table-responsive" >
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Name') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($branch->children as $child): ?>
                <tr>
                    <td><?= h($child->name) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $child->id], ['title' => __('View'), 'class' => 'btn btn-secondary']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="related">
        <h4><?= __('Members') ?></h4>
        <?php if (!empty($branch->members)) : ?> 
        <div class="table-responsive" >
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Name') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($branch->members as $member): ?>
                <tr>
                    <td><?= h($member->sca_name) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['controller'=>'members', 'action' => 'view', $member->id], ['title' => __('View'), 'class' => 'btn btn-secondary']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>


<?php 
    $this->start('modals');
    echo $this->Modal->create("Edit Branch", ['id' => 'editModal', 'close' => true]) ;
?>
    <fieldset>
        <?php
         echo $this->Form->create($branch, ['id' => 'edit_entity', 'url' => ['controller' => 'Branches', 'action' => 'edit', $branch->id]]);
         echo $this->Form->control('name');
         echo $this->Form->control('location');
         echo $this->Form->control('parent_id', ['options' => $treeList, 'empty' => true]);
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