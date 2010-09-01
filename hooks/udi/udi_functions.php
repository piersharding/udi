<?php
/**
 * Classes and functions for importing data to LDAP
 *
 * These classes provide differnet import formats.
 *
 * @author Piers Harding
 * @package phpLDAPadmin
 */

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
    $query = $app['server']->query(array('base' => $dn, 'attrs' => array('dn')), 'login');
    if (empty($query)) {
        // base does not exist
        $request['page']->error($msg, $area);
        return false;
    }
    return true;
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

    $socs = $app['server']->SchemaObjectClasses('login');
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
    
    $file_pattern = $request['udiconfig']['reportpath'].'/'.$type.'_*.txt';
    $reports = array();
    foreach (glob($file_pattern) as $file) {
        // delete old files
        $ctime = filectime($file);
        $age = time() - $ctime;
        if ($age > ($expire_time)){
            unlink($file);
            continue;
        }
        
        $data = preg_grep('/\w/', explode("\n", file_get_contents($file)));
        if (count($data) > 0) {
            $header = array_shift($data);
            list($label, $header) = explode("\t", $header, 2);
            eval('$header = ' . $header . ';');
            $footer = false;
            if (count($data) > 0 && substr($data[count($data)-1], 0, 4) == "end\t") {
                $footer = array_pop($data);
                list($label, $footer) = explode("\t", $footer, 2);
                eval('$footer = ' . $footer . ';');
            } 
            // if footer then finished successfully
            $start = (int)$header['time'];
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
            $reports[$start]= $report;
        }
    }
    // sort into date order - $header['time']
    krsort($reports, SORT_NUMERIC);
    return $reports;
}


function read_process_reports() {
    $reports = read_reports('processor');
    return $reports;
}


/**
 * Importer Class
 *
 * This class serves as a top level importer class, which will return
 * the correct Import class.
 * This is derived from the core PLA LDIF import functionality, and has been
 * specialised for CSV files
 *
 * @package phpLDAPadmin
 * @subpackage UDIImport
 */
class Importer {
    # Server ID that the export is linked to
    private $server_id;
    # Import Type
    private $template_id;
    private $template;
    private $delimiter;

    /**
     * Constructor - this builds the environment connected to the 
     * currently selected LDAP server, and hands off to CSV file
     * importer object
     * 
     * @param Integer $server_id LDAP server connection Id
     * @param Integer $template_id Id of template
     * @param char $delimiter delimiter of the file
     */
    public function __construct($server_id, $template_id, $delimiter) {
        $this->server_id = $server_id;
        $this->template_id = $template_id;
        $this->delimiter = $delimiter;

        $this->accept();
    }

    /**
     * Accept the file for upload from the browser
     * then trigger the initial file processing
     * 
     */
    private function accept() {
        switch($this->template_id) {
            case 'CSV':
                if (isset($_FILES['csv_file']) && is_array($_FILES['csv_file']) && ! $_FILES['csv_file']['error']) {
                    $this->template = new ImportCSV($this->server_id, $_FILES['csv_file']['tmp_name'], $_FILES['csv_file']['name']);
                } else {
                    system_message(array(
                        'title'=>_('No UDI import input'),
                        'body'=>_('You must specify a file for upload.'),
                        'type'=>'error'),
                    sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
                    get_request('udi_nav','REQUEST'),
                    get_request('server_id','REQUEST')));
                    die();
                }
                break;

            default:
                system_message(array(
					'title'=>sprintf('%s %s',_('Unknown Import Type'),$this->template_id),
					'body'=>_('phpLDAPadmin has not been configured for that import type'),
					'type'=>'warn'),'index.php');
                die();
        }
        $this->template->accept($this->delimiter);
    }

    /**
     * accessor for the template object
     * 
     * @return object template
     */
    public function getTemplate() {
        return $this->template;
    }
}

/**
 * Import Class
 *
 * This abstract classes provides all the common methods and variables for the
 * custom import classes.
 * Copied from the original abstract class for PLA LDIF file imports -
 * specialised for CSV files, including selection of file delimiter.
 *
 * @package phpLDAPadmin
 * @subpackage UDIImport
 */
abstract class Import {
    protected $server_id = null;
    protected $filename;
    protected $realname;
    protected $input = null;
    protected $source = array();
    protected $delimiter;

    /**
     * Constructor
     * hook in the current directory environment, and the file details
     * 
     * @param Integer $server_id - LDAP connection Id
     * @param String $file file - could be tmp name
     * @param String $realname name of the file
     */
    public function __construct($server_id, $file, $realname='') {
        $this->server_id = $server_id;
        $this->filename = $file;
        $this->realname = ($realname ? $realname : $file);
    }

    /**
     * Accept the file - pull in it's contents and split into lines
     * 
     * @param Char $delimiter delimiter of csv file - tab, ',', ;
     */
    public function accept($delimiter) {
        $this->delimiter = $delimiter;
        if (file_exists($this->filename)) {
            $this->source['name'] = $this->realname;
            $this->source['size'] = filesize($this->filename);
            $input = file_get_contents($this->filename);
            $this->input = preg_split("/\n|\r\n|\r/",$input);
            	
        } else {
            system_message(array(
                'title'=>_('No UDI import input'),
                'body'=>_('You must specify a valid file for upload: ').$this->realname,
                'type'=>'error'),
            sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
            get_request('udi_nav','REQUEST'),
            get_request('server_id','REQUEST')));
            die();
        }
    }

    /**
     * Accessor for source file details
     * 
     * @param $attr - which source file detail - name/size
     * 
     * @return String file attrbute value
     */
    public function getSource($attr) {
        if (isset($this->source[$attr])) {
            return $this->source[$attr];
        }
        return null;
    }
}

/**
 * Import entries from the UDI CSV file format
 * Implementation of abstract class
 * 
 *
 * @package phpLDAPadmin
 * @subpackage UDIImport
 */
class ImportCSV extends Import {
    private $_currentLineNumber = 0;
    private $_currentLine = '';
    private $_valid_attrs;
    private $_headers;
    private $_header_line;
    private $template;
    public $error = array();

    /**
     * Accept the file details, and then grab the first line
     * as a header
     * 
     * Validate the header line - this gives assurance that:
     * a) it is actually a headerline
     * b) that it will map to directory elements
     * 
     * @param String $delimiter csv file delimiter
     */
    public function accept($delimiter) {
        global $app;
        parent::accept($delimiter);
        $this->_header_line = $this->readEntry(true);
        // header must exists
        if (empty($this->_header_line)) {
            system_message(array(
                'title'=>_('No valid header line in UDI import'),
                'body'=>_('You must specify a valid file for upload, with the first record being the column headings.'),
                'type'=>'error'),
            sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
            get_request('udi_nav','REQUEST'),
            get_request('server_id','REQUEST')));
            die();
        }

