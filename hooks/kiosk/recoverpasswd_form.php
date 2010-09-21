<?php
$cfg = $udiconfig->getConfig();
    
echo '<div class="kioskform">';
    echo '<fieldset class="kiosk"><legend>'._('Recover Password').'</legend>';

    $token = get_request('token', 'REQUEST');
    $request_user = kiosk_verify_token($token);
    if (empty($request_user)) {
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
        echo $request['page']->configEntry('username', _('Username:'), array('type' => 'text', 'size' => 30, 'value' => get_request('username')), true);
        echo $request['page']->configEntry('email', _('Email Address:'), array('type' => 'text', 'size' => 30, 'value' => get_request('email')), true);
        echo $request['page']->configRow($request['page']->configFieldLabel('action', _('')), '<div class="felement ftext">'._('Username and email address must match. A temporary password<br/> will be emailed to you').'</div>', false);
    }
    else {
        // confirm the token and get the new password
        echo $request['page']->configEntry('server_id', '', array('type' => 'hidden', 'value' => $request_user['server_id']), false);
        echo $request['page']->configEntry('token', '', array('type' => 'hidden', 'value' => $token), false);
        echo $request['page']->configEntry('username', _('Username:'), array('type' => 'text', 'size' => 30, 'value' => $request_user['username'], 'disabled' => 'disabled'), true, false);
        echo $request['page']->configEntry('username', '', array('type' => 'hidden', 'value' => $request_user['username']), false);
        echo $request['page']->configEntry('newpassword', _('New Password:'), array('type' => 'password', 'value' => ''), true);
        echo $request['page']->configEntry('confirm', _('Confirm:'), array('type' => 'password', 'value' => ''), true);
        $policy = '';
        $result = udi_run_hook('passwd_policy_algorithm_label',array(), $cfg['passwd_policy_algo'].'_label');
        if (is_array($result)) {
            $result = array_shift($result);
            if (is_array($result) && isset($result['kiosk_label'])) {
                $policy = $result['kiosk_label'];
            }
        }
        echo $request['page']->configRow($request['page']->configFieldLabel('policy', _('Policy: ')), '<div class="felement ftext">'.$policy.'</div>', false);
    }
    
    echo "<br/>";
    
  // page save button
    echo $request['page']->configEntry('submitbutton', '', array('type' => 'submit', 'value' => _('Submit')), false);
    echo '</fieldset>';    
    
echo '</div>';    
    
?>
