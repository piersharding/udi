<?php
$username = get_request('username');
$newpassword = get_request('newpassword');
$confirm = get_request('confirm');
$token = get_request('token');
$server_id = get_request('server_id');

// we have gone for the password change
if (!empty($token)) {
    $request_user = kiosk_verify_token($token, true);
    if (!empty($request_user)) {
        if (trim($username) != $request_user['username']) {
            $request['page']->error(_('Authentication token invalid mismatch - please resubmit request'), 'Password Recovery');
        }
        else {
            $admin_user = $app['server']->getValue('login','kiosk_bind_id');
            $admin_passwd = $app['server']->getValue('login','kiosk_bind_pass');
            if (!kiosk_change_passwd($username, false, $newpassword, $confirm, $admin_user, $admin_passwd)) {
                $request['page']->error(_('Password change failed - please resubmit request'), 'Password Recovery');
            }
        }
    }
    $method = "http";
    if ( $_SERVER['HTTPS'] ) { $method .= "s"; }
    $server_name = $_SERVER['SERVER_NAME'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $reset_url = $method."://".$server_name.$script_name."?cmd=recoverpasswd";
    header("Location: $reset_url");
    die();
}

$email = get_request('email');
//var_dump($username);
kiosk_recover_passwd($username, $email);
?>
