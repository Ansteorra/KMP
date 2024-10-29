{
"name": "<?= $this->KMP->getAppSetting("KMP.ShortSiteTitle") ?> Authorization Card",
"short_name": "<?= $this->KMP->getAppSetting("KMP.ShortSiteTitle") ?> Card",
"icons": [
{
"src": "<?= $this->Url->image($this->KMP->getAppSetting("KMP.BannerLogo")) ?>",
"sizes": "192x192",
"type": "image/png"
}
],
"theme_color": "<?= $this->KMP->getAppSetting("Member.MobileCard.ThemeColor") ?>",
"background_color": "<?= $this->KMP->getAppSetting("Member.MobileCard.BgColor") ?>",
"display": "standalone",
"lang": "en-US",
"start_url":"<?= $this->Url->build(["controller" => "Members", "action" => "ViewMobileCard", $mobile_token]) ?>"
}