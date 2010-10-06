<?php
$cfg = $request['udiconfig'];
$socs = $app['server']->SchemaObjectClasses('user');

$configuration_action = get_request('configuration');
$confirm = get_request('confirm');
            
$result = udi_run_hook('userid_algorithm_label',array());
$userid_algols = array('' => '');
if (!empty($result)) {
    foreach ($result as $algo) {
        $userid_algols[$algo['name']]= htmlspecialchars($algo['title']);
    }
}                
$result = udi_run_hook('passwd_algorithm_label',array());
$passwd_algols = array('' => '');
if (!empty($result)) {
    foreach ($result as $algo) {
        $passwd_algols[$algo['name']]= htmlspecialchars($algo['title']);
    }
}                

$result = udi_run_hook('passwd_policy_algorithm_label',array());
$passwd_policy_algols = array();
if (!empty($result)) {
    foreach ($result as $algo) {
        $passwd_policy_algols[$algo['name']]= htmlspecialchars($algo['title']);
    }
}                


echo '<div class="center">';
echo '<div class="help">';
include 'userpass.help.php';
echo '</div>';
echo '<div class="udiform">';
echo '<div class="udi_clear"></div>';

/*
 * User Id and Password group
 */
// Create In bucket for new accounts - must be one of the search bases
echo '<fieldset class="config-block"><legend>'._('User Id Generation').'</legend>';
// UserId generation algorithms - list of hooks, and parameters to pass to hooks
// User Ids       
$ignore_userids_opts = array('value' => 0, 'type' => 'checkbox');
$userid_algo_opts = array('value' => 0, 'type' => 'checkbox');
$userid_parameters_opts = array('type' => 'text', 'value' => $cfg['userid_parameters'], 'size' => 50);
if (isset($cfg['ignore_userids']) && $cfg['ignore_userids'] == 'checked') {
    $ignore_userids_opts['checked'] = 'checked';
    $ignore_userids_opts['value'] = 1;
    $userid_algo_opts['disabled'] = 'disabled';
    $userid_parameters_opts['disabled'] = 'disabled';
}
echo $request['page']->configEntry('ignore_userids', _('No userid processing:'), $ignore_userids_opts, true, false);    
if (isset($userid_algo_opts['disabled'])) {
    echo $request['page']->configEntry('userid_algo',  _('User Id algorithm:'), array('type' => 'text', 'value' => $userid_algols[$cfg['userid_algo']], 'size' => 25, 'disabled' => 'disabled'), true, false);
    echo $request['page']->configEntry('userid_algo', '', array('type' => 'hidden', 'value' => $cfg['userid_algo']), false);
    echo $request['page']->configEntry('userid_parameters', '', array('type' => 'hidden', 'value' => $cfg['userid_parameters']), false);
}
else {
    // select algorythm
    echo $request['page']->configSelectEntryBasic('userid_algo', _('User Id algorithm:'), $userid_algols, $cfg['userid_algo'], false);
    
}
// parameters to pass to plugin
echo $request['page']->configEntry('userid_parameters', _('User Id generation parameters:'), $userid_parameters_opts, true, false);
echo '</fieldset>';

// Passwords
echo '<fieldset class="config-block"><legend>'._('Passwords Generation').'</legend>';
$ignore_passwds_opts = array('value' => 0, 'type' => 'checkbox');
$passwd_algo_opts = array('value' => 0, 'type' => 'checkbox');
$passwd_reset_state_opts = array('value' => 0, 'type' => 'checkbox');
$passwd_parameters_opts = array('type' => 'text', 'value' => $cfg['passwd_parameters'], 'size' => 50);
if (isset($cfg['ignore_passwds']) && $cfg['ignore_passwds'] == 'checked') {
    $ignore_passwds_opts['checked'] = 'checked';
    $ignore_passwds_opts['value'] = 1;
    $passwd_algo_opts['disabled'] = 'disabled';
    $passwd_parameters_opts['disabled'] = 'disabled';
    $passwd_reset_state_opts['disabled'] = 'disabled';
}
if (isset($cfg['passwd_reset_state']) && $cfg['passwd_reset_state'] == 'checked') {
    $passwd_reset_state_opts['checked'] = 'checked';
    $passwd_reset_state_opts['value'] = 1;
}

