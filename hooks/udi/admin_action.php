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
            
        case 'container_mapping':
            $container_mapping = get_request('container_mapping');
            $mappings = $udiconfig->getContainerMappings();
            if (!empty($container_mapping) && !empty($mappings)) {
                $pos = 0;
                foreach ($mappings as $map) {
                    if ($map['source'] == $container_mapping) {
                        // delete this group mapping
                        array_splice($mappings, $pos, 1);
                        $cfg = $udiconfig->updateContainerMappings($mappings);
                        $cfg = $udiconfig->updateConfig();
                        $request['page']->info(_('Container mapping deleted: ').$container_mapping);
                        break;
                    }
                    $pos += 1;
                }
            }
            break;
            
        case 'ignore_attrs':
            $attr_delete = (int)get_request('ignore_attrs');
            if ($attr_delete > 0 && isset($cfg['ignore_attrs'])) {
                $attrs = $udiconfig->getIgnoreAttrs();
                if (count($attrs) >= $attr_delete) {
                    $attr = $attrs[$attr_delete - 1];
                    array_splice($attrs, $attr_delete - 1, 1);
                    $udiconfig->updateIgnoreAttrs($attrs);
                    $request['page']->info(_('Ignore update attribute deleted: ').$attr);
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
    
    foreach (array('filepath', 'udi_version', 'enabled', 'ignore_deletes', 
                   'ignore_creates', 'ignore_updates', 'move_on_delete',  
                   'enable_reporting', 'reportpath', 'reportemail',
                   'move_to', 'dir_match_on', 'import_match_on', 
                   'dn_attribute', 'ignore_membership_updates',
                    'process_role_Student',
                    'process_role_TeachingStaff',
                    'process_role_NonTeachingStaff',
                    'process_role_ParentCaregiver',
                    'process_role_Alumni',
                    'strict_checks',
                    ) as $config) {
        $cfg[$config] = get_request($config);
        // enabled is a checkbox
        if ($config == 'enabled' || $config == 'ignore_deletes' || $config == 'move_on_delete' || 
            $config == 'ignore_creates' || $config == 'ignore_updates' || $config == 'enable_reporting' ||
            $config == 'strict_checks' ||
            $config == 'ignore_membership_updates' ||
            $config == 'process_role_Student' ||
            $config == 'process_role_TeachingStaff' ||
            $config == 'process_role_NonTeachingStaff' ||
            $config == 'process_role_ParentCaregiver' ||
            $config == 'process_role_Alumni'
            ) {
            $udiconfig->setConfigCheckBox($config, $cfg[$config]);
        }
        else {
            $udiconfig->setConfig($config, $cfg[$config]);
        }
    }
//    var_dump($cfg);

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

    
    $no_mappings = (int)get_request('no_of_container_mappings');
    $cfg_container_mappings = array();
    // do we have any mappings
    if ($no_mappings > 0) {
        // cycle through each source mapping
        foreach (range(1, $no_mappings) as $this_mapping) {
            $map = get_request('container_mapping_'.$this_mapping);
            if (empty($map)) {
                continue;
            }
            $target = get_request('container_mapping_'.$this_mapping.'_target');
            if (empty($target)) {
                continue;
            }
            // collect this source
            $cfg_container_mappings []= array('source' => $map, 'target' => $target);
        }
    }
    // did we add a new mapping source
    $source = get_request('new_container_mapping');
    if (!empty($source)) {
        $cfg_container_mappings []= array('source' => $source, 'target' => '');
    }
    
    // get the ignore attributes
    $attrs = array();
    $no_attrs = (int)get_request('no_of_ignore_attrs');
    if ($no_attrs > 0 && $no_attrs <= 20) {
        foreach (range(1, $no_attrs) as $i) {
            $attr = get_request('ignore_attrs_'.$i);
            if (!empty($attr)) {
                $attrs[]= $attr;
            }
        }
    }
    $attr = get_request('new_ignore_attrs');
    if (!empty($attr) && $attr != 'none') {
        $attrs[]= $attr;
    }
    $udiconfig->setConfig('ignore_attrs', implode(';', $attrs));
    
    // finally update all the config
    $cfg = $udiconfig->updateContainerMappings($cfg_container_mappings);
    
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
