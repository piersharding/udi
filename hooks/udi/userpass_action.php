<?php
$cfg = $udiconfig->getConfig();

// framework for different types of action - see admin_action
$configuration_action = get_request('userpass');
switch ($configuration_action) {
default:
    // get the config values posted
    $update = true;
    foreach (array('ignore_passwds', 'passwd_algo', 
                   'passwd_parameters', 'ignore_userids', 'userid_algo', 
                   'userid_parameters', 'encrypt_passwd', 'enable_kiosk_recover',
                   'enable_kiosk', 'passwd_reset_state') as $config) {
        $cfg[$config] = get_request($config);
        // enabled is a checkbox
        if (preg_match('/ignore_userids|ignore_passwds|enable_kiosk|enable_kiosk_recover|passwd_reset_state/', $config)) {
            $udiconfig->setConfigCheckBox($config, $cfg[$config]);
        }
        else {
            $udiconfig->setConfig($config, $cfg[$config]);
        }
    }

    // commit the config changes
    if ($udiconfig->validate(true)) {
        $cfg = $udiconfig->updateConfig();
        $request['page']->info(_('User Id and Passwd Configuration saved - <a href="cmd.php?cmd=purge_cache">please purge the cache</a>'));
    }
    else {
        $request['page']->warning(_('User Id and Passwd Configuration NOT saved'));
    }
    break;
}

?>
