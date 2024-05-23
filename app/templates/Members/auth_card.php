<?php
    function checkCardCount($cardCount){
        if($cardCount == 2){
            echo("</div><div style='clear:both'></div><div class='cards'>");
            return 0;
        }else{
            return $cardCount;
        }
    }
    $watermarkimg = "data:image/gif;base64,".  base64_encode(file_get_contents($this->Url->image('home_marshal6.gif', array('fullBase' => true))));
    //sort authorization types by group
    $member->authorizations = $member->authorizations->sortBy(function($authorization, $key){
        return $authorization->authorization_type->authorization_group->name;
    });
?>
<html><head>
<style>
    .letter{
        font-size:10pt;
        clear:both;
        margin-left:10px;
        margin-right:10px;
        margin-top:10px;
        margin-bottom:20px;
    }
    .header{
        width:100%;
        height:68px;
    }
    .header-left, .header-right{
        float:left;
        width:20%;
        text-align:center;
    }
    .header-left img, .header-right img{
        height:68px;
    }
    .header-center{
        background-color:gold;
        float:left;
        font-size:18pt;
        text-align:center;
        width:60%;
        vertical-align:middle;
        font-weight:bold;
    }
    .cardbox::after {
        content: "";
        background-image:url('<?php echo $watermarkimg ?>');
        background-size: 45mm 42mm;
        background-repeat: no-repeat;
        background-position: center;
        opacity: 0.05;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
        position: absolute;
        z-index: -1;   
        display:inline-block;
        }
    .cardbox{
        border:.5mm;
        border-style:solid;
        border-radius:3mm;
        border-color:black;
        width:48mm;
        height:75mm;
        text-align:center;
        position:relative;
        margin:0px;
        overflow:hidden;
        float:left;
    }
    .cards{
        text-align:center;
        font-size: 0;
    }
    .card{
        display:inline-block;
        margin:0;
    }
    .cardboxheader{
        font-weight:bold;
        font-size:9pt;
    }
    .cardbox dl{
        display:block;
        width:60%;
        margin:0px;
        margin-left:5px;
        padding:0px;
        text-align:left;
        font-size:7pt;
        margin-bottom:0px;
    }
    .cardbox dl dt{
        font-weight:900;
        margin:0px;
        padding:0px;
    }
    .cardbox dl dd{
        margin-left:3px;
    }
    .cardboxAuthorizingLabel, .cardboxAuthorizationsLabel{
        font-size:7pt;
        font-weight:900;
        width:95%;
    }
    
    .cardboxAuthorizing{
        margin:0px;
        padding:0px;
        margin-left:5px;
        font-size:7pt;
        list-style:none;
        text-align:left;
    }
    .cardboxAuthorizations{
        margin:0px;
        padding:0px;
        margin-left:5px;
        font-size:7pt;
        list-style:none;
        text-align:center;
        width:95%;
    }
    hr{
        border:.5mm;
        border-style:solid;
        border-color:black;
        margin:0px;
    }
</style>
</head>
<body>
<div class="header">
<div class="header-left">
<img src='<?php echo $watermarkimg ?>'>
</div>
<div class="header-center">
Kingdom of Ansteorra<br/>
Martial Authorization
</div> 
<div class="header-right">
<img src='<?php echo $watermarkimg ?>'>
</div>
<div style="clear:both"></div>
</div>
<div class="letter">
<p>Greetings Ansteorra Participant, </p>
 
<p>You will be pleased to find your new fighter and marshal authorization card below. Please note that while
there is an expiration date, it can be revoked per the customs and laws of the Kingdom of Ansteorra and the
Society for Creative Anachronism. Your authorization comes from the Crown, Earl Marshal and respective
deputies, so remember that you are representing the Crown and their trust in you everytime you take the
field. </p>
 
<p>Remember to have your fighter authorization card with you at any SCA event or practice that you will be
fighting or marshalling. It can also be asked to be seen by the Marshal in Charge or a senior Marshal at any
time. At most interkingdom wars, it is normal to also be required to provide your site token and legal
identification when being inspected. </p>
 
<p>Cut off around the edges of the below box, fold on the dotted line and keep the card safe. Please feel free
to print out multiple copies and keep them where you will have them available at SCA events and practices. </p>
 
<p>It is recommend that you laminate your card to protect from moisture (dew, sweat, water, etc). You can do
this by buying a laminating pouch or carefully putting clear packing tape on both sides to cover. </p>
 
<p>If something is missing or is incorrect, don't hesitate to contact me. </p>
 
