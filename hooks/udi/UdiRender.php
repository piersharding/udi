<?php
/**
 * This class will render the page for the UDI.
 *
 * @author The phpLDAPadmin development team
 * @package phpLDAPadmin
 */

/**
 * UdiRender class
 *
 * @package phpLDAPadmin
 * @subpackage Templates
 */
class UdiRender extends PageRender {
	# Page number
	private $pagelast;
	
	private $udiconfig = false;
	
	private $logfile = false;

	public $messages = array();
	
	public function setConfig($config) {
	    $this->udiconfig = $config;
	}
	
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
	 * Generate the output formated messages
	 */
    public function outputMessagesConsole() {
        $msgs = array();
        foreach ($_SESSION['sysmsg'] as $msg) {
            if (!preg_match('/DEBUG/', $msg['body'])) {
                $msg['body'] = preg_replace('/\<.*?\>/', ' ', $msg['body']);
                $msg['body'] = preg_replace('/  /', ' ', $msg['body']);
                $msgs []= $msg['type'].": ".trim($msg['body']);
            }
        }
        return implode("\n", $msgs);
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
	 * Initialise and Render the UdiRender
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

		$href = sprintf('cmd=udi&udi_nav=%s&%s',$item['name'], $this->url_base);
		$after_function = isset($item['onload']) ? $item['onload'].';' : '';
        $layout = '<li  class="ui-state-default ui-corner-top %s"><a href="cmd.php?%s" title="%s" onclick="var res = ajDISPLAY(\'BODY\',\'%s\',\'%s\'); '.$after_function.' return res;"><img src="%s/%s" alt="%s" /> %s</a></li>';
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
        $menu = "<div id='udi-menu' class='ui-tabs ui-widget ui-widget-content ui-corner-all'>";
        $menu .= '<ul class="tabset_tabs ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header ui-corner-all">';
        $menus = array(
                array('name' => 'admin', 'text' => _('Configuration'), 'title' => _('Configure the UDI'), 'image' => 'tools.png', 'imagetext' => _('Admin'),),
                array('name' => 'mapping', 'text' => _('Mapping'), 'title' => _('Perform data mapping'), 'image' => 'add.png', 'imagetext' => _('Mapping'),), 
                array('name' => 'userpass', 'text' => _('User & Pass'), 'title' => _('UserId & Passwd processing control'), 'image' => 'key.png', 'imagetext' => _('UserId & Passwd'),), 
                array('name' => 'upload', 'text' => _('Upload'), 'title' => _('Upload a file'), 'image' => 'import.png', 'imagetext' => _('Upload'),), 
                array('name' => 'process', 'text' => _('Processing'), 'title' => _('Process the UDI'), 'image' => 'timeout.png', 'imagetext' => _('Process'),),
                array('name' => 'reporting', 'text' => _('Reporting'), 'title' => _('Reporting on the UDI'), 'image' => 'files-small.png', 'imagetext' => _('Reporting'), ), 
                array('name' => 'help', 'text' => _('Help'), 'title' => _('UDI Help'), 'image' => 'help-small.png', 'imagetext' => _('Help'),),
                );
        
        foreach ($menus as $item) {
            $menu .= $this->getMenuItem($item, ($active == $item['name'] ? true : false));
        }
        $menu .= "</ul></div>";
        return $menu;
    }
    
    private static    $skip_fields = array('objectclass', 'dn');
    
