<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Branch[]|\Cake\Collection\CollectionInterface $branches
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Branches';
$this->KMP->endBlock();
function branchHierachyTable($branches, $me, $parent_string = "")
{
?>
    <?php foreach ($branches as $branch) {
        $name = $parent_string . "/" . $branch->name; ?>
        <tr>
            <td><?= h($name) ?></td>
            <td><?= h($branch->type) ?></td>
            <td><?= h($branch->location) ?></td>
            <td class="actions text-end text-nowrap">
                <?= $me->Html->link(
                    __(""),
                    ["action" => "view", $branch->id],
                    ["title" => __("View"), "class" => "btn-sm btn btn-secondary bi bi-binoculars-fill", "data-turbo-frame" => "_top"],
                ) ?>
            </td>
        </tr>
        <?php if (!empty($branch->children)) { ?>
            <?php branchHierachyTable($branch->children, $me, $name); ?>
    <?php }
    } ?>
<?php
}
?>
<h3>
    Branches
</h3>
<turbo-frame id="branchesList" data-turbo='true'>
    <table class="table table-striped">
        <thead>
            <tr>
                <td colspan="2">
                <td colspan="2" class="text-end">
                    <form class="form-inline">

                        <div class="input-group">
                            <div class="input-group-text" id="btnSearch"><span class='bi bi-search'></span></div>
                            <input type="text" name="search" class="form-control" placeholder="Search..."
                                value="<?= $search ?>" aria-describedby="btnSearch" aria-label="Search">
                        </div>
                    </form>
                </td>
            </tr>
            <tr>
                <th scope="col"><?= h("Branch") ?></th>
                <th scope="col"><?= h("Type") ?></th>
                <th scope="col"><?= h("Location") ?></th>
                <th scope="col" class="actions"></th>
            </tr>
        </thead>
        <tbody>
            <?php branchHierachyTable($branches, $this, "", true); ?>
        </tbody>
    </table>
</turbo-frame>


<?php $this->append("script", $this->Html->script(["app/branches/index.js"])); ?>