        // mandatory headers must exist
        $socs = $app['server']->SchemaObjectClasses('login');
        $mlepPerson = $socs['mlepperson'];
        $must = $mlepPerson->getMustAttrs();
        $dmo_attrs = $app['server']->SchemaAttributes('login');
        $dmo_attrs = array_merge(array("mlepgroupmembership" => new ObjectClass_ObjectClassAttribute("mlepgroupmembership", "mlepGroupMembership")), $dmo_attrs);
        //        $this->_valid_attrs = $dmo_attrs;
        $headers = array();
        foreach ($this->_header_line as $hdr) {
            $headers[strtolower($hdr)]= $hdr;
        }
        foreach ($must as $attr) {
            if (!isset($headers[$attr->getName()])) {
                system_message(array(
                    'title'=>_('UDI import mandatory field missing: ').$attr->getName(false),
                    'body'=>_('You must specify a valid file for upload, with the first record being the column headings, that must atleast contain all the mandatory fields, and fields that correspond to valid LDAP schema attributes.'),
                    'type'=>'error'),
                sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
                get_request('udi_nav','REQUEST'),
                get_request('server_id','REQUEST')));
                die();
            }
        }

        // all other headers must exist in the schema as a valid attribute
        foreach ($headers as $hdr => $name) {
            if (!isset($dmo_attrs[$hdr])) {
                system_message(array(
                    'title'=>_('UDI import illegal field specified: ').$name,
                    'body'=>_('You must specify a valid file for upload, with the first record being the column headings, that must atleast contain all the mandatory fields, and fields that correspond to valid LDAP schema attributes.'),
                    'type'=>'error'),
                sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
                get_request('udi_nav','REQUEST'),
                get_request('server_id','REQUEST')));
                die();
            }
        }

        // save headers
        $this->_headers = $headers;

    }

    /**
     * Accessor for the unpacked csv header line
     * 
     * @return array header line columns
     */
    public function getCSVHeader() {
        return $this->_header_line;
    }

    /**
     * declaration of this type of object
     * 
     * @return array of class attributes
     */
    static public function getType() {
        return array('type'=>'CSV','description' => _('CSV Import'),'extension'=>'csv');
    }

    /**
     * Accessor for template object
     * 
     * @return object the template
     */
    protected function getTemplate() {
        return $this->template;
    }

    /**
     * Get the current LDAP server object
     * 
     * @return object server
     */
    protected function getServer() {
        return $_SESSION[APPCONFIG]->getServer($this->server_id);
    }

    /**
     * Get the next line of the file
     * 
     * @param bool $header true - get the header line
     * 
     * @return the next line of the file or false
     */
    public function readEntry($header=false) {

        if ($line = $this->nextLine($header)) {
            return $line;
        }
        else {
            return false;
        }
    }

    /**
     * Get the line of the next entry
     *
     * @return The lines (unfolded) of the next entry
     */
    private function nextLine($header=false) {

        if (!$this->eof()) {
            $this->advanceNextLine();
            while (!$this->eof() && ($this->isCommentLine() || $this->isBlankLine())) {
                $this->advanceNextLine();
            }
            if ($this->isCommentLine() || $this->isBlankLine()) {
                return false;
            }
            else {
                if ($header) {
                    return str_getcsv(trim($this->_currentLine), $this->delimiter);
                }
                return $this->validateLine();
            }
        }
        else {
            return false;
        }
    }

    /**
     * Get the line of the next entry - check that it has the correct number of 
     * columns
     *
     * @return The lines (unfolded) of the next entry
     */
    private function validateLine() {
        $line = str_getcsv(trim($this->_currentLine), $this->delimiter);

        if (count($line) != count($this->_headers)) {
            system_message(array(
                'title'=>sprintf(_('UDI import invalid record: %s'), $this->_currentLineNumber),
                'body'=>sprintf(_('You must specify a valid file for upload, with all records having the same number and type of column as specified by the column headings. <br/> Record no. %s is: %s <br/> Header is: %s'), $this->_currentLineNumber, $this->_currentLine, implode(', ', $this->_header_line)),
                'type'=>'error'),
            sprintf('cmd.php?cmd=udi_form&udi_nav=%s&server_id=%s',
            get_request('udi_nav','REQUEST'),
            get_request('server_id','REQUEST')));
            die();
        }

        return $line;
    }

    /**
     * Get the line of the next entry
     *
     * @return The lines (unfolded) of the next entry
     */
    private function advanceNextLine() {

        $this->_currentLineNumber++;
        $this->_currentLine = array_shift($this->input);
    }

    /**
     * Check if it's a comment line.
     *
     * @return boolean true if it's a comment line,false otherwise
     */
    private function isCommentLine() {
        return substr(trim($this->_currentLine),0,1) == '#' ? true : false;
    }

    /**
     * Check if is the current line is a blank line.
     *
     * @return boolean if it is a blank line,false otherwise.
     */
    private function isBlankLine() {
        $blank = trim($this->_currentLine);
        return empty($blank) ? true : false;
    }

    /**
     * Returns true if we reached the end of the input.
     *
     * @return boolean true if it's the end of file, false otherwise.
     */
    public function eof() {
        return count($this->input) > 0 ? false : true;
    }

    /**
     * Store errors for later display
     * 
     * @param String $msg message to display
     * @param String $data to attach to error
     */
    private function error($msg,$data) {
        $this->error['message'] = sprintf('%s [%s]',$msg,$this->template ? $this->template->getDN() : '');
        $this->error['line'] = $this->_currentLineNumber;
        $this->error['data'] = $data;
        $this->error['changetype'] = $this->template ? $this->template->getType() : 'Not set';

        return false;
    }
}

/**
 * CSV Import file processor
 * 
 * validation, create, update, delete routines for user accounts
 * 
 * @author piers
 *
 * @package phpLDAPadmin
 * @subpackage UDIProcessor
 *
 */
class Processor {
    // Server that the export is linked to
    private $server;

    // The actual import data
    private $data;

    // Current config
    private $cfg;
    public $udiconfig;

    // arrays of the different record types
    private $to_be_deleted;
    private $to_be_created;
    private $to_be_updated;

    // user groups derived from config
    private $total_groups;
    private $group_mappings;
    
    /**
     * Constructor
     * build connection to environment and LDAP directory
     * pull in the UDI config
     * 
     * @param object $server LDAP directory
     * @param array $data CSV file contents
     */
    public function __construct($server, $data=array()) {
        $this->server = $server;
        $this->data = $data;
        $this->udiconfig = new UdiConfig($this->server);
        $this->cfg = $this->udiconfig->getConfig();
    }


