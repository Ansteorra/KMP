Good day <?= $memberScaName ?>

First we would like to thank you for your offer of service in the office of <?= $officeName ?> for <?= $branchName ?>.
We are pleased to inform you that your offer has been accepted and you have been appointed and can start in the role on
<?= $hireDate ?>.

<?= $requiresWarrant ? "Please note that this office requires a warrant. A reqest for that warrent has been forwarded to the Crown for approval." : "" ?>

<table>
    <tr>
        <td>Office:</td>
        <td><?= $officeName ?></td>
    </tr>
    <tr>
        <td>Branch:</td>
        <td><?= $branchName ?></td>
    </tr>
    <tr>
        <td>Start Date:</td>
        <td><?= $hireDate ?></td>
    </tr>
    <tr>
        <td>End Date:</td>
        <td><?= $endDate ?></td>
    </tr>
</table>

Thank you
<?= $siteAdminSignature ?>.