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

require './common.php';
require HOOKSDIR.'udi/UdiRender.php';
require HOOKSDIR.'udi/UdiConfig.php';


if (! ini_get('file_uploads'))
	error(_('Your PHP.INI does not have file_uploads = ON. Please enable file uploads in PHP.'),'error','index.php');

$request['page'] = new UdiRender($app['server']->getIndex(),get_request('template','REQUEST',false,'none'));

$udiconfig = new UdiConfig($app['server']);
$config = $udiconfig->getConfig();
$udiconfigdn = $udiconfig->getBaseDN();

var_dump($config);

$request['page']->setContainer($udiconfigdn);

$request['page']->accept();
$request['page']->drawTitle(sprintf('<b>%s</b>',_('UDI')));
$request['page']->drawSubTitle(sprintf('%s: <b>%s</b>',_('Server'),$app['server']->getName()));

$udi_nav = get_request('udi_nav','REQUEST');
if (empty($udi_nav) && isset($_SESSION['udi_nav'])) {
    $udi_nav = $_SESSION['udi_nav'];
}
if (empty($udi_nav)) {
    $udi_nav = 'admin';
}

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
printf('<td>%s</td>',_('Select an LDIF file'));
echo '<td>';
echo '<input type="file" name="ldif_file" />';
echo '</td></tr>';

printf('<tr><td>&nbsp;</td><td class="small"><b>%s %s</b></td></tr>',_('Maximum file size'),ini_get('upload_max_filesize'));

echo '<tr><td colspan=2>&nbsp;</td></tr>';
printf('<tr><td>%s</td></tr>',_('Or paste your LDIF here'));
echo '<tr><td colspan=2><textarea name="ldif" rows="20" cols="100"></textarea></td></tr>';
echo '<tr><td colspan=2>&nbsp;</td></tr>';
printf('<tr><td>&nbsp;</td><td class="small"><input type="checkbox" name="continuous_mode" value="1" />%s</td></tr>',
	_("Don't stop on errors"));
printf('<tr><td>&nbsp;</td><td><input type="submit" value="%s" /></td></tr>',_('Proceed >>'));
echo '</table>';
echo '</form>';
echo '</center>';
?>
