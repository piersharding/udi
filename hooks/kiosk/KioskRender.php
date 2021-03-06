<?php
/**
 * This class will render the page for the UDI.
 *
 * @author The phpLDAPadmin development team
 * @package phpLDAPadmin
 */

/**
 * KioskRender class
 *
 * @package phpLDAPadmin
 * @subpackage Templates
 */
class KioskRender extends PageRender {
	# Page number
	private $pagelast;
	
	private $udiconfig;
	
	public $messages = array();
	
	public function isError() {
	    foreach ($this->messages as $msg) {
	        if ($msg['type'] == 'error') {
	            return true;
	        }
	    }
        foreach ($_SESSION['sysmsg'] as $msg) {
            if ($msg['type'] == 'error' && !preg_match('/DEBUG/', $msg['body'])) {
                return true;
            }
        }
	    return false;
	}

    
    /**
     * Log the system messages to report file
     */
    public function log_system_messages() {
        foreach ($_SESSION['sysmsg'] as $msg) {
            if (!preg_match('/DEBUG/', $msg['body'])) {
                $this->log_to_file($msg['type'], $msg['body']);
            }
        }
    }
    
    
	/** CORE FUNCTIONS **/

	/**
	 * Initialise and Render the KioskRender
	 */
	public function accept($norender=false) {
		if (DEBUG_ENABLED && (($fargs=func_get_args())||$fargs='NOARGS'))
			debug_log('Entered (%%)',129,0,__FILE__,__LINE__,__METHOD__,$fargs);

		if (DEBUGTMP) printf('<font size=-2>%s:%s</font><br />',time(),__METHOD__);
		if (DEBUGTMP||DEBUGTMPSUB) printf('<font size=-2>* %s [Visit-Start:%s]</font><br />',__METHOD__,get_class($this));

		$this->page = get_request('page','REQUEST',false,1);

		$this->url_base = sprintf('server_id=%s&', $this->getServerID());
		$this->layout['hint'] = sprintf('<td class="icon"><img src="%s/light.png" alt="%s" /></td><td colspan="3"><span class="hint">%%s</span></td>',
			IMGDIR,_('Hint'));
		$this->layout['action'] = '<td class="icon"><img src="%s/%s" alt="%s" /></td><td><a href="cmd.php?%s" title="%s">%s</a></td>';
		$this->layout['actionajax'] = '<td class="icon"><img src="%s/%s" alt="%s" /></td><td><a href="cmd.php?%s" title="%s" onclick="return ajDISPLAY(\'BODY\',\'%s\',\'%s\');">%s</a></td>';
	}


	private function getMenuItem($item, $selected) {

		$href = sprintf('cmd=%s&%s',$item['name'], $this->url_base);
		$after_function = isset($item['onload']) ? $item['onload'].';' : '';
        $layout = '<li  class="ui-state-default ui-corner-top %s"><a href="kiosk.php?%s" title="%s" onclick="var res = ajDISPLAY(\'BODY\',\'%s\',\'%s\'); '.$after_function.' return res;"><img src="%s/%s" alt="%s" /> %s</a></li>';
        $classes = '';
        
        if ($selected) {
            $classes = ' ui-tabs-selected ui-state-active';
        }
	    
		return sprintf($layout,
		        $classes,
				htmlspecialchars($href), // href
				$item['title'], // title
				htmlspecialchars($href), $item['text'], // ajax bits
        		IMGDIR, $item['image'], $item['imagetext'], // image bits
				$item['text'] // anchor text
				);
	}

    public function getMenu($active) {
        global $udiconfig, $app;
        $cfg = $udiconfig->getConfig();
        
        $menu = "<div id='udi-menu' class='ui-tabs ui-widget ui-widget-content ui-corner-all'>";
        $menu .= '<ul class="tabset_tabs ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header ui-corner-all">';
        $menus = array(
               'changepasswd' => array('name' => 'changepasswd', 'text' => _('Change Password'), 'title' => _('Change Password'), 'image' => 'key.png', 'imagetext' => _('Password'),),
               'recoverpasswd' => array('name' => 'recoverpasswd', 'text' => _('Recover Password'), 'title' => _('Recover Password'), 'image' => 'light.png', 'imagetext' => _('Password'),), 
               'resetpasswd' => array('name' => 'resetpasswd', 'text' => _('Reset Password'), 'title' => _('Reset Password'), 'image' => 'user.png', 'imagetext' => _('User'),), 
               'lockaccount' => array('name' => 'lockaccount', 'text' => _('Un/Lock Account'), 'title' => _('Un/Lock Account'), 'image' => 'key.png', 'imagetext' => _('Key'),), 
               'help' => array('name' => 'help', 'text' => _('Help'), 'title' => _('Kiosk Help'), 'image' => 'help-small.png', 'imagetext' => _('Help'),),
                );
        $enable_kiosk = $app['server']->getValue('server','kiosk_enable_recover');
        if (!$enable_kiosk) {
            unset($menus['recoverpasswd']);
        }
        foreach ($menus as $item) {
            $menu .= $this->getMenuItem($item, ($active == $item['name'] ? true : false));
        }
        $menu .= "</ul></div>";
        return $menu;
    }
    
    
    
