<?php
$cfg = $request['udiconfig'];
$action = (get_request('cancel') ? 'cancel' : '').
          (get_request('process') ? 'process' : '').
          (get_request('validate') ? 'validate' : '').
          (get_request('reactivate') ? 'reactivate' : '').
          (get_request('delete') ? 'delete' : '');
          $confirm = get_request('confirm');
if ($action == 'reactivate' && empty($confirm)) {
    // output the reactivation confirmation page
    echo $request['page']->confirmationPage(_('Distinguished Name'), 
                                            _('Restore users from '), 
                                            $cfg['move_to'], 
                                            _('Are you sure you want to restore the deactivated users?'), 
                                            _('Restore'), 
                                            array('server_id' => $app['server']->getIndex(), 'cmd' => 'udi', 'udi_nav' => $udi_nav, 'reactivate' => 'reactivate'));
}
else if ($action == 'delete' && empty($confirm)) {
    // output the deletion confirmation page
    echo $request['page']->confirmationPage(_('Distinguished Name'), 
                                            _('Delete users completely from '), 
                                            $cfg['move_to'], 
                                            _('Are you sure you want to completely delete the deactivated users?'), 
                                            _('Delete'), 
                                            array('server_id' => $app['server']->getIndex(), 'cmd' => 'udi', 'udi_nav' => $udi_nav, 'delete' => 'delete'));
}
else {
    echo '<div class="center">';
    echo '<div class="help">';
    include 'process.help.php';
    echo '</div>';
    echo '<div class="udiform">';
    echo '<span style="white-space: nowrap;">';
    echo '<div class="tools-right">';
    if ((!isset($cfg['ignore_deletes']) || $cfg['ignore_deletes'] != 'checked') && $cfg['move_to']) {
        $children = $app['server']->getContainerContents($cfg['move_to'], 'user', 0, '(objectClass=*)', LDAP_DEREF_NEVER);
        if (count($children) > 0) {
            echo '<a href="" title="'._('Restore users').'" onclick="post_to_url(\'cmd.php\', {\'reactivate\': \'reactivate\'}); return false;"><img src="images/udi/import-big.png" alt="'._('Restore users').'"/> &nbsp; '._('Restore users').'</a>';
            echo '&nbsp; ';
            echo '<a href="" title="'._('Completely delete users').'" onclick="post_to_url(\'cmd.php\', {\'delete\': \'delete\'}); return false;"><img src="images/udi/trash-big.png" alt="'._('Completely delete users').'"/> &nbsp; '._('Completely delete users').'</a>';
        }
    }
    echo '</div>';
    echo '</span>';
    echo '<div class="udi_clear"></div>';
    
    // is the import enabled
    $enabled_opts = array('value' => 0, 'type' => 'checkbox');
    if (isset($cfg['enabled']) && $cfg['enabled'] == 'checked') {
        $enabled_opts['checked'] = 'checked';
        $enabled_opts['value'] = 1;
    }
    $enabled_opts['disabled'] = 'disabled';
    echo $request['page']->configEntry('enabled', _('Processing Enabled:'), $enabled_opts, true, false);
    
    // file to be processed
    echo '<fieldset class="config-block"><legend>'._('File control').'</legend>';
    if (isset($_SESSION['udi_import_file'])) {
        echo $request['page']->configEntry('filesize', _('Upload file size:'), array('type' => 'text', 'value' => count($_SESSION['udi_import_file']['contents']).'&nbsp;'._('rows'), 'size' => 10, 'disabled' => 'disabled'), true, false);
    }
    else {
        echo $request['page']->configEntry('filepath', _('File path:'), array('type' => 'text', 'value' => $cfg['filepath'], 'size' => 75, 'disabled' => 'disabled'), true, false);
    }
    
    // matching attributes - file to directory
    $socs = $app['server']->SchemaObjectClasses('user');
    $mlepPerson = $socs['mlepperson'];
    $must = $mlepPerson->getMustAttrs();
    $may = $mlepPerson->getMayAttrs();
    $imo_attrs = array_merge($must, $may);
    $imo_default = isset($cfg['import_match_on']) ? $cfg['import_match_on'] : 'mlepsmspersonid';
    echo $request['page']->configEntry('import_match_on', _('Match from Import on:'), array('type' => 'text', 'value' => $imo_default, 'size' => 50, 'disabled' => 'disabled'), true, false);
    
    $dmo_default = isset($cfg['dir_match_on']) ? $cfg['dir_match_on'] : 'mlepsmspersonid';
    $dmo_attrs = $app['server']->SchemaAttributes('user');
    echo $request['page']->configEntry('dir_match_on', _('Match to Directory on:'), array('type' => 'text', 'value' => $dmo_default, 'size' => 50, 'disabled' => 'disabled'), true, false);
    
    // Allow for multiple search bases
    $field = '<table class="item-list">';
    $no_bases = 0;
    if (isset($cfg['search_bases'])) {
        $bases = explode(';', $cfg['search_bases']);
        foreach ($bases as $base) {
            $no_bases += 1;
            $field .= '<tr><td><span style="white-space: nowrap;">'.$request['page']->configField('search_base_'.$no_bases, array('type' => 'text', 'value' => $base, 'size' => 50, 'disabled' => 'disabled'), array());
            $field .= '</span></td></tr>';
        }
    }
    $field .= '</table>';
    echo $request['page']->configRow($request['page']->configFieldLabel('search_bases', _('Search bases for users:')), $field, false);
    // Roles to process
    $roles = '<table class="item-list">';
    foreach (array('Student', 'TeachingStaff', 'NonTeachingStaff', 'ParentCaregiver', 'Alumni') as $role) {
        $role_opts = array('value' => 0, 'type' => 'checkbox', 'disabled' => 'disabled');
        if (isset($cfg['process_role_'.$role]) && $cfg['process_role_'.$role] == 'checked') {
            $role_opts['checked'] = 'checked';
            $role_opts['value'] = 1;
        }
        $roles .= '<tr><td><span style="white-space: nowrap;">'.
                  $request['page']->configField('process_role_'.$role, $role_opts, array()).$role.'</span></td></tr>';
    }
    $roles .= '</table>';
    echo $request['page']->configRow($request['page']->configFieldLabel('process_roles', _('Which mlepRoles to Process:')), $roles, false);
    $strict_checks_opts = array('value' => 0, 'type' => 'checkbox', 'disabled' => 'disabled');
    if (isset($cfg['strict_checks']) && $cfg['strict_checks'] == 'checked') {
        $strict_checks_opts['checked'] = 'checked';
        $strict_checks_opts['value'] = 1;
    }
    echo $request['page']->configEntry('strict_checks', _('Apply strict checks:'), $strict_checks_opts, true, false);
    
    echo '</fieldset>';
    
    // ignore account creations
    echo '<fieldset class="config-block"><legend>'._('Account creation').'</legend>';
    $ignore_creates_opts = array('value' => 0, 'type' => 'checkbox');
    $create_in_opts = array('type' => 'text', 'value' => $cfg['create_in'], 'size' => 50);
    if (isset($cfg['ignore_creates']) && $cfg['ignore_creates'] == 'checked') {
        $ignore_creates_opts['checked'] = 'checked';
        $ignore_creates_opts['value'] = 1;
        $create_in_opts['disabled'] = 'disabled';
    }
    $ignore_creates_opts['disabled'] = 'disabled';
    $create_in_opts['disabled'] = 'disabled';
    echo $request['page']->configEntry('ignore_creates', _('Ignore account creations:'), $ignore_creates_opts, true, false);    
    
    // where to create new accounts
    $field = '<div class="felement ftext"><span style="white-space: nowrap;">';
    $field .= $request['page']->configField('create_in', $create_in_opts, array());
    if (isset($create_in_opts['disabled'])) {
        echo $request['page']->configEntry('create_in', '', array('type' => 'hidden', 'value' => $cfg['create_in']), false);
    }
    $field .= '</span></div>';
    echo $request['page']->configRow($request['page']->configFieldLabel('create_in', _('Create new accounts in (with base):')), $field, (isset($create_in_opts['disabled']) ? false : true));
    
    // DN attribute
    $dn_default = isset($cfg['dn_attribute']) ? $cfg['dn_attribute'] : 'cn';
    echo $request['page']->configEntry('dn_attribute', _('DN Attribute:'), array('type' => 'text', 'value' => $dn_default, 'size' => 5, 'disabled' => 'disabled'), true, false);
    
    $no_classes = 0;
    $class = '<div class="felement ftext"><table class="item-list-config">';
    if (isset($cfg['objectclasses'])) {
        $classes = $udiconfig->getObjectClasses();
        foreach ($classes as $oc) {
            $no_classes += 1;
            $class .= '<tr><td>';
            $class .= $request['page']->configField('objectclass_'.$no_classes, array('type' => 'text', 'value' => $oc, 'size' => 30, 'disabled' => 'disabled'), array());
            $class .= $request['page']->configEntry('objectclass_'.$no_classes, '', array('type' => 'hidden', 'value' => $oc), false);
            $class .= '</td></tr>';
        }
    }
    $class .= '</table></div>';
    
    echo $request['page']->configRow(
                $request['page']->configFieldLabel(
                                    'objectclasses',
                                    _('objectClasses for account creation:') 
                                    ), 
                $class, 
                (isset($create_in_opts['disabled']) ? false : true));
    
                
                
    if (!empty($cfg['container_mappings'])) {
        $container_mappings = explode(';', $cfg['container_mappings']);
        $no_mappings = 0;
        echo '<p class="shrink">'._('Map groups to containers:').'</p>';
        foreach ($container_mappings as $map) {
            // break the mapping into source and targets
            list($group, $target) = explode('|', $map);
            // ignore broken mappings
            if (empty($group)) {
                continue;
            }
            $no_mappings += 1;
            $field = $request['page']->configField('container_mapping_'.$no_mappings.'_target', array('type' => 'text', 'value' => $target, 'size' => 50, 'disabled' => 'disabled'), array());
            echo $request['page']->configRow(
                        $request['page']->configFieldLabel(
                                            'container_mapping_'.$no_mappings, 
                                            $request['page']->configField(
                                                                'container_mapping_'.$no_mappings, 
                                                                array('type' => 'text', 'value' => $group, 'size' => 13, 'disabled' => 'disabled'), array())
                                            ), 
                        '<div class="felement_free ftext">'.$field.'</div>', 
                        false);
        }
    }
    echo '</fieldset>';
        
    // ignore account updates
    echo '<fieldset class="config-block"><legend>'._('Account updates').'</legend>';
    $ignore_updates_opts = array('value' => 0, 'type' => 'checkbox');
    if (isset($cfg['ignore_updates']) && $cfg['ignore_updates'] == 'checked') {
        $ignore_updates_opts['checked'] = 'checked';
        $ignore_updates_opts['value'] = 1;
    }
    $ignore_updates_opts['disabled'] = 'disabled';
    echo $request['page']->configEntry('ignore_updates', _('Ignore account updates:'), $ignore_updates_opts, true, false);  
    $ignore_membership_opts = array('value' => 0, 'type' => 'checkbox', 'disabled' => 'disabled');
    if (isset($cfg['ignore_membership_updates']) && $cfg['ignore_membership_updates'] == 'checked') {
        $ignore_membership_opts['checked'] = 'checked';
        $ignore_membership_opts['value'] = 1;
    }    echo $request['page']->configEntry('ignore_membership_updates', _('Ignore membership updates:'), $ignore_membership_opts, true, false);
    echo '</fieldset>';
        
    
    // do we move deletes or flag them
    echo '<fieldset class="config-block"><legend>'._('Account deletion').'</legend>';
    $ignore_deletes_opts = array('value' => 0, 'type' => 'checkbox');
    $move_on_delete_opts = array('value' => 0, 'type' => 'checkbox');
    $move_to_opts = array('type' => 'text', 'value' => $cfg['move_to'], 'size' => 50);
    if (isset($cfg['ignore_deletes']) && $cfg['ignore_deletes'] == 'checked') {
        $ignore_deletes_opts['checked'] = 'checked';
        $ignore_deletes_opts['value'] = 1;
    }
    if (isset($cfg['move_on_delete']) && $cfg['move_on_delete'] == 'checked') {
        $move_on_delete_opts['checked'] = 'checked';
        $move_on_delete_opts['value'] = 1;
    }
    $ignore_deletes_opts['disabled'] = 'disabled';
    $move_on_delete_opts['disabled'] = 'disabled';
    $move_to_opts['disabled'] = 'disabled';
    echo $request['page']->configEntry('ignore_deletes', _('Ignore account deletes:'), $ignore_deletes_opts, true, false);    
    echo $request['page']->configEntry('move_on_delete', _('Move accounts on Delete:'), $move_on_delete_opts, true, false);
    // where do we move deletes to
    echo $request['page']->configEntry('move_to', _('Move deleted to(with base):'), $move_to_opts, true, false);
    echo '</fieldset>';
    
    
    // end of page stuff - buttons and so on
    echo '<div class="fitem">';
    echo '<div class="ftitle">&nbsp;</div>';
    
    echo '<div class="felement">';
    if ($cfg['enabled']) {
        echo $request['page']->configField('validate', array('type' => 'submit', 'value' => _('Validate data')), array());
        if (isset($_SESSION['udi_import_file'])) {
            echo $request['page']->configField('process', array('type' => 'submit', 'value' => _('Process upload file')), array());
            echo '&nbsp; &nbsp;'.$request['page']->configField('cancel', array('type' => 'submit', 'value' => _('Cancel')), array());
        }
        else {
            echo $request['page']->configField('process', array('type' => 'submit', 'value' => _('Process standard configuration')), array());
        }
    }
    echo '</div>'; // end of felement
    echo '</div>'; // end of fitem
    
    echo '</div>'; // end of udiform
    echo '<div class="udi_clear"></div>';
    echo '</div>'; // end of center
}
?>
