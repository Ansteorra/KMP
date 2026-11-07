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
            <kmp-icon name="circle-check" class="me-2" aria-hidden="true"></kmp-icon>
            <strong>Member is warrantable</strong> - All requirements met
        </div>
    <?php else: ?>
        <div class="text-warning">
            <kmp-icon name="alert-triangle" class="me-2" aria-hidden="true"></kmp-icon>
            <strong>Cannot receive warrant due to:</strong>
        </div>
        <ul class="mb-0 mt-2">
            <?php foreach ($reasons as $reason): ?>
                <li><?= h($reason) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>