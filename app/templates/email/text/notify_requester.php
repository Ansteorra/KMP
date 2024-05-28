<?php
?>
Good day <?= $particpant ?>

    <?= $marshal ?> has responded to your request and has <?=$result?> the authorization for <?=$authorization_type?>.

<?php if ($result == "Approved") : ?>
    You may download an updated authorizations card at the following URL:

    <?= $participantCardPDFUrl ?>.pdf
<?php endif; ?>


Thank you 
Marshallet Web Minister. 