echo $request['page']->configEntry('ignore_passwds', _('No password processing:'), $ignore_passwds_opts, true, false);    
if (isset($passwd_algo_opts['disabled'])) {
    echo $request['page']->configEntry('passwd_algo', _('Password algorithm:'), array('type' => 'text', 'value' => $passwd_algols[$cfg['passwd_algo']], 'size' => 25, 'disabled' => 'disabled'), true, false);
    echo $request['page']->configEntry('passwd_algo', '', array('type' => 'hidden', 'value' => $cfg['passwd_algo']), false);
    echo $request['page']->configEntry('passwd_parameters', '', array('type' => 'hidden', 'value' => $cfg['passwd_parameters']), false);
}
else {
    // select algorythm
    echo $request['page']->configSelectEntryBasic('passwd_algo', _('Password algorithm:'), $passwd_algols, $cfg['passwd_algo'], false);
}
echo $request['page']->configEntry('passwd_parameters', _('Password generation parameters:'), $passwd_parameters_opts, true, false);
// how to encrypt the passwd value
$enc_methods = password_types();
if (isset($passwd_algo_opts['disabled'])) {
    echo $request['page']->configEntry('encrypt_passwd', _('Password ecryption:'), array('type' => 'text', 'value' => $cfg['encrypt_passwd'], 'size' => 5, 'disabled' => 'disabled'), true, false);
    echo $request['page']->configEntry('encrypt_passwd', '', array('type' => 'hidden', 'value' => $cfg['encrypt_passwd']), false);
}
else {
    // select algorythm
    echo $request['page']->configSelectEntryBasic('encrypt_passwd', _('Password ecryption:'), $enc_methods, $cfg['encrypt_passwd'], false);
}

if ($cfg['server_type'] == 'ad') {
    echo $request['page']->configEntry('passwd_reset_state', _('AD passwd reset on create:'), $passwd_reset_state_opts, true, false);
    if (isset($cfg['ignore_passwds']) && $cfg['ignore_passwds'] == 'checked') {
        if (isset($cfg['passwd_reset_state']) && $cfg['passwd_reset_state'] == 'checked') {
            echo $request['page']->configEntry('passwd_reset_state', '', array('type' => 'hidden', 'value' => 1), false);
        }
    }    
}
echo '</fieldset>';




// Password policy
echo '<fieldset class="config-block"><legend>'._('Kiosk Password Policy').'</legend>';
echo $request['page']->configSelectEntryBasic('passwd_policy_algo', _('Password policy algorithm:'), $passwd_policy_algols, $cfg['passwd_policy_algo'], false);
echo $request['page']->configEntry('passwd_policy_parameters', _('Policy parameters:'), array('type' => 'text', 'value' => htmlspecialchars($cfg['passwd_policy_parameters'], ENT_QUOTES), 'size' => 75), true, false);
echo '</fieldset>';

///*
// * Kiosk actions allowed
// */
//// Create In bucket for new accounts - must be one of the search bases
//echo '<fieldset class="config-block"><legend>'._('Password Change Kiosk Control').'</legend>';
//// UserId generation algorithms - list of hooks, and parameters to pass to hooks
//// User Ids       
////$enable_kiosk_opts = array('value' => 0, 'type' => 'checkbox');
//$enable_kiosk_recover_opts = array('value' => 0, 'type' => 'checkbox');
//if (isset($cfg['enable_kiosk_recover']) && $cfg['enable_kiosk_recover'] == 'checked') {
//    $enable_kiosk_recover_opts['checked'] = 'checked';
//    $enable_kiosk_recover_opts['value'] = 1;
//}
////if (isset($cfg['enable_kiosk']) && $cfg['enable_kiosk'] == 'checked') {
////    $enable_kiosk_opts['checked'] = 'checked';
////    $enable_kiosk_opts['value'] = 1;
////}
////else {
////    $enable_kiosk_recover_opts['disabled'] = 'disabled';
////}    
////echo $request['page']->configEntry('enable_kiosk', _('Enable Kiosk:'), $enable_kiosk_opts, true, false);    
//echo $request['page']->configEntry('enable_kiosk_recover', _('Enable Password Recovery:'), $enable_kiosk_recover_opts, true, false);
////if (isset($enable_kiosk_recover_opts['disabled']) && isset($enable_kiosk_recover_opts['checked'])){
////    echo $request['page']->configEntry('enable_kiosk_recover', '', array('type' => 'hidden', 'value' => $enable_kiosk_recover_opts['value']), false);
////}
//echo '</fieldset>';


// page save button
echo $request['page']->configEntry('submitbutton', '', array('type' => 'submit', 'value' => _('Save')), false);
echo '</div>'; // end of udiform
echo '<div class="udi_clear"></div>';
echo '</div>'; // end of center

?>
