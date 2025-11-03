<?php
echo $this->Form->create($branch, [
    "id" => "edit_entity",
    "url" => [
        "controller" => "Branches",
        "action" => "edit",
        $branch->id,
    ],
    'data-controller' => 'branch-links'
]);
echo $this->Modal->create("Edit Branch", [
    "id" => "editModal",
    "close" => true,
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