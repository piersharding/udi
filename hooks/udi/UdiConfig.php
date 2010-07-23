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
    
    private $server;

    private $base;
    
    private $config = null;
    
	# Config DN
    protected $configdnname = 'cn=UDIConfig';
    protected $configdn;

    public function __construct($server) {
        if (defined('DEBUG_ENABLED') && DEBUG_ENABLED && (($fargs=func_get_args())||$fargs='NOARGS'))
            debug_log('Entered (%%)',17,0,__FILE__,__LINE__,__METHOD__,$fargs);

        $this->server = $server;
        $base = $this->server->getBaseDN();
        $this->base = $base[0];
        $this->configdn = $this->configdnname.','.$this->base;
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
     * Get the Config
     */
    public function getConfig($force=false) {
        if (!$force && $this->config) {
            return $this->config;
        }

        echo "getting config";
        $this->config = array();
        $query = $this->server->query(array('base' => $this->configdn, 'attrs' => array('description')), 'login');
        if (!empty($query)) {
            $query = array_pop($query);
            if (isset($query['description'])) {
//        var_dump($query);
                foreach ($query['description'] as $attr) {
                    if (preg_match('/.*?\=/', $attr)) {
                        $config_var = explode('=', $attr, 2);
                        $this->config[$config_var[0]] = $config_var[1];
                    }
                }
            }            
        }
        return $this->config;
        
    }
}
?>