    public function configRow($label, $field, $required=true) {
        if ($required) {
            return '<div class="fitem required">' . $label . $field .  '</div>';
        }
        else {
            return '<div class="fitem">' . $label . $field .  '</div>';
        }
    }

    public function configFieldLabel($name, $text, $variant='') {
        return '<div class="fitemtitle'.$variant.'"><label for="id_'.$name.'">'.$text.'</label></div>';
    }

    public function configField($name, $attrs = array(), $container = array('class' => 'felement ftext')) {
        //return '<div class="felement ftext"><input name="'.$name.'" type="text" value="test 2004" onblur="validate_mod_scorm_mod_form_name(this)" onchange="validate_mod_scorm_mod_form_name(this)" id="id_'.$name.'"></div>';
        $opts = array();
        if (isset($attrs['type']) && $attrs['type'] == 'submit') {
            $attrs['class'] = 'udi-button';
        }
        foreach  ($attrs as $k => $v) {
            $opts[]= "$k='$v'";
        }
        $field_opts = implode(' ', $opts);
        $opts = array();
        foreach  ($container as $k => $v) {
            $opts[]= "$k='$v'";
        }
        $container_opts = implode(' ', $opts);
        if (empty($container_opts)) {
            return '<input name="'.$name.'" '.$field_opts.' id="id_'.$name.'">';
        }
        else {
            return '<div '.$container_opts.'><input name="'.$name.'" '.$field_opts.' id="id_'.$name.'"></div>';
        }
    }

    public function configButton($name, $attrs = array(), $container = array('class' => 'felement ftext')) {
        $opts = array();
        foreach  ($attrs as $k => $v) {
            $opts[]= "$k='$v'";
        }
        $field_opts = implode(' ', $opts);
        $opts = array();
        foreach  ($container as $k => $v) {
            $opts[]= "$k='$v'";
        }
        $container_opts = implode(' ', $opts);
        if (empty($container_opts)) {
            return '<input type="button" '.$field_opts.' />';
        }
        else {
            return '<div '.$container_opts.'><input type="button" '.$field_opts.'/></div>';
        }
    }
    
    public function configEntry($name, $text, $attrs = array(), $label=true, $required=true) {

        if ($label) {
            return  $this->configRow($this->configFieldLabel($name, $text), $this->configField($name, $attrs), $required);
        }
        else {
            return  $this->configRow('', $this->configField($name, $attrs), $required);
        }
    }

