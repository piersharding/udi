<?php
$cfg = $udiconfig->getConfig();
    

echo '<div class="kioskform">';

    if (!$confirmnow) {
        echo '<fieldset class="kiosk"><legend>'._('Un/Lock Account').'</legend>';
            // display the server selector
            if (count(array_keys($_SESSION[APPCONFIG]->getServerList())) > 1) {
                $index = $app['server']->getIndex();
                if (is_null($index)) {
                    $index = min(array_keys($_SESSION[APPCONFIG]->getServerList()));
                }
                if (count($_SESSION[APPCONFIG]->getServerList()) > 1) {
                    echo $request['page']->configRow($request['page']->configFieldLabel('server_id', _('Select Directory')), 
                                                     '<div class="felement ftext">'.server_select_list($index,false,'server_id',true, "onchange=\"switch_servers('get', this, 'changepasswd')\"").'</div>');
                }
            }
            // change password fields
            echo $request['page']->configEntry('adminusername', _('Admin User:'), array('type' => 'text', 'size' => 30, 'value' => get_request('adminusername')), true);
            echo $request['page']->configEntry('adminpassword', _('Admin Password:'), array('type' => 'password', 'value' => ''), true);
            echo "<br/>";
            echo $request['page']->configEntry('username', _('Username:'), array('type' => 'text', 'size' => 30, 'value' => get_request('username')), true);
        echo "<br/>";
        
      // page save button
        echo $request['page']->configEntry('submitbutton', '', array('type' => 'submit', 'value' => _('Submit')), false);
        echo '</fieldset>';    
    
    }
    else {
        if ($user['deactive'] == true) {
            echo $request['page']->confirmationPage(_('User Account'), 
                                        _('Reactivate'), 
                                        get_request('username'), 
                                        _('Are you sure you want to reactivate this UDI account?'), 
                                        _('Reactivate'), 
                                        array('cmd' => 'lockaccount', 'action' => 'reactivate', 'dn' => $dn, 'adminusername' => $adminusername, 'username' => $username));
        }
        else {
            echo $request['page']->confirmationPage(_('User Account'), 
                                            _('Deactivate'), 
                                            get_request('username'), 
                                            _('Are you sure you want to deactivate this UDI account?'), 
                                            _('Deactivate'), 
                                            array('cmd' => 'lockaccount', 'action' => 'deactivate', 'dn' => $dn, 'adminusername' => $adminusername, 'username' => $username));
        }
//        echo $request['page']->configEntry('adminusername', _('Admin User:'), array('type' => 'text', 'size' => 30, 'value' => get_request('adminusername'), 'disabled' => 'disabled'), true);
//        echo "<br/>";
//        echo $request['page']->configEntry('username', _('Username:'), array('type' => 'text', 'size' => 30, 'value' => get_request('username'), 'disabled' => 'disabled'), true);
    }
    
echo '</div>';    
?>