    public function reportSummary($report) {
        $header = $report['header'];
        $footer = $report['footer'];
        $messages = $report['messages'];
        $table = '<table class="udi-report-container"><tr><td class="udi-report-header-collapse"><span style="display: none;" id="udi-report-'.$header['id'].'-collapse" ><a href="" onclick="udi_report_toggle(\''.'udi-report-'.$header['id'].'\'); return false;"><img src="'.IMGDIR.'/collapse.png" alt="Toggle"/></a></span></td>';
        $table .= '<td id="udi-report-'.$header['id'].'" class="udi-report"><table class="udi-report"><tr class="udi-report-header"><td class="udi-report-header-left">'._('Action: ').$header['action'].'</td><td class="udi-report-middle">';
        $table .= _('Who: ').$header['user'].' &nbsp; '.
                  _('Mode: ').$header['mode'].' &nbsp; '.
                  _('Started: ').$header['time']; //.' &nbsp; '.$report['file'];
        $table .= '</td><td class="far-right"><a href="" onclick="udi_report_toggle(\''.'udi-report-'.$header['id'].'\'); return false;"><img src="'.IMGDIR.'/help.png" alt="Toggle"/></a></td></tr>';
        $cnt = 0;
        $errors = false;
        $data_tables = array();
        foreach ($report['messages'] as $message) {
            if (!in_array($message['type'], array('error', 'warning', 'info', 'debug'))) {
                $table_data = false;
                eval('$table_data = ' . $message['message'] . ';');
                if ($table_data) {
                    if (!isset($data_tables[$message['type']])) {
                        $data_tables[$message['type']] = array();
                    }
                    $data_tables[$message['type']] []= $table_data;
                }
                continue;
            }
            $cnt++;
            if ($message['type'] == 'error') {
                $errors = true;
            }
            $class = 'udi-report-'.$message['type'];
            $id = 'udi-report-'.$header['id'].'-'.$cnt;
            $table .= '<tr id="'.$id.'" style="display: none;" class="udi-report-message '.$class.'"><td class="udi-report-left">'.$message['type'].'</td><td colspan="2">'.$message['message'].'</td></tr>';
        }
        
        // add the table data to the report
        if (!empty($data_tables)) {
            foreach ($data_tables as $table_description => $table_lines) {  
                $first = $table_lines[0];
                $table_data = '<table class="udi-report-tabledata"><tr class="udi-report-tabledata-header">';
                // add the header line
                foreach ($first as $field => $value) {
                    if (in_array($field, self::$skip_fields)) {
                        continue;
                    }
                    $table_data .= '<td class="udi-report-tabledata">'.$field.'</td>';
                }
                $table_data .= '</tr>';
                // add the rows
                foreach ($table_lines as $row) {
                    $table_data .= '<tr>';
                    foreach ($first as $field => $discard) {
                        if (in_array($field, self::$skip_fields)) {
                            continue;
                        }
                        $value = isset($row[$field]) ? $row[$field] : '###';
                        $table_data .= '<td class="udi-report-tabledata">'.$value.'</td>';
                    }
                    $table_data .= '</tr>';
                }
                $table_data .= '</table>';
                $cnt++;
                $class = 'udi-report-info';
                $id = 'udi-report-'.$header['id'].'-'.$cnt;
                $table .= '<tr id="'.$id.'" style="display: none;" class="udi-report-message '.$class.'"><td class="udi-report-left">'.$table_description.'</td><td colspan="2">'.$table_data.'</td></tr>';
            }
        }
        
        if ($footer) {
            if ($errors) {
                $table .= '<tr class="udi-report-footer udi-report-error">';
            }
            else {
                $table .= '<tr class="udi-report-footer udi-report-complete">';
            }
            $table .= '<td class="udi-report-left">'._('Action: ').$header['action'].'</td>'.       
                      '<td colspan="2">'._('Finished: ').$footer['time'].'</td>';
            $table .= '</tr>';
        }
        else {
            $table .= '<tr class="udi-report-footer udi-report-error"><td colspan="3" class="udi-report-error">'._('processing step failed to complete (is it still running?)').'</td></tr>';
        }
        $table .= '</table></td></tr></table>';
        return $table;
    }

    
    public function emailSummary($report) {
        $cssclasses = array(
                            'udi-report-info' => 'background: #ffffff;',
                            'udi-report-warning' => 'background: #fff194;',
                            'udi-report-error' => 'background: #ffd6d6;',
                            'udi-report-complete' => 'background: #c1f9a5;',
                            'udi-report-header' => 'background: #a2cffd;',
                            'udi-report-left' => 'white-space: nowrap; vertical-align: top; padding-right: 10px;',
                            'udi-report-container' => 'margin: 0; border-spacing: 0px;',
                            'udi-report-tabledata' => 'text-align: left; vertical-align: top; margin: 0; border-spacing: 0px;',
                            'udi-report-tabledata-header' => 'border: 1px solid #AAC; padding: 10px; border-spacing: 0px; vertical-align: top;',
                            'udi-report' => 'border: 1px solid #AAC; padding: 10px; border-spacing: 0px; vertical-align: top;',
                            );
            
                            
        $header = $report['header'];
        $footer = $report['footer'];
        $messages = $report['messages'];
        $table = '<table width="100%" cell-padding="2px" cell-spacing="0px" class="udi-report-container"><tr><td></td>';
        $table .= '<td id="udi-report-'.$header['id'].'" style="'.$cssclasses['udi-report'].'"><table  width="100%" cell-padding="0px" cell-spacing="0px" style="'.$cssclasses['udi-report'].'"><tr  style="'.$cssclasses['udi-report-header'].'"><td style="'.$cssclasses['udi-report-header-left'].'">'._('Action: ').$header['action'].'</td><td style="'.$cssclasses['udi-report-header-middle'].'">';
        $table .= _('Who: ').$header['user'].' &nbsp; '.
                  _('Mode: ').$header['mode'].' &nbsp; '.
                  _('Started: ').$header['time']; //.' &nbsp; '.$report['file'];
        $table .= '</td><td class="far-right"></td></tr>';
        $cnt = 0;
        $errors = false;
        $data_tables = array();
        foreach ($report['messages'] as $message) {
            if (!in_array($message['type'], array('error', 'warning', 'info', 'debug'))) {
                $table_data = false;
                eval('$table_data = ' . $message['message'] . ';');
                if ($table_data) {
                    if (!isset($data_tables[$message['type']])) {
                        $data_tables[$message['type']] = array();
                    }
                    $data_tables[$message['type']] []= $table_data;
                }
                continue;
            }
            $cnt++;
            if ($message['type'] == 'error') {
                $errors = true;
            }
            $class = 'udi-report-'.$message['type'];
            $id = 'udi-report-'.$header['id'].'-'.$cnt;
            $table .= '<tr id="'.$id.'" style="'.$cssclasses[$class].'"><td  valign="top" style="'.$cssclasses['udi-report-left'].'">'.$message['type'].'</td><td colspan="2">'.$message['message'].'</td></tr>';
        }
        
        // add the table data to the report
        if (!empty($data_tables)) {
            foreach ($data_tables as $table_description => $table_lines) {  
                $first = $table_lines[0];
                $table_data = '<table  width="100%" cell-padding="0px" cell-spacing="0px"  style="'.$cssclasses['udi-report-tabledata'].'"><tr  style="'.$cssclasses['udi-report-tabledata-header'].'">';
                // add the header line
                foreach ($first as $field => $value) {
                    if (in_array($field, self::$skip_fields)) {
                        continue;
                    }
                    $table_data .= '<td  style="'.$cssclasses['udi-report-tabledata'].'">'.$field.'</td>';
                }
                $table_data .= '</tr>';
                // add the rows
                foreach ($table_lines as $row) {
                    $table_data .= '<tr>';
                    foreach ($first as $field => $discard) {
                        if (in_array($field, self::$skip_fields)) {
                            continue;
                        }
                        $table_data .= '<td  style="'.$cssclasses['udi-report-tabledata'].'">'.$row[$field].'</td>';
                    }
                    $table_data .= '</tr>';
                }
                $table_data .= '</table>';
                $cnt++;
                $class = 'udi-report-info';
                $id = 'udi-report-'.$header['id'].'-'.$cnt;
                $table .= '<tr id="'.$id.'" style="display: none;"  style="'.$cssclasses['udi-report-message'].$cssclasses[$class].'"><td  valign="top" class="udi-report-left">'.$table_description.'</td><td colspan="2">'.$table_data.'</td></tr>';
            }
        }
        
        if ($footer) {
            if ($errors) {
                $table .= '<tr style="'.$cssclasses['udi-report-footer'].$cssclasses['udi-report-error'].'">';
            }
            else {
                $table .= '<tr style="'.$cssclasses['udi-report-footer'].$cssclasses['udi-report-complete'].'">';
            }
            $table .= '<td  valign="top" style="'.$cssclasses['udi-report-left'].'">'._('Action: ').$header['action'].'</td>'.       
                      '<td colspan="2">'._('Finished: ').$footer['time'].'</td>';
            $table .= '</tr>';
        }
        else {
            $table .= '<tr style="'.$cssclasses['udi-report-footer'].$cssclasses['udi-report-error'].'"><td colspan="3"  style="'.$cssclasses['udi-report-error'].'">'._('processing step failed to complete (is it still running?)').'</td></tr>';
        }
        $table .= '</table></td></tr></table>';
        return $table;
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

    public function configChooser($name) {
        ob_start();
        draw_chooser_link('udi_form.'.$name, false);
        $chooser = ob_get_contents();
        ob_end_clean();
        return $chooser;
    }

    public function configMoreField($name, $text, $attrs = array(), $chooser=false, $link_text=false) {
        $add_name = $name.'_ADD';
        $desc = $link_text ? '&nbsp;'.$text : '';
        $field = '
        <a href="" title="'._('Add '.$text).'" onclick="if (getDiv(\''.$add_name.'\').style.display == \'block\'){getDiv(\''.$add_name.'\').style.display = \'none\';}else{getDiv(\''.$add_name.'\').style.display = \'block\';};return false;"><img src="images/udi/add.png" alt="'._('Add '.$text).'"/>'.$desc.'</a></div>
        <div id="aj'.$add_name.'" class="ftext" style="display: none; float:left; clear:both;"><table style="margin-left: 0px;"><tbody><tr>
        <td><fieldset><legend>'.$text.'</legend>
        <div id="aj'.$add_name.'ATTR"><table cellspacing="0" border="0"><tbody><tr><td valign="top"><span style="white-space: nowrap;">';
        $field .= $this->configField($name, $attrs, array());
        if ($chooser) {
            $field .= $this->configChooser($name);
        }
        $field .= '</span></td></tr></tbody></table></div></fieldset></td></tr></tbody></table>';
        return $field;
    }

    public function configMoreSelect($name, $text, $attrs, $container = array('class' => 'felement ftext')) {
        $opts = array();
        foreach  ($container as $k => $v) {
            $opts[]= "$k='$v'";
        }
        $container_opts = implode(' ', $opts);
        $add_name = $name.'_ADD';
        $field = '';
        if (!empty($container_opts)) {
            $field .= '<div '.$container_opts.'>';
        }
        $field .= '
        <a href="" title="'._('Add '.$text).'" onclick="if (getDiv(\''.$add_name.'\').style.display == \'block\'){getDiv(\''.$add_name.'\').style.display = \'none\';}else{getDiv(\''.$add_name.'\').style.display = \'block\';};return false;"><img src="images/udi/add.png" alt="'._('Add '.$text).'"/></a></div>
        <div id="aj'.$add_name.'" class="felement ftext" style="display: none; "><table style="margin-left: 0px;"><tbody><tr>
        <td><fieldset><legend>'.$text.'</legend>
        <div id="aj'.$add_name.'ATTR"><table cellspacing="0" border="0"><tbody><tr><td valign="top"><span style="white-space: nowrap;">';
        $field .= $this->configSelect($name, $attrs, array());
        $field .= '</span></td></tr></tbody></table></div></fieldset></td></tr></tbody></table>';
        if (!empty($container_opts)) {
             $field .= '</div>';
        }
        return $field;
    }

    public function configMoreOrSelect($name, $text, $attrs, $opts) {
        $add_name = $name.'_ADD';
        $field = '<div class="felement ftext">
        <a href="" title="'._('Add '.$text).'" onclick="if (getDiv(\''.$add_name.'\').style.display == \'block\'){getDiv(\''.$add_name.'\').style.display = \'none\';}else{getDiv(\''.$add_name.'\').style.display = \'block\';};return false;"><img src="images/udi/add.png" alt="'._('Add '.$text).'"/>&nbsp;'.$text.'</a></div>
        <div id="aj'.$add_name.'" class="felement ftext" style="display: none; "><table><tbody><tr>
        <td><fieldset><legend>'.$text.'</legend>
        <div id="aj'.$add_name.'ATTR"><table cellspacing="0" border="0"><tbody><tr><td valign="top"><span style="white-space: nowrap;">';
        $field .= $this->configSelect($name.'_select', $opts, array());
        $field .= '&nbsp;'._('or').'&nbsp;';
        $field .= $this->configField($name.'_field', $attrs, array());
        $field .= '</span></td></tr></tbody></table></div></fieldset></td></tr></tbody></table></div>';
        return $field;
    }


    public function configSelect($name, $attrs = array(), $default = 'none', $class='') {
        if (!empty($class)) {
            $class = " class='$class' ";
        }
        $select = '<select '.$class.' name="'.$name.'">';
        foreach ($attrs as $attr) {
            $opt_name = $attr->getName(false);
            $opt_text = $attr->getName(false);
            if (empty($opt_name)) {
                $opt_name = 'none';
                $opt_text = _(' - unselected - ');
            }
            $select .= sprintf('<option value="%s" %s>%s</option>', 
                    $opt_name,strtolower($opt_name) == strtolower("".$default) ? 'selected ': '',$opt_text);
        }   
        $select .= '</select>';
        return $select;
    }


    public function configSelectBasic($name, $opts = array(), $default = 'none', $class='') {
        if (!empty($class)) {
            $class = " class='$class' ";
        }
        $select = '<select '.$class.' name="'.$name.'">';
        foreach ($opts as $label => $opt) {
            if (empty($label)) {
                $label = 'none';
                $opt = _(' - unselected - ');
            }
            $select .= sprintf('<option value="%s" %s>%s</option>', 
                    $label,strtolower($label) == strtolower("".$default) ? 'selected ': '',$opt);
        }   
        $select .= '</select>';
        return $select;
    }
    
    public function configSelectEntry($name, $text, $attrs = array(), $default='mlepSmsPersonId', $required=true) {

        return  $this->configRow($this->configFieldLabel($name, $text), '<div class="felement ftext">'.$this->configSelect($name, $attrs, $default).'</div>', $required);
    }
    
    public function configSelectEntryBasic($name, $text, $opts = array(), $default='none', $required=true) {

        return  $this->configRow($this->configFieldLabel($name, $text), '<div class="felement ftext">'.$this->configSelectBasic($name, $opts, $default).'</div>', $required);
    }
    
    public function info($msg, $action='') {
        $this->messages[]= array('type' => 'info', 'message' => $msg);
        $this->log_to_file('info', $msg);
        system_message(array(
                     'title'=>_('UDI '.$action),
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
                     'title'=>_('UDI '.$action),
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
                     'title'=>_('UDI '.$action),
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
    
    public function email_report() {
        if ($this->udiconfig && $this->logfile) {
            $cfg = $this->udiconfig->getConfig();
            $report = read_report($this->logfile);
            $report = $this->emailSummary($report);
            // series of nasty hacks because css is not honoured in email clients
            $to = $cfg['reportemail']; 
            $subject = _('UDI Processing report'); 
            $random_hash = md5(date('r', time())); 
            $headers = "From: udi@localhost\r\nReply-To: noreply@localhost"; 
            $headers .= "\r\nContent-Type: multipart/mixed; boundary=\"PHP-mixed-".$random_hash."\"";
            $body = "
--PHP-mixed-$random_hash
Content-Type: multipart/alternative; boundary=\"PHP-alt-$random_hash\"

--PHP-alt-$random_hash
Content-Type: text/plain; charset=\"iso-8859-1\" 
Content-Transfer-Encoding: 7bit

This report is only visible in HTML format 

--PHP-alt-$random_hash
Content-Type: text/html; charset=\"iso-8859-1\"
Content-Transfer-Encoding: 7bit

<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">
<html>
<head>
    <title>UDI Processing Report</title>
<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">
</head>
<body>
<h2>UDI Processing report</h2> 
$report
</body>
</html>
--PHP-alt-$random_hash-- 

"; 
            $mail_sent = @mail( $to, $subject, $body, $headers ); 
        }
    }
    
    public function outputMessages() {
        $msgs = '';
        foreach ($this->messages as $msg) {
            $msgs.= $msg;
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
        $out .=  '<form action="cmd.php" method="post">';
        $out .= sprintf('<input type="hidden" name="server_id" value="%s" />', $app['server']->getIndex());
        foreach ($params as $k => $v) {
            $out .= sprintf('<input type="hidden" name="%s" value="%s" />', $k, $v);
        }
        $out .=  '<input type="hidden" name="confirm" value="yes" />';
        $out .= sprintf('<input type="submit" name="submit" value="%s" />', $action);
        $out .=  '</form>';
        $out .=  '</td><td>';
        $out .=  '<form action="cmd.php" method="post">';
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
    
}
?>
