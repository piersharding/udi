<?php
$cfg = $request['udiconfig'];
echo '<div class="center">';
echo '<div class="help">';
include 'mapping.help.php';
echo '</div>';
echo '<div class="udiform">';
echo '<div class="item-list">';
echo '<h2 class="shrink">'._('Import File Mappings:').'</h2>';

$mandatory_fields = array(
                            'mlepSmsPersonId',
                            'mlepStudentNSN',
                            'mlepUsername',
                            'mlepFirstAttending',
                            'mlepLastAttendance',
                            'mlepFirstName',
                            'mlepLastName',
                            'mlepRole',
                            'mlepAssociatedNSN',
                            'mlepEmail',
                            'mlepOrganisation',
                            'mlepGroupMembership',
                            );

$socs = $app['server']->SchemaObjectClasses('user');
$mlepPerson = $socs['mlepperson'];
$must = $mlepPerson->getMustAttrs();
$may = $mlepPerson->getMayAttrs();
$imo_attrs = array_merge($must, $may);
$dmo_attrs = $app['server']->SchemaAttributes('user');
$dmo_attrs = array_merge(array("none" => new ObjectClass_ObjectClassAttribute("", "")), $dmo_attrs);

$no_mappings = 0;
echo '<div class="underline">&nbsp;</div>';
if (isset($cfg['mappings'])) {
    $mappings = explode(';', $cfg['mappings']);
    //var_dump($mappings);
    foreach ($mappings as $map) {
        // break the mapping into source and targets
        list($source, $targets) = explode('(', $map);
        $targets = preg_replace('/^(.*?)\)$/','$1', $targets);
        $targets = explode(',', $targets);
        //var_dump($source);
        //var_dump($targets);
        //exit(0);
        // ignore broken mappings
        if (empty($source)) {
            continue;
        }
        $no_mappings += 1;
        $no_fields = 0;
        $field = '<table class="item-list">';
        foreach ($targets as $target) {
            $no_fields += 1;
            $field .= '<tr><td>';
            $field .= '<div class="felement ftext"><span style="white-space: nowrap;">'.$request['page']->configSelect('mapping_'.$no_mappings.'_field_'.$no_fields, $dmo_attrs, strtolower($target)).
                        '&nbsp;<a href="" title="'._('Delete target').'" onclick="post_to_url(\'cmd.php\', {\'delete\': \'field_mapping\', \'field_mapping\': \''.$source.':'.$no_fields.'\'}); return false;"><img src="images/udi/trash.png" alt="'._('Delete target').'"/></a> &nbsp;'.
                        '</span></div>';
            $field .= '</td></tr>';
        }
        $source_attrs = array_merge($imo_attrs, array());
        if (!array_key_exists(strtolower($source), $imo_attrs)) {
            $source_attrs[strtolower($source)] = new ObjectClass_ObjectClassAttribute($source, $source);
        }
        $field .= '<tr><td>'.$request['page']->configMoreSelect('new_mapping_'.$no_mappings, _('New Target'), $dmo_attrs).'</td></tr>';
        $field .= '</table>';
        $field .= $request['page']->configField('no_of_fields_in_mapping_'.$no_mappings, array('type' => 'hidden', 'value' => $no_fields), array());

        echo $request['page']->configRow(
                    $request['page']->configFieldLabel(
                                        'mapping_'.$no_mappings, 
        '<span style="white-space: nowrap;"><a href="" title="'._('Delete entire source').'" onclick="post_to_url(\'cmd.php\', {\'delete\': \'mapping\', \'mapping\': \''.$source.'\'}); return false;"><img src="images/udi/trash.png" alt="'._('Delete entire source').'"/></a> &nbsp;'.
                                        $request['page']->configSelect(
                                                            'mapping_'.$no_mappings, 
                                                            $source_attrs, 
                                                            strtolower($source)).'</span>',
                                        '_left_wide'), 
                    $field, 
                    false);
        echo '<div class="underline">&nbsp;</div>';
    }

}

// add the default Add on the end XXX fix add name or select field
$imo_attrs = array_merge(array("none" => new ObjectClass_ObjectClassAttribute("", "")), $imo_attrs);
echo $request['page']->configRow(
                                '<div class="fitemtitle_left">'.
                                $request['page']->configMoreOrSelect(
                                                        'new_mapping', 
                                                        _('New Mapping'),
                                                        array('type' => 'text', 'value' => '', 'size' => 35),
                                                        $imo_attrs 
                                                        ).
                                '</div>',
            '',
            false);

// space out to the group mappings
echo '<p>&nbsp;</p>';
echo '<h2 class="shrink">'._('Group Membership Mappings:').'</h2>';
echo '<div class="underline">&nbsp;</div>';

// indicate how many mappings to deal with
echo $request['page']->configField('no_of_mappings', array('type' => 'hidden', 'value' => $no_mappings), array());

//$field .= $request['page']->configMoreField('new_base', _('Search Base'), array('type' => 'text', 'value' => '', 'size' => 50), true);
//echo $request['page']->configRow($request['page']->configFieldLabel('search_bases', _('Search bases for users:')), $field, true);

