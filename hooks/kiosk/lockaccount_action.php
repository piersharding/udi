<?php
$confirm = get_request('confirm');
$action = get_request('action');
$adminuser = get_request('adminusername');
$adminpassword = get_request('adminpassword');
$username = get_request('username');
$dn = get_request('dn');

// must be a valid action
if (!in_array($action, array('deactivate', 'reactivate'))) {
    $action = null;
}

if ($confirm) {
    if ($confirm == 'yes') {
        // do the toggle
        if (empty($dn) || empty($adminuser) || empty($action)) {
            $request['page']->error(_('Invalid request'));
        }
        else {
            $adminpassword = $_SESSION['kioskadminpass'];
            unset($_SESSION['kioskadminpass']);
            $admindn = kiosk_check_admin($adminuser);
            if (empty($admindn)) {
                $_SESSION['sysmsg'] = array();
                $admindn = $adminuser;
            }
            if (empty($admindn) || empty($adminpassword) || empty($dn)) {
                $request['page']->error(_('Invalid request'));
            }
            else {
                kiosk_toggle_user($dn, $admindn, $adminpassword, $action);
            }
        }
    }
    else if($confirm == 'no') {
        // else it's a cancel
        $request['page']->info(_('Request cancelled'));
    }
}
else {
    // this is the first post
    // admin user must exist
    $admindn = kiosk_check_admin($adminuser);
    if (empty($admindn)) {
        $_SESSION['sysmsg'] = array();
        $admindn = $adminuser;
    }
    $user = kiosk_check_user_active($username, $admindn, $adminpassword);
    if (!empty($admindn) && !empty($user)) {
        $dn = $user['dn'];
        // stash the password
        $_SESSION['kioskadminpass'] = $adminpassword;
        // do the confirm loop
        $confirmnow = true;
    }
}



?>
