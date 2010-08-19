<?php
$cfg = $udiconfig->getConfig();

// get all of the existing mappings
$cfg_mappings = $udiconfig->getMappings();
$cfg_group_mappings = $udiconfig->getGroupMappings();

//var_dump($_SERVER['REQUEST_METHOD']); var_dump($_GET); var_dump($_POST); var_dump($cfg_mappings); exit(0);
//var_dump($_SERVER['REQUEST_METHOD']); var_dump($_GET); var_dump($_POST); var_dump($cfg_group_mappings); exit(0);

// need to eliminate the two types of deletes first
$delete_action = get_request('delete');
switch ($delete_action) {
case 'mapping':
    $mapping = get_request('mapping');
    if (!empty($mapping)) {
        $pos = 0;
        foreach ($cfg_mappings as $map) {
            if ($map['source'] == $mapping) {
                // delete this mapping
                array_splice($cfg_mappings, $pos, 1);
                $cfg = $udiconfig->updateMappings($cfg_mappings);
                $request['page']->info(_('Entire source mapping deleted: ').$mapping);
                break;
            }
            $pos += 1;
        }
    }
    break;
case 'field_mapping':
    $field_mapping = get_request('field_mapping');
    if (!empty($field_mapping)) {
        list($mapping, $field) = explode(':', $field_mapping);
        if (!empty($mapping) && $field > 0) {
            $pos = 0;
            foreach ($cfg_mappings as $map) {
                if ($map['source'] == $mapping) {
                    // delete this field mapping
                    if (isset($cfg_mappings[$pos]['targets'][$field - 1])) {
                        $target = $cfg_mappings[$pos]['targets'][$field - 1];
                        unset($cfg_mappings[$pos]['targets'][$field - 1]);
                        $cfg = $udiconfig->updateMappings($cfg_mappings);
                        $request['page']->info(_('mapping target ').$target._(' deleted from source: ').$mapping);
                        break;
                    }
                }
                $pos += 1;
            }
        }
    }
    break;
case 'group_mapping':
    $mapping = get_request('group_mapping');
    if (!empty($mapping)) {
        $pos = 0;
        foreach ($cfg_group_mappings as $map) {
            if ($map['source'] == $mapping) {
                // delete this group mapping
                array_splice($cfg_group_mappings, $pos, 1);
                $cfg = $udiconfig->updateGroupMappings($cfg_group_mappings);
                $request['page']->info(_('Entire group membership source mapping deleted: ').$mapping);
                break;
            }
            $pos += 1;
        }
    }
    break;
case 'group_field_mapping':
    $field_mapping = get_request('group_field_mapping');
    if (!empty($field_mapping)) {
        list($mapping, $field) = explode(':', $field_mapping);
        if (!empty($mapping) && $field > 0) {
            $pos = 0;
            foreach ($cfg_group_mappings as $map) {
                if ($map['source'] == $mapping) {
                    // delete this group field mapping
                    if (isset($cfg_group_mappings[$pos]['targets'][$field - 1])) {
                        $target = $cfg_group_mappings[$pos]['targets'][$field - 1];
                        unset($cfg_group_mappings[$pos]['targets'][$field - 1]);
                        $cfg = $udiconfig->updateGroupMappings($cfg_group_mappings);
                        $request['page']->info(_('group membership mapping target ').$target._(' deleted from group: ').$mapping);
                        break;
                    }
                }
                $pos += 1;
            }
        }
    }
    break;

default:
    // this is a value create or update

    // process source/traget mappings
    $no_mappings = (int)get_request('no_of_mappings');
    $cfg_mappings = array();
    // do we have any mappings
    if ($no_mappings > 0) {
        // cycle through each source mapping
        foreach (range(1, $no_mappings) as $this_mapping) {
            $source = get_request('mapping_'.$this_mapping);
            if (empty($source)) {
                continue;
            }
            // do we have any target fields for this mapping
            $no_fields = (int)get_request('no_of_fields_in_mapping_'.$this_mapping);
            $targets = array();
            if ($no_fields > 0) {
                // cycle through each target field
                foreach (range(1, $no_fields) as $this_field) {
                    $target = get_request('mapping_'.$this_mapping.'_field_'.$this_field);
                    if (!empty($target) && $target != 'none') {
                        $targets []= $target;
                    }
                }
            }
            // did we add a target
            $target = get_request('new_mapping_'.$this_mapping);
            if (!empty($target) && $target != 'none') {
                $targets []= $target;
            }
            // collect this source
            $cfg_mappings []= array('source' => $source, 'targets' => $targets);
        }
        // did we add a new mapping source
        $source_field = get_request('new_mapping_field');
        $source = get_request('new_mapping_select');
        if (!empty($source_field)) {
            $source = $source_field;
        }
        if (!empty($source) && $source != 'none') {
            $cfg_mappings []= array('source' => $source, 'targets' => array());
        }
    }
    $cfg = $udiconfig->updateMappings($cfg_mappings);

    // process group membership mappings
    
    
    
    // WE NEED TO CHECK THAT THESE ARE REAL GROUPS eg. posixGroup objectClass XXX !!!!!!!!!!!!!!!
    
    
    
    $no_mappings = (int)get_request('no_of_group_mappings');
    $cfg_group_mappings = array();
    // do we have any mappings
    if ($no_mappings > 0) {
        // cycle through each source mapping
        foreach (range(1, $no_mappings) as $this_mapping) {
            $map = get_request('group_mapping_'.$this_mapping);
            if (empty($map)) {
                continue;
            }
            // do we have any target fields for this mapping
            $no_fields = (int)get_request('no_of_fields_in_group_mapping_'.$this_mapping);
            $targets = array();
            if ($no_fields > 0) {
                // cycle through each target field
                foreach (range(1, $no_fields) as $this_field) {
                    $target = get_request('group_mapping_'.$this_mapping.'_field_'.$this_field);
                    if (!empty($target)) {
                        // validate target here XXX
                        check_target_dn($target, $map) ? $targets []= $target :  false;
                    }
                }
            }
            // did we add a target
            $target = get_request('new_group_mapping_'.$this_mapping);
            if (!empty($target)) {
                // validate target here XXX
                check_target_dn($target, $map) ? $targets []= $target :  false;
            }
            // collect this source
            $cfg_group_mappings []= array('source' => $map, 'targets' => $targets);
        }
        // did we add a new mapping source
        $source = get_request('new_group_mapping');
        if (!empty($source)) {
            $cfg_group_mappings []= array('source' => $source, 'targets' => array());
        }
    }

    // groups_enabled is a checkbox
    $groups_enabled = get_request('groups_enabled');
    $udiconfig->setConfigCheckBox('groups_enabled', $groups_enabled);
    
    // group membership attribute
    $group_attr = get_request('group_attr');
    $udiconfig->setConfig('group_attr', $group_attr);
    
    // finally update all the config
    $cfg = $udiconfig->updateGroupMappings($cfg_group_mappings);
    $request['page']->info(_('Mappings saved'));
}


function check_target_dn($target, $map) {
    // base does not exist
    return check_dn_exists($target, _('Target DN does not exist: ').$target._(' for group: ').$map, 'mapping');
}
?>
