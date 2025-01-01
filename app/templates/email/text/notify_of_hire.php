Good day <?= $memberScaName ?>

First we would like to thank you for your offer of service in the office of <?= $officeName ?> for <?= $branchName ?>.

We are pleased to inform you that your offer has been accepted and you have been appointed and can start in the role on
<?= $hireDate ?>.

<?= $requiresWarrant ? "Please note that this office requires a warrant. A request for that warrent has been forwarded to the Crown for approval." : "" ?>


Details:

* Office:<?= $officeName ?>

* Branch:<?= $branchName ?>

* Start Date:<?= $hireDate ?>

* End Date:<?= $endDate ?>


Thank you
<?= $siteAdminSignature ?>.