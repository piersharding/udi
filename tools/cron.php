<?php
include ("Console/Getopt.php");

// increase error reporting
error_reporting(E_ALL);

// make sure that it is only run from the command line
if (isset($_SERVER['REMOTE_ADDR']) || isset($_SERVER['GATEWAY_INTERFACE'])){
    console_write(_('This script can only be run from the console'));
    exit(-1);
}

// allow unlimited execution time
@set_time_limit(0);

/// The current directory in PHP version 4.3.0 and above isn't necessarily the
/// directory of the script when run from the command line. The require_once()
/// would fail, so we'll have to chdir()

if (!isset($_SERVER['REMOTE_ADDR']) && isset($_SERVER['argv'][0])) {
    chdir(dirname($_SERVER['argv'][0]));
}

/// increase memory limit (PHP 5.2 does different calculation, we need more memory now)
ini_set('memory_limit', '512M');

//fetch arguments
$args = Console_Getopt::readPHPArgv();


//checking errors for argument fetching
if (PEAR::isError($args)) {
    console_write(_('Invalid arguments'));
    exit(1);
}

// remove stderr/stdout redirection args
$args = preg_grep('/2>&1/', $args, PREG_GREP_INVERT);

// must supply at least one arg for the action to perform
if (count($args) <= 1) {
    console_write(_('no arguments supplied'));
    exit(1);
}

$short_opts = 's:v';
$long_opts = array('server=', 'validate');
// still parse/check rest of options
// override the values with the command line opts now - take precedence over stdin values
// parse the command line options
$console_opt = Console_Getopt::getOpt($args, $short_opts, $long_opts);

//detect errors in the options such as invalid opt
if (PEAR::isError($console_opt)) {
    $errormsg = str_replace('Console_Getopt: ', '', $console_opt->message);
    console_write(_('argument error: ').$errormsg);
    console_write(help_text());
    exit(1);
}

// stash the values from the command line
$values = array();
$opts = $console_opt[0];
if (sizeof($opts) > 0) {
    foreach ($opts as $o) {
        $values[trim($o[0], '- ')] = $o[1];
    }
}
//var_dump($values);

// map the arguments supplied to variables
$server_name = '';
$validate = false;
foreach ($values as $param => $value) {
    switch ($param) {
        case 's':
        case 'server':
            $server_name = $value;
            break;
        case 'v':
        case 'validate':
            $validate = true;
            break;
        default:
            break;
    }
}
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

// get the server list - and do a match
define('DEBUG_ENABLED', false);

$server_id = false;
$servers = $_SESSION[APPCONFIG]->getServerList(true);
foreach ($servers as $server) {
    if (strtolower($server_name) == strtolower($server->getName())) {
        $server_id = $server->getIndex();
    }
}
if (false === $server_id) {
    console_write(_('Invalid server name supplied'));
    console_write(help_text());
    exit(1);
}
$_REQUEST['server_id'] = $server_id;

global $app, $udiconfig, $request;
// now fire common
require_once '../lib/common.php';

// We are now ready for the main event - load classes, and then run the process
require_once HOOKSDIR.'udi/UdiConfig.php';
require_once HOOKSDIR.'udi/UdiRender.php';
require_once HOOKSDIR.'udi/udi_functions.php';

// setup the page renderer
$request['page'] = new UdiRender($app['server']->getIndex(),get_request('template','REQUEST',false,'none'));

// initialise the cache
$tree = Tree::getInstance($app['server']->getIndex());
set_cached_item($app['server']->getIndex(),'tree','null',$tree);


// get the UDI config
$udiconfig = new UdiConfig($app['server']);
$config = $udiconfig->getConfig();
$udiconfigdn = $udiconfig->getBaseDN();

$cfg = $udiconfig->getConfig();

// validate config
if (!$udiconfig->validate()) {
    console_write(_('Configuration validation failed'));
    console_write($request['page']->outputMessagesConsole());
    exit(1);
}

// really process the file now
$request['page']->info(_('File processing started'));
// process the file specified in the config
// validate the file specified in the config
$import = new ImportCSV($app['server']->getIndex(), $cfg['filepath']);
$import->accept(',');
$header = $import->getCSVHeader();
$rows = array();
while ($entry = $import->readEntry()) {
    $rows []= $entry;
}

// bail on errors
if ($request['page']->isError()) {
    console_write($request['page']->outputMessagesConsole());
    exit(1);
}

$processor = new Processor($app['server'], array('header' => $header, 'contents' => $rows));
if ($processor->validate()) {
    if (!$validate) {
        $processor->import();
    }
}
$request['page']->info(_('File processing finished'));

// output messages
console_write($request['page']->outputMessagesConsole());
if ($request['page']->isError()) {
    exit(1);
}

exit(0);
/******************************************************************************/

/**
 *  Write out console message
 * @param String $msg - the message
 */
function console_write($msg) {
    // emulated cli script - something like cron
    fwrite(STDOUT, $msg."\n");
    // clear all output
    fflush(STDOUT);
}

/**
 * The static help text printed at the console
 * 
 * @return string of help text
 */
function help_text() {
    return 
    "Usage: cron [arguments]
    <arguments>: these are:
       --help  - this help text
       -s 1 or --server='The Server Name' - the LDAP directory connection to use
                        This is the name as entered in the config.php file

    This is the back ground processor for the User Directory Interface (UDI)
    service.  It relies on the configuration o the UDI to be setup correctly
    in advance.  Please log into the web interface and check this first.";
}
