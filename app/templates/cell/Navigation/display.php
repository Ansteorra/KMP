<?php
$user = $this->request->getAttribute('identity');
echo $this->Kmp->appNav(
    $menu,
    $this->request,
    $user,
    $this->Html,
);