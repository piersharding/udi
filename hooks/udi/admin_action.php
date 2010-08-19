<?php
$cfg = $udiconfig->getConfig();

// need to eliminate the two types of deletes first
$configuration_action = get_request('configuration');
switch ($configuration_action) {
case 'delete':
    $delete = get_request('delete');
    switch ($delete) {
        case 'base':
            $base_delete = (int)get_request('base');
            if ($base_delete > 0 && isset($cfg['search_bases'])) {
                $bases = explode(';', $cfg['search_bases']);
                if (count($bases) >= $base_delete) {
                    $base = $bases[$base_delete - 1];
                    array_splice($bases, $base_delete - 1, 1);
                    $udiconfig->setConfig('search_bases', implode(';', $bases));
                    $cfg = $udiconfig->updateConfig();
                    $request['page']->info(_('Search base deleted: ').$base);
                    break;
                }
            }
            break;
            
        case 'objectclass':
            $objectclass_delete = (int)get_request('objectclass');
            if ($objectclass_delete > 0 && isset($cfg['objectclasses'])) {
                $classes = $udiconfig->getObjectClasses();
                if (count($classes) >= $objectclass_delete) {
                    $class = $classes[$objectclass_delete - 1];
                    array_splice($classes, $objectclass_delete - 1, 1);
                    $udiconfig->updateObjectClasses($classes);
                    $request['page']->info(_('objectClass deleted: ').$class);
                    break;
                }
            }
            break;
    }
    break;

case 'backup':
    $confirm = get_request('confirm');
    if ($confirm == 'yes') {
        $cfg = $udiconfig->backupConfig();
        $request['page']->info(_('Configuration saved to backup'));
    }
    else if ($confirm == 'no') {
        $request['page']->info(_('Configuration backup cancelled'));
    }
    break;

case 'restore':
    $confirm = get_request('confirm');
    if ($confirm == 'yes') {
        $cfg = $udiconfig->restoreConfig();
        $request['page']->info(_('Configuration restored'));
    }
    else if ($confirm == 'no') {
        $request['page']->info(_('Configuration restore cancelled'));
    }
    break;

default:
    // get the config values posted
    $update = true;
    foreach (array('filepath', 'udi_version', 'enabled', 'ignore_deletes', 'ignore_creates', 'ignore_updates', 'move_on_delete', 'move_to', 'dir_match_on', 'import_match_on') as $config) {
        $cfg[$config] = get_request($config);
        // enabled is a checkbox
        if ($config == 'enabled' || $config == 'ignore_deletes' || $config == 'move_on_delete' || $config == 'ignore_creates' || $config == 'ignore_updates') {
            $udiconfig->setConfigCheckBox($config, $cfg[$config]);
        }
        else {
            $udiconfig->setConfig($config, $cfg[$config]);
        }
    }

    // get the search bases
    $bases = array();
    $no_bases = (int)get_request('no_of_bases');
    if ($no_bases > 0 && $no_bases <= 20) {
        foreach (range(1, $no_bases) as $i) {
            $base = get_request('search_base_'.$i);
            if (!empty($base)) {
                $bases[]= $base;
            }
        }
    }
    $base = get_request('new_base');
    if (!empty($base)) {
        $bases[]= $base;
    }
    $udiconfig->setConfig('search_bases', implode(';', $bases));
    
    // The create in bucket for new accounts
    $create_in = get_request('create_in');
    $udiconfig->setConfig('create_in', $create_in);
    
    // get the objectClasses
    $classes = array();
    $no_classes = (int)get_request('no_of_objectclasses');
    if ($no_classes > 0 && $no_classes <= 20) {
        foreach (range(1, $no_classes) as $i) {
            $class = get_request('objectclass_'.$i);
            if (!empty($class)) {
                $classes[]= $class;
            }
        }
    }
    $class = get_request('new_objectclass');
    if (!empty($class) && $class != 'none') {
        $classes[]= $class;
    }
    $udiconfig->setConfig('objectclasses', implode(';', $classes));
    
    
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
