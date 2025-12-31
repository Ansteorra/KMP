<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 * @var array $policiesFlat
 * @var array $policyMap
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Permissions Matrix';
$this->KMP->endBlock(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Permissions Matrix</h3>
</div>

<div class="permissions-matrix" data-controller="permission-manage-policies"
    data-permission-manage-policies-url-value="<?= $this->Url->build([
                                                    "controller" => "Permissions",
                                                    "action" => "updatePolicy"
                                                ], ["fullBase" => true]) ?>">


    <div class="table-container">
        <div class="matrix-wrapper">
            <table class="permissions-table">
                <thead>
                    <tr>
                        <th class="policy-header fixed-cell">Policy</th>
                        <?php foreach ($permissions as $permission): ?>
                            <th class="permission-header">
                                <?= h($permission->name) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $currentClass = '';
                    $currentNamespace = '';
                    $isAssigned = false;
                    foreach ($policiesFlat as $policy):
                        $newClass = $policy['className'];
                        $fullClassName = str_replace('\\', '-', $policy['class']);
                        $nameSpace = str_replace('\\', '-', $policy['namespace']);
                        $isNewNamespace = $currentNamespace !== $nameSpace;
                        if ($isNewNamespace) {
                            $currentNamespace = $nameSpace;
                        }
                        $isNewClass = $currentClass !== $newClass;
                        if ($isNewClass) {
                            $currentClass = $newClass;
                        }
                    ?>
                        <?php if ($isNewNamespace): ?>
                            <tr class="namespace-row">
                                <td colspan="<?= count($permissions) + 1 ?>" class="namespace-cell">
                                    <strong><?= h($policy['namespace']) ?></strong>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr <?php if ($isNewClass):
                                echo 'class="new-group row_' . $nameSpace . '"';
                            else:
                                echo 'class="row_' . $nameSpace . ' row_' . $fullClassName . ' collapse" ';
                            endif; ?>>
                            <td class="policy-cell fixed-cell <?= $isNewClass ? 'policy-class' : '' ?>"
                                <?= $isNewClass ? ' data-bs-toggle="collapse" data-bs-target=".row_' . $fullClassName . '"' : '' ?>>
                                <?= $isNewClass ? '<strong>' . h($policy['className']) . '</strong>' : '' ?>
                                <?= h($policy['display']) ?>
                            </td>
                            <?php foreach ($permissions as $permission): ?>
                                <?php
                                $key = $permission->id . '_' . $policy['class'] . '_' . $policy['method'];
                                $checked = isset($policyMap[$key]);
                                $className = $fullClassName;
                                ?>
                                <td class="checkbox-cell">
                                    <?php if ($isNewClass): ?>
                                        <?= $this->Form->control($key, [
                                            "type" => "checkbox",
                                            "switch" => true,
                                            'label' => "",
                                            "data-permission-manage-policies-target" => "policyClass",
                                            "data-class-name" => $className,
                                            "data-permission-id" => $permission->id
                                        ]) ?>
                                    <?php else: ?>
                                        <?= $this->Form->control($key, [
                                            "type" => "checkbox",
                                            "checked" => $checked,
                                            "switch" => true,
                                            'label' => '',
                                            "data-permission-manage-policies-target" => "policyMethod",
                                            "data-class-name" => $className,
                                            "data-method-name" => h($policy['method']),
                                            "data-permission-id" => $permission->id
                                        ]) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div id="save-status" class="save-status"></div>
</div>