    /**
     * Validates the import against the user directory
     *
     * @return boolean true if file validates.
     */
    public function validate() {
        global $request;
        /*
         * Validation
         *
         * 3 main cases:
         *
         * user exists in file but not in directory
         *      - create account
         *
         * user exists in directory - but not in file
         *      - delete/deactivate account
         *
         * user exists in directory and file
         *      - update account
         *
         * When creating or updating an account - ensure
         * that the account has all the correct objectClasses
         * This may require some to be added
         * This also requires checking that the MUST attributes either
         * exist already, or will be added
         *
         * user existance is checked by using the match_from / match_to
         *
         * New accounts created into a specified bucket - must be one
         * of the search bases
         * 
         * Validation stashes the sets of creates/updates/deletes ready for the 
         * next phase of processing
         *
         */
        
        // is the UDI enabled
        if ($this->cfg['enabled'] != 'checked') {
            $request['page']->error(_('Processing is not enabled - check configuration'), _('processing'));
            return false;
        }

        // first, find a list of all the existing user accounts
        $accounts = array();
        
        $bases = explode(';', $this->cfg['search_bases']);
//        // also check the deactivated base
//        $bases []= $this->cfg['move_to'];
        
        // target identifier - this is the attribute in the directory to match accounts on
        $id = strtolower($this->cfg['dir_match_on']);
        
        // run through all the search bases
        foreach ($bases as $base) {
            // ensure that accounts inspected have the mlepPerson object class
            $query = $this->server->query(array('base' => $base, 'filter' => "(&(objectclass=mlepperson)($id=*))"), 'login');
            if (empty($query)) {
                // base does not exist
                $request['page']->warning(_('No user accounts found in search base: ').$base, _('processing'));
            }
            else {
                // run through each discovered account
                foreach ($query as $user) {
                    $uid = $user[$id][0];
                    // uid MUST NOT already exist
                    if (isset($accounts[$uid])) {
                        $request['page']->error(_('Duplicate user accounts found: ').
                        $user['dn'].
                        _(' clashes with: ').
                        $accounts[$uid]['dn'].
                        _(' on matching: ').
                        $id.'/'.$uid, _('processing'));
                        return false;
                    }
                    $accounts[$uid] = $user;
                }
            }
        }

       // get mapping configuration - map input file fields to LDAP attributes
        $cfg_mappings = $this->udiconfig->getMappings();
        $field_mappings = array();
        foreach ($cfg_mappings as $mapping) {
            $field_mappings[$mapping['source']] = $mapping['targets'];
        }
        $total_fields = array();
        
        // check for duplication of fields in header line
        foreach ($this->data['header'] as $header) {
            // skip the group membership column
            if (strtolower($header) == 'mlepgroupmembership') {
                continue;
            }
            // dont worry about the ones covered by mappings
            if (isset($field_mappings[$header])) {
                continue;
            }
            if (isset($total_fields[strtolower($header)])) {
                $request['page']->error(_('Duplicate target field in header: ').$header, _('processing'));
                return false;
            }
            $total_fields[strtolower($header)] = $header;
        }
        
        // check for compounded duplication from the mapping
        foreach ($cfg_mappings as $mapping) {
            foreach($mapping['targets'] as $field) {
                if (isset($total_fields[strtolower($field)])) {
                    $request['page']->error(_('Duplicate target field in mapping source: ').$mapping['source']._(' to target: ').$field, _('processing'));
                    return false;
                }
                $total_fields[strtolower($field)] = $field;
            }
        }
        
        // all target fields must exist in the schema
        $total_attrs = array();
        $classes = $this->udiconfig->getObjectClasses();
        $socs = $this->server->SchemaObjectClasses('login');
        foreach ($classes as $class) {
            foreach ($socs[strtolower($class)]->getMustAttrs(true) as $attr) {
                $total_attrs[$attr->getName()] = true;
            }
            foreach ($socs[strtolower($class)]->getMayAttrs(true) as $attr) {
                $total_attrs[$attr->getName()] = false;
            }
        }
        foreach ($total_fields as $field) {
            if (!isset($total_attrs[strtolower($field)])) {
                $request['page']->error(_('Unknown target attribute name (check the column headings, and mapping): ').$field, _('processing'));
                return false;
            }
        }

        // make sure that all mandatory attributes from the required object classes
        // are present
        foreach ($total_attrs as $attr => $mandatory) {
            // skip some core attributes
            if ($attr == 'objectclass' || $attr == 'cn') {
                continue;
            }
            if ($mandatory && !isset($total_fields[$attr])) {
                $request['page']->error(_('Mandatory LDAP attribute missing from import: ').$attr, _('processing'));
                return false;
            }
        }
        
        // reorder the list of import users based on their match_from
        $imports = array();
        $iuid = $this->cfg['import_match_on'];
        $row_cnt = 0;
        foreach ($this->data['contents'] as $row) {
            $row_cnt++;
            $cell = 0;
            $user = array();
            // check for MUST mapped values
            foreach ($this->data['header'] as $header) {
                $user[$header] = $row[$cell];
                if (strtolower($header) == 'mlepgroupmembership') {
                    $cell++;
                    continue;
                }
                if (isset($field_mappings[$header])) {
                    foreach ($field_mappings[$header] as $target) {
                        $value = trim($user[$header]);
                        if ($total_attrs[strtolower($target)] && empty($value) && strtolower($target) != 'mlepusername') {
                            return $request['page']->error(_('Mandatory value: ').$header._(' (maps to: ').$target.')'._(' is empty in row: ').$row_cnt, _('processing'));
                        }
                    }
                }
                else {
                    $value = trim($user[$header]);
                    if ($total_attrs[strtolower($header)] && empty($value) && strtolower($header) != 'mlepusername') {
                        return $request['page']->error(_('Mandatory value: ').$header._(' (maps to: ').$header.')'._(' is empty in row: ').$row_cnt, _('processing'));
                    }
                }
                $cell++;
            }
            $imports[$user[$iuid]] = $user;
        }

        // find the missing accounts in the directory
        $this->to_be_deleted = array_diff_key($accounts, $imports);
        
        // find the new accounts in the file
        $this->to_be_created = array_diff_key($imports, $accounts);

        // find the accounts to be updated
        $to_be_updated = array_intersect_key($accounts, $imports);
        $this->to_be_updated = array();  
        foreach ($to_be_updated as $id => $account) {
            $data = $imports[$id];
            $data['dn'] = $account['dn'];
            $this->to_be_updated[$id] = $data;
        }
        
        // Hunt down existing uid/mlepUsernames to avoid duplicates
        $duplicates = array();
        $row_cnt = 0;
        foreach ($this->to_be_created as $account) {
            $row_cnt++;
            
            // run userid hook
            if (!isset($this->cfg['ignore_userids']) || !$this->cfg['ignore_userids']) {
                $result = udi_run_hook('account_create_before',array($this->server, $this->udiconfig, $account), $this->cfg['userid_algo']);
                if (is_array($result)) {
                    $result = array_pop($result);
                    if (is_array($result)) {
                        $account = $result;
                    }
                }
            }

            // User Id must exist now
            if (empty($account['mlepUsername'])) {
                return $request['page']->error(_('Mandatory value: mlepUsername ')._(' is empty in row: ').$row_cnt, _('processing'));
            }
           
            // run passwd hook
            if (!isset($this->cfg['ignore_passwds']) || !$this->cfg['ignore_passwds']) {
                $result = udi_run_hook('passwd_algorithm',array($this->server, $this->udiconfig, $account, $this->cfg['passwd_parameters']), $this->cfg['passwd_algo']);
                if (is_array($result)) {
                    $result = array_pop($result);
                    if (!empty($result)) {
                        $account['userPassword'] = $result;
                    }
                }
            }
            
            $uid = (isset($account['mlepUsername']) ? $account['mlepUsername'] : false);
            if (isset($duplicates[$uid])) {
                $request['page']->error(_('User account is duplicate in import file: '), _('processing'));
                return false;
            }
            $duplicates[$uid] = $uid;
             
            // make sure that an account doesn't allready exist in the directory
            // with this Id 
            if ($uid) {
                // check for mlepUsername
                $query = $this->server->query(array('base' => $this->udiconfig->getBaseDN(), 'filter' => "(mlepUsername=$uid)", 'attrs' => array('dn')), 'login');
                if (!empty($query)) {
                    // base does not exist
                    $request['page']->warning(_('User account is duplicate in directory for mlepUsername: ').$uid, _('processing'));
                }
                // check for uid
                $query = $this->server->query(array('base' => $this->udiconfig->getBaseDN(), 'filter' => "(uid=$uid)", 'attrs' => array('dn')), 'login');
                if (!empty($query)) {
                    // base does not exist
                    $request['page']->warning(_('User account is duplicate in directory for uid: ').$uid, _('processing'));
                }

                // check for mlepUsername in the deletions directory
                if (!empty($this->cfg['move_to'])) {
                    $query = $this->server->query(array('base' => $this->cfg['move_to'], 'filter' => "(mlepUsername=$uid)", 'attrs' => array('dn')), 'login');
                    if (!empty($query)) {
                        // base does not exist
                        $request['page']->warning(_('User account is duplicate in deletion (').$this->cfg['move_to']._(') directory for mlepUsername: ').$uid, _('processing'));
                    }
                }
            }
        }
        
        $request['page']->info(_('Calculated: ').count($this->to_be_created)._(' creates ').count($this->to_be_updated)._(' updates ').count($this->to_be_deleted)._(' deletes'), _('processing'));
        return true;
    }


