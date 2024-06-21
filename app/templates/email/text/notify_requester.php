Good day <?= $memberScaName ?>

<?= $approverScaName ?> has responded to your request and the authorization is now <?= $status ?> for
<?= $activityName ?>.


<?php if ($status == "Pending") : ?>
You request has been forwarded to <?= $nextApproverScaName ?> for additional approval.
<?php endif; ?>

<?php if ($status == "Denied") : ?>
If you feel this decision was made in error please reach out to <?= $approverScaName ?> for more information.
<?php endif; ?>

<?php if ($status == "Revoked") : ?>
If you feel this decision was made in error please reach out to <?= $approverScaName ?> for more information.
<?php endif; ?>


<?php if ($status == "Approved" || $status == "Revoked") : ?>
You may view your updated member card at the following UR:

<?= $memberCardUrl ?>
<?php endif; ?>

Thank you
<?= $siteAdminSignature ?>.