<?php
/**
 * Displays a form for confirmation of UDI account reactivation
 * an LDIF file.
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


// get the UDI config
$udiconfig = new UdiConfig($app['server']);
$request['udiconfig'] = $udiconfig->getConfig();
$udiconfigdn = $udiconfig->getBaseDN();
//var_dump($_POST);
//var_dump(get_request('udi_decoration', 'REQUEST'));
//exit(0);

// setup the page renderer - if not already there - it maybe there because of the POST action
if (!isset($request['page'])) {
    $request['page'] = new UdiRender($app['server']->getIndex(),get_request('template','REQUEST',false,'none'));
}

// set the headings
$request['page']->setContainer($udiconfigdn);
$request['page']->accept();

$request['page']->drawTitle(sprintf('<b>%s</b>',_('UDI Reactivate ').get_rdn($dn)));
$request['page']->drawSubTitle(sprintf('%s: <b>%s</b> %s: <b>%s</b>',_('Server'),$app['server']->getName(), _('Distinguished Name'), $dn));

$dn = get_request('dn', 'REQUEST');
echo $request['page']->confirmationPage(_('User Account'), 
                                        _('Reactivate'), 
                                        $dn, 
                                        _('Are you sure you want to reactivate this UDI account?'), 
                                        _('Reactivate'), 
                                        array('server_id' => $app['server']->getIndex(), 'cmd' => 'reactivate', 'dn' => $dn));


?>