    /**
     * Generate a list of the existing accounts as per the mlepPerson schema
     *
     * @return array list of accounts
     */
    public function listAccounts() {
        global $request;
        
        // is the UDI enabled
        if ($this->cfg['enabled'] != 'checked') {
            $request['page']->error(_('Processing is not enabled - check configuration'), _('processing'));
            return false;
        }

        // first, find a list of all the existing user accounts
        $accounts = array();
        $bases = explode(';', $this->cfg['search_bases']);
        
        // target identifier - this is the attribute in the directory to match accounts on
        $id = strtolower($this->cfg['dir_match_on']);

        $total_attrs = array();
        $classes = $this->udiconfig->getObjectClasses();
        $socs = $this->server->SchemaObjectClasses('login');
        $skip = array('objectclass', 'userpassword');
        foreach ($classes as $class) {
            foreach ($socs[strtolower($class)]->getMustAttrs(true) as $attr) {
                if (!in_array($attr->getName(), $skip)) {
                    $total_attrs[$attr->getName()] = $attr->getName();
                }
            }
            foreach ($socs[strtolower($class)]->getMayAttrs(true) as $attr) {
                if (!in_array($attr->getName(), $skip)) {
                    $total_attrs[$attr->getName()] = $attr->getName();
                }
            }
        }

        // add header record
        $account = array();
        foreach ($total_attrs as $attr) {
            $account[]= $attr;
        }
        $accounts[]= $account;
        
        // run through all the search bases
        foreach ($bases as $base) {
            // ensure that accounts inspected have the mlepPerson object class
            $query = $this->server->query(array('base' => $base, 'filter' => "(&(objectclass=mlepperson)($id=*))"), 'login');
            if (!empty($query)) {
                // run through each discovered account
                foreach ($query as $user) {
                    $account = array();
                    foreach ($total_attrs as $attr) {
                        $account[] = ((isset($user[$attr]) && isset($user[$attr][0])) ? $user[$attr][0] : ''); 
                    }
                    $accounts[]= $account;
                }
            }
        }
        return $accounts;
    }

    
    /**
     * Process the entire file according to the config
     * 
     * @return bool true on success
     */
    public function import() {
        
        global $request;
        
        $result = true;
        
        if ($this->cfg['enabled'] != 'checked') {
            $request['page']->error(_('Processing is not enabled - check configuration'), _('processing'));
            return false;
        }

        // must do deletes first - as there might be rename issues
        if ($result && $this->cfg['ignore_deletes'] != 'checked') {
            $result = $this->processDeletes();
        }

        if ($result && $this->cfg['ignore_updates'] != 'checked') {
            $result = $this->processUpdates();
        }
        
        if ($this->cfg['ignore_creates'] != 'checked') {
            $result = $this->processCreates();
        }

        return $result;
    }
    
    
    /**
     * Process user create records
     * cycle through each record mapping out the user attributes for creation
     * remove (as a caution) the user from configured user groups, and then readd them
     * to the specified ones on the group mapping
     * 
     * @return bool true on success
     */
    public function processCreates() {
        global $request;

        // get mapping configuration
        $cfg_mappings = $this->udiconfig->getMappings();
        $cfg_group_mappings = $this->udiconfig->getGroupMappings();
        $cfg_container_mappings = $this->udiconfig->getContainerMappings();
        $container_mappings = array();
        $group_mappings = array();
        $total_groups = array();
        foreach ($cfg_container_mappings as $mapping) {
            $container_mappings[$mapping['source']] = $mapping['target'];
        }
        foreach ($cfg_group_mappings as $mapping) {
            $group_mappings[$mapping['source']] = $mapping['targets'];
            foreach ($mapping['targets'] as $target) {
                $total_groups[$target] = $target;
            }
        }
        $field_mappings = array();
        foreach ($cfg_mappings as $mapping) {
            $field_mappings[$mapping['source']] = $mapping['targets'];
        }

        // create the missing
        foreach ($this->to_be_created as $account) {

            // inject object classes
            $account['objectclass'] = $this->udiconfig->getObjectClasses();

            // start building up the creation template
            $template = new Template($this->server->getIndex(),null,null,'add');
            
            // sort out the common name
            $cn = '';
            if (isset($acount['cn'])) {
                $cn = $acount['cn'];
            }
            else {
                $cn = $account['mlepFirstName'].' '.$account['mlepLastName'];
            }
            
            // sort out uid
            $uid = '';
            if (isset($acount['uid'])) {
                $uid = $acount['uid'];
            }
            else {
                $uid = $account['mlepUsername'];
            }
            
            // store the mlepGroupMembership
            $group_membership = false;
            if (isset($account['mlepgroupmembership'])) {
                $group_membership = $account['mlepgroupmembership'];
            }
            else if (isset($account['mlepGroupMembership'])) {
                $group_membership = $account['mlepGroupMembership'];
            }

            // determine the target container
            $user_container = $this->cfg['create_in'];
            if ($group_membership) {
                $groups = explode('#', $group_membership);
                foreach ($groups as $group) {
                    if (isset($container_mappings[$group])) {
                        $user_container = $container_mappings[$group];
                        break;
                    }
                }
            }
            
            // sort out what the dn attribute is
            $dn = '';
            switch ($this->cfg['dn_attribute']) {
                case 'cn':
                    $dn = 'cn='.$cn.','.$user_container;
                    break;
                case 'uid':
                    $dn = 'uid='.$uid.','.$user_container;
                    break;
            }
            $rdn = get_rdn($dn);
            $container = $this->server->getContainer($dn);
            $template->setContainer($container);
            $template->accept();
            
            // run userid hook
            if (!isset($this->cfg['ignore_userids']) || !$this->cfg['ignore_userids']) {
                $result = udi_run_hook('account_create_before',array($this->server, $this->udiconfig, $account), $this->cfg['userid_algo']);
                if (is_array($result)) {
                    $result = array_pop($result);
                    if (is_array($result)) {
                        $account = $result;
                    }
                }
            }
           
            // run passwd hook
            if (!isset($this->cfg['ignore_passwds']) || !$this->cfg['ignore_passwds']) {
                $result = udi_run_hook('passwd_algorithm',array($this->server, $this->udiconfig, $account, $this->cfg['passwd_parameters']), $this->cfg['passwd_algo']);
                if (is_array($result)) {
                    $result = array_pop($result);
                    if (!empty($result)) {
                        $account['userPassword'] = $result;
                    }
                }
            }
            
            // encrypt the passwords
            $account['raw_passwd'] = $account['userPassword'];
            if (isset($account['userPassword']) && $this->cfg['encrypt_passwd'] != 'none') {
                $account['userPassword'] = password_hash($account['userPassword'], $this->cfg['encrypt_passwd']);
            }

            // need to prevent doubling up of attribute values
            $total_fields = array();
            $uid = false;
            $mlepusername = false;
            foreach ($account as $attr => $value) {
                // skip the stashed raw password value
                if (strtolower($attr) == 'raw_passwd') {
                    continue;
                }
                
                // skip the mlepgroupmembership
                if (strtolower($attr) == 'mlepgroupmembership') {
                    continue;
                }

                if ($attr != 'objectclass') {
                    $value = trim($value);
                }
                
                // split the multi-value attributes
                if (strtolower($attr) == 'mlepassociatednsn') {
                    $value = empty($value) ? array() : explode('#', $value);
                }
                
                // store UserId candidates
                if (strtolower($attr) == 'mlepusername') {
                    $mlepusername = $value;
                }
                else if(strtolower($attr) == 'uid') {
                    $uid = $value;
                }
                
                // map attributes here
                if (isset($field_mappings[$attr])) {
                    foreach ($field_mappings[$attr] as $target) {
                        // dont allow doubling up
                        if (isset($total_fields[$target])) {
                            continue;
                        }
                        $total_fields[$target] = $target;
                        $this->addAttribute($template, $target, $value);
                    }
                }
                else {
                    // dont allow doubling up
                    if (!isset($total_fields[$attr])) {
                        $total_fields[$attr] = $attr;
                        $this->addAttribute($template, $attr, $value);
                    }
                }
            }
            // ensure the sanity fields are set
            if (!isset($total_fields['cn'])) {
                $this->addAttribute($template, 'cn', array($cn));
            }
//            if (!isset($total_fields['uid'])) {
//                $this->addAttribute($template, 'uid', array($uid));
//            }
            $template->setRDNAttributes($rdn);
            // set the CN
            $result = $this->server->add($dn, $template->getLDAPadd(), 'login');
            if (!$result) {
                $request['page']->error(_('Could not create: ').$dn, _('processing'));
                return $result;
            }
            else {
                // need to set the group membership
                // need to find all existing groups, and then delete those memberships first
                if (strtolower($this->cfg['group_attr']) == 'memberuid') {
                    if (empty($uid)) {
                        $uid = $mlepusername;
                    }
                }
                else {
                    // it must a member style DN group
                    $uid = $dn; 
                }
                if (!$this->replaceGroupMembership(false, $uid, $group_membership, $dn)) {
                    return false;
                }
            }
            // now access for reporting
            if (isset($account['raw_passwd'])) {
                $account['userPassword'] = $account['raw_passwd'];
                unset($account['raw_passwd']);
            }
            udi_run_hook('account_create_after', array($this->server, $this->udiconfig, $account));
        }
        return true;
    }

