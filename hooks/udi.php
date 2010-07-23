<?php
/**
 * Imports an LDIF file to the specified LDAP server.
 *
 * @package phpLDAPadmin
 * @subpackage Page
 */

/**
 */

require_once './common.php';
require_once HOOKSDIR.'udi/UdiConfig.php';
require_once HOOKSDIR.'udi/udi_functions.php';


// get the UDI config
$udiconfig = new UdiConfig($app['server']);
$config = $udiconfig->getConfig();
$udiconfigdn = $udiconfig->getBaseDN();

// sort out which UDI navigation page we should be on
$udi_nav = get_request('udi_nav','REQUEST');
if (empty($udi_nav) && isset($_SESSION['udi_nav'])) {
    $udi_nav = $_SESSION['udi_nav'];
}
if (!in_array($udi_nav, array('admin', 'mapping', 'upload', 'process'))) {
    $udi_nav = 'admin';
}

// get the specific template for this panel
require_once HOOKSDIR.'udi/'.$udi_nav.'_action.php';


// now process output
require_once HOOKSDIR.'udi_form.php';
?>
