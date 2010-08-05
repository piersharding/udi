<?php
$cfg = $udiconfig->getConfig();

// need to eliminate the two types of deletes first
$configuration_action = get_request('configuration');
switch ($configuration_action) {
case 'delete':
    $delete = (int)get_request('delete');
    if ($delete > 0 && isset($cfg['search_bases'])) {
        $bases = explode(';', $cfg['search_bases']);
        if (count($bases) >= $delete) {
            $base = $bases[$delete - 1];
            array_splice($bases, $delete - 1, 1);
            $udiconfig->setConfig('search_bases', implode(';', $bases));
            $cfg = $udiconfig->updateConfig();
            $request['page']->info(_('Search base deleted: ').$base);
            break;
        }
    }
    break;

case 'backup':
    $cfg = $udiconfig->backupConfig();
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
    foreach (array('filepath', 'udi_version', 'enabled', 'move_on_delete', 'move_to', 'dir_match_on', 'import_match_on') as $config) {
        $cfg[$config] = get_request($config);
        // enabled is a checkbox
        if ($config == 'enabled' || $config == 'move_on_delete') {
            $udiconfig->setConfigCheckBox($config, $cfg[$config]);
        }
        else {
            $udiconfig->setConfig($config, $cfg[$config]);
        }
    }

    // validate the file path - must exist
    if (preg_match('/^http/', $cfg['filepath'])) {
        $hdrs = get_headers($cfg['filepath']);
        if (!preg_match('/^HTTP.*? 200 .*?OK/', $hdrs[0])) {
            $request['page']->warning(_('Source import URL does not exist: ').$cfg['filepath'], _('configuration'));
        }
    } 
    else if (!file_exists($cfg['filepath'])) {
        $request['page']->warning(_('Source import file does not exist: ').$cfg['filepath'], _('configuration'));
    }
    
    // validate the target DN for moving deletes
    if ($cfg['move_on_delete'] !== null) {
        $update = check_dn_exists($cfg['move_to'], _('Target delete DN does not exist: ').$cfg['move_to']);
    }

    // get the search bases
    $bases = array();
    $no_bases = (int)get_request('no_of_bases');
    if ($no_bases > 0 && $no_bases <= 20) {
        foreach (range(1, $no_bases) as $i) {
            $base = get_request('search_base_'.$i);
            if (!empty($base)) {
                check_search_base($base) ? $bases[]= $base : $update = false;
            }
        }
        $base = get_request('new_base');
        if (!empty($base)) {
            check_search_base($base) ? $bases[]= $base : $update = false;
        }
    }
    $udiconfig->setConfig('search_bases', implode(';', $bases));

    // commit the config changes
    if ($update) {
        $cfg = $udiconfig->updateConfig();
        $request['page']->info(_('Configuration saved'));
    }
    else {
        $request['page']->warn(_('Configuration NOT saved'));
    }
    break;
}

function check_search_base($base) {
    // base does not exist
    return check_dn_exists($base, _('Search base DN does not exist: ').$base);
}

?>
