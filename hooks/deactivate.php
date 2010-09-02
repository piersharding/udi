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

$confirm = get_request('confirm');
if ($confirm == 'yes') {
    $processor = new Processor($app['server']);
    $processor->to_be_deactivated = $app['server']->query(array('base' => $dn), 'user');
    if (count($processor->to_be_deactivated) ==  1) {
        if ($processor->processDeactivations()) {
            $request['page']->info(_('User account deactivated'));
        }
        else {
            $request['page']->info(_('Account not deactivated'));
        }
    }
    else {
        $request['page']->info(_('Account not found'));
    }
}
else if ($confirm == 'no') {
    $request['page']->info(_('Deactivation cancelled'));
}


?>
