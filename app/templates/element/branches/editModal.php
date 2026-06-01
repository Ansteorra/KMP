<?php
echo $this->Form->create($branch, [
    "id" => "edit_entity",
    "url" => [
        "controller" => "Branches",
        "action" => "edit",
        $branch->public_id,
    ],
    'data-controller' => 'branch-links'
]);
echo $this->Modal->create("Edit Branch", [
    "id" => "editModal",
    "close" => true,
    "size" => "lg",
]);
?>
<fieldset>
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
    echo $this->Form->control("can_have_officers", [
        "switch" => true,
        "label" => "Can Have Officers",
    ]);
    ?>
    <?php
    $contactUrl = $this->Url->build([
        'controller' => 'Members',
        'action' => 'AutoComplete',
        'plugin' => null,
    ]);
    $contactAttrs = [];
    if (!empty($branch->contact)) {
        $contactAttrs['data-ac-init-selection-value'] = json_encode([
            'value' => $branch->contact->public_id,
            'text' => $branch->contact->sca_name,
        ]);
    }
    echo $this->KMP->autoCompleteControl(
        $this->Form,
        'sca_name',
        'contact_id',
        $contactUrl,
        'Point of Contact',
        false,
        false,
        3,
        $contactAttrs
    );
    ?><?php
    echo $this->Form->control("parent_id", [
        "options" => $treeList,
        "empty" => true,
    ]);
    $links = json_encode($branch->links);
    if ($links === 'null') {
        $links = '[]';
    }
    echo $this->Form->control("domain", ['label' => 'Email Domain', 'placeholder' => 'e.g. branch.example.com']);
    echo $this->Form->hidden('branch_links', ['value' => $links, 'id' => 'links', 'data-branch-links-target' => 'formValue']); ?>
    <div class="mb-3 form-group links">
        <label class="form-label" for="links">Links</label>
        <div data-branch-links-target='displayList' class="mb-3"></div>
        <div class="input-group">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= h(__('Select branch link type')) ?>">
                <i class="bi bi-link" data-value="link" data-branch-links-target="linkType" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu">
                <li><button type="button" class="dropdown-item" data-value="link"
                        data-action="branch-links#setLinkType"><i class="bi bi-link" data-value="link" aria-hidden="true"></i> Website</button></li>
                <li><button type="button" class="dropdown-item" data-value="discord"
                        data-action="branch-links#setLinkType"><i class="bi bi-discord" data-value="discord" aria-hidden="true"></i> Discord</button></li>
                <li><button type="button" class="dropdown-item" data-value="facebook"
                        data-action="branch-links#setLinkType"><i class="bi bi-facebook" data-value="facebook" aria-hidden="true"></i> Facebook</button></li>
                <li><button type="button" class="dropdown-item" data-value="instagram"
                        data-action="branch-links#setLinkType"><i class="bi bi-instagram" data-value="instagram" aria-hidden="true"></i> Instagram</button></li>
                <li><button type="button" class="dropdown-item" data-value="tiktok"
                        data-action="branch-links#setLinkType"><i class="bi bi-tiktok" data-value="tiktok" aria-hidden="true"></i> TikTok</button></li>
                <li><button type="button" class="dropdown-item" data-value="threads"
                        data-action="branch-links#setLinkType"><i class="bi bi-threads" data-value="threads" aria-hidden="true"></i> Threads</button></li>
                <li><button type="button" class="dropdown-item" data-value="twitter-x"
                        data-action="branch-links#setLinkType"><i class="bi bi-twitter-x" data-value="twitter-x" aria-hidden="true"></i> X/Twitter</button></li>
                <li><button type="button" class="dropdown-item" data-value="youtube"
                        data-action="branch-links#setLinkType"><i class="bi bi-youtube" data-value="youtube" aria-hidden="true"></i> YouTube</button></li>
            </ul>
            <input type="url" data-branch-links-target="new" class="form-control col-8"
                placeholder="https://example.com">
            <button type="button" class="btn btn-primary btn-sm" data-action="branch-links#add">Add</button>
        </div>
    </div>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "branch-edit-submit"
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();
?>