    /**
     * Add a DN to the internal PLA tree cache - must be done prior to 
     * manipulation
     * 
     * @param String $dn DN of tree node
     */
    private function addTreeItem($dn) {
        $tree = get_cached_item($this->server->getIndex(),'tree');
        if (!$tree->getEntry($dn)) {
            $tree->addEntry($dn);
        }
    }
   
    
    /**
     * Process user update records
     * 
     * @return bool true on success
     */
    public function processUpdates() {
        global $request;

        // get mapping configuration
        $cfg_mappings = $this->udiconfig->getMappings();
        
        // inject object classes
        $objectclass = $this->udiconfig->getObjectClasses();        
        
        // get Ignore for update attributes
        $ignore_attrs = array();
        foreach ($this->udiconfig->getIgnoreAttrs() as $attr) {
            $ignore_attrs[strtolower($attr)] = $attr;
        }

        $field_mappings = array();
        foreach ($cfg_mappings as $mapping) {
            $field_mappings[$mapping['source']] = $mapping['targets'];
        }

        // process the updates
        foreach ($this->to_be_updated as $account) {

            $dn = $account['dn'];
            
            // find the existing one
            $query = $this->server->query(array('base' => $dn), 'login');
            $existing_account = array_shift($query);
            $user_total_classes = array_unique(array_merge($existing_account['objectclass'], $objectclass));
            $old_uid = false;
            if (isset($existing_account['uid']) && !empty($existing_account['uid'][0])) {
                $old_uid = $existing_account['uid'][0];
            }
            else if (isset($existing_account['mlepusername']) && !empty($existing_account['mlepusername'][0])) {
                $old_uid = $existing_account['mlepusername'][0];
            }
            
            // start building up the modification template
            $template = new Template($this->server->getIndex(),null,null,'modify');
            
            $rdn = get_rdn($dn);
            $template->setDN($dn);
            $template->accept();
            
            // run userid hook
            if (!isset($this->cfg['ignore_userids']) || !$this->cfg['ignore_userids']) {
                $result = udi_run_hook('userid_algorithm',array($this->server, $this->udiconfig, $account), $this->cfg['userid_algo']);
                if (is_array($result)) {
                    $result = array_pop($result);
                    if (is_array($result)) {
                        $account = $result;
                    }
                }
            }
            
            // check object classes
            if (count($existing_account['objectclass']) < $user_total_classes) {
                // update user object classes
                $this->modifyAttribute($template, 'objectclass', $user_total_classes);
            }
            
            $group_membership = false;
            $uid = false;
            $mlepusername = false;
//            var_dump($account);
            foreach ($account as $attr => $value) {
                // ignore the dn
                if ($attr == 'dn') {
                    continue;
                }
                // skip the mlepgroupmembership
                if (strtolower($attr) == 'mlepgroupmembership') {
                    $group_membership = $value;
                    continue;
                }
                // store UserId candidates
                if (strtolower($attr) == 'mlepusername') {
                    $mlepusername = $value;
                }
                else if(strtolower($attr) == 'uid') {
                    $uid = $value;
                }
                
                $value = trim($value);
                
                // split the multi-value attributes
                if (strtolower($attr) == 'mlepassociatednsn') {
                    $value = empty($value) ? array() : explode('#', $value);
                }
                
                // map attributes here
                if (isset($field_mappings[$attr])) {
                    foreach ($field_mappings[$attr] as $target) {
                        // check ignore attrs
                        if (isset($ignore_attrs[strtolower($target)])) {
                            continue;
                        }
                        if (isset($existing_account[strtolower($attr)]) || !empty($value)) {
                            $this->modifyAttribute($template, $target, $value);
                        }
                    }
                }
                else {
                    // check ignore attrs
                    if (!isset($ignore_attrs[strtolower($attr)])) {
                        if (isset($existing_account[strtolower($attr)]) || !empty($value)) {
                            $this->modifyAttribute($template, $attr, $value);
                        }
                    }
                }
            }
            // make sure item exists in the tree
            $this->addTreeItem($dn);
            $result = $this->server->modify($dn, $template->getLDAPmodify(), 'login');
            if (!$result) {
                $request['page']->error(_('Could not create: ').$dn, _('processing'));
                return $result;
            }
            else {
                // need to set the group membership
                // need to find all existing groups, and then delete those memberships first
                $new_uid = false;
                if (strtolower($this->cfg['group_attr']) == 'memberuid') {
                    if ($uid) {
                        $new_uid = $uid;
                    }
                    else if ($mlepusername) {
                        $new_uid = $mlepusername;
                    }
                    // need to cover the cases of where a userid is generated
                    // so it is never passed, and therefore should never be updated
                    if (empty($new_uid)) {
                        $new_uid = $old_uid;
                    }
                }
                else {
                    // it must a member style DN group - CN can't change so old == new
                    $new_uid = $dn; 
                    $old_uid = $new_uid;
                }
                if (!$this->replaceGroupMembership($old_uid, $new_uid, $group_membership, $dn)) {
                    return false;
                }
            }
        }
        return true;
    }

    
    /**
     * Replace a users group membership 
     * 
     * @param String $uid userid relative to the configured user attribute 
     * @param String $group_membership as per mlepGroupMembership export schema definition
     * 
     * @return bool true on success
     */
    private function removeGroupMembership($uid) {
        global $request;
        
        // must have a user id
        if (!$uid) {
            return false;
        }
        
        // cache the groups to deal with
        $this->cacheGroups();
        
        // hunt for existing group membership and remove
        $group_attr = $this->cfg['group_attr'];
//        if (strtolower($group_attr) != 'memberof') {
        foreach ($this->total_groups as $group) {
            // check and delete from group
            
            $query = $this->server->query(array('base' => $group, 'filter' => "($group_attr=$uid)"), 'login');
            if (!empty($query)) {
                // user exists in group
                $query = $this->server->query(array('base' => $group), 'login');
                // remove user from membership attribute and then save again
                $existing = array_shift($query);
                $template = $this->createModifyTemplate($group);
                $attribute = $template->getAttribute(strtolower($group_attr));
                $values = $existing[strtolower($group_attr)];
                $values = array_merge(preg_grep('/^'.$uid.'$/', $values, PREG_GREP_INVERT), array());
                $attribute->setValue($values);
                // Perform the modification
                $this->addTreeItem($group);
                $result = $this->server->modify($group, $template->getLDAPmodify(), 'login');
                if (!$result) {
                    return $request['page']->error(_('Could not remove user from group: ').$uid.'/'.$group, _('processing'));
                }
            }
//            }
        }
        return true;
    }
    
   
    /**
     * Ensure that the caches are purged 
     * 
     * @return bool true on success
     */
    public function purge() {
        $tree = get_cached_item($this->server->getIndex(),'tree');
        del_cached_item($this->server->getIndex(),'tree');
    
        if ($tree)
            $openDNs = $tree->listOpenItems();
        else
            $openDNs = array();
    
        $tree = Tree::getInstance($this->server->getIndex());
    
        foreach ($openDNs as $value) {
            $entry = $tree->getEntry($value);
            if (! $entry) {
                $tree->addEntry($value);
                $entry = $tree->getEntry($value);
            }
    
            $tree->readChildren($value,true);
            $entry->open();
        }
    
        set_cached_item($this->server->getIndex(),'tree','null',$tree);
        
//        $purge_session_keys = array('app_initialized','backtrace','cache');
//        foreach ($purge_session_keys as $key) {
//            if (isset($_SESSION[$key])) {
//                unset($_SESSION[$key]);
//            }
//        }
        return true;
    }
    
   
    /**
     * Ensure that the group membership data is cached 
     * 
     * @return bool true on success
     */
    private function cacheGroups() {
        // cache the groups to deal with
        if (!$this->total_groups) {
            $cfg_group_mappings = $this->udiconfig->getGroupMappings();
            $this->total_groups = array();
            $this->group_mappings = array();
            foreach ($cfg_group_mappings as $mapping) {
                $this->group_mappings[$mapping['source']] = $mapping['targets'];
                foreach ($mapping['targets'] as $target) {
                    $this->total_groups[$target] = $target;
                }
            }
        }
        return true;
    }
    
    
    /**
     * Replace a users group membership 
     * 
     * @param String $uid userid relative to the configured user attribute 
     * @param String $group_membership as per mlepGroupMembership export schema definition
     * 
     * @return bool true on success
     */
    private function replaceGroupMembership($old_uid, $new_uid, $group_membership, $user_dn) {
        global $request;

//        echo "old: $old_uid  new: $new_uid groups: $group_membership dn: $user_dn\n";

        // must have a user id
        if (!$new_uid) {
            return $request['page']->error(_('No uid passed, so cannot alter membership: ').$user_dn, _('processing'));
        }
            
        // cache the groups to deal with
        $this->cacheGroups();
                
        
        // hunt for existing group membership and remove
        $this->removeGroupMembership($old_uid);

        // then re add memberships
        $group_attr = $this->cfg['group_attr'];
//        $memberof_groups = array();
        if ($group_membership) {
            $groups = explode('#', $group_membership);
            foreach ($groups as $group) {
                if (isset($this->group_mappings[$group])) {
                    foreach($this->group_mappings[$group] as $mapping) {
                        // insert mlepUsername in to the group from here
                        $template = $this->createModifyTemplate($mapping);
                        $query = $this->server->query(array('base' => $mapping), 'login');
                        if (empty($query)) {
                            // group does not exist
                            return $request['page']->error(_('Membership group does not exist: ').$mapping, _('processing'));
                        }

                        // add back all the existing attribute values
                        $existing = array_shift($query);
//                        // check for memberOf, as this goes on the user - not the group container
//                        if (strtolower($group_attr) == 'memberof') {
//                            $memberof_groups []= $existing['dn'];
//                        }
//                        else {
                        if (isset($existing[strtolower($group_attr)])) {
                            $values = $existing[strtolower($group_attr)];
                        }
                        else {
                            $values = array();
                        }
                        // don't attempt to add them if they are allready there
                        if (!in_array($new_uid, $values)) {
                            $values[] = $new_uid;
                            $this->modifyAttribute($template, $group_attr, $values);
                            # Perform the modification
                            $this->addTreeItem($mapping);
                            $result = $this->server->modify($mapping,$template->getLDAPmodify(), 'login');
                            if (!$result) {
                                return $request['page']->error(_('Could not add user to group: ').$new_uid.'/'.$mapping, _('processing'));
                            }
                        }
//                        }
                    }
                }
            }
//            // if this is controlled by memberOf - then update the user with the list of group DNs
//            if (strtolower($group_attr) == 'memberof') {
//                $template = new Template($this->server->getIndex(),null,null,'modify');
//                $rdn = get_rdn($user_dn);
//                $template->setDN($user_dn);
//                $template->accept();
//                $this->modifyAttribute($template, $group_attr, $memberof_groups);
//                # Perform the modification
//                $result = $this->server->modify($user_dn,$template->getLDAPmodify(), 'login');
//                if (!$result) {
//                    $request['page']->error(_('Could not update user groups: ').$user_dn.'/'.implode('|', $memberof_groups), _('processing'));
//                }
//                
//            }
        }
        return true;
    }

    
    /**
     * validate the user reactivation request
     * 
     * @return bool true on success
     */
    public function validateReactivation() {
        global $request;
        $result = true;

        $children = $this->server->getContainerContents($this->cfg['move_to'], 'login', 0, '(objectClass=*)', LDAP_DEREF_NEVER);
        foreach ($children as $child) {
            $query = $this->server->query(array('base' => $child), 'login');
            $account = array_shift($query);
            // check that there isnt a duplicate there already
            $deactive_dn = $account['dn'];
            if (!isset($account['labeleduri'])) {
                $request['page']->info(_('Deactivated account does not have old DN - cannot restore: ').$dactive_dn, _('processing'));
                $result = false;
            }
            else {
                $labeleduri = $account['labeleduri'];
                $old_dn = preg_grep('/^udi_deactivated:/', $account['labeleduri']);
                $old_dn = array_shift($old_dn);
                if (empty($old_dn)) {
                    $request['page']->info(_('Deactivated account does not have old DN on lableURI - cannot restore: ').$deactive_dn, _('processing'));
                    $result = false;
                }
                else {
                    list($discard, $old_dn) = explode('udi_deactivated:', $old_dn, 2);
                    $query = $this->server->query(array('base' => $old_dn), 'login');
                    if (!empty($query)) {
                        $existing_account = array_shift($query);
                        $request['page']->info(_('Deactivated account ').$deactive_dn._(' cannot be restored over: ').$old_dn, _('processing'));
                        $result = false;
                    }
                    // check that the target container exists
                    $container = $this->server->getContainer($old_dn);
                    $query = $this->server->query(array('base' => $container), 'login');
                    if (empty($query)) {
                        $request['page']->info(_('Deactivated account ').$deactive_dn._(' cannot be restored to non-existent container: ').$container, _('processing'));
                        $result = false;
                    }
                }
            }
        }
        
        $request['page']->info(_('Calculated: ').count($children)._(' accounts to be resurected'), _('processing'));
        return $result;
    }
    
