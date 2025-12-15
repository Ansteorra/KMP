<?php

/**
 * Warrant Reasons Sub-Row Template
 * 
 * Displays the list of reasons why a member is not warrantable.
 * This template is loaded via AJAX when the warrantable cell is clicked.
 * 
 * @var array $reasons Array of non-warrantable reason strings
 */
?>
<div class="warrant-reasons p-3">
    <?php if (empty($reasons)): ?>
        <div class="text-success">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Member is warrantable</strong> - All requirements met
        </div>
    <?php else: ?>
        <div class="text-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Cannot receive warrant due to:</strong>
        </div>
        <ul class="mb-0 mt-2">
            <?php foreach ($reasons as $reason): ?>
                <li><?= h($reason) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>