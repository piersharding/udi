<?php
/**
 * All copied from index.php
 * 
 * modified to remove tree, and top navigation control
 *
 * @package phpLDAPadmin
 * @subpackage Page
 */

/**
 */

/*******************************************
<pre>

If you are seeing this in your browser,
PHP is not installed on your web server!!!

</pre>
*******************************************/

/**
 * We will perform some sanity checking here, since this file is normally loaded first when users
 * first access the application.
 */

# The index we will store our config in $_SESSION
define('APPCONFIG','plaConfig');

define('LIBDIR',sprintf('%s/',realpath('../lib/')));
ini_set('display_errors',1);
error_reporting(E_ALL);

# General functions needed to proceed.
ob_start();
if (! file_exists(LIBDIR.'functions.php')) {
    if (ob_get_level()) ob_end_clean();
    die(sprintf("Fatal error: Required file '<b>%sfunctions.php</b>' does not exist.",LIBDIR));
}

if (! is_readable(LIBDIR.'functions.php')) {
    if (ob_get_level()) ob_end_clean();
    die(sprintf("Cannot read the file '<b>%sfunctions.php</b>' its permissions may be too strict.",LIBDIR));
}

if (ob_get_level())
    ob_end_clean();

# Make sure this PHP install has pcre
if (! extension_loaded('pcre'))
    die('<p>Your install of PHP appears to be missing PCRE support.</p><p>Please install PCRE support before using phpLDAPadmin.<br /><small>(Dont forget to restart your web server afterwards)</small></p>');

require LIBDIR.'functions.php';

# Define the path to our configuration file.
if (defined('CONFDIR'))
    $app['config_file'] = CONFDIR.'config.php';
else
    $app['config_file'] = 'config.php';

# Make sure this PHP install has session support
if (! extension_loaded('session'))
    error('<p>Your install of PHP appears to be missing php-session support.</p><p>Please install php-session support before using phpLDAPadmin.<br /><small>(Dont forget to restart your web server afterwards)</small></p>','error',null,true);

# Make sure this PHP install has gettext, we use it for language translation
if (! extension_loaded('gettext'))
    system_message(array(
        'title'=>_('Missing required extension'),
        'body'=>'Your install of PHP appears to be missing GETTEXT support.</p><p>GETTEXT is used for language translation.</p><p>Please install GETTEXT support before using phpLDAPadmin.<br /><small>(Dont forget to restart your web server afterwards)</small>',
        'type'=>'error'));

# Make sure this PHP install has all our required extensions
if (! extension_loaded('ldap'))
    system_message(array(
        'title'=>_('Missing required extension'),
        'body'=>'Your install of PHP appears to be missing LDAP support.<br /><br />Please install LDAP support before using phpLDAPadmin.<br /><small>(Dont forget to restart your web server afterwards)</small>',
        'type'=>'error'));

# Make sure that we have php-xml loaded.
if (! function_exists('xml_parser_create'))
    system_message(array(
        'title'=>_('Missing required extension'),
        'body'=>'Your install of PHP appears to be missing XML support.<br /><br />Please install XML support before using phpLDAPadmin.<br /><small>(Dont forget to restart your web server afterwards)</small>',
        'type'=>'error'));

/**
 * Helper functions.
 * Our required helper functions are defined in functions.php
 */
if (isset($app['function_files']) && is_array($app['function_files']))
    foreach ($app['function_files'] as $file_name ) {
        if (! file_exists($file_name))
            error(sprintf('Fatal error: Required file "%s" does not exist.',$file_name),'error',null,true);

        if (! is_readable($file_name))
            error(sprintf('Fatal error: Cannot read the file "%s", its permissions may be too strict.',$file_name),'error',null,true);

        ob_start();
        require $file_name;
        if (ob_get_level()) ob_end_clean();
    }

# Configuration File check
if (! file_exists($app['config_file'])) {
    error(sprintf(_('You need to configure %s. Edit the file "%s" to do so. An example config file is provided in "%s.example".'),app_name(),$app['config_file'],$app['config_file']),'error',null,true);

} elseif (! is_readable($app['config_file'])) {
    error(sprintf('Fatal error: Cannot read your configuration file "%s", its permissions may be too strict.',$app['config_file']),'error',null,true);
}

class kiosk_page extends page {