    /**
     * reactivate the users in the deactivation container
     * 
     * @return bool true on success
     */
    public function reactivate() {
        global $request;
        $result = true;

        $children = $this->server->getContainerContents($this->cfg['move_to'], 'login', 0, '(objectClass=*)', LDAP_DEREF_NEVER);
        foreach ($children as $child) {
            $query = $this->server->query(array('base' => $child), 'login');
            $account = array_shift($query);
            // check that there isnt a duplicate there already
            $deactive_dn = $account['dn'];
            if (!isset($account['labeleduri'])) {
                $request['page']->info(_('Deactivated account does not have old DN - cannot restore: ').$dactive_dn, _('processing'));
                $result = false;
            }
            else {
                $labeleduri = $account['labeleduri'];
                $old_dn = preg_grep('/^udi_deactivated:/', $account['labeleduri']);
                $old_dn = array_shift($old_dn);
                if (empty($old_dn)) {
                    $request['page']->info(_('Deactivated account does not have old DN on lableURI - cannot restore: ').$deactive_dn, _('processing'));
                    $result = false;
                }
                else {
                    list($discard, $old_dn) = explode('udi_deactivated:', $old_dn, 2);
                    // now - move them back to old location, and delete the labelURI
                    $container = $this->server->getContainer($old_dn);
                    $labeleduri = preg_grep('/^udi_deactivated:/', $account['labeleduri'], PREG_GREP_INVERT);
                    
                    // do the move
                    $template = new Template($this->server->getIndex(),null,null,'modrdn');
                    $rdn = get_rdn($old_dn);
                    $template->setDN($deactive_dn);
                    $template->accept();
                    $attrs = array();
                    $attrs['newrdn'] = $rdn;
                    $attrs['deleteoldrdn'] = '1';
                    $attrs['newsuperior'] = $container;
                    $template->modrdn = $attrs;
                    $this->addTreeItem($deactive_dn);
                    $result = $this->server->rename($deactive_dn, $template->modrdn['newrdn'], $template->modrdn['newsuperior'], $template->modrdn['deleteoldrdn'], 'login');
                    if (!$result) {
                        $request['page']->error(_('Could not resurect (rename): ').$deactive_dn, _('processing'));
                        return $result;
                    }
                    
                    // sort out the label
                    $template = new Template($this->server->getIndex(),null,null,'modify');
                    $rdn = get_rdn($old_dn);
                    $template->setDN($old_dn);
                    $template->accept();
                    $this->modifyAttribute($template, 'labeleduri', $labeleduri);
                    $result = $this->server->modify($old_dn, $template->getLDAPmodify(), 'login');
                    if (!$result) {
                        $request['page']->error(_('Could not modify: ').$old_dn, _('processing'));
                        return $result;
                    }
                }
            }
        }
        
        $request['page']->info(_('Processed: ').count($children)._(' accounts resurected'), _('processing'));
        return $result;
    }
    
    
    /**
     * Completely delete the deactivated users
     * 
     * @return bool true on success
     */
    public function deleteDeactivated() {
        global $request;
        $result = true;

        $children = $this->server->getContainerContents($this->cfg['move_to'], 'login', 0, '(objectClass=*)', LDAP_DEREF_NEVER);
        foreach ($children as $child) {
            $query = $this->server->query(array('base' => $child), 'login');
            $account = array_shift($query);
            // check that there isnt a duplicate there already
            $deactive_dn = $account['dn'];
            
            // determine type of group membership - memberUid, member, uniqueMember, memberOf
            $uid = '';
            if (strtolower($this->cfg['group_attr']) == 'memberuid') {
                 $uid = (isset($account['uid']) ? $account['uid'][0] : false);
                 if (empty($uid)) {
                    $uid = (isset($account['mlepusername']) ? $account['mlepusername'][0] : false);
                 }
            }
            else {
                // it must a member style DN group
                if (!isset($account['labeleduri'])) {
                    $request['page']->warning(_('Deactivated account does not have old DN - cannot remove from groups: ').$dactive_dn, _('processing'));
                }
                else {
                    $labeleduri = $account['labeleduri'];
                    $old_dn = preg_grep('/^udi_deactivated:/', $account['labeleduri']);
                    $old_dn = array_shift($old_dn);
                    if (empty($old_dn)) {
                        $request['page']->warning(_('Deactivated account does not have old DN on labeledURI - cannot restore: ').$deactive_dn, _('processing'));
                        $result = false;
                    }
                    else {
                        list($discard, $old_dn) = explode('udi_deactivated:', $old_dn, 2);
                        $uid = $old_dn; 
                    }
                }
            }
            
            // hunt for existing group membership and remove
            if (!empty($uid)) {
                $this->removeGroupMembership($uid);
            }
            
            // Delete the entry.
            $result = $this->server->delete($deactive_dn, 'login');
            if (!$result) {
                $request['page']->error(_('Could not completely delete: ').$deactive_dn, _('processing'));
                return $result;
            }
        }
        
        $request['page']->info(_('Processed: ').count($children)._(' accounts completely deleted'), _('processing'));
        return $result;
    }
    
    
    
