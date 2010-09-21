<?php

$adminuser = get_request('adminusername');
$adminpassword = get_request('adminpassword');
$username = get_request('username');
$newpassword = get_request('newpassword');
$confirm = get_request('confirm');

//var_dump($username);

kiosk_change_passwd($username, false, $newpassword, $confirm, $adminuser, $adminpassword);

?>