    protected function body($raw=false) {
        if (defined('DEBUG_ENABLED') && DEBUG_ENABLED && (($fargs=func_get_args())||$fargs='NOARGS'))
            debug_log('Entered (%%)',129,0,__FILE__,__LINE__,__METHOD__,$fargs);

        # Add the Session System Messages
        if (isset($_SESSION['sysmsg']) && is_array($_SESSION['sysmsg'])) {
            foreach ($_SESSION['sysmsg'] as $msg) 
                $this->setsysmsg($msg);

            unset($_SESSION['sysmsg']);
        }

        if (isset($this->sysmsg)) {
            echo '<table border="0" class="center-msg"><tr><td width="30%"></td><td width="40%">';
            echo '<table class="sysmsg">';
            $this->sysmsg();
            echo '</table>';
            echo "<br/>";
            echo '</td><td width="30%"></td></tr></table>';
            echo "\n";
        }

        if (isset($this->_block['body']))
            foreach ($this->_block['body'] as $object)
                echo $object->draw('body',$raw);
    }
}

# If our config file fails the sanity check, then stop now.
if (! $config = check_config($app['config_file'])) {
    $www['page'] = new page();
    $www['body'] = new block();
    $www['page']->block_add('body',$www['body']);
    $www['page']->display();
    exit;

} else {
    app_session_start();
    $_SESSION[APPCONFIG] = $config;
}


if ($uri = get_request('URI','GET'))
    header(sprintf('Location: cmd.php?%s',base64_decode($uri)));

if (! preg_match('/^([0-9]+\.?)+/',app_version())) {
    system_message(array(
        'title'=>_('This is a development version of phpLDAPadmin'),
        'body'=>'This is a development version of phpLDAPadmin! You should <b>NOT</b> use it in a production environment (although we dont think it should do any damage).',
        'type'=>'info','special'=>true));

    if (count($_SESSION[APPCONFIG]->untested()))
        system_message(array(
            'title'=>'Untested configuration paramaters',
            'body'=>sprintf('The following parameters have not been tested. If you have configured these parameters, and they are working as expected, please let the developers know, so that they can be removed from this message.<br/><small>%s</small>',implode(', ',$_SESSION[APPCONFIG]->untested())),
            'type'=>'info','special'=>true));

    $server = $_SESSION[APPCONFIG]->getServer(get_request('server_id','REQUEST'));
    if (count($server->untested()))
        system_message(array(
            'title'=>'Untested server configuration paramaters',
            'body'=>sprintf('The following parameters have not been tested. If you have configured these parameters, and they are working as expected, please let the developers know, so that they can be removed from this message.<br/><small>%s</small>',implode(', ',$server->untested())),
            'type'=>'info','special'=>true));
}




require_once './common.php';

// is this activated
$kiosk = isset($_SESSION[APPCONFIG]) ? $_SESSION[APPCONFIG]->isCommandAvailable('script','kiosk') : false;
if (!$kiosk) {
    // we are out of here
    header("Location: index.php");
    die();
}
$www = array();
$www['cmd'] = get_request('cmd','REQUEST');
$www['meth'] = get_request('meth','REQUEST');

ob_start();

switch ($www['cmd']) {
    case '_debug':
        debug_dump($_REQUEST,1);
        break;

    default:
        if (defined('HOOKSDIR') && file_exists(HOOKSDIR.'kiosk/'.$www['cmd'].'.php'))
            $app['script_cmd'] = HOOKSDIR.'kiosk/'.$www['cmd'].'.php';
        else
            $app['script_cmd'] = HOOKSDIR.'kiosk/change_passwd.php';

}

if (DEBUG_ENABLED)
    debug_log('Ready to render page for command [%s,%s].',128,0,__FILE__,__LINE__,__METHOD__,$www['cmd'],$app['script_cmd']);

# Create page.
# Set the index so that we render the right server tree.
$www['page'] = new kiosk_page($app['server']->getIndex());


// kiosk common stuff
require_once './common.php';
require_once HOOKSDIR.'udi/udi_functions.php';
require_once HOOKSDIR.'kiosk/kiosk_functions.php';
require_once HOOKSDIR.'kiosk/KioskRender.php';
require_once HOOKSDIR.'udi/UdiConfig.php';

// setup the page renderer
$request['page'] = new KioskRender($app['server']->getIndex(),get_request('template','REQUEST',false,'none'));

// ensure that this is not an existing logged in account
if ($app['server']->isLoggedIn('user')) {
    $app['server']->logout('user');
}