    /**
     * Process user delete records
     * 
     * @return bool true on success
     */
    public function processDeletes() {
        global $request;
        
        // process the deletes, which are really moves
        foreach ($this->to_be_deleted as $account) {

            $dn = $account['dn'];
            
            // First flag accounts where they come from - accounts can be 
            // resurected with this later
            $template = new Template($this->server->getIndex(),null,null,'modify');
            $rdn = get_rdn($dn);
            $template->setDN($dn);
            $template->accept();
            $values = isset($account['labeleduri']) ? $account['labeleduri'] : array();
            $values []= 'udi_deactivated:'.$dn;
            $this->modifyAttribute($template, 'labeleduri', $values);
            $result = $this->server->modify($dn, $template->getLDAPmodify(), 'login');
            if (!$result) {
                $request['page']->error(_('Could not modify: ').$dn, _('processing'));
                return $result;
            }
            
            // start building up the move template
            $template = new Template($this->server->getIndex(),null,null,'modrdn');
            $rdn = get_rdn($dn);
            $template->setDN($dn);
            $template->accept();
            $attrs = array();
            $attrs['newrdn'] = $rdn;
            $attrs['deleteoldrdn'] = '1';
            $attrs['newsuperior'] = $this->cfg['move_to'];
            $template->modrdn = $attrs;

            // DN must exist
            if (! $this->server->dnExists($dn)) {
                return $request['page']->error(sprintf('%s %s',_('DN does not exist'),$dn), _('processing'));
            }
            
            // might not be able to rename branches
            if (! $this->server->isBranchRenameEnabled()) {
                // We search all children, not only the visible children in the tree
                $children = $this->server->getContainerContents($dn, 'login', 0, '(objectClass=*)', LDAP_DEREF_NEVER);
            
                if (count($children) > 0) {
                    return $request['page']->error(_('You cannot rename an entry which has children entries (eg, the rename operation is not allowed on non-leaf entries)'), _('processing'));
                }
           }

           // make sure that this rename wont attempt an overwrite
           $query = $this->server->query(array('base' => $rdn.','.$this->cfg['move_to']), 'login');
            if (!empty($query)) {
                // group does not exist
                $request['page']->warning(_('Target DN allready exists for deactivate of : ').$dn, _('processing'));
                continue;
            }
            
            // make sure that the existing dn is in the tree
            $this->addTreeItem($dn);
            $result = $this->server->rename($dn, $template->modrdn['newrdn'], $template->modrdn['newsuperior'], $template->modrdn['deleteoldrdn'], 'login');
            if (!$result) {
                $request['page']->error(_('Could not delete (rename): ').$dn, _('processing'));
                return $result;
            }
        }
        
        return true;
    }
        
