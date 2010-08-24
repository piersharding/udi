<?php
$cfg = $request['udiconfig'];
$socs = $app['server']->SchemaObjectClasses('login');

$configuration_action = get_request('configuration');
$confirm = get_request('confirm');

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
echo '<fieldset class="config-block"><legend>'._('User Id & Passwords control').'</legend>';
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
            
$result = udi_run_hook('userid_algorithm_label',array());
$algols = array();
if (!empty($result)) {
    foreach ($result as $algo) {
        $algols[$algo['name']]= $algo['title'];
    }
}                
$algo_default = isset($cfg['userid_algo']) ? $cfg['userid_algo'] : 'userid_alg_01';
if (isset($userid_algo_opts['disabled'])) {
    echo $request['page']->configEntry('userid_algo',  _('User Id algorithm:'), array('type' => 'text', 'value' => $algols[$algo_default], 'size' => 25, 'disabled' => 'disabled'), true, false);
    echo $request['page']->configEntry('userid_algo', '', array('type' => 'hidden', 'value' => $algo_default), false);
    echo $request['page']->configEntry('userid_parameters', '', array('type' => 'hidden', 'value' => $cfg['userid_parameters']), false);
}
else {
    // select algorythm
    echo $request['page']->configSelectEntryBasic('userid_algo', _('User Id algorithm:'), $algols, $algo_default, false);
    
}
// parameters to pass to plugin
echo $request['page']->configEntry('userid_parameters', _('User Id generation parameters:'), $userid_parameters_opts, true, false);

// Passwords
$ignore_passwds_opts = array('value' => 0, 'type' => 'checkbox');
$passwd_algo_opts = array('value' => 0, 'type' => 'checkbox');
$passwd_parameters_opts = array('type' => 'text', 'value' => $cfg['userid_parameters'], 'size' => 50);
if (isset($cfg['ignore_passwds']) && $cfg['ignore_passwds'] == 'checked') {
    $ignore_passwds_opts['checked'] = 'checked';
    $ignore_passwds_opts['value'] = 1;
    $passwd_algo_opts['disabled'] = 'disabled';
    $passwd_parameters_opts['disabled'] = 'disabled';
}
echo $request['page']->configEntry('ignore_passwds', _('No password processing:'), $ignore_passwds_opts, true, false);    
$result = udi_run_hook('passwd_algorithm_label',array());
$algols = array();
if (!empty($result)) {
    foreach ($result as $algo) {
        $algols[$algo['name']]= $algo['title'];
    }
}                
$algo_default = isset($cfg['passwd_algo']) ? $cfg['passwd_algo'] : 'passwd_alg_01';
if (isset($passwd_algo_opts['disabled'])) {
    echo $request['page']->configEntry('passwd_algo', _('Password algorithm:'), array('type' => 'text', 'value' => $algols[$algo_default], 'size' => 25, 'disabled' => 'disabled'), true, false);
    echo $request['page']->configEntry('passwd_algo', '', array('type' => 'hidden', 'value' => $algo_default), false);
    echo $request['page']->configEntry('passwd_parameters', '', array('type' => 'hidden', 'value' => $cfg['passwd_parameters']), false);
}
else {
    // select algorythm
    echo $request['page']->configSelectEntryBasic('passwd_algo', _('Password algorithm:'), $algols, $algo_default, false);
}
// parameters to pass to plugin
echo $request['page']->configEntry('passwd_parameters', _('Password generation parameters:'), $passwd_parameters_opts, true, false);
echo '</fieldset>';

// page save button
echo $request['page']->configEntry('submitbutton', '', array('type' => 'submit', 'value' => _('Save')), false);
echo '</div>'; // end of udiform
echo '<div class="udi_clear"></div>';
echo '</div>'; // end of center

?>
