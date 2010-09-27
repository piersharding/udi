<?php

$adminuser = get_request('adminusername');
$adminpassword = get_request('adminpassword');
$username = get_request('username');
$newpassword = get_request('newpassword');
$confirm = get_request('confirm');
$username = kiosk_clean_value($username, true);
$adminuser = kiosk_clean_value($adminuser, true);

// admin user must exist
$query = $app['server']->query(array('base' => $udiconfig->getBaseDN(), 'filter' => "(|(mlepUsername=".$adminuser.")(uid=".$adminuser.")(sAMAccountName=".$adminuser."))"), 'anon');
if (empty($query)) {
//    $request['page']->error(_('Administration User could not be found'), 'Password Reset');
    $dn = $adminuser;
}
else {
    // stash the DN for the actual update
    $query = array_shift($query);
    $dn = $query['dn'];
}
kiosk_change_passwd($username, false, $newpassword, $confirm, $dn, $adminpassword);
?>
