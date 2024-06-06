Good day <?= $memberScaName ?>

<?= $approverScaName ?> has responded to your request and the authorization is now <?= $status ?> for
<?= $authorizationTypeName ?>.


<?php if ($result == "pending") : ?>
    You request has been forwarded to <?= $nextApproverScaName ?> for additional approval.
<?php endif; ?>

<?php if ($result == "rejected") : ?>
    If you feel this decision was made in error please reach out to <?= $approverScaName ?> for more information.
<?php endif; ?>

<?php if ($result == "revoked") : ?>
    If you feel this decision was made in error please reach out to <?= $approverScaName ?> for more information.
<?php endif; ?>


<?php if ($result == "approved" || $result == "revoked") : ?>
    You may view your motified member card at the following UR:

    <?= $memberCardUrl ?>
<?php endif; ?>

Thank you
<?= $siteAdminSignature ?>.