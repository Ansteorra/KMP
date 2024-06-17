<?php
$user = $this->request->getAttribute('identity');
echo $this->Kmp->appNav(
    $menu,
    $user,
    $this->Html,
);