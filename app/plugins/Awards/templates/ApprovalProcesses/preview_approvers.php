<?php
/**
 * Turbo Frame response for approval-process approver preview.
 *
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\ApprovalProcess $approvalProcess
 * @var array $awards
 * @var array|null $preview
 * @var string|int|null $previewAwardId
 * @var string $previewFrameId
 */

echo $this->element('ApprovalProcesses/approver_preview', [
    'approvalProcess' => $approvalProcess,
    'awards' => $awards,
    'preview' => $preview,
    'previewAwardId' => $previewAwardId,
    'previewFrameId' => $previewFrameId,
]);
