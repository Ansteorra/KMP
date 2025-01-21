<?php

use App\KMP\StaticHelpers;

?>

<turbo-frame id="<?= $tableConfig["id"] ?>" data-turbo='true'>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <?php foreach ($tableConfig["columns"] as $column => $value) { ?>
                    <th scope="col <?= $column == "Actions" ? "actions" : "" ?>">
                        <?php
                            //if the colunm name is in the sortable array then add the sort icon
                            if (isset($tableConfig["columns"]) && in_array($column, $tableConfig["columns"])) {
                                $sort = $this->Paginator->sort($column);
                                echo $sort;
                            } else {
                                echo __($column);
                            }
                            ?>
                    </th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableConfig["data"] as $data) { ?>

                <tr>
                    <?php foreach ($tableConfig["columns"] as $column => $value) {
                            if ($column == "Actions") { ?>
                    <td class="actions">
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
                                } else { ?>
                    <td class="align-top"><?php

                                                        $record = StaticHelpers::getValue($value, $data);
                                                        //if the value is a DateTime then call ->toDateString() on it
                                                        if ($record instanceof Cake\I18n\DateTime) {
                                                            echo $record->toDateString();
                                                        } elseif (is_string($value)) {
                                                            if (strpos($value, "{{") !== false) {
                                                                $record = StaticHelpers::processTemplate($value, $data);
                                                            }
                                                            //if the string has a carriage return the run the convert to paragraph
                                                            if (strpos($value, "\n") !== false) {
                                                                echo $this->Text->autoParagraph(h($record));
                                                            } else {
                                                                echo h($record);
                                                            }
                                                        } else {
                                                            echo h($record);
                                                        }
                                                        ?></td>
                    <?php }
                            } ?>
                </tr>

                <?php } ?>
            </tbody>
        </table>
        <?php if ($tableConfig["usePagination"]) { ?>
        <div class="paginator">
            <ul class="pagination">
                <?= $this->Paginator->first("«", ["label" => __("First")]) ?>
                <?= $this->Paginator->prev("‹", [
                        "label" => __("Previous"),
                    ]) ?>
                <?= $this->Paginator->numbers() ?>
                <?= $this->Paginator->next("›", ["label" => __("Next")]) ?>
                <?= $this->Paginator->last("»", ["label" => __("Last")]) ?>
            </ul>
            <p><?= $this->Paginator->counter(
                        __(
                            "Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total",
                        ),
                    ) ?></p>
        </div>
        <?php } ?>
</turbo-frame>