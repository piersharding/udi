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

    $socs = $app['server']->SchemaObjectClasses();
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
        $socs = $app['server']->SchemaObjectClasses();
        $mlepPerson = $socs['mlepperson'];
        $must = $mlepPerson->getMustAttrs();
        $dmo_attrs = $app['server']->SchemaAttributes();
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
    public function __construct($server, $data) {
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
        
        // target identifier - this is the attribute in the directory to match accounts on
        $id = strtolower($this->cfg['dir_match_on']);
        
        // run through all the search bases
        foreach ($bases as $base) {
            $query = $this->server->query(array('base' => $base, 'filter' => "($id=*)"), 'login');
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
        $mappings = array();
        foreach ($cfg_mappings as $mapping) {
            $mappings[$mapping['source']] = $mapping['targets'];
        }        
        $field_mappings = array();
        $total_fields = array();
        
        // check for duplication of fields in header line
        foreach ($this->data['header'] as $header) {
            // skip the group membership column
            if (strtolower($header) == 'mlepgroupmembership') {
                continue;
            }
            // dont worry about the ones covered by mappings
            if (isset($mappings[$header])) {
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
        $socs = $this->server->SchemaObjectClasses();
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
                        if ($total_attrs[strtolower($target)] && empty($user[$header])) {
                            return $request['page']->error(_('Mandatory value: ').$header._(' (maps to: ').$target.')'._(' is empty in row: ').$row_cnt, _('processing'));
                        }
                    }
                }
                else {
                    if ($total_attrs[strtolower($header)] && empty($user[$header])) {
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
        foreach ($this->to_be_created as $account) {
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
        
        $request['page']->info(_('Calculated: ').count($this->to_be_created)._(' creates'), _('processing'));
        $request['page']->info(_('Calculated: ').count($this->to_be_updated)._(' updates'), _('processing'));
        $request['page']->info(_('Calculated: ').count($this->to_be_deleted)._(' deletes'), _('processing'));
        return true;
    }


    
    /**
     * Process the entire file according to the config
     * 
     * @return bool true on success
     */
    public function import() {
        
        $result = true;
        if ($this->cfg['enabled'] != 'checked') {
            $request['page']->error(_('Processing is not enabled - check configuration'), _('processing'));
            return false;
        }
        
        if ($this->cfg['ignore_creates'] != 'checked') {
            $result = $this->processCreates();
        }

        if ($result && $this->cfg['ignore_updates'] != 'checked') {
            $result = $this->processUpdates();
        }

        if ($result && $this->cfg['ignore_deletes'] != 'checked') {
            $result = $this->processDeletes();
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
        $group_mappings = array();
        $total_groups = array();
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
            
            $cn = $account['mlepFirstName'].' '.$account['mlepLastName'];
            $dn = 'cn='.$cn.','.$this->cfg['create_in'];
            $rdn = get_rdn($dn);
            $container = $this->server->getContainer($dn);
            $template->setContainer($container);
            $template->accept();

            $group_membership = false;
            // need to prevent doubling up of attribute values
            $total_fields = array();
            foreach ($account as $attr => $cluster) {
                // skip the mlepgroupmembership
                if (strtolower($attr) == 'mlepgroupmembership') {
                    $group_membership = $cluster;
                    continue;
                }
                // map attributes here
                if (isset($field_mappings[$attr])) {
                    foreach ($field_mappings[$attr] as $target) {
                        if (isset($total_fields[$target])) {
                            continue;
                        }
                        $total_fields[$target] = $target;
                        $this->addAttribute($template, $target, $cluster);
                    }
                }
                else {
                    if (!isset($total_fields[$attr])) {
                        $total_fields[$attr] = $attr;
                        $this->addAttribute($template, $attr, $cluster);
                    }
                }
            }
            $template->setRDNAttributes($rdn);
            // set the CN
            $result = $this->server->add($dn, $template->getLDAPadd());
            if (!$result) {
                $request['page']->error(_('Could not create: ').$dn, _('processing'));
                return $result;
            }
            else {
                // need to set the group membership
                // need to find all existing groups, and then delete those memberships first
                $uid = (isset($account['mlepUsername']) ? $account['mlepUsername'] : false);
                if (!$this->replaceGroupMembership($uid, $group_membership)) {
                    return false;
                }
            }
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

        $field_mappings = array();
        foreach ($cfg_mappings as $mapping) {
            $field_mappings[$mapping['source']] = $mapping['targets'];
        }

        // process the updates
        foreach ($this->to_be_updated as $account) {

            // start building up the creation template
            $template = new Template($this->server->getIndex(),null,null,'modify');
            
            $dn = $account['dn'];
            $rdn = get_rdn($dn);
            $template->setDN($dn);
            $template->accept();

            $group_membership = false;
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
                
                // map attributes here
                if (isset($field_mappings[$attr])) {
                    foreach ($field_mappings[$attr] as $target) {
                        $this->modifyAttribute($template, $target, $value);
                    }
                }
                else {
                    $this->modifyAttribute($template, $attr, $value);
                }
            }
            // make sure item exists in the tree
            $this->addTreeItem($dn);
            $result = $this->server->modify($dn, $template->getLDAPmodify());
            if (!$result) {
                $request['page']->error(_('Could not create: ').$dn, _('processing'));
                return $result;
            }
            else {
                // need to set the group membership
                // need to find all existing groups, and then delete those memberships first
                $uid = (isset($account['mlepUsername']) ? $account['mlepUsername'] : false);
                if (!$this->replaceGroupMembership($uid, $group_membership)) {
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
    private function replaceGroupMembership($uid, $group_membership) {
        
        // must have a user id
        if (!$uid) {
            return false;
        }
        
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
        
        // hunt for existing group membership and remove
        $group_attr = $this->cfg['group_attr'];
        foreach ($this->total_groups as $group) {
            // check and delete from group
            $query = $this->server->query(array('base' => $group, 'filter' => "($group_attr=$uid)"), 'login');
            if (!empty($query)) {
                // user exists in group
                $query = $this->server->query(array('base' => $group), 'login');
                //if (empty($query)) {
                //    // group does not exist
                //    return $request['page']->error(_('Membership group does not exist: ').$group, _('processing'));
                //}
                // remove user from membership attribute and then save again
                $template = $this->createModifyTemplate($group);
                $existing = array_shift($query);
                $attribute = $template->getAttribute(strtolower($group_attr));
                $values = $existing[strtolower($group_attr)];
                $values = array_merge(preg_grep('/^'.$uid.'$/', $values, PREG_GREP_INVERT), array());
                $attribute->setValue($values);
                // Perform the modification
                $this->addTreeItem($group);
                $result = $this->server->modify($group, $template->getLDAPmodify());
                if (!$result) {
                    return $request['page']->error(_('Could not remove user from group: ').$uid.'/'.$group, _('processing'));
                }
            }
        }

        // then re add memberships
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
                        if (isset($existing[strtolower($group_attr)])) {
                            $values = $existing[strtolower($group_attr)];
                        }
                        else {
                            $values = array();
                        }
                        $values[] = $uid;
                        $this->modifyAttribute($template, $group_attr, $values);
                        # Perform the modification
                        $this->addTreeItem($mapping);
                        $result = $this->server->modify($mapping,$template->getLDAPmodify());
                        if (!$result) {
                            return $request['page']->error(_('Could not add user to group: ').$uid.'/'.$mapping, _('processing'));
                        }
                    }
                }
            }
        }
        return true;
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

            // start building up the creation template
            $template = new Template($this->server->getIndex(),null,null,'modrdn');
            $dn = $account['dn'];
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
                $children = $this->server->getContainerContents($dn,null,0,'(objectClass=*)',LDAP_DEREF_NEVER);
            
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
            $result = $this->server->rename($dn, $template->modrdn['newrdn'], $template->modrdn['newsuperior'], $template->modrdn['deleteoldrdn']);
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
    }
    
    
    /**
     * Add a new attribute to a DN Template
     * 
     * @param object $template Template of the DN being created
     * @param String $attr attribute name
     * @param String/Array $cluster value of the attribute
     */
    private function addAttribute($template, $attr, $cluster) {
        // skip the DN attribute
        if ($attr == 'dn') {
            return;
        }

        // skip empty attributes
        if (empty($cluster)) {
            return;
        }

        if (!is_array($cluster)) {
            $cluster = array($cluster);
        }
        foreach ($cluster as $value) {
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