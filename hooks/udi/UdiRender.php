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

	/** CORE FUNCTIONS **/

	/**
	 * Initialise and Render the UdiRender
	 */
	public function accept($norender=false) {
		if (DEBUG_ENABLED && (($fargs=func_get_args())||$fargs='NOARGS'))
			debug_log('Entered (%%)',129,0,__FILE__,__LINE__,__METHOD__,$fargs);

		if (DEBUGTMP) printf('<font size=-2>%s:%s</font><br />',time(),__METHOD__);
		if (DEBUGTMP||DEBUGTMPSUB) printf('<font size=-2>* %s [Visit-Start:%s]</font><br />',__METHOD__,get_class($this));

		$tree = get_cached_item($this->server_id,'tree');
		if (! $tree)
			$tree = Tree::getInstance($this->server_id);

		$treeitem = $tree->getEntry($this->dn);

		# If we have a DN, and no template_id, see if the tree has one from last time
		if ($this->dn && is_null($this->template_id) && $treeitem && $treeitem->getTemplate())
			$this->template_id = $treeitem->getTemplate();

		# Check that we have a valid template, or present a selection
		# @todo change this so that the modification templates rendered are the ones for the objectclass of the dn.
		if (! $this->template_id)
			$this->template_id = $this->getTemplateChoice();

		if ($treeitem)
			$treeitem->setTemplate($this->template_id);

		$this->page = get_request('page','REQUEST',false,1);

		if ($this->template_id) {
			parent::accept();

			$this->url_base = sprintf('server_id=%s&', $this->getServerID());
			//$this->url_base = sprintf('server_id=%s&dn=%s',
		//		$this->getServerID(),rawurlencode($this->template->getDN()));
			$this->layout['hint'] = sprintf('<td class="icon"><img src="%s/light.png" alt="%s" /></td><td colspan="3"><span class="hint">%%s</span></td>',
				IMGDIR,_('Hint'));
			$this->layout['action'] = '<td class="icon"><img src="%s/%s" alt="%s" /></td><td><a href="cmd.php?%s" title="%s">%s</a></td>';
			$this->layout['actionajax'] = '<td class="icon"><img src="%s/%s" alt="%s" /></td><td><a href="cmd.php?%s" title="%s" onclick="return ajDISPLAY(\'BODY\',\'%s\',\'%s\');">%s</a></td>';
			
			# If we dont want to render this template automatically, we'll return here.
			if ($norender)
				return;
		}
	}


	private function getMenuItem($item, $selected) {

		$href = sprintf('cmd=udi&udi_nav=%s&%s',$item['name'], $this->url_base);
        $layout = '<li  class="ui-state-default ui-corner-top %s"><a href="cmd.php?%s" title="%s" onclick="return ajDISPLAY(\'BODY\',\'%s\',\'%s\');"><img src="%s/%s" alt="%s" /> %s</a></li>';
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
                array('name' => 'upload', 'text' => _('Upload'), 'title' => _('Upload a file'), 'image' => 'import.png', 'imagetext' => _('Upload'),), 
                array('name' => 'process', 'text' => _('Process'), 'title' => _('Process the UDI'), 'image' => 'timeout.png', 'imagetext' => _('Process'),),
                );
        
        foreach ($menus as $item) {
            $menu .= $this->getMenuItem($item, ($active == $item['name'] ? true : false));
        }
        $menu .= "</ul></div>";
        return $menu;
    }
}
?>
