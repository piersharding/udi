<?php
$cfg = $udiconfig->getConfig();

// framework for different types of action - see admin_action
$configuration_action = get_request('userpass');
switch ($configuration_action) {
default:
    // get the config values posted
    $update = true;
    foreach (array('ignore_passwds', 'passwd_algo', 
                   'passwd_parameters', 'ignore_userids', 'userid_algo', 'userid_parameters') as $config) {
        $cfg[$config] = get_request($config);
        // enabled is a checkbox
        if ($config == 'ignore_userids' || 
            $config == 'ignore_passwds') {
            $udiconfig->setConfigCheckBox($config, $cfg[$config]);
        }
        else {
            $udiconfig->setConfig($config, $cfg[$config]);
        }
    }

    // commit the config changes
    if ($udiconfig->validate()) {
        $cfg = $udiconfig->updateConfig();
        $request['page']->info(_('Configuration saved'));
    }
    else {
        $request['page']->warning(_('Configuration NOT saved'));
    }
    break;
}

?>