$adminuser = $app['server']->getValue('login','kiosk_bind_id');
$adminpass = $app['server']->getValue('login','kiosk_bind_pass');
if (!empty($adminuser) && !empty($adminpass)) {
    $app['server']->setLogin($adminuser, $adminpass, 'user');
    $result = $app['server']->connect('user');
    if (!$result) {
        $_SESSION['sysmsg'] = array();
        $request['page']->error(_('Invalid login for Kiosk Administrator account'), 'Kiosk');
    }
}

// get the UDI config
$udiconfig = new UdiConfig($app['server']);
$config = $udiconfig->getConfig(false, 'user');
$udiconfigdn = $udiconfig->getBaseDN();
if ($app['server']->isLoggedIn('user')) {
    $app['server']->logout('user');
}

// sort out available commands
$cmdlist = array('changepasswd', 'resetpasswd', 'lockaccount', 'help');
if (isset($config['enable_kiosk_recover']) && $config['enable_kiosk_recover'] == 'checked') {
    $cmdlist []= 'recoverpasswd';
}
# See if we can render the command
$www['cmd'] = trim($www['cmd']);
if (!in_array($www['cmd'], $cmdlist)) {
    $www['cmd'] = 'changepasswd';
}    

$_SESSION['sysmsg'] = array();


$confirmnow = false;

// get the specific action for this panel, if it was POSTed
//var_dump($_SERVER['REQUEST_METHOD']); var_dump($_GET); var_dump($_POST); exit(0);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once HOOKSDIR.'kiosk/'.$www['cmd'].'_action.php';
}

// set the headings
$request['page']->setContainer($udiconfigdn);
$request['page']->accept();

// add in the server selector code
   echo '
    <script id="post-click" type="text/JavaScript">
    function switch_servers(method, server_id, cmd) {
        var chosenoption = server_id.options[server_id.selectedIndex];
        var all_params = {\'server_id\': chosenoption.value, \'cmd\': cmd};
        if (method == "get") {
            var url = "kiosk.php?";
            for(var key in all_params) {
                url = url + key + "=" + all_params[key] + "&";
            }
            location.href = url;
        }
        else {
            var form = document.createElement("form");
            form.setAttribute("method", "post");
            form.setAttribute("action", "kiosk.php");
            for(var key in all_params) {
                var hiddenField = document.createElement("input");
                hiddenField.setAttribute("type", "hidden");
                hiddenField.setAttribute("name", key);
                hiddenField.setAttribute("value", all_params[key]);
                form.appendChild(hiddenField);
            }
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
    ';
// frame up the page - set the menus
echo '<center>';
echo '<table id="kiosk" class="no-border"><tr><td>';
echo $request['page']->getMenu($www['cmd']);
echo '</td></tr></table>';
echo '<form name="udi_form" action="kiosk.php" method="post" class="new_value" enctype="multipart/form-data">';
printf('<input type="hidden" name="server_id" value="%s" />',$app['server']->getIndex());
echo '<input type="hidden" name="cmd" value="'.$www['cmd'].'" />';

echo '<table class="forminput" border=0>';

echo '<tr><td>';
//echo $request['page']->outputMessages();
echo '</td></tr>';
//echo '<tr><td colspan=2>&nbsp;</td></tr>';
echo '<tr>';
echo '<td>';

    

// now process output
require_once HOOKSDIR.'kiosk/'.$www['cmd'].'_form.php';    
    
//# Refresh a frame - this is so that one frame can trigger another frame to be refreshed.
//if (isAjaxEnabled() && get_request('refresh','REQUEST') && get_request('refresh','REQUEST') != get_request('frame','REQUEST')) {
//    echo '<script type="text/javascript" language="javascript">';
//    printf("ajDISPLAY('%s','cmd=refresh&server_id=%s&meth=ajax&noheader=%s','%s');",
//        get_request('refresh','REQUEST'),$app['server']->getIndex(),get_request('noheader','REQUEST',false,0),_('Auto refresh'));
//    echo '</script>';
//}

echo '</td>';
echo '</tr>';
echo '</table>';
echo '</form>';
echo '</center>';


# Capture the output and put into the body of the page.
$www['body'] = new block();
$www['body']->SetBody(ob_get_contents());
$www['page']->block_add('body',$www['body']);
ob_end_clean();

// control the output 
$display = array(
                 'HEAD'=>true,
                 'CONTROL'=>true,
                 'TREE'=>false,
                 'FOOT'=>true
                 );

if ($www['meth'] == 'ajax')
	$www['page']->show(get_request('frame','REQUEST',false,'BODY'),true,get_request('raw','REQUEST',false,false));
else
	$www['page']->display($display);
?>
