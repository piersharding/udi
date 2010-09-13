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
           'ad' => array(
                    'enabled' => null,
                    'ignore_deletes' => null,
                    'ignore_creates' => null,
                    'ignore_updates' => null,
                    'enable_reporting' => null,
                    'move_on_delete' => 'checked',
                    'ignore_userids' => null,
                    'ignore_passwds' => null,
                    'move_to' => '',
                    'filepath' => '',
                    'reportpath' => '',
                    'reportemail' => '',
                    'dir_match_on' => 'mlepsmspersonid',
                    'import_match_on' => 'mlepsmspersonid',
                    'groups_enabled' => null,
                    'mappings' => 'mlepSmsPersonId(mlepSmsPersonId);mlepStudentNSN(mlepStudentNSN);mlepUsername(mlepUsername,sAMAccountName,uid);mlepFirstAttending(mlepFirstAttending);mlepLastAttendance(mlepLastAttendance);mlepFirstName(mlepFirstName,givenName);mlepLastName(mlepLastName,sn);mlepRole(mlepRole);mlepAssociatedNSN(mlepAssociatedNSN);mlepEmail(mlepEmail,mail);mlepOrganisation(mlepOrganisation)',
                    'group_mappings' => '',
                    'container_mappings' => '',
                    'group_attr' => 'member',
                    'create_in' => '',
                    'dn_attribute' => 'cn',
                    'objectclasses' => 'user;mlepPerson;securityPrincipal',
                    'ignore_attrs' => 'mlepUsername;samaccountname;uid',
                    'userid_algo' => 'userid_alg_03_userid_algorithm',
                    'userid_parameters' => '%[mlepUsername]',
                    'encrypt_passwd' => 'md5',
                    'passwd_algo' => 'passwd_alg_01_passwd_algorithm',
                    'passwd_parameters' => 'pass',
                    'search_bases' => '',
                    'next_seq_no' => 0,
                    'udi_version' => '1.2.0.5',
                    'server_type' => 'ad',
            ),
           'default' => array(
                    'enabled' => null,
                    'ignore_deletes' => null,
                    'ignore_creates' => null,
                    'ignore_updates' => null,
                    'enable_reporting' => null,
                    'move_on_delete' => 'checked',
                    'ignore_userids' => null,
                    'ignore_passwds' => null,
                    'move_to' => '',
                    'filepath' => '',
                    'reportpath' => '',
                    'reportemail' => '',
                    'dir_match_on' => 'mlepsmspersonid',
                    'import_match_on' => 'mlepsmspersonid',
                    'groups_enabled' => null,
                    'mappings' => 'mlepSmsPersonId(mlepSmsPersonId);mlepStudentNSN(mlepStudentNSN);mlepUsername(mlepUsername,uid);mlepFirstAttending(mlepFirstAttending);mlepLastAttendance(mlepLastAttendance);mlepFirstName(mlepFirstName,givenName);mlepLastName(mlepLastName,sn);mlepRole(mlepRole);mlepAssociatedNSN(mlepAssociatedNSN);mlepEmail(mlepEmail,mail);mlepOrganisation(mlepOrganisation)',
                    'group_mappings' => '',
                    'container_mappings' => '',
                    'group_attr' => 'memberUid',
                    'create_in' => '',
                    'dn_attribute' => 'cn',
                    'objectclasses' => 'inetOrgPerson;mlepPerson',
                    'ignore_attrs' => 'mlepUsername;uid',
                    'userid_algo' => 'userid_alg_03_userid_algorithm',
                    'userid_parameters' => '%[mlepUsername]',
                    'encrypt_passwd' => 'md5',
                    'passwd_algo' => 'passwd_alg_01_passwd_algorithm',
                    'passwd_parameters' => 'pass',
                    'search_bases' => '',
                    'next_seq_no' => 0,
                    'udi_version' => '1.2.0.5',
                    'server_type' => 'default',
            ),            
    );
    
    private $server;

    private $base;
    
    public $config = null;
    
	# Config DN
    protected $configouname = 'OU=UDIConfig';
    protected $configdnname = 'cn=UDIConfig';
    protected $configbackupdnname = 'cn=UDIConfig.backup';
    protected $configou;
    protected $configdn;
    protected $configbackupdn;

    public function __construct($server) {
        if (defined('DEBUG_ENABLED') && DEBUG_ENABLED && (($fargs=func_get_args())||$fargs='NOARGS'))
            debug_log('Entered (%%)',17,0,__FILE__,__LINE__,__METHOD__,$fargs);

        $this->server = $server;
        $base = $this->server->getBaseDN();
        $this->base = $base[0];
        $this->configdn = $this->configdnname.','.$this->configouname.','.$this->base;
        $this->configbackupdn = $this->configbackupdnname.','.$this->configouname.','.$this->base;
        $this->configou = $this->configouname.','.$this->base;
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
        $query = $this->server->query(array('base' => $this->configdn, 'attrs' => array('description')), 'user');
        $this->unpackConfig($query);
        return $this->config;
    }
    

    /**
     * Get the next sequential number
     */
    public function nextNumber() {
        $seq = $this->config['next_seq_no'] + 1;
        $this->setConfig('next_seq_no', $seq);
        $this->updateConfig();
        return $seq;
    }
    
    
    /**
     * Validate the Config
     */
    public function validate($all=false) {
        global $request;
        
        $valid = true;
        
        // prepoulate defaults
//        foreach (self::$DEFAULTS as $var => $default) {
//            if (!isset($this->config[$var])) {
//                $this->config[$var] = $default;
//            }
//        }
                
        // validate the file path - must exist
        if (preg_match('/^http/', $this->config['filepath'])) {
            $hdrs = get_headers($this->config['filepath']);
            if (!preg_match('/^HTTP.*? 200 .*?OK/', $hdrs[0])) {
                $request['page']->warning(_('Source import URL does not exist: ').$this->config['filepath'], _('configuration'));
            }
        } 
        else if (!file_exists($this->config['filepath'])) {
            $request['page']->warning(_('Source import file does not exist: ').$this->config['filepath'], _('configuration'));
        }
        
        if (isset($this->config['enable_reporting']) && $this->config['enable_reporting'] == 'checked') {
            // validate reporting email address
            if (empty($this->config['reportemail'])) {
                $request['page']->warning(_('Reporting email address is empty'), _('configuration'));
            }
            else if (!preg_match('#^[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+'.
                 '(\.[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+)*'.
                  '@'.
                  '[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+\.'.
                  '[-!\#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+$#',
                  $this->config['reportemail'])) {
                $request['page']->error(_('Reporting email address is invalid: ').$this->config['reportemail'], _('configuration'));
                $valid = false;
            }
            
            // create missing directory
            if (empty($this->config['reportpath'])) {
                $request['page']->warning(_('Reporting file path is empty'), _('configuration'));
            }
            else {
                if (!file_exists($this->config['reportpath'])) {
                    if (!mkdir($this->config['reportpath'], 0755)) {
                        $request['page']->error(_('Reporting file path does not exist, and could not be created: ').$this->config['reportpath'], _('configuration'));
                        $valid = false;
                    }
                }
                // check permissions
                if (!is_writable($this->config['reportpath'])) {
                    $request['page']->error(_('Reporting file path cannot be written to: ').$this->config['reportpath'], _('configuration'));
                    $valid = false;
                }
            }
        }
        
        // get the search bases
        if (isset($this->config['search_bases'])) {
            $bases = explode(';', $this->config['search_bases']);
            foreach ($bases as $base) {
                if (!empty($base)) {
                    check_search_base($base) ? true : $valid = false;
                }
            }
        }
        else {
            $request['page']->warning(_('Search bases are empty - the UDI cannot run without them set'), _('configuration'));
        }
        
        // The create in bucket for new accounts
        if (!empty($this->config['create_in'])) {
            if (check_dn_exists($this->config['create_in'], _('Create new accounts target DN does not exist: ').$this->config['create_in'])) {
                // check through search bases to see if this is equal to or child of
                $found = false;
                foreach ($bases as $base) {
                    if (preg_match('/'.get_canonical_name($base).'$/', get_canonical_name($this->config['create_in']))) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $valid = $request['page']->error(_('Create new accounts target DN must be within one of the search bases: ').$this->config['create_in'], _('configuration'));
                }
            }
            else {
                $valid = false;
            }
        }
        
        // validate the target DN for moving deletes
        if (!isset($this->config['ignore_deletes']) || $this->config['ignore_deletes'] != 'checked') {
            if (isset($this->config['move_on_delete']) && $this->config['move_on_delete'] !== null) {
                if (isset($this->config['move_to']) && check_dn_exists($this->config['move_to'], _('Target delete DN does not exist: ').$this->config['move_to'])) {
                    // check that deletes are not going to the new bucket or one of the search bases
                    if ($this->config['move_to'] == $this->config['create_in']) {
                        $request['page']->error(_('Target delete DN must be different from the create new accounts target: ').$this->config['create_in'], _('configuration'));
                        $valid = false;
                    }
                    else if (in_array($this->config['move_to'], $bases)){
                        $request['page']->error(_('Target delete DN must not be one of the search bases: ').$this->config['move_to'], _('configuration'));
                        $valid = false;   
                    }
                }
                else {
                    $request['page']->warning(_('Target delete DN is not set, but move accounts on delete is'), _('configuration'));
//                    $valid = false;
                }
            }
        }
        
        // get the objectClasses
        $classes = explode(';', $this->config['objectclasses']);
        foreach ($classes as $class) {
            if (!empty($class)) {
                check_objectclass($class) ? true : $valid = false;
            }
        }
        
        // validate container mappings
        $container_mappings = $this->getContainerMappings();
        foreach ($container_mappings as $mapping) {
            if (empty($mapping['target'])) {
                continue;
            }
            if (!check_dn_exists($mapping['target'], _('Target container DN does not exist: ').$mapping['target'])) {
                $valid = false;
            }
            $found = false;
            foreach ($bases as $base) {
                if (preg_match('/^.*?'.$base.'$/', $mapping['target'])) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $request['page']->error(_('Target container DN must be within one of the search bases: ').$mapping['target'], _('configuration'));
                $valid = false;
            }
        }
        
        if ($all) {
            if ((!isset($this->config['ignore_userids']) || $this->config['ignore_userids'] != 'checked') && $this->config['userid_algo'] != 'none') {
                if (!function_exists($this->config['userid_algo'])) {
                    $request['page']->error(_('User Id algorithm does not exist: ').$this->config['userid_algo']);
                    $valid = false;
                }
            }
            if ((!isset($this->config['ignore_passwds']) || $this->config['ignore_passwds'] != 'checked') && $this->config['passwd_algo'] != 'none') {
                if (!function_exists($this->config['passwd_algo'])) {
                    $request['page']->error(_('Password algorithm does not exist: ').$this->config['passwd_algo']);
                    $valid = false;
                }
            }
        }
        return $valid;
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
     * Set a config checkbox value
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
     * Get the objectclasses config value
     */
    public function getObjectClasses() {
        $cfg_objectclasses = array();
        $this->getConfig();
        if (isset($this->config['objectclasses'])) {
            $cfg_objectclasses = explode(';', $this->config['objectclasses']);
        }
        return $cfg_objectclasses;
    }


    /**
     * Get the ignore attrs config value
     */
    public function getIgnoreAttrs() {
        $cfg_ignore_attrs = array();
        $this->getConfig();
        if (isset($this->config['ignore_attrs'])) {
            $cfg_ignore_attrs = explode(';', $this->config['ignore_attrs']);
        }
        return $cfg_ignore_attrs;
    }
    

    /**
     * Set the objectclasses config value
     */
    public function updateObjectClasses($objectclasses) {
        $objectclasses = implode(';', $objectclasses);
        $this->setConfig('objectclasses', $objectclasses);
        return $this->updateConfig();
    }
   

    /**
     * Set the ignore_attrs config value
     */
    public function updateIgnoreAttrs($attrs) {
        $attrs = implode(';', $attrs);
        $this->setConfig('ignore_attrs', $attrs);
        return $this->updateConfig();
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
     * Get the container mapping config value
     */
    public function getContainerMappings() {
        $cfg_mappings = array();
        if (isset($this->config['container_mappings']) && !empty($this->config['container_mappings'])) {
            $mappings = explode(';', $this->config['container_mappings']);
            foreach ($mappings as $map) {
                list($source, $target) = explode('|', $map);
                if (empty($source)) {
                    continue;
                }
                $cfg_mappings []= array('source' => $source, 'target' => $target);
            }
        }
        return $cfg_mappings;
    }
    
    /**
     * Set the container mapping config value
     */
    public function updateContainerMappings($mappings) {
        $maps = array();
        foreach ($mappings as $map) {
            $maps[]= $map['source'].'|'.$map['target'];
        }
        $map = implode(';', $maps);
        $this->setConfig('container_mappings', $map);
        return true;
    }
    
    /**
     * Update the Config
     */
    public function updateConfig() {

        // check that the config exists
        if (!$this->createConfigDn($this->configdn)) {
            return false;
        }
        $attrs = array('description' => array());
        foreach ($this->config as $k => $v) {
            $attrs['description'][]= "$k=$v";
        }
//        global $request;
//        $request['page']->warning(var_export($attrs, true), _('configuration'));
        $result = $this->server->modify($this->configdn, $attrs, 'user');
        return $this->getConfig(true);
    }

    /**
     * Create a config DN node
     */
    private function createConfigDn($dn) {

        // check that the backup DN exists
        $query = $this->server->query(array('base' => $dn, 'attrs' => array('description')), 'user');
        if (empty($query)) {
            $query = $this->server->query(array('base' => $this->configou, 'attrs' => array('ou')), 'user');
            if (empty($query)) {
                // create the UDIConfig OU container
                if (!$this->server->add($this->configou, array('objectClass' => array('organizationalUnit'), 'ou' => array('UDIConfig'), 'description' => array('UDIConfig')))) {
                    system_message(array(
                                 'title'=>_('UDI Configuration update Failed'),
                                            'body'=> _('Failed to create configuration OU: '.$this->configou.' - check server write permissions'),
                                            'type'=>'error'));
                    return false;
                }
            }
            // check if this server is an Active Directory
            $this->serverType();
            if (!$this->server->add($dn, array('objectClass' => array('document'), 'cn' => array('UDIConfig'), 'documentIdentifier' => array('UDIConfig')))) {
                system_message(array(
                             'title'=>_('UDI Configuration backup Failed'),
                                        'body'=> _('Failed to create configuration node: '.$dn.' - check server write permissions'),
                                        'type'=>'error'));
                return false;
             };
             // Flush the cache
             $processor = new Processor($this->server);
             $processor->purge();
        }
        return true;
    }

    /**
     * Determine the server type and then set the config 
     * for it - server_type
     * @return String server type
     */
    public function serverType() {
        if (!isset($this->config['server_type'])) {
            // check if this server is an Active Directory
            $this->config['server_type'] = 'other';
            $dmo_attrs = $this->server->SchemaAttributes('user');
            if (isset($dmo_attrs['samaccountname'])) {
                $this->config['server_type'] = 'ad';
            }
        }
        return $this->config['server_type'];
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
        // determine server type
        $this->serverType();

        // set defaults for values that are missing
        foreach (self::$DEFAULTS[$this->config['server_type']] as $var => $default) {
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
        $query = $this->server->query(array('base' => $this->configbackupdn, 'attrs' => array('description')), 'user');
        $this->unpackConfig($query);
        return $this->updateConfig();
    }
}
?>
