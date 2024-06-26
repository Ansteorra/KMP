<?php
$user = $this->request->getAttribute('identity');
echo $this->Kmp->appNav(
    $menu,
    $user,
    $this->Html,
    $this->Url,
    $this->request->getSession()->read("navbarState")
);