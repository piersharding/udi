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
require_once HOOKSDIR.'udi/UdiRender.php';
require_once HOOKSDIR.'udi/udi_functions.php';

// must be logged in
if ($app['server']->isReadOnly()) {
    system_message(array(
    'title'=>_('Not logged in'),
    'body'=> 'You must be logged in to perform this function',
    'type'=>'error'),
    'index.php');
}

// setup the page renderer
$request['page'] = new UdiRender($app['server']->getIndex(),get_request('template','REQUEST',false,'none'));

// get the UDI config
$udiconfig = new UdiConfig($app['server']);
$config = $udiconfig->getConfig();
$udiconfigdn = $udiconfig->getBaseDN();

// sort out which UDI navigation page we should be on
$udi_nav = get_request('udi_nav','REQUEST');
if (empty($udi_nav) && isset($_SESSION['udi_nav'])) {
    $udi_nav = $_SESSION['udi_nav'];
}
if (!in_array($udi_nav, array('admin', 'userpass', 'mapping', 'upload', 'process', 'reporting', 'help'))) {
    $udi_nav = 'admin';
}

// get the specific action for this panel, if it was POSTed
//var_dump($_SERVER['REQUEST_METHOD']); var_dump($_GET); var_dump($_POST); exit(0);
if ($_SERVER['REQUEST_METHOD'] == 'POST' || get_request('udi_decoration', 'REQUEST') == 'none') {
    require_once HOOKSDIR.'udi/'.$udi_nav.'_action.php';
}

// now process output
require_once HOOKSDIR.'udi_form.php';
?>
