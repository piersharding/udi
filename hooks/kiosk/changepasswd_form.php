<?php
echo '<div class="kioskform">';
    echo '<fieldset class="kiosk"><legend>'._('Change Password').'</legend>';

    // display the server selector
    $index = $app['server']->getIndex();
    if (is_null($index)) {
        $index = min(array_keys($_SESSION[APPCONFIG]->getServerList()));
    }
    if (count($_SESSION[APPCONFIG]->getServerList()) > 1) {
        echo $request['page']->configRow($request['page']->configFieldLabel('server_id', _('Select Directory')), 
                                         '<div class="felement ftext">'.server_select_list($index,false,'server_id',true, "onchange=\"switch_servers('get', this, 'changepasswd')\"").'</div>');
    }

    // change password fields
    echo $request['page']->configEntry('username', _('Username:'), array('type' => 'text', 'size' => 30, 'value' => get_request('username')), true);
    echo $request['page']->configEntry('oldpassword', _('Old Password:'), array('type' => 'password', 'value' => get_request('oldpassword')), true);
    echo $request['page']->configEntry('newpassword', _('New Password:'), array('type' => 'password', 'value' => ''), true);
    echo $request['page']->configEntry('confirm', _('Confirm:'), array('type' => 'password', 'value' => ''), true);
    echo "<br/>";
    
  // page save button
    echo $request['page']->configEntry('submitbutton', '', array('type' => 'submit', 'value' => _('Change')), false);
    echo '</fieldset>';    
    
    echo "passwd policy - check password against policy - display policy - display server list to choose from";
echo '</div>';    
?>