// handle group mappings separately
# “Board of Trustees#Parent Helper#LMS Access”
$groups_enabled_opts = array('value' => 0, 'type' => 'checkbox');
if (isset($cfg['groups_enabled']) && $cfg['groups_enabled'] == 'checked') {
    $groups_enabled_opts['checked'] = 'checked';
    $groups_enabled_opts['value'] = 1;
}
echo $request['page']->configEntry('groups_enabled', _('Processing mlepGroupMembership Enabled:'), $groups_enabled_opts, true, false);
$group_attrs = array('none' => new ObjectClass_ObjectClassAttribute("", ""), 
                     'member' => new ObjectClass_ObjectClassAttribute("member", "member"),
                     'memberuid' => new ObjectClass_ObjectClassAttribute("memberUid", "memberuid"),
                     'uniquemember' => new ObjectClass_ObjectClassAttribute("uniqueMember", "uniquemember"),
//                     'memberof' => new ObjectClass_ObjectClassAttribute("memberOf", "memberof"),
);
if ($groups_enabled_opts['value'] == 1) {
    echo $request['page']->configSelectEntry('group_attr', _('Group Membership Attribute:'), $group_attrs, $cfg['group_attr'], false);
}
else {
    echo $request['page']->configEntry('group_attr', _('Group Membership Attribute:'), array('type' => 'text', 'value' => $cfg['group_attr'], 'disabled' => 'disabled'), true, false);
    echo $request['page']->configEntry('group_attr', '', array('type' => 'hidden', 'value' => $cfg['group_attr']), false);
}
echo '<div class="underline">&nbsp;</div>';

if (!empty($cfg['group_mappings'])) {
    $group_mappings = explode(';', $cfg['group_mappings']);
    //var_dump($mappings);
    foreach ($group_mappings as $map) {
        // break the mapping into source and targets
        list($group, $targets) = explode('(', $map);
        $targets = preg_replace('/^(.*?)\)$/','$1', $targets);
        $targets = explode('|', $targets);
        //var_dump($group);
        //var_dump($targets);
        //exit(0);
        // ignore broken mappings
        if (empty($group)) {
            continue;
        }
        $no_mappings += 1;
        $no_fields = 0;
        $field = '<table class="item-list">';
        foreach ($targets as $target) {
            if (empty($target)) {
                continue;
            }
            $no_fields += 1;
            $field .= '<tr><td>';
            $field .= '<span style="white-space: nowrap;">';
            $field .= $request['page']->configField('group_mapping_'.$no_mappings.'_field_'.$no_fields, array('type' => 'text', 'value' => $target, 'size' => 45), array());
            $field .= '&nbsp;<a href="" title="'._('Delete target').'" onclick="post_to_url(\'cmd.php\', {\'delete\': \'group_field_mapping\', \'group_field_mapping\': \''.$group.':'.$no_fields.'\'}); return false;"><img src="images/udi/trash.png" alt="'._('Delete target').'"/></a> &nbsp;';
            $field .= '</span>';
            $field .= '</td></tr>';
        }
        //$field .= '<tr><td>'.$request['page']->configMoreSelect('new_mapping_'.$no_mappings, _('New Target'), $dmo_attrs).'</td></tr>';
        $field .= '<tr><td>'.$request['page']->configMoreField('new_group_mapping_'.$no_mappings, _('New Target'), array('type' => 'text', 'value' => '', 'size' => 45), true).'</td></tr>';
        $field .= '</table>';
        $field .= $request['page']->configField('no_of_fields_in_group_mapping_'.$no_mappings, array('type' => 'hidden', 'value' => $no_fields), array());

        echo $request['page']->configRow(
                    $request['page']->configFieldLabel(
                                        'group_mapping_'.$no_mappings, 
        '<span style="white-space: nowrap;"><a href="" title="'._('Delete entire group').'" onclick="post_to_url(\'cmd.php\', {\'delete\': \'group_mapping\', \'group_mapping\': \''.$group.'\'}); return false;"><img src="images/udi/trash.png" alt="'._('Delete entire group').'"/></a> &nbsp;'.
                                        $request['page']->configField(
                                                            'group_mapping_'.$no_mappings, 
                                                            array('type' => 'text', 'value' => $group, 'size' => 13), array()).'</span>'
                                        ), 
                    '<div class="felement_free ftext">'.$field.'</div>', 
                    //$field, 
                    false);
        echo '<div class="underline">&nbsp;</div>';
    }
}

// add the default Add on the end XXX fix add name or select field
echo $request['page']->configRow(
                                '<div class="fitemtitle_left">'.
                                $request['page']->configMoreField('new_group_mapping', _('New Group Mapping'), array('type' => 'text', 'value' => '', 'size' => 35), false, true).
                                '</div>',
            '',
            false);

// indicate how many group mappings to deal with
echo $request['page']->configField('no_of_group_mappings', array('type' => 'hidden', 'value' => $no_mappings), array());


// page save button
echo '<p>&nbsp;</p>';
echo $request['page']->configEntry('submitbutton', '', array('type' => 'submit', 'value' => '&nbsp;'._('Update').'&nbsp;'), false);

echo '</div>'; // end of item-list
echo '</div>'; // end of udiform
echo '<div class="udi_clear"></div>';
echo '</div>'; // end of center
?>
