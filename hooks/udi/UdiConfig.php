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
class UdiConfig {

    public static $DEFAULTS = array(
                    'enabled' => null,
                    'move_on_delete' => null,
                    'move_to' => '',
                    'filepath' => '',
                    'dir_match_on' => 'mlepsmspersonid',
                    'import_match_on' => 'mlepsmspersonid',
                    'groups_enabled' => null,
                    'mappings' => 'mlepSmsPersonId(mlepSmsPersonId);mlepStudentNSN(mlepStudentNSN);mlepUsername(mlepUsername,uid);mlepFirstAttending(mlepFirstAttending);mlepLastAttendance(mlepLastAttendance);mlepFirstName(mlepFirstName,givenName);mlepLastName(mlepLastName,sn);mlepRole(mlepRole);mlepAssociatedNSN(mlepAssociatedNSN);mlepEmail(mlepEmail,mail);mlepOrganisation(mlepOrganisation,o)',
                    'group_mappings' => '',
                    'group_attr' => '',
                    );
    
    private $server;

    private $base;
    
    private $config = null;
    
	# Config DN
    protected $configdnname = 'cn=UDIConfig';
    protected $configbackupdnname = 'cn=UDIConfig.backup';
    protected $configdn;
    protected $configbackupdn;

    public function __construct($server) {
        if (defined('DEBUG_ENABLED') && DEBUG_ENABLED && (($fargs=func_get_args())||$fargs='NOARGS'))
            debug_log('Entered (%%)',17,0,__FILE__,__LINE__,__METHOD__,$fargs);

        $this->server = $server;
        $base = $this->server->getBaseDN();
        $this->base = $base[0];
        $this->configdn = $this->configdnname.','.$this->base;
        $this->configbackupdn = $this->configbackupdnname.','.$this->base;
    }
	

	/**
	 * Get the base DN
	 */
	public function getBaseDN() {
        return $this->base;	    
	}

	/**
     * Get the Config base DN
     */
    public function getConfigDN() {
        return $this->configdn;        
    }

    /**
     * Get the Config backup base DN
     */
    public function getConfigBackupDN() {
        return $this->configbackupdn;        
    }
    
    /**
     * Get the Config
     */
    public function getConfig($force=false) {
        if (!$force && $this->config) {
            return $this->config;
        }

        $this->config = array();
        $query = $this->server->query(array('base' => $this->configdn, 'attrs' => array('description')), 'login');
        $this->unpackConfig($query);
        return $this->config;
    }

    /**
     * Set a config value
     */
    public function setConfig($field, $value=false) {

        if (!$value) {
            unset($this->config[$field]);
            return $value;
        }
        else {
            $this->config[$field] = $value;
            return $this->config[$field];
        }
    }

    /**
     * Set a config value
     */
    public function setConfigCheckBox($field, $value=false) {

        if ($value === null) {
            return $this->setConfig($field);
        }
        else {
            return $this->setConfig($field, 'checked');
        }
    }


    /**
     * Get the mapping config value
     */
    public function getMappings() {
        $cfg_mappings = array();
        $this->getConfig();
        if (isset($this->config['mappings'])) {
            $mappings = explode(';', $this->config['mappings']);
            foreach ($mappings as $map) {
                list($source, $targets) = explode('(', $map);
                if (empty($source) || empty($targets)) {
                    continue;
                }
                $targets = preg_replace('/^(.*?)\)$/','$1', $targets);
                $targets = explode(',', $targets);
                $cfg_mappings []= array('source' => $source, 'targets' => $targets);
            }
        }
        return $cfg_mappings;
    }


    /**
     * Set the mapping config value
     */
    public function updateMappings($mappings) {
        $maps = array();
        foreach ($mappings as $map) {
            $maps[]= $map['source'].'('.implode(',', $map['targets']).')';
        }
        $map = implode(';', $maps);
        $this->setConfig('mappings', $map);
        return $this->updateConfig();
    }


    /**
     * Get the group mapping config value
     */
    public function getGroupMappings() {
        $cfg_mappings = array();
        $this->getConfig();
        if (isset($this->config['group_mappings']) && !empty($this->config['group_mappings'])) {
            $mappings = explode(';', $this->config['group_mappings']);
            foreach ($mappings as $map) {
                list($source, $targets) = explode('(', $map);
                if (empty($source)) {
                    continue;
                }
                $targets = preg_replace('/^(.*?)\)$/','$1', $targets);
                $targets = preg_grep('/^$/', explode('|', $targets), PREG_GREP_INVERT);
                $cfg_mappings []= array('source' => $source, 'targets' => $targets);
            }
        }
        return $cfg_mappings;
    }


    /**
     * Set the group mapping config value
     */
    public function updateGroupMappings($mappings) {
        $maps = array();
        foreach ($mappings as $map) {
            $maps[]= $map['source'].'('.implode('|', $map['targets']).')';
        }
        $map = implode(';', $maps);
        $this->setConfig('group_mappings', $map);
        return $this->updateConfig();
    }

    /**
     * Update the Config
     */
    public function updateConfig() {

        $attrs = array('description' => array());
        foreach ($this->config as $k => $v) {
            $attrs['description'][]= "$k=$v";
        }
        $result = $this->server->modify($this->configdn, $attrs);
        return $this->getConfig(true);
    }

    /**
     * Create a config DN node
     */
    private function createConfigDn($dn) {

        // check that the backup DN exists
        $query = $this->server->query(array('base' => $dn, 'attrs' => array('description')), 'login');
        if (empty($query)) {
             if (!$this->server->add($dn, array('objectClass' => 'top', 'objectClass' => 'applicationProcess'))) {
                system_message(array(
                             'title'=>_('UDI Configuration backup Failed'),
                                        'body'=> _('Failed to create configuration node: '.$dn.' - check server write permissions'),
                                        'type'=>'error'));
                return false;
             };
        }
        return true;
    }

    /**
     * backup the Config
     */
    public function backupConfig() {

        // check that the backup DN exists
        if (!$this->createConfigDn($this->configbackupdn)) {
            return false;
        }

        $attrs = array('description' => array());
        foreach ($this->config as $k => $v) {
            $attrs['description'][]= "$k=$v";
        }
        $result = $this->server->modify($this->configbackupdn, $attrs);
        return $this->getConfig(true);
    }

    /**
     * unpack the Config
     */
    private function unpackConfig($query) {
        if (!empty($query)) {
            $query = array_pop($query);
            if (isset($query['description'])) {
                foreach ($query['description'] as $attr) {
                    if (preg_match('/.*?\=/', $attr)) {
                        $config_var = explode('=', $attr, 2);
                        $this->config[$config_var[0]] = $config_var[1];
                    }
                }
            }            
        }
        foreach (self::$DEFAULTS as $var => $default) {
            if (!isset($this->config[$var])) {
                $this->config[$var] = $default;
            }
        }
    }

    /**
     * Restore the Config
     */
    public function restoreConfig() {

        $this->config = array();
        $query = $this->server->query(array('base' => $this->configbackupdn, 'attrs' => array('description')), 'login');
        $this->unpackConfig($query);
        return $this->updateConfig();
    }
}
?>
