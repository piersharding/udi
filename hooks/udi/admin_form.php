<?php
$cfg = $request['udiconfig'];
$socs = $app['server']->SchemaObjectClasses('user');

$configuration_action = get_request('configuration');
$confirm = get_request('confirm');

if ($configuration_action == 'restore' && empty($confirm)) {
    // output the confirmation page
    echo $request['page']->confirmationPage(_('Distinguished Name'), 
                                            _('Backup DN'), 
                                            $udiconfig->getConfigBackupDN(), 
                                            _('Are you sure you want to restore the configuration backup?'), 
                                            _('Restore'), 
                                            array('server_id' => $app['server']->getIndex(), 'cmd' => 'udi', 'udi_nav' => $udi_nav, 'configuration' => 'restore'));
}
else if ($configuration_action == 'backup' && empty($confirm)) {
    // output the confirmation page
    echo $request['page']->confirmationPage(_('Distinguished Name'), 
                                            _('Backup DN'), 
                                            $udiconfig->getConfigDN(), 
                                            _('Are you sure you want to backup the configuration?'), 
                                            _('Backup'), 
                                            array('server_id' => $app['server']->getIndex(), 'cmd' => 'udi', 'udi_nav' => $udi_nav, 'configuration' => 'backup'));
}
else {
    echo '<div class="center">';
//    echo '<div id="help" class="help_hide">';
    echo '<div id="help" class="help">';
    include 'admin.help.php';
    echo '</div>';
//    echo '<div id="udiform" class="udiform_full">';
    echo '<div id="udiform" class="udiform">';
    echo '<span style="white-space: nowrap;">';
    echo '<div class="tools-right">';
    echo '<a href="" title="'._('Backup Configuration').'" onclick="post_to_url(\'cmd.php\', {\'configuration\': \'backup\'}); return false;"><img src="images/udi/export.png" alt="'._('Backup Configuration').'"/> &nbsp; '._('Backup Configuration').'</a>';
    $query = $app['server']->query(array('base' => $udiconfig->getConfigBackupDN(), 'attrs' => array('dn')), 'user');
    if (!empty($query)) {
        // base does not exist
        echo ' &nbsp; ';
        echo '<a href="" title="'._('Restore Configuration').'" onclick="post_to_url(\'cmd.php\', {\'configuration\': \'restore\'}); return false;"><img src="images/udi/import-big.png" alt="'._('Restore Configuration').'"/> &nbsp; '._('Restore Configuration').'</a>';
    }
//    echo ' &nbsp; ';
//    echo '<a href="" title="'._('Configuration help').'" onclick="h = document.getElementById(\'help\'); f = document.getElementById(\'udiform\'); if (h.className == \'help_hide\') {f.className = \'udiform\'; h.className = \'help\';} else {h.className = \'help_hide\'; f.className = \'udiform_full\';}; return false;"><img src="images/udi/help-small.png" alt="'._('Configuration help').'"/> &nbsp;'._('help').'</a>';
    echo '</div>';
    echo '</span>';
    echo '<div class="udi_clear"></div>';
    
    /*
     * Basic Data configuration group
     */
    // the configured version
    echo '<fieldset class="config-block"><legend>'._('Basic data').'</legend>';
    echo $request['page']->configEntry('udi_version', _('Configuration version:'), array('type' => 'text', 'value' => $cfg['udi_version'], 'disabled' => 'disabled'), true, false);
    echo $request['page']->configEntry('udi_version', '', array('type' => 'hidden', 'value' => $cfg['udi_version']), false);
    
    // is the import enabled
    $enabled_opts = array('value' => 0, 'type' => 'checkbox');
    if (isset($cfg['enabled']) && $cfg['enabled'] == 'checked') {
        $enabled_opts['checked'] = 'checked';
        $enabled_opts['value'] = 1;
    }
    echo $request['page']->configEntry('enabled', _('Processing Enabled:'), $enabled_opts, true, false);
    echo '</fieldset>';

    /*
     * File control configuration group
     */
    // file path for the UDI import
    echo '<fieldset class="config-block"><legend>'._('File control').'</legend>';
    echo $request['page']->configEntry('filepath', _('File path:'), array('type' => 'text', 'value' => $cfg['filepath'], 'size' => 75));
    
    $objectclasses = array_merge(array("none" => new ObjectClass_ObjectClassAttribute("", "")), $socs);
    $mlepPerson = $socs['mlepperson'];
    $must = $mlepPerson->getMustAttrs();
    $may = $mlepPerson->getMayAttrs();
    $imo_attrs = array_merge($must, $may);
    $imo_default = isset($cfg['import_match_on']) ? $cfg['import_match_on'] : 'mlepsmspersonid';
    echo $request['page']->configSelectEntry('import_match_on', _('Match from Import on:'), $imo_attrs, $imo_default);
    
    $dmo_default = isset($cfg['dir_match_on']) ? $cfg['dir_match_on'] : 'mlepsmspersonid';
    $dmo_attrs = $app['server']->SchemaAttributes('user');
    echo $request['page']->configSelectEntry('dir_match_on', _('Match to Directory on:'), $dmo_attrs, $dmo_default);
    
    // Allow for multiple search bases
    $field = '<table class="item-list">';
    $no_bases = 0;
    if (isset($cfg['search_bases'])) {
        $bases = explode(';', $cfg['search_bases']);
        foreach ($bases as $base) {
            if (empty($base)) {
                continue;
            }
            $no_bases += 1;
            $field .= '<tr><td><span style="white-space: nowrap;">'.$request['page']->configField('search_base_'.$no_bases, array('type' => 'text', 'value' => $base, 'size' => 50), array());
            $field .= '&nbsp;<a href="" title="'._('Delete search base').'" onclick="post_to_url(\'cmd.php\', {\'configuration\': \'delete\', \'delete\': \'base\', \'base\':\''.$no_bases.'\'}); return false;"><img src="images/udi/trash.png" alt="'._('Delete search base').'"/></a> &nbsp;';
            $field .= '</span></td></tr>';
        }
    }
    $field .= $request['page']->configField('no_of_bases', array('type' => 'hidden', 'value' => $no_bases));
    $field .= '<tr><td>'.$request['page']->configMoreField('new_base', _('Search Base'), array('type' => 'text', 'value' => '', 'size' => 50), true).'</td></tr>';
    $field .= '</table>';
    echo $request['page']->configRow($request['page']->configFieldLabel('search_bases', _('Search bases for users:')), $field, true);
    echo '</fieldset>';

    /*
     * Reporting control configuration group
     */
    // file path for the UDI import
    echo '<fieldset class="config-block"><legend>'._('Reporting control').'</legend>';
    // enable reporting
    $enable_reporting_opts = array('value' => 0, 'type' => 'checkbox');
    
    $reportpath_opts = array('type' => 'text', 'value' => $cfg['reportpath'], 'size' => 75);
    $reportemail_opts = array('type' => 'text', 'value' => $cfg['reportemail'], 'size' => 60);
    if (isset($cfg['enable_reporting']) && $cfg['enable_reporting'] == 'checked') {
        $enable_reporting_opts['checked'] = 'checked';
        $enable_reporting_opts['value'] = 1;
    }
    else {
        $reportpath_opts['disabled'] = 'disabled';
        $reportemail_opts['disabled'] = 'disabled';
    }
    echo $request['page']->configEntry('enable_reporting', _('Enable reporting:'), $enable_reporting_opts, true, false);    
    if (isset($reportpath_opts['disabled'])) {
        echo $request['page']->configEntry('reportemail', _('Reporting notification email:'), $reportemail_opts, true, false);
        echo $request['page']->configEntry('reportemail', '', array('type' => 'hidden', 'value' => $cfg['reportemail']), false);
        echo $request['page']->configEntry('reportpath', _('Report storage file path:'), $reportpath_opts, true, false);
        echo $request['page']->configEntry('reportpath', '', array('type' => 'hidden', 'value' => $cfg['reportpath']), false);
    }
    else {
        echo $request['page']->configEntry('reportemail', _('Reporting notification email:'), $reportemail_opts);
        echo $request['page']->configEntry('reportpath', _('Report storage file path:'), $reportpath_opts);
    }
    echo '</fieldset>';
    
    /*
     * Account creation group
     */
    // Create In bucket for new accounts - must be one of the search bases
    echo '<fieldset class="config-block"><legend>'._('Account creation').'</legend>';
    // ignore account creations
    $ignore_creates_opts = array('value' => 0, 'type' => 'checkbox');
    $create_in_opts = array('type' => 'text', 'value' => $cfg['create_in'], 'size' => 50);
   if (isset($cfg['ignore_creates']) && $cfg['ignore_creates'] == 'checked') {
        $ignore_creates_opts['checked'] = 'checked';
        $ignore_creates_opts['value'] = 1;
        $create_in_opts['disabled'] = 'disabled';
    }
    echo $request['page']->configEntry('ignore_creates', _('Ignore account creations:'), $ignore_creates_opts, true, false);    
    
    // objectClasses for account creation
    $no_classes = 0;
    $class = '<div class="felement ftext"><table class="item-list-config">';
    if (isset($cfg['objectclasses'])) {
        $classes = $udiconfig->getObjectClasses();
        foreach ($classes as $oc) {
            $no_classes += 1;
            $class .= '<tr><td>';
            if (isset($create_in_opts['disabled'])) {
                $class .= $request['page']->configField('objectclass_'.$no_classes, array('type' => 'text', 'value' => $oc, 'size' => 30, 'disabled' => 'disabled'), array());
                $class .= $request['page']->configEntry('objectclass_'.$no_classes, '', array('type' => 'hidden', 'value' => $oc), false);
            }
            else {
                $class .= '<span style="white-space: nowrap;">'.$request['page']->configSelect('objectclass_'.$no_classes, $socs, strtolower($oc)).
                            '&nbsp;<a href="" title="'._('Delete objectClass').'" onclick="post_to_url(\'cmd.php\', {\'configuration\': \'delete\', \'delete\': \'objectclass\', \'objectclass\': \''.$no_classes.'\'}); return false;"><img src="images/udi/trash.png" alt="'._('Delete objectClass').'"/></a> &nbsp;'.
                            '</span>';
            }
            $class .= '</td></tr>';
        }
    }
    if (!isset($create_in_opts['disabled'])) {
        $class .= '<tr><td>'.$request['page']->configMoreSelect('new_objectclass', _('New objectClass'), $objectclasses, array()).'</td></tr>';
    }
    $class .= '</table></div>';
    $class .= $request['page']->configField('no_of_objectclasses', array('type' => 'hidden', 'value' => $no_classes), array());

    echo $request['page']->configRow(
                $request['page']->configFieldLabel(
                                    'objectclasses',
                                    _('objectClasses for account creation:') 
                                    ), 
                $class, 
                (isset($create_in_opts['disabled']) ? false : true));

    // DN attribute
    $dn_default = isset($cfg['dn_attribute']) ? $cfg['dn_attribute'] : 'cn';
    if (isset($create_in_opts['disabled'])) {
        echo $request['page']->configEntry('dn_attribute', _('DN Attribute:'), array('type' => 'text', 'value' => $dn_default, 'size' => 5, 'disabled' => 'disabled'), true, false);
        echo $request['page']->configEntry('dn_attribute', '', array('type' => 'hidden', 'value' => $dn_default), false);
    }
    else {
        echo $request['page']->configSelectEntry('dn_attribute', _('DN Attribute:'), 
                                                 array("cn" => new ObjectClass_ObjectClassAttribute("cn", "cn"), 
                                                       "uid" => new ObjectClass_ObjectClassAttribute("uid", "uid")), $dn_default);
    }
                
    // where to create new accounts
    $field = '<div class="felement ftext"><span style="white-space: nowrap;">';
    $field .= $request['page']->configField('create_in', $create_in_opts, array());
    if (isset($create_in_opts['disabled'])) {
        echo $request['page']->configEntry('create_in', '', array('type' => 'hidden', 'value' => $cfg['create_in']), false);
    }
    else {
        $field .= $request['page']->configChooser('create_in');
    }
    $field .= '</span></div>';
    echo $request['page']->configRow($request['page']->configFieldLabel('create_in', _('Default new accounts container (with base):')), $field, (isset($create_in_opts['disabled']) ? false : true));
    
    // map mlepGroupMembership values to directory containers                
    echo '<p class="shrink">'._('Map groups to containers:').'</p>';
    // indicate how many mappings to deal with
    $no_mappings = 0;
    // handle group mappings separately
    # “Board of Trustees#Parent Helper#LMS Access”
    $groups_enabled_opts = array('value' => 0, 'type' => 'checkbox');
    if (isset($cfg['groups_enabled']) && $cfg['groups_enabled'] == 'checked') {
        $groups_enabled_opts['checked'] = 'checked';
        $groups_enabled_opts['value'] = 1;
    }
    //echo '<div class="underline">&nbsp;</div>';
    if (!empty($cfg['container_mappings'])) {
        $container_mappings = explode(';', $cfg['container_mappings']);
        //var_dump($mappings);
        foreach ($container_mappings as $map) {
            // break the mapping into source and targets
            list($group, $target) = explode('|', $map);
            // ignore broken mappings
            if (empty($group)) {
                continue;
            }
            $no_mappings += 1;
            if (!isset($create_in_opts['disabled'])) {
                $field = $request['page']->configField('container_mapping_'.$no_mappings.'_target', array('type' => 'text', 'value' => $target, 'size' => 50), array());
                $field .= $request['page']->configChooser('container_mapping_'.$no_mappings.'_target');
                echo $request['page']->configRow(
                            $request['page']->configFieldLabel(
                                                'container_mapping_'.$no_mappings, 
                                                '<a href="" title="'._('Delete mapping').'" onclick="post_to_url(\'cmd.php\', {\'configuration\': \'delete\', \'delete\': \'container_mapping\', \'container_mapping\': \''.
                                                $group.'\'}); return false;"><img src="images/udi/trash.png" alt="'._('Delete entire group').'"/></a> &nbsp;'.
                                                $request['page']->configField(
                                                                    'container_mapping_'.$no_mappings, 
                                                                    array('type' => 'text', 'value' => $group, 'size' => 13), array())
                                                ), 
                            '<div class="felement_free ftext">'.$field.'</div>', 
                            false);
            }
            else {
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
                echo $request['page']->configEntry('container_mapping_'.$no_mappings, '', array('type' => 'hidden', 'value' => $group), false);
                echo $request['page']->configEntry('container_mapping_'.$no_mappings.'_target', '', array('type' => 'hidden', 'value' => $target), false);
            }
        }
    }
    
    // add a new mapping
    if (!isset($create_in_opts['disabled'])) {
        echo $request['page']->configRow(
                                        '<div class="fitemtitle">'.
                                        $request['page']->configMoreField('new_container_mapping', _('New Group Mapping'), array('type' => 'text', 'value' => '', 'size' => 35), false, true).
                                        '</div>',
                    '',
                    false);
    }
    
    // indicate how many group mappings to deal with
    echo $request['page']->configField('no_of_container_mappings', array('type' => 'hidden', 'value' => $no_mappings), array());
                
    echo '</fieldset>';

    /*
     * Account updates configuration group
     */
    // ignore account updates
    echo '<fieldset class="config-block"><legend>'._('Account updates').'</legend>';
    $ignore_updates_opts = array('value' => 0, 'type' => 'checkbox');
   if (isset($cfg['ignore_updates']) && $cfg['ignore_updates'] == 'checked') {
        $ignore_updates_opts['checked'] = 'checked';
        $ignore_updates_opts['value'] = 1;
    }
    echo $request['page']->configEntry('ignore_updates', _('Ignore account updates:'), $ignore_updates_opts, true, false);    

    
    // ignore fields for update
    $no_attrs = 0;
    $attributes = array_merge(array("none" => new ObjectClass_ObjectClassAttribute("", "")), $dmo_attrs);
    $attr = '<div class="felement ftext"><table class="item-list-config">';
    if (isset($cfg['ignore_attrs'])) {
        $attrs = $udiconfig->getIgnoreAttrs();
        foreach ($attrs as $attribute) {
            $no_attrs += 1;
            $attr .= '<tr><td>';
            if (isset($ignore_updates_opts['checked'])) {
                $attr .= $request['page']->configField('ignore_attrs_'.$no_attrs, array('type' => 'text', 'value' => $attribute, 'size' => 20, 'disabled' => 'disabled'), array());
                $attr .= $request['page']->configEntry('ignore_attrs_'.$no_attrs, '', array('type' => 'hidden', 'value' => $attribute), false);
            }
            else {
                $attr .= '<span style="white-space: nowrap;">'.$request['page']->configSelect('ignore_attrs_'.$no_attrs, $attributes, strtolower($attribute)).
                            '&nbsp;<a href="" title="'._('Delete attribute').'" onclick="post_to_url(\'cmd.php\', {\'configuration\': \'delete\', \'delete\': \'ignore_attrs\', \'ignore_attrs\': \''.$no_attrs.'\'}); return false;"><img src="images/udi/trash.png" alt="'._('Delete attribute').'"/></a> &nbsp;'.
                            '</span>';
            }
            $attr .= '</td></tr>';
        }
    }
    if (!isset($ignore_updates_opts['checked'])) {
        $attr .= '<tr><td>'.$request['page']->configMoreSelect('new_ignore_attrs', _('New attribute'), $attributes, array()).'</td></tr>';
    }
    $attr .= '</table></div>';
    $attr .= $request['page']->configField('no_of_ignore_attrs', array('type' => 'hidden', 'value' => $no_attrs), array());

    echo $request['page']->configRow(
                $request['page']->configFieldLabel(
                                    'ignore_attrs',
                                    _('Ignore attributes for account update:') 
                                    ), 
                $attr, 
                (isset($ignore_updates_opts['checked']) ? false : true));
    echo '</fieldset>';
    
    
    /*
     * Account deletes configuration group
     */
    // ignore deletes
    echo '<fieldset class="config-block"><legend>'._('Account deletion').'</legend>';
    $ignore_deletes_opts = array('value' => 0, 'type' => 'checkbox');
    $move_on_delete_opts = array('value' => 0, 'type' => 'checkbox');
    $move_to_opts = array('type' => 'text', 'value' => $cfg['move_to'], 'size' => 50);
    
    if (isset($cfg['ignore_deletes']) && $cfg['ignore_deletes'] == 'checked') {
        $ignore_deletes_opts['checked'] = 'checked';
        $ignore_deletes_opts['value'] = 1;
        $move_on_delete_opts['disabled'] = 'disabled';
        $move_to_opts['disabled'] = 'disabled';
    }
    else {
        // do we move deletes or flag them
        if (isset($cfg['move_on_delete']) && $cfg['move_on_delete'] == 'checked') {
            $move_on_delete_opts['checked'] = 'checked';
            $move_on_delete_opts['value'] = 1;
        }
        else {
            $move_to_opts['disabled'] = 'disabled';
        }
    }
    echo $request['page']->configEntry('ignore_deletes', _('Ignore account deletes:'), $ignore_deletes_opts, true, false);    
    if (isset($move_on_delete_opts['disabled'])) {
        echo $request['page']->configEntry('move_on_delete', _('Move accounts on Delete:'), $move_on_delete_opts, true, false);
        echo $request['page']->configEntry('move_on_delete', '', array('type' => 'hidden', 'value' => $cfg['move_on_delete']), false);
    }
    else {
        echo $request['page']->configEntry('move_on_delete', _('Move accounts on Delete:'), $move_on_delete_opts);
    }
    
    // where do we move deletes to
    if (isset($move_to_opts['disabled'])) {
        echo $request['page']->configEntry('move_to', _('Move deleted to(without base):'), $move_to_opts, true, false);
        echo $request['page']->configEntry('move_to', '', array('type' => 'hidden', 'value' => $cfg['move_to']), false);
    }
    else {
        $field = '<div class="felement ftext"><span style="white-space: nowrap;">';
        $field .= $request['page']->configField('move_to', $move_to_opts, array());
        $field .= $request['page']->configChooser('move_to');
        $field .= '</span></div>';
        echo $request['page']->configRow($request['page']->configFieldLabel('move_to', _('Move deleted to(with base):')), $field, true);
    }
    
    // if not move to out - what attribute and value to set
    echo '</fieldset>';
    
    
    // page save button
    echo $request['page']->configEntry('submitbutton', '', array('type' => 'submit', 'value' => _('Save')), false);
    echo '</div>'; // end of udiform
    echo '<div class="udi_clear"></div>';
    echo '</div>'; // end of center
}

?>
