<?php
$user = $this->request->getAttribute('identity');
$navbarState = $this->request->getSession()->read("navbarState");
if ($navbarState === null) {
    $navbarState = [];
}
echo $this->Kmp->appNav(
    $menu,
    $user,
    $navbarState
);