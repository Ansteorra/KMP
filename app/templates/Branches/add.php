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
                        <label class="form-label" for="branch-new-link">Links</label>
                        <div data-branch-links-target='displayList' class="mb-3"></div>
                        <div class="input-group">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-value="link"
                                        data-branch-links-target="linkType" aria-label="Link type: Link" data-bs-toggle="dropdown" aria-expanded="false"><kmp-icon name="link" aria-hidden="true"></kmp-icon> </button>
                                <ul class="dropdown-menu">
                                        <li><button type="button" class="dropdown-item" data-value="link"
                                                        data-action="branch-links#setLinkType"><kmp-icon name="link" class="me-2" aria-hidden="true"></kmp-icon> Link</button></li>
                                        <li><button type="button" class="dropdown-item" data-value="discord"
                                                        data-action="branch-links#setLinkType"><kmp-icon name="brand-discord" class="me-2" aria-hidden="true"></kmp-icon> Discord</button></li>
                                        <li><button type="button" class="dropdown-item" data-value="facebook"
                                                        data-action="branch-links#setLinkType"><kmp-icon name="brand-facebook" class="me-2" aria-hidden="true"></kmp-icon> Facebook</button></li>
                                        <li><button type="button" class="dropdown-item" data-value="instagram"
                                                        data-action="branch-links#setLinkType"><kmp-icon name="brand-instagram" class="me-2" aria-hidden="true"></kmp-icon> Instagram</button></li>
                                        <li><button type="button" class="dropdown-item" data-value="tiktok"
                                                        data-action="branch-links#setLinkType"><kmp-icon name="brand-tiktok" class="me-2" aria-hidden="true"></kmp-icon> TikTok</button></li>
                                        <li><button type="button" class="dropdown-item" data-value="threads"
                                                        data-action="branch-links#setLinkType"><kmp-icon name="brand-threads" class="me-2" aria-hidden="true"></kmp-icon> Threads</button></li>
                                        <li><button type="button" class="dropdown-item" data-value="twitter-x"
                                                        data-action="branch-links#setLinkType"><kmp-icon name="brand-x" class="me-2" aria-hidden="true"></kmp-icon> X (Twitter)</button></li>
                                        <li><button type="button" class="dropdown-item" data-value="youtube"
                                                        data-action="branch-links#setLinkType"><kmp-icon name="brand-youtube" class="me-2" aria-hidden="true"></kmp-icon> YouTube</button></li>
                                </ul>
                                <input type="url" id="branch-new-link" data-branch-links-target="new" class="form-control col-8" placeholder="Link">
                                <button type="button" class="btn btn-primary btn-sm" data-action="branch-links#add">Add</button>
                        </div>
                </div>
        </fieldset>
        <?= $this->Form->button(__("Submit")) ?>
        <?= $this->Form->end() ?>
</div>