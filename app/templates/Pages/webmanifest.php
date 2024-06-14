{
"name": "<?= $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") ?> Authorization Card",
"short_name": "<?= $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") ?> Card",
"icons": [
{
"src": "<?= $this->Url->image($this->KMP->getAppSetting("KMP.BannerLogo", "badge.png")) ?>",
"sizes": "192x192",
"type": "image/png"
}
],
"theme_color": "<?= $this->KMP->getAppSetting("Member.MobileCard.ThemeColor", "gold") ?>",
"background_color": "<?= $this->KMP->getAppSetting("Member.MobileCard.BgColor", "gold") ?>",
"display": "standalone",
"lang": "en-US",
"start_url":"<?= $this->Url->build(["controller" => "Members", "action" => "ViewMobileCard", $mobile_token], ['fullBase' => true]) ?>"
}