<p>Happy Fighting,<br/>
 Marshallate Secretary<br/>
 Kingdom of Ansteorra - Society for Creative Anachronism<br/>
 secretary@marshal.ansteorra.org<br/>
</p>
</div>
<?php $cardCount=1?>
<div class="cards">
    <div class="card">
        <div class="cardbox">
            <div class="cardboxheader">
                Kingdom of Ansteorra<br/>
                Martial Authorization
            </div>
        <dl>
            <dt>Legal Name</dt>
            <dd><?=h($member->first_name)?> <?=h($member->last_name)?></dd>
            <dt>Society Name</dt>
            <dd><?=h($member->sca_name)?></dd>
            <dt>SCA Branch</dt>
            <dd><?=h($member->branch->name)?></dd>
            <dt>SCA Membership</dt>
            <dd><?=h($member->membership_number)?> Expires:<?=h($member->membership_expires_on)?></dd>
            <dt>SCA Background Check</dt>
            <dd>
                <?php if($this->Time->isFuture($member->background_check_expires_on)) :?>
                    Current - Expires on : <?=h($member->background_check_expires_on)?>
                <?php else:?>
                    Expired
                <?php endif;?> 
            </dd>
        </dl>
        <?php if(count($member->authorizerForList) > 0 && count($member->authorizerForList) < 13):?>
            <hr>
            <div class="cardboxAuthorizingLabel">Authorizing Marshal for:</div>
            <table class='cardboxAuthorizing'>
                <?php $i = 0; ?>
                <?php foreach($participant->authorizerForList as $role):?>
                    <?php $i++; ?>
                    <?php if($i == 1):?>
                        <tr>
                    <?php endif;?>
                    <td><?=str_replace("Authorizing Marshal","",$role)?></td>
                    <?php if($i == 2):?>
                        </tr>
                        <?php $i= 0; ?>
                    <?php endif;?>
                <?php endforeach;?>
                <?php if($i == 1):?>
                    </tr>
                <?php endif;?>
            </table>
        <?php endif;?>
        </div>
    </div><?php if(count($participant->authorizerForList) > 12):?><div class="card">
        <?php $cardCount++?>
        <div class="cardbox">
            <div class="cardboxContent">
                <div class="cardboxAuthorizingLabel">Authorizing Marshal for:</div>
                <table class='cardboxAuthorizing'>
                    <?php $i = 0; ?>
                    <?php foreach($participant->roles as $role):?>
                        <?php $i++; ?>
                        <?php if($i == 1):?>
                            <tr>
                        <?php endif;?>
                        <td><?=str_replace("Authorizing Marshal","",$role)?></td>
                        <?php if($i == 2):?>
                            </tr>
                            <?php $i= 0; ?>
                        <?php endif;?>
                    <?php endforeach;?>
                    <?php if($i == 1):?>
                        </tr>
                    <?php endif;?>
                </table>
            </div>
        </div>
    </div><?php endif;?>
        <?php $group = ""; $authCount=0?>
        <?php foreach($member->authorizations as $auth):?>
            <?php if ($authCount == 0):?>
                <?php $cardCount = checkCardCount($cardCount);
                    $cardCount++;
                    $group = "";
                ?>
                <div class="card">
                    <div class="cardbox">
                        <div class="cardboxContent">
                            <div class="cardboxAuthorizationsLabel">Authorizations:</div>
                            <table class='cardboxAuthorizations'>
            <?php endif;?>
                                <?php $authCount++?>
                                <?php if ($group != $auth->authorization_type->authorization_group->name):?>
                                    <?php $group = $auth->authorization_type->authorization_group->name?>
                                    <?php $authCount++?>
                                    <tr><td colspan="2" class="cardboxAuthorizationsLabel"><hr/><?=$group?><hr/></td></tr>
                                <?php endif;?>
                                <tr>
                                    <td style="text-align:left"><?=$auth->authorization_type->name?></td>
                                    <td style="text-align:right"><?=$auth->expires_on?></td>
                                </tr>
            <?php if($authCount == 15):?>
                            </table>
                        </div>
                    </div>
                </div>
                <?php $authCount=0?>
            <?php endif;?>
        <?php endforeach;?>
        <?php if($authCount != 0):?>
                            </table>
                        </div>
                    </div>
                </div>
        <?php endif;?>
        <?php if($cardCount == 1):?>
            <div class="card">  
            <div class="cardbox">
                <div class="cardboxContent">
                    <h3>This card intentionally left blank.</h3>
                </div>
            </div>
        <?php endif;?>
    </div>
</div>
</body></html>