    public function info($msg, $action='') {
        $this->messages[]= array('type' => 'info', 'message' => $msg);
        $this->log_to_file('info', $msg);
        system_message(array(
                     'title'=>_($action),
                                'body'=> $msg,
                                'type'=>'info',
                sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
                    get_request('udi_nav','REQUEST'),
                    get_request('server_id','REQUEST'))));
    }

    public function warning($msg, $action='') {
        $this->messages[]= array('type' => 'warning', 'message' => $msg);
        $this->log_to_file('warning', $msg);
        system_message(array(
                     'title'=>_($action),
                                'body'=> $msg,
                                'type'=>'warn',
                sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
                    get_request('udi_nav','REQUEST'),
                    get_request('server_id','REQUEST'))));
    }

    public function error($msg, $action='') {
        $this->messages[]= array('type' => 'error', 'message' => $msg);
        $this->log_to_file('error', $msg);
        system_message(array(
                     'title'=>_($action),
                                'body'=> $msg,
                                'type'=>'error',
                sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
                    get_request('udi_nav','REQUEST'),
                    get_request('server_id','REQUEST'))));
        return false;
    }
    
    public function log_to_file($type, $msg) {
        if ($this->udiconfig) {
            if (!$this->logfile) {
                $cfg = $this->udiconfig->getConfig();
                // dont bother if reporting is disabled
                if (!isset($cfg['enable_reporting']) || $cfg['enable_reporting'] != 'checked') {
                    return;
                }
                $file = 'processor_'.date("Y-m-d-H:i:s", strtotime("+0 days")).'.txt';
                $this->logfile = $cfg['reportpath'].'/'.$file;
            }
            $fh = fopen($this->logfile, 'a');
            fwrite($fh, $type."\t".$msg."\n");
            fflush($fh);
            fclose($fh);
        }
    }
    
    public function log_header($action, $cron=false) {
        global $app;
        $mode = $cron ? 'cron' : 'web';
        if (!$app['server']->getLogin('user') && $cron) {
            $user = 'cron';
        }
        else {
            $user = $app['server']->getLogin('user');
        }
        $msg = var_export(array('action' => $action, 
                                            'time' => time(), 
                                            'user' => $user,
                                            'mode' => $mode), true);
        $msg = preg_replace('/\n/', '', $msg);
        $this->log_to_file('start', $msg);
    }
    
    public function log_footer() {
        global $app;
        $msg = var_export(array('time' => date("d/m/Y H:i:s", strtotime('+0 days'))), true);
        $msg = preg_replace('/\n/', '', $msg);
        $this->log_to_file('end', $msg);
    }
    
    public function outputMessages() {
        $msgs = '';
        foreach ($this->messages as $msg) {
            $msgs.= '<div  class="'.$msg['type'].'">'.$msg['message'].'</div>';
        }
        $this->messages = array();
        return "<div class='messages'>".$msgs.'</div>';
    }
    
    public function confirmationPage($title, $acronym, $element, $msg, $action, $params) {
        global $app;
        $out  =  '<table class="forminput" border=0>';
        $out .= sprintf('<tr><td colspan=4>%s</td></tr>', $msg);
        $out .=  '<tr><td colspan=4>&nbsp;</td></tr>';
        $out .= sprintf('<tr><td width=10%%>%s:</td><td colspan=3 width=75%%><b>%s</b></td></tr>', _('Server'), $app['server']->getName());
        $out .= sprintf('<tr><td width=10%%><acronym title="%s">%s</acronym></td><td colspan=3 width=75%%><b>%s</b></td></tr>',
            $title, $acronym, $element);
        $out .=  '<tr><td colspan=4>&nbsp;</td></tr>';
        $out .=  "\n";
        $out .=  '<tr>';
        $out .=  '<td width"10%">&nbsp;</td>';
        $out .=  '<td>';
        $out .=  '<table><tr><td>';
        $out .=  '<form action="kiosk.php" method="post">';
        $out .= sprintf('<input type="hidden" name="server_id" value="%s" />', $app['server']->getIndex());
        foreach ($params as $k => $v) {
            $out .= sprintf('<input type="hidden" name="%s" value="%s" />', $k, $v);
        }
        $out .=  '<input type="hidden" name="confirm" value="yes" />';
        $out .= sprintf('<input type="submit" name="submit" value="%s" />', $action);
        $out .=  '</form>';
        $out .=  '</td><td>';
        $out .=  '<form action="kiosk.php" method="post">';
        foreach ($params as $k => $v) {
            $out .= sprintf('<input type="hidden" name="%s" value="%s" />', $k, $v);
        }
        $out .=  '<input type="hidden" name="confirm" value="no" />';
        $out .= sprintf('<input type="submit" name="submit" value="%s" />', _('Cancel'));
        $out .=  '</form>';
        $out .=  '</td></tr></table>';
        $out .=  '</td>';
        $out .=  '</tr>';
        $out .=  '</table>';
        return $out;
    }
    
    /**
     * email out password reset request token
     * 
     * @param String $to
     * @param String $for
     * @param String $token
     */
    public function email_reset_token($to, $for, $token) {
        global $udiconfig;
        $cfg = $udiconfig->getConfig();
//        $to = $cfg['reportemail'].', '.$to; 
        $method = "http";
        if ( $_SERVER['HTTPS'] ) { $method .= "s"; }
        $server_name = $_SERVER['SERVER_NAME'];
        $script_name = $_SERVER['SCRIPT_NAME'];
        $reset_url = $method."://".$server_name.$script_name."?cmd=recoverpasswd&token=".$token;
        
        $subject = _('Password Reset'); 
        $random_hash = md5(date('r', time())); 
        $headers = "From: udi@localhost\r\nReply-To: noreply@localhost"; 
        $headers .= "\r\nContent-Type: multipart/mixed; boundary=\"PHP-mixed-".$random_hash."\"";
        $body = "
--PHP-mixed-$random_hash
Content-Type: multipart/alternative; boundary=\"PHP-alt-$random_hash\"

--PHP-alt-$random_hash
Content-Type: text/plain; charset=\"iso-8859-1\" 
Content-Transfer-Encoding: 7bit

A request to reset the password for user $for, has been received. 
To reset your password, please follow the link:
$reset_url

--PHP-alt-$random_hash
Content-Type: text/html; charset=\"iso-8859-1\"
Content-Transfer-Encoding: 7bit

<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">
<html>
<head>
    <title>$subject</title>
<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">
</head>
<body>
<h2$subject</h2> 
<p>
A request to reset the password for user $for has been received.  To reset the password, 
please follow the link <a href='$reset_url'>$reset_url</a>.
</p>
</body>
</html>
--PHP-alt-$random_hash-- 

"; 
        @mail( $to, $subject, $body, $headers );

                
        // no point if there is no admin email address
        if (empty($cfg['reportemail'])) {
            return true;
        }
        
        $client_ip = $_SERVER['REMOTE_ADDR'];
        $time = date('r', time());
        
        // now email the attempt to the admin
        $body = "
--PHP-mixed-$random_hash
Content-Type: multipart/alternative; boundary=\"PHP-alt-$random_hash\"

--PHP-alt-$random_hash
Content-Type: text/plain; charset=\"iso-8859-1\" 
Content-Transfer-Encoding: 7bit

A request to reset the password for user $for, has been received. 
To reset your password, please follow the link:
$reset_url

Client IP: $client_ip
When:      $time

--PHP-alt-$random_hash
Content-Type: text/html; charset=\"iso-8859-1\"
Content-Transfer-Encoding: 7bit

<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">
<html>
<head>
    <title>$subject</title>
<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">
</head>
<body>
<h2$subject</h2> 
<p>
A request to reset the password for user $for has been received.  To reset the password, 
please follow the link <a href='$reset_url'>$reset_url</a>.
</p>
Client IP: $client_ip <br/>
When:      $time
<p>
</p>
</body>
</html>
--PHP-alt-$random_hash-- 

"; 
        @mail( $cfg['reportemail'], $subject.": ".$for, $body, $headers );
        return true;
    }    

    
    /**
     * email out password change notification
     * 
     * @param String $username
     * @param String $adminuser
     */
    public function email_passwd_change($username, $adminuser) {
        global $udiconfig;
        $cfg = $udiconfig->getConfig();
        
        // no point if there is no admin email address
        if (empty($cfg['reportemail'])) {
            return;
        }
        
        $subject = _('Password Change'); 
        $random_hash = md5(date('r', time())); 
        $headers = "From: udi@localhost\r\nReply-To: noreply@localhost"; 
        $headers .= "\r\nContent-Type: multipart/mixed; boundary=\"PHP-mixed-".$random_hash."\"";
        $client_ip = $_SERVER['REMOTE_ADDR'];
        $time = date('r', time());
        
        // now email the attempt to the admin
        $body = "
--PHP-mixed-$random_hash
Content-Type: multipart/alternative; boundary=\"PHP-alt-$random_hash\"

--PHP-alt-$random_hash
Content-Type: text/plain; charset=\"iso-8859-1\" 
Content-Transfer-Encoding: 7bit

Password has been changed for user: $username
Client IP: $client_ip
When:      $time
By:        $adminuser

--PHP-alt-$random_hash
Content-Type: text/html; charset=\"iso-8859-1\"
Content-Transfer-Encoding: 7bit

<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">
<html>
<head>
    <title>$subject</title>
<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">
</head>
<body>
<h2$subject</h2> 
<p>
Password has been changed for user: $username
</p>
Client IP: $client_ip <br/>
When:      $time <br/>
By:        $adminuser
<p>
</p>
</body>
</html>
--PHP-alt-$random_hash-- 

"; 
        @mail( $cfg['reportemail'], $subject.": ".$username, $body, $headers );
        return true;
    }    

}
?>
