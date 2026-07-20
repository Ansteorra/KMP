<?php

use App\KMP\StaticHelpers;

?>

<turbo-frame id="<?= $tableConfig["id"] ?>" data-turbo='true'>
    <?php if (!empty($tableConfig['exportButton'])): ?>
        <div class="d-flex justify-content-end align-items-center mb-2 mt-2">
            <a href="<?= h($tableConfig['exportButton']['url']) ?>" class="btn btn-outline-primary btn-sm"
                data-controller="csv-download" data-csv-download-url-value="<?= h($tableConfig['exportButton']['url']) ?>"
                <?php if (!empty($tableConfig['exportButton']['filename'])): ?>
                data-csv-download-filename-value="<?= h($tableConfig['exportButton']['filename']) ?>" <?php endif; ?>
                <?php if (!empty($tableConfig['exportButton']['fields'])): ?>
                data-csv-download-fields-value='<?= json_encode($tableConfig['exportButton']['fields']) ?>' <?php endif; ?>
                title="Download CSV">
                <i class="bi bi-download"></i> Download CSV
            </a>
        </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <?php foreach ($tableConfig["columns"] as $column => $value) { ?>
                        <th scope="col" class="<?= $column == "Actions" ? "actions" : "" ?>">
                            <?php
                            //if the colunm name is in the sortable array then add the sort icon
                            if (isset($tableConfig["columns"]) && in_array($column, $tableConfig["columns"])) {
                                $sort = $this->Paginator->sort($column);
                                echo $sort;
                            } else {
                                echo __($column == "Actions" ? "" : $column);
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
                                                    // Add data-turbo-frame="_top" to break out of turbo frame
                                                    $link["options"]["data-turbo-frame"] = "_top";
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
                                                    // Add data-turbo-frame="_top" to break out of turbo frame
                                                    $link["options"]["data-turbo-frame"] = "_top";
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
                                                        //if the value is a DateTime then format it
                                                        if ($record instanceof Cake\I18n\DateTime) {
                                                            // Format as date-only without timezone conversion;
                                                            // these represent calendar dates, not specific moments in time
                                                            echo $record->format('Y-m-d');
                                                        } elseif (is_string($value)) {
                                                            if (strpos($value, "{{") !== false) {
                                                                $record = StaticHelpers::processTemplate($value, $data);
                                                            }
                                                            //if the string has a carriage return the run the convert to paragraph
                                                            if (strpos($value, "\n") !== false) {
                                                                echo $this->Text->autoParagraph(h($record));
                                                            } else {
                                                                echo $record;
                                                            }
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