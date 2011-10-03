<?php
/**
 * Classes and functions for importing data to LDAP
 *
 * These classes provide differnet import formats.
 *
 * @author Piers Harding
 * @package phpLDAPadmin
 */

if (!defined('AUTH_AD_ACCOUNTDISABLE')) {
    define('AUTH_AD_ACCOUNTDISABLE', 0x0002);
}
if (!defined('AUTH_AD_NORMAL_ACCOUNT')) {
    define('AUTH_AD_NORMAL_ACCOUNT', 0x0200);
}

// data validation scheme for mlep values
$mlep_mandatory_fields = array(
                            'mlepRole' => array('mandatory' => true, 'match' => array('Student', 'TeachingStaff', 'NonTeachingStaff', 'ParentCaregiver', 'Alumni')),
                            'mlepSmsPersonId' => array('mandatory' => true, 'match' => '/[a-zA-Z0-9]+/'),
                            'mlepStudentNSN' => array('mandatory' => false, 'match' => '/^\d{10}$/'),
                            'mlepUsername' => array('mandatory' => false, 'match' => '/.+/'),
                            'mlepFirstAttending' => array('mandatory' => true, 'match' => '/^\d{4}\-\d{2}\-\d{2}$/', 'group' => array('Student')),
                            'mlepLastAttendance' => array('mandatory' => false, 'match' => '/^\d{4}\-\d{2}\-\d{2}$/', 'group' => array('Student')),
//                            'mlepFirstName' => array('mandatory' => true, 'match' => '/^[^\*\?\;\,\<\>\!\%\^\&\|]+$/'),
//                            'mlepLastName' => array('mandatory' => true, 'match' => '/^[^\*\?\;\,\<\>\!\%\^\&\|]+$/'),
                            'mlepFirstName' => array('mandatory' => true, 'match' => '/^[^\*\?\;\,\<\>\!\%\^\|]+$/'),
                            'mlepLastName' => array('mandatory' => true, 'match' => '/^[^\*\?\;\,\<\>\!\%\^\|]+$/'),
                            'mlepPreferredName' => array('mandatory' => false, 'match' => '/^[^\*\?\;\,\<\>\!\%\^\|]+$/'),
                            'mlepAssociatedNSN' => array('mandatory' => false, 'match' => '/^\d{10}((\#\d{10})+)?$/', 'group' => array('ParentCaregiver')),
                            'mlepEmail' => array('mandatory' => false, 'match' => '#^[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+'.
                                                                                  '(\.[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+)*'.
                                                                                  '@'.
                                                                                  '[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+\.'.
                                                                                  '[-!\#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+$#'),
                            'mlepOrganisation' => array('mandatory' => false, 'match' => '/^[\w\d\.-]+$/'),
                            'mlepGroupMembership' => array('mandatory' => false, 'match' => '/[\w\d\s\#]+/'),
                            );
/**
 *  Check that a DN exists in the directory
 *
 * @param string $dn DN to check
 * @param string $msg message on failure
 * @param string $area message area
 *
 * @return bool true on success
 */
function check_dn_exists($dn, $msg, $area = 'configuration') {
    //$query['filter'] = '(&(objectClass=*))';
    global $app, $udiconfig, $request;
    // check DN exists
    $query = $app['server']->query(array('base' => $dn, 'attrs' => array('dn')), 'user');
    if (empty($query)) {
        // base does not exist
        $request['page']->error($msg, $area);
        return false;
    }

    // now check that this DN is within the scope of the server base DN
    $query = array_keys($query);
    $dn = array_shift($query);
    if (!preg_match('/'.get_canonical_name($udiconfig->getBaseDN()).'$/', get_canonical_name($dn))) {
        return $request['page']->error(_('DN does not exist inside server connection Base DN: ').$dn, 'configuration');
    }
    return true;
}

/**
 * Calculate a homogenised version of a given dn, for string comparison
 *
 * @param String $dn DN to clean
 */
function get_canonical_name($dn) {
    $parts = explode(',', $dn);
    $cleaned = array();
    foreach ($parts as $part) {
        $els = explode('=', $part);
        $attr_type = strtolower(trim($els[0]));
        if (!isset($els[1])) {
            system_message(array(
                'title'=>_('Serious error in DN structure'),
                'body'=>_('There was a bad problem with the structure of a DN: '.$dn.' Where part ('.$part.') did not conform.'),
                'type'=>'error'),
            sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
            get_request('udi_nav','REQUEST'),
            get_request('server_id','REQUEST')));
            die();
        }
        $attr = strtolower(trim($els[1]));
        $cleaned[]= $attr_type."=".$attr;
    }
    return implode(',', $cleaned);
}

/**
 * Check that a search base exists
 *
 * @param string $base the DN of a search base
 *
 * @returm bool true on success
 */
function check_search_base($base) {
    // base does not exist
    return check_dn_exists($base, _('Search base DN does not exist: ').$base);
}


/**
 * Check that an objectClass exists
 *
 * @param string $class objectClass to check
 *
 * @return bool true on success
 */
function check_objectclass($class) {
    // objectClass does not exist
    global $app, $udiconfig, $request;

    $socs = $app['server']->SchemaObjectClasses('user');
    if (!isset($socs[strtolower($class)])) {
        $request['page']->error(_('objectClass does not exist: ').$class, 'configuration');
        return false;
    }
    return true;
}


/**
 * clean a dn value
 *
 * @param string $dn
 *
 * @return string cleaned DN value
 */
function udi_clean_dn($dn) {
    if (substr($dn,0,1) == ':') {
        $value = base64_decode(trim(substr($dn,1)));
    }
    else {
        $value = trim($dn);
    }
    return $value;
}


/**
 * UDI version of run hook - the difference is that it collects the results and passes them back
 *
 * Runs procedures attached to a hook.
 *
 * @param hook_name Name of hook to run.
 * @param args Array of optional arguments set by phpldapadmin. It is normally in a form known by call_user_func_array() :
 *
 * <pre>[ 'server_id' => 0,
 * 'dn' => 'uid=epoussa,ou=tech,o=corp,o=fr' ]</pre>
 *
 * @return true if no hooks
 *         false if there was a failure
 *         if all hooks run OK then return an array of results
 */
function udi_run_hook($hook_name,$args,$instance=false) {
    if (DEBUG_ENABLED && (($fargs=func_get_args())||$fargs='NOARGS'))
        debug_log('Entered (%%)',257,0,__FILE__,__LINE__,__METHOD__,$fargs);

    $hooks = isset($_SESSION[APPCONFIG]) ? $_SESSION[APPCONFIG]->hooks : array();

    if (! count($hooks) || ! array_key_exists($hook_name,$hooks)) {
        if (DEBUG_ENABLED)
            debug_log('Returning, HOOK not defined (%s)',257,0,__FILE__,__LINE__,__METHOD__,$hook_name);

        return true;
    }

    $rollbacks = array();
    reset($hooks[$hook_name]);

    /* Execution of procedures attached is done using a numeric order
     * since all procedures have been attached to the hook with a
     * numerical weight. */
    $all_results = array();
    while (list($key,$hook) = each($hooks[$hook_name])) {
        if ($instance && $instance != $hook['hook_function']) {
            continue;
        }
//        var_dump($key);
//        var_dump($hook);
//        exit(0);
        if (DEBUG_ENABLED)
            debug_log('Calling HOOK Function (%s)(%s)',257,0,__FILE__,__LINE__,__METHOD__,
                $hook['hook_function'],$args);

        array_push($rollbacks,$hook['rollback_function']);

        $result = call_user_func_array($hook['hook_function'],$args);
        if (DEBUG_ENABLED)
            debug_log('Called HOOK Function (%s)',257,0,__FILE__,__LINE__,__METHOD__,
                $hook['hook_function']);

        /* If a procedure fails (identified by a false return), its optional rollback is executed with
         * the same arguments. After that, all rollbacks from
         * previously executed procedures are executed in the reverse
         * order. */
        if (! is_null($result) && $result == false) {
            if (DEBUG_ENABLED)
                debug_log('HOOK Function [%s] return (%s)',257,0,__FILE__,__LINE__,__METHOD__,
                    $hook['hook_function'],$result);

            while ($rollbacks) {
                $rollback = array_pop($rollbacks);

                if ($rollback != false) {
                    if (DEBUG_ENABLED)
                        debug_log('HOOK Function Rollback (%s)',257,0,__FILE__,__LINE__,__METHOD__,
                            $rollback);

                    call_user_func_array($rollback,$args);
                }
            }

            return false;
        }
        else {
            // collect results
            $all_results[]= $result;
        }
    }

    return $all_results;
}


function read_reports ($type) {
    global $request;
    $expire_time = 45 * 24 * 60 * 60; // 45 days * hours * mins * secs

    $reports = array();
    // bail if reporting is not active
    if (!isset($request['udiconfig']['enable_reporting']) || $request['udiconfig']['enable_reporting'] != 'checked') {
        return $reports;
    }

    $file_pattern = $request['udiconfig']['reportpath'].'/'.$type.'_*.txt';
    foreach (glob($file_pattern) as $file) {
        // delete old files
        $ctime = filectime($file);
        $age = time() - $ctime;
        if ($age > ($expire_time)){
            unlink($file);
            continue;
        }
        $report = read_report($file);
        if (!empty($report)) {
            $reports[$report['header']['id']]= $report;
        }
    }
    // sort into date order - $header['time']
    krsort($reports, SORT_NUMERIC);
    return $reports;
}


function read_report ($file) {
    $data = preg_grep('/\w/', explode("\n", file_get_contents($file)));
    $report = array();
    if (count($data) > 0) {
        $header = array_shift($data);
        list($label, $header) = explode("\t", $header, 2);
        @eval('$header = ' . $header . ';');
        $footer = false;
        if (count($data) > 0 && substr($data[count($data)-1], 0, 4) == "end\t") {
            $footer = array_pop($data);
            list($label, $footer) = explode("\t", $footer, 2);
            eval('$footer = ' . $footer . ';');
        }
        // if footer then finished successfully
        $start = (int)$header['time'];
//        date_default_timezone_set('Pacific/Auckland');
        $header['time'] = date("d/m/Y H:i:s", $start);
        $header['id'] = $start;
        $report = array('file' => $file, 'header' => $header, 'footer' => $footer, 'messages' => array());
        foreach ($data as $line) {
            list($type, $msg) = explode("\t", $line, 2);
            if ($type == 'warn') {
                $type = 'warning';
            }
            $report['messages'] []= array('type' => $type, 'message' => $msg);
        }
    }
    return $report;
}


function read_process_reports() {
    $reports = read_reports('processor');
    return $reports;
}


// importer and processor classes
require_once HOOKSDIR.'udi/udi_importer.php';
require_once HOOKSDIR.'udi/udi_processor.php';


// for the missing str_
if (!function_exists('str_getcsv')) {
    function str_getcsv($input, $delimiter = ",", $enclosure = '"', $escape = "\\") {
        $fiveMBs = 5 * 1024 * 1024;
        $fp = fopen("php://temp/maxmemory:$fiveMBs", 'r+');
        fputs($fp, $input);
        rewind($fp);

        $data = fgetcsv($fp, 1000, $delimiter, $enclosure); //  $escape only got added in 5.3.0

        fclose($fp);
        return $data;
    }
}

?>