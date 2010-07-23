<?php
/**
 * Displays a form to allow the user to upload and import
 * an LDIF file.
 *
 * @package phpLDAPadmin
 * @subpackage Page
 */

/**
 */

require_once './common.php';
require_once HOOKSDIR.'udi/UdiRender.php';
require_once HOOKSDIR.'udi/UdiConfig.php';


if (! ini_get('file_uploads'))
	error(_('Your PHP.INI does not have file_uploads = ON. Please enable file uploads in PHP.'),'error','index.php');

// setup the page renderer
$request['page'] = new UdiRender($app['server']->getIndex(),get_request('template','REQUEST',false,'none'));

// get the UDI config
$udiconfig = new UdiConfig($app['server']);
$config = $udiconfig->getConfig();
$udiconfigdn = $udiconfig->getBaseDN();

// set the headings
$request['page']->setContainer($udiconfigdn);
$request['page']->accept();
$request['page']->drawTitle(sprintf('<b>%s</b>',_('UDI')));
$request['page']->drawSubTitle(sprintf('%s: <b>%s</b>',_('Server'),$app['server']->getName()));

// sort out which UDI navigation page we should be on
$udi_nav = get_request('udi_nav','REQUEST');
if (empty($udi_nav) && isset($_SESSION['udi_nav'])) {
    $udi_nav = $_SESSION['udi_nav'];
}
if (!in_array($udi_nav, array('admin', 'mapping', 'upload', 'process'))) {
    $udi_nav = 'admin';
}

echo "nav is: $udi_nav";
var_dump($config);


// frame up the page - set the menus
echo '<center>';
echo '<table id="udi-nav" class="no-border"><tr><td>';
echo $request['page']->getMenu($udi_nav);
echo '</td></tr></table>';
echo '<form action="cmd.php" method="post" class="new_value" enctype="multipart/form-data">';
printf('<input type="hidden" name="server_id" value="%s" />',$app['server']->getIndex());
echo '<input type="hidden" name="cmd" value="udi" />';
echo '<input type="hidden" name="udi_nav" value="'.$udi_nav.'" />';

echo '<table class="forminput" border=0>';

echo '<tr><td colspan=2>&nbsp;</td></tr>';
echo '<tr>';
echo '<td>';

// get the specific template for this panel
require_once HOOKSDIR.'udi/'.$udi_nav.'_form.php';

echo '</td>';
echo '</tr>';
echo '</table>';
echo '</form>';
echo '</center>';
?>
