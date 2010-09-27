<?php

$username = get_request('username');
$oldpassword = get_request('oldpassword');
$newpassword = get_request('newpassword');
$confirm = get_request('confirm');
$username = kiosk_clean_value($username, true);
    
// ensure that this is not an existing logged in account
if ($app['server']->isLoggedIn('user')) {
    $app['server']->logout('user');
}
$adminuser = $app['server']->getValue('login','kiosk_bind_id');
$adminpass = $app['server']->getValue('login','kiosk_bind_pass');
kiosk_change_passwd($username, $oldpassword, $newpassword, $confirm, $adminuser, $adminpass);

?>
