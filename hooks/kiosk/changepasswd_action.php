<?php

$username = get_request('username');
$oldpassword = get_request('oldpassword');
$newpassword = get_request('newpassword');
$confirm = get_request('confirm');

//var_dump($username);

kiosk_change_passwd($username, $oldpassword, $newpassword, $confirm);

?>