    /**
     * create a modify template
     * 
     * @param String $dn DN of the node to be modified
     * 
     * @return object Template object
     */    
    private function createModifyTemplate($dn) {
        $template = new Template($this->server->getIndex(),null,null,'modify');
        $rdn = get_rdn($dn);
        $container = $this->server->getContainer($dn);
        $template->setDN($dn);
        $template->accept();
        return $template;
    }

    /**
     * modify an attribute of a DN defined by a template
     * 
     * @param object $template Template of the DN
     * @param String $attr name of the attribute to be modified
     * @param String/Array $value new value of the attribute
     */
    private function modifyAttribute($template, $attr, $value) {
        // skip the DN attribute
        if ($attr == 'dn') {
            return;
        }
        if (!is_array($value)) {
            $value = array($value);
        }
        if (is_null($attribute = $template->getAttribute(strtolower($attr)))) {
            $attribute = $template->addAttribute(strtolower($attr),array('values'=> $value));
            $attribute->justModified();
        }
        else {
            $attribute->clearValue();
            $attribute->setValue($value);
        }
        if (empty($value) || empty($value[0])) {
            $attribute->setForceDelete();
        }
    }
    
    /**
     * Add a new attribute to a DN Template
     * 
     * @param object $template Template of the DN being created
     * @param String $attr attribute name
     * @param String/Array $cluster value of the attribute
     */
    private function addAttribute($template, $attr, $values) {
        // skip the DN attribute
        if ($attr == 'dn') {
            return;
        }

        // skip empty attributes
        if (empty($values)) {
            return;
        }

        if (!is_array($values)) {
            $values = array($values);
        }
        foreach ($values as $value) {
            if (is_null($attribute = $template->getAttribute($attr))) {
                $attribute = $template->addAttribute($attr,array('values'=>array($value)));
                $attribute->justModified();
            }
            else {
                if ($attribute->hasBeenModified()) {
                    $attribute->addValue($value);
                }
                else {
                    $attribute->setValue(array($value));
                }
            }
        }
    }
}
?>