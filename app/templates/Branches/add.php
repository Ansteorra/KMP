<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Branch $branch
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Branch';
$this->KMP->endBlock(); ?>


<div class="branches form content">
        <?= $this->Form->create($branch, ['data-controller' => 'branch-links']) ?>
        <fieldset>
                <legend><?= $this->element('backButton') ?> <?= __("Add Branch") ?></legend>
                <?php
                echo $this->Form->control("name");
                echo $this->Form->control("type", [
                        "options" => $branch_types,
                        "empty" => true,
                ]);
                echo $this->Form->control("location");
                echo $this->Form->control("can_have_members", [
                        "switch" => true,
                        "label" => "Can Have Members",
                ]);
                echo $this->Form->control("parent_id", [
                        "options" => $treeList,
                        "empty" => true,
                ]);
                echo $this->Form->control("domain", ['label' => 'Email Domain', 'placeholder' => 'e.g. branch.example.com']);
                $links = '[]';
                echo $this->Form->hidden('branch_links', ['value' => $links, 'id' => 'links', 'data-branch-links-target' => 'formValue']); ?>
                <div class="mb-3 form-group links">
                        <label class="form-label" for="links">Links</label>
                        <div data-branch-links-target='displayList' class="mb-3"></div>
                        <div class="input-group">
                                <button class="btn btn-outline-secondary dropdown-toggle bi bi-link" type="button" data-value="link"
                                        data-branch-links-target="linkType" data-bs-toggle="dropdown" aria-expanded="false"></button>
                                <ul class="dropdown-menu">
                                        <li><a class="dropdown-item bi bi-link" href="#" data-value="link"
                                                        data-action="branch-links#setLinkType"></a></li>
                                        <li><a class="dropdown-item bi bi-discord" href="#" data-value="discord"
                                                        data-action="branch-links#setLinkType"></a></li>
                                        <li><a class="dropdown-item bi bi-facebook" href="#" data-value="facebook"
                                                        data-action="branch-links#setLinkType"></a></li>
                                        <li><a class="dropdown-item bi bi-instagram" href="#" data-value="instagram"
                                                        data-action="branch-links#setLinkType"></a></li>
                                        <li><a class="dropdown-item bi bi-tiktok" href="#" data-value="tiktok"
                                                        data-action="branch-links#setLinkType"></a></li>
                                        <li><a class="dropdown-item bi bi-threads" href="#" data-value="threads"
                                                        data-action="branch-links#setLinkType"></a></li>
                                        <li><a class="dropdown-item bi bi-twitter-x" href="#" data-value="twitter-x"
                                                        data-action="branch-links#setLinkType"></a></li>
                                        <li><a class="dropdown-item bi bi-youtube" href="#" data-value="youtube"
                                                        data-action="branch-links#setLinkType"></a></li>
                                </ul>
                                <input type="url" data-branch-links-target="new" class="form-control col-8" placeholder="Link">
                                <button type="button" class="btn btn-primary btn-sm" data-action="branch-links#add">Add</button>
                        </div>
                </div>
        </fieldset>
        <?= $this->Form->button(__("Submit")) ?>
        <?= $this->Form->end() ?>
</div>