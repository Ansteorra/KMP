<?php

use App\KMP\StaticHelpers;
//find out which tab should be selected by first seeing if any tab has selected true.. then check if that tab data is empty.. if it is select the next one.
$selected = false;
foreach ($tabs as $tab) {
    if ($tab["selected"]) {
        $selected = true;
        if (empty($tab["data"])) {
            $tab["selected"] = false;
            $selected = false;
        }
    }
    if ($selected) {
        break;
    }
}
//if no tab is selected, select the first tab with data
if (!$selected) {
    foreach ($tabs as &$tab) {
        if (!empty($tab["data"])) {
            $tab["selected"] = true;
            break;
        }
    }
}


?>
<nav>
    <div class="nav nav-tabs" id="nav-<?= $tabGroupName ?>" role="tablist">
        <?php foreach ($tabs as &$tab) { ?>
            <?php if (!empty($tab["data"])) { ?>
                <button class="nav-link <?= $tab["selected"] ? "active" : "" ?>" id="nav-<?= $tab["id"] ?>-tab"
                    data-bs-toggle="tab" data-bs-target="#nav-<?= $tab["id"] ?>" type="button" role="tab"
                    data-level="activityWindow" aria-controls="nav-<?= $tab["id"] ?>"
                    aria-selected="<?= $tab["selected"] ? "true" : "false" ?>"><?= $tab["label"] ?>
                    <?php if (isset($tab["badge"]) && $tab["badge"] != "" && $tab["badge"] > 0) { ?>
                        <span class="badge <?= $tab["badgeClass"] ?>"><?= $tab["badge"] ?></span>
                    <?php } ?>
                </button>
            <?php } ?>
        <?php } ?>
    </div>
</nav>
<div class="tab-content" id="nav-tabContent">
    <?php foreach ($tabs as &$tab) { ?>
        <?php if (!empty($tab["data"])) { ?>
            <div class="tab-pane fade <?= $tab["selected"] ? "show active" : "" ?>" id="nav-<?= $tab["id"] ?>" role="tabpanel"
                aria-labelledby="nav-<?= $tab["id"] ?>-tab" tabindex="0">
                <turbo-frame id="turbo-<?= $tab["id"] ?>">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <?php foreach ($tab["columns"] as $column => $value) { ?>
                                        <th scope="col" class="<?= $column == "Actions" ? "actions" : "" ?>">
                                            <?= $column == "Actions" ? "" : $column ?></th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tab["data"] as $data) { ?>

                                    <tr>
                                        <?php foreach ($tab["columns"] as $column => $value) {
                                            if ($column == "Actions") { ?>
                                                <td class="actions text-end text-nowrap">
                                                    <?php foreach ($value as $link) {
                                                        if (isset($link["condition"])) {
                                                            //loop through the conditions and if any of them are false, skip this link
                                                            $skip = false;
                                                            foreach ($link["condition"] as $key => $value) {
                                                                if (StaticHelpers::getValue($key, $data) != $value) {
                                                                    $skip = true;
                                                                    break;
                                                                }
                                                            }
                                                            if ($skip) {
                                                                continue;
                                                            }
                                                        }
                                                        $verified = false;
                                                        if (!$link["verify"]) {
                                                            $verified = true;
                                                        } else {
                                                            if (isset($link["authData"])) {
                                                                $authEntity = StaticHelpers::getValue($link["authData"], $data);
                                                            } else {
                                                                $authEntity = $data;
                                                            }
                                                            $verified = $user->checkCan($link["action"], $authEntity);
                                                        }

                                                        if ($verified) {
                                                            //loop through options and process all the templates in case there is data to pull out
                                                            foreach ($link["options"] as $key => $option) {
                                                                $link["options"][$key] = StaticHelpers::processTemplate($option, $data);
                                                            }
                                                            if (!isset($link["plugin"])) {
                                                                $link["plugin"] = null;
                                                            }
                                                            if (!isset($link["?"])) {
                                                                $link["?"] = null;
                                                            }
                                                            switch ($link["type"]) {
                                                                case "link":
                                                                    echo $this->Html->link(
                                                                        __($link["label"]),
                                                                        [
                                                                            "controller" => $link["controller"],
                                                                            "action" => $link["action"],
                                                                            "plugin" => $link["plugin"],
                                                                            "?" => $link["?"],
                                                                            StaticHelpers::getValue($link["id"], $data),
                                                                        ],
                                                                        $link["options"]
                                                                    );
                                                                    break;
                                                                case "button":
                                                                    echo $this->Html->tag(
                                                                        "button",
                                                                        __($link["label"]),
                                                                        $link["options"]
                                                                    );
                                                                    break;
                                                                case "postLink":
                                                                    echo $this->Form->postLink(
                                                                        __($link["label"]),
                                                                        [
                                                                            "plugin" => $link["plugin"],
                                                                            "controller" => $link["controller"],
                                                                            "action" => $link["action"],
                                                                            StaticHelpers::getValue($link["id"], $data),
                                                                        ],
                                                                        $link["options"]
                                                                    );
                                                                    break;
                                                            }
                                                            echo " ";
                                                        }
                                                    }
                                                    echo "</td>";
                                                } else {
                                                    if ($data instanceof Cake\ORM\Entity) {
                                                        $processData = $data->toArray();
                                                    } else {
                                                        $processData = $data;
                                                    }
                                                    ?>
                                                <td class="align-middle"><?php

                                                                            $record = StaticHelpers::getValue($value, $processData);
                                                                            //if the value is a DateTime then call ->toDateString() on it
                                                                            if ($record instanceof Cake\I18n\DateTime) {
                                                                                echo $record->toDateString();
                                                                            } elseif (is_string($value) && strpos($value, "{{") !== false) {
                                                                                echo StaticHelpers::processTemplate($value, $processData);
                                                                            } else {
                                                                                echo $record;
                                                                            }
                                                                            ?></td>
                                        <?php }
                                            } ?>
                                    </tr>

                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </turbo-frame>
            </div>
    <?php }
    } ?>
</div>