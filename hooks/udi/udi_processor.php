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

    // file version
    private $version;

    // The actual import data
    private $data;

    // Current config
    private $cfg;
    public $udiconfig;

    // arrays of the different record types
    public  $to_be_deactivated;
    private $to_be_created;
    private $to_be_updated;

    // user groups derived from config
    private $total_groups;
    private $group_mappings;

    // group membership cache
    private $group_cache;

    // dn exists cache
    private $dn_cache;

    // userpassalgols cache
    private $userpassalgols;

    /**
     * Constructor
     * build connection to environment and LDAP directory
     * pull in the UDI config
     *
     * @param object $server LDAP directory
     * @param array $data CSV file contents
     */
    public function __construct($server, $data=array(), $version='1.6') {
        $this->server = $server;
        $this->data = $data;
        $this->version = $version;
        $this->udiconfig = new UdiConfig($this->server);
        $this->cfg = $this->udiconfig->getConfig();
        $this->group_cache = array();
        $this->dn_cache = array();
    }

    /**
     * Get the list of register user id and password
     * algorythms
     *
     */
    private function getUserPassAlgorythms() {

        if (!$this->userpassalgols) {
            // userid and passwd algorythms
            $result = udi_run_hook('userid_algorithm_label',array());
            $algols = array();
            if (!empty($result)) {
                foreach ($result as $algo) {
                    $algols[]= $algo['name'];
                }
            }
            $result = udi_run_hook('passwd_algorithm_label',array());
            if (!empty($result)) {
                foreach ($result as $algo) {
                    $algols[]= $algo['name'];
                }
            }
            $this->userpassalgols = $algols;
        }
        return $this->userpassalgols;
    }

    /**
     * Validates the import against the user directory
     *
     * @return boolean true if file validates.
     */
    public function validate($logtofile=false) {
        global $request, $mlep_mandatory_fields;
        /*
         * Validation$algols[]= $algo['name'];
         *
         * 3 main cases:
         *
         * user exists in file but not in directory
         *      - create account
         *
         * user exists in directory - but not in file
         *      - delete/deactivate account
         *           $query = $this->server->query(array('base' => $group, 'filter' => "($group_attr=$uid)"), 'user');
            if (!empty($query)) {

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

        // also check the deactivated base
        if (!empty($this->cfg['move_to'])) {
            $bases []= $this->cfg['move_to'];
        }

        // target identifier - this is the attribute in the directory to match accounts on
        $id = strtolower($this->cfg['dir_match_on']);

        // run through all the search bases
        foreach ($bases as $base) {
            // ensure that accounts inspected have the mlepPerson object class
            $query = $this->server->query(array('base' => $base, 'filter' => "(&(|(objectclass=user)(objectclass=inetorgperson)(objectclass=mlepperson))($id=*))"), 'user');
            if (empty($query)) {
                // base does not exist
                $request['page']->warning(_('No user accounts found in search base: ').$base, _('processing'));
            }
            else {
                // run through each discovered account
                foreach ($query as $dn => $user) {
                    // check that the mlepRole of this user us active - if mlepRole exists
                    if (isset($user['mleprole']) && !empty($user['mleprole'])) {
                        if ($this->udiconfig->getRole($user['mleprole'][0]) == 0) {
                            // skip this user
//                            echo 'skipping a user: '.$user['mleprole'][0].' '.$dn;
                            continue;
                        }
                    }
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
                    $this->dn_cache[get_canonical_name($dn)] = $user;
                }
            }
        }

       // get mapping configuration - map input file fields to LDAP attributes
        $cfg_mappings = $this->udiconfig->getMappings();
        $field_mappings = array();
        $targets_to_source = array();
        foreach ($cfg_mappings as $mapping) {
            $field_mappings[$mapping['source']] = $mapping['targets'];
            foreach($mapping['targets'] as $target) {
                if (!isset($targets_to_source[$target])) {
                    $targets_to_source[$target] = $mapping['source'];
                }
            }
        }
        $total_fields = array();

        // check for duplication of fields in header line
        foreach ($this->data['header'] as $header) {
            // skip the group membership columns
            if (strtolower($header) == 'mlepgroupmembership' ||
                strtolower($header) == 'mlephomegroup') {
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
                    $request['page']->error(_('Duplicate target field in mapping source: ').$mapping['source']._(' to target: ').$field, _('processing (check columns in file)'));
                    return false;
                }
                $total_fields[strtolower($field)] = $field;
            }
        }

        // all target fields must exist in the schema
        $total_attrs = array();
        $classes = $this->udiconfig->getObjectClasses();
        $socs = $this->server->SchemaObjectClasses('user');
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
                // some mandatory attributes aren't really mandatory
                if ($this->cfg['server_type'] == 'ad') {
                    if (in_array($attr, array('objectsid'))) {
                        continue;
                    }
                }
                $request['page']->error(_('Mandatory LDAP attribute missing from import: ').$attr, _('processing'));
                return false;
            }
        }

        // Initialise any account create before algorithms
        udi_run_hook('account_create_before_init', array($this->server, $this->udiconfig));

        // reorder the list of import users based on their match_from
        $imports = array();
        $iuid = $this->cfg['import_match_on'];
        $row_cnt = 0;
        $found_bad_records = false;
        $skips = 0;
        foreach ($this->data['contents'] as $row) {
            $row_cnt++;
            $row_cnt = isset($row['lineno']) ? $row['lineno'] : $row_cnt;
            $row = $row['data'];
            $cell = 0;
            $user = array();

            // check for MUST mapped values
            foreach ($this->data['header'] as $header) {
                $user[$header] = $row[$cell];
                // skip past the group columns
                if (strtolower($header) == 'mlepgroupmembership' ||
                    strtolower($header) == 'mlephomegroup') {
                    $cell++;
                    continue;
                }
                if (isset($field_mappings[$header])) {
                    foreach ($field_mappings[$header] as $target) {
                        $value = trim($user[$header]);
                        if ($total_attrs[strtolower($target)] && empty($value) && !in_array(strtolower($target), array('mlepusername', 'samaccountname', 'uid'))) {
                            return $request['page']->error(_('Mandatory value: ').$header._(' (maps to: ').$target.')'._(' is empty in row: ').$row_cnt, _('processing'));
                        }
                    }
                }
                else {
                    $value = trim($user[$header]);
                    if ($total_attrs[strtolower($header)] && empty($value) && !in_array(strtolower($header), array('mlepusername', 'samaccountname', 'uid'))) {
                        return $request['page']->error(_('Mandatory value: ').$header._(' (maps to: ').$header.')'._(' is empty in row: ').$row_cnt, _('processing'));
                    }
                }
                $cell++;
            }

            // check for a valid mlepRole
            if (!isset($user['mlepRole']) || !in_array($user['mlepRole'], $mlep_mandatory_fields['mlepRole']['match'])) {
                if ($logtofile) {
                    array_unshift($user, 'mlepRole('.$user['mlepRole'].')');
                    $request['page']->log_to_file('Invalid data in record', preg_replace('/\n/', '', var_export($user, true)));
                }
                else {
                    $request['page']->warning(_('Invalid data in record: ').$row_cnt._(' broken values: mlepRole').'('.$user['mlepRole'].')', _('processing'));
                }
                unset($accounts[$user[$iuid]]);
                $skips++;
                // skip this user
                continue;
            }

            // check that the mlepRole of this user is active
            if (isset($user['mlepRole']) && $this->udiconfig->getRole($user['mlepRole']) == 0) {
                // skip this user
                unset($accounts[$user[$iuid]]);
                $skips++;
                continue;
            }

            // check for bad records values
            // if problems found then log, and remove ID from read directory list $accounts
            // error_log('version: '.$this->version);  
            $field_errors = array();
            if ($this->cfg['strict_checks'] == 'checked') {
                foreach ($mlep_mandatory_fields as $field => $tests) {
                    // mandatory field test
                    if ($tests['mandatory']) {
                        // some fields are mandatory - but only for certain groups
                        if (isset($tests['group'])) {
                            if (empty($user[$field]) && !in_array($user['mlepRole'], $tests['group'])) {
                                // they are OK - this is allowed to be empty
                                continue;
                            }

                            // check file version related new mandatory fields
                            if ($this->version < 1.71 && in_array(strtolower($field), array('mlepgender', 'mlepdob'))) {
                                // error_log('skipping field: '.$field);
                                // error_log('mandatory fields: '.var_export($mlep_mandatory_fields, true));
                                continue;
                            }

                            // mandatory fields for every file version - 1.6 onwards
                            if (empty($user[$field]) && in_array($user['mlepRole'], $tests['group'])) {
                                //meh - you are bad!
                                if (!isset($user[$field])) {
                                    return $request['page']->error(_('Mandatory value: ').$field._(' is missing from file version: ').$this->version, _('processing'));
                                }
                                $field_errors[]= $field.'('.$user[$field].')'._(' is empty');
                                continue;
                            }
                        }
                        // mandatory for all
                        else {
                            if (empty($user[$field])) {
                                // meh - bad!
                                $field_errors[]= $field.'('.$user[$field].')';
                                continue;
                            }
                        }
                    }
                    // now - if they are empty - then they can short circuit
                    if (empty($user[$field])) {
                        continue;
                    }

                    // not an empty field and a test available
                    if (isset($tests['match'])) {
                        // is in the group list specified
                        if (isset($tests['group'])) {
                            if (in_array($user['mlepRole'], $tests['group'])) {
                                if (is_array($tests['match'])) {
                                    // check that value is in the list
                                    if (!in_array($user[$field], $tests['match'])) {
                                        $field_errors[]= $field.'('.$user[$field].')';
                                    }
                                }
                                // apply the test regex
                                else {
                                    if (!preg_match($tests['match'], $user[$field])) {
                                        $field_errors[]= $field.'('.$user[$field].')';
                                    }
                                }
                            }
                        }
                        // not a group limited test
                        else {
                            // check that value is in the match list
                            if (is_array($tests['match'])) {
                                if (!in_array($user[$field], $tests['match'])) {
                                    $field_errors[]= $field.'('.$user[$field].')';
                                }
                            }
                            else {
                                // this is a regex test
                                if (!preg_match($tests['match'], $user[$field])) {
                                    $field_errors[]= $field.'('.$user[$field].')';
                                }
                            }
                        }
                    }
                }
                // extra checks are done for mlepUsername later
                if (!empty($field_errors)) {
                    if ($logtofile) {
                        $request['page']->log_to_file('Invalid data in record', preg_replace('/\n/', '', var_export($user, true)));
                    }
                    $request['page']->warning(_('Invalid data in record: ').$row_cnt._(' values: ').implode(', ', $field_errors), _('processing'));
                    $skips++;
                    unset($accounts[$user[$iuid]]);
                    continue;
                }
            }

            $user['_row_cnt'] = $row_cnt;
            $imports[$user[$iuid]] = $user;
        }

        // find the missing accounts in the directory
        $this->to_be_deactivated = array_diff_key($accounts, $imports);

        // find the new accounts in the file
        $this->to_be_created = array_diff_key($imports, $accounts);

        // find the accounts to be updated
        $to_be_updated = array_intersect_key($accounts, $imports);
        $this->to_be_updated = array();
        foreach ($to_be_updated as $id => $account) {
            $data = $imports[$id];
            $data['dn'] = $account['dn'];
            unset($data['_row_cnt']);
            $this->to_be_updated[$id] = $data;
        }

        // userid and passwd algorythms
        $algols = $this->getUserPassAlgorythms();

        // Hunt down existing uid/mlepUsernames to avoid duplicates
        $uid_duplicates = array();
        $dn_duplicates = array();
        $mlepmail_duplicates = array();
        $mail_duplicates = array();
        $remove_duplicates = array();
        $row_cnt = 0;
        foreach ($this->to_be_created as $id => $account) {
            $row_cnt++;

            // run all other account_create_before hooks
            $hooks = isset($_SESSION[APPCONFIG]) ? $_SESSION[APPCONFIG]->hooks : array();
            while (list($key,$hook) = each($hooks['account_create_before'])) {
                // ignore the active userid algorythm
                if (in_array($hook['hook_function'], $algols)) {
                    continue;
                }
                // run each hook
//                var_dump($hook['hook_function']);
                $result = udi_run_hook('account_create_before',array($this->server, $this->udiconfig, $account), $hook['hook_function']);
                if (is_array($result)) {
                    $result = array_pop($result);
                    if (is_array($result)) {
                        $account = $result;
                    }
                }
            }
            // run userid hook
            if (!isset($this->cfg['ignore_userids']) || $this->cfg['ignore_userids'] != 'checked') {
                $result = udi_run_hook('account_create_before',array($this->server, $this->udiconfig, $account), $this->cfg['userid_algo']);
                if (is_array($result)) {
                    $result = array_pop($result);
                    if (is_array($result)) {
                        $account = $result;
                    }
                }
            }

            // User Id must exist now
//            var_dump($account);
            if (empty($account['mlepUsername'])) {
                $request['page']->warning(_('Mandatory value: mlepUsername ')._(' is empty in row: ').$account['_row_cnt']._(' - skipping'), _('processing'));
                $remove_duplicates[]= $row_cnt;
                continue;
            }

            // run passwd hook
            if (!isset($this->cfg['ignore_passwds']) || $this->cfg['ignore_passwds'] != 'checked') {
                $result = udi_run_hook('passwd_algorithm',array($this->server, $this->udiconfig, $account, $this->cfg['passwd_parameters']), $this->cfg['passwd_algo']);
                if (is_array($result)) {
                    $result = array_pop($result);
                    if (!empty($result)) {
                        $account['userPassword'] = $result;
                    }
                }
            }

            $uid = $account['mlepUsername'];
            if (isset($uid_duplicates[$uid])) {
                $request['page']->warning(_('User account is duplicate in import file based on mlepUsername: ').$uid._(' record: ').$account['_row_cnt']._(' - skipping'), _('processing'));
                $remove_duplicates[]= $row_cnt;
                continue;
            }
            $uid_duplicates[$uid] = $uid;

            // make sure that an account doesn't allready exist in the directory
            // with this Id
            // check for mlepUsername
            $query = $this->server->query(array('base' => $this->udiconfig->getBaseDN(), 'filter' => "(mlepUsername=$uid)", 'attrs' => array('dn')), 'user');
            if (!empty($query)) {
                $query = array_shift($query);
                $request['page']->warning(_('User account is duplicate in directory for mlepUsername: ').$uid.' ('.$query['dn'].')'._(' record: ').$account['_row_cnt']._(' - skipping'), _('processing'));
                $remove_duplicates[]= $row_cnt;
                continue;
            }
            // check for uid or sAMAccountName
            if ($this->cfg['server_type'] == 'ad') {
                $uid_attr = 'sAMAccountName';
            }
            else {
                $uid_attr = 'uid';
            }
            $query = $this->server->query(array('base' => $this->udiconfig->getBaseDN(), 'filter' => "($uid_attr=$uid)", 'attrs' => array('dn')), 'user');
            if (!empty($query)) {
                $query = array_shift($query);
                $request['page']->warning(_('User account is duplicate in directory for ').$uid_attr.': '.$uid.' ('.$query['dn'].')'._(' record: ').$account['_row_cnt']._(' - skipping'), _('processing'));
                $remove_duplicates[]= $row_cnt;
                continue;
            }

            // unique DN checking
            // sort out the common name
            $cn = $this->makeCN($account);

            // sort out uid
            $uid = $this->makeUid($account);

            // store the mlepGroupMembership
            $group_membership = $this->makeGroupMembership($account);

            // determine the target container
            $user_container = $this->makeAccountContainer($group_membership);

            // sort out what the dn attribute is
            $dn = $this->makeDN($user_container, $cn, $uid);

            // canonicalise DN
            $dn = get_canonical_name($dn);

            // check and stash
            if (isset($dn_duplicates[$dn])) {
                $request['page']->warning(_('User account is duplicate in import file based on DN attribute: ').$dn._(' record: ').$account['_row_cnt']._(' - skipping'), _('processing'));
                $remove_duplicates[]= $row_cnt;
                continue;
            }
            $dn_duplicates[$dn] = $dn;

            // check for duplicates in directory for DN
            if ($this->check_user_dn($dn)) {
                $request['page']->warning(_('User account is duplicate in directory: ').$dn._(' record: ').$account['_row_cnt']._(' - skipping'), _('processing'));
                $remove_duplicates[]= $row_cnt;
                continue;
            }

//            // check for duplicate email addresses
//            if ($this->cfg['strict_checks'] == 'checked') {
//                if (isset($account['mlepEmail']) && !empty($account['mlepEmail'])) {
//                    $mail = $account['mlepEmail'];
//                    if (isset($mlepmail_duplicates[$mail])) {
//                        $request['page']->warning(_('User email address is duplicate in import file: ').$mail._(' record: ').$account['_row_cnt']._(' - skipping'), _('processing'));
//                        $remove_duplicates[]= $row_cnt;
//                        continue;
//                    }
//                    $mlepmail_duplicates[$mail] = $mail;
//                    $query = $this->server->query(array('base' => $this->udiconfig->getBaseDN(), 'filter' => "(mail=$mail)", 'attrs' => array('dn')), 'user');
//                    if (!empty($query)) {
//                        $query = array_shift($query);
//                        $request['page']->warning(_('Email address is duplicate in directory for ').': '.$mail.' ('.$query['dn'].')'._(' record: ').$account['_row_cnt']._(' - skipping'), _('processing'));
//                        $remove_duplicates[]= $row_cnt;
//                        continue;
//                    }
//                }
//            }

            // check for duplicate email addresses - mail attribute
            if (in_array('mail', $this->server->getValue('unique','attrs'))) {
                // figure out what the mail attribute will be - if at all
                $mail = '';
                if (isset($account['mail']) && !empty($account['mail'])) {
                    $mail = $account['mail'];
                }
                // find mapping - if exists
                else if (isset($targets_to_source['mail'])) {
                    if (isset($account[$targets_to_source['mail']])) {
                        $mail = $account[$targets_to_source['mail']];
                    }
                    else {
                        $mail = $targets_to_source['mail']; // constant value
                    }
                }
                // only bother checking if there is a mail address, and it doesn't
                // have attribute substitutions
                if (!empty($mail) && !preg_match('/\%\[.*?\]/', $mail)) {
                    if (isset($mail_duplicates[$mail])) {
                        $request['page']->warning(_('User email (mail) address is duplicate in import file: ').$mail._(' record: ').$account['_row_cnt']._(' - skipping'), _('processing'));
                        $remove_duplicates[]= $row_cnt;
                        continue;
                    }
                    $mail_duplicates[$mail] = $mail;
                    $query = $this->server->query(array('base' => $this->udiconfig->getBaseDN(), 'filter' => "(mail=$mail)", 'attrs' => array('dn')), 'user');
                    if (!empty($query)) {
                        $query = array_shift($query);
                        $request['page']->warning(_('Email address (mail) is duplicate in directory for ').': '.$mail.' ('.$query['dn'].')'._(' record: ').$account['_row_cnt']._(' - skipping'), _('processing'));
                        $remove_duplicates[]= $row_cnt;
                        continue;
                    }
                }
            }

            // check for mlepUsername in the deletions directory
            if (!empty($this->cfg['move_to'])) {
                $query = $this->server->query(array('base' => $this->cfg['move_to'], 'filter' => "(mlepUsername=$uid)", 'attrs' => array('dn')), 'user');
                if (!empty($query)) {
                    // base does not exist
                    $request['page']->warning(_('User account is duplicate in deletion (').$this->cfg['move_to']._(') directory for mlepUsername: ').$uid._(' record: ').$account['_row_cnt']._(' - skipping'), _('processing'));
                    $remove_duplicates[]= $row_cnt;
                    continue;
                }
            }
            unset($account['_row_cnt']);

            // now paste $account back on as it may have been modified
            $this->to_be_created[$id] = $account;
        }

        // remove the dropped records
        $skips += count($remove_duplicates);
        foreach (array_reverse($remove_duplicates) as $pos) {
            array_splice($this->to_be_created, $pos - 1, 1);
        }

        // check deactivated for allready deavtive
        $allready_deactive = 0;
        if (!empty($this->cfg['move_to'])) {
            $remove_existing = array();
            $row_cnt = 0;
            $ddn = get_canonical_name($this->cfg['move_to']);
            foreach ($this->to_be_deactivated as $id => $account) {
                $row_cnt++;
                $dn = get_canonical_name($account['dn']);
                // are they allready deactive
                if (preg_match('/'.preg_quote($ddn).'$/', $dn)) {
                    $remove_existing[]= $row_cnt;
                }
            }
            $allready_deactive = count($remove_existing);
            foreach (array_reverse($remove_existing) as $pos) {
                array_splice($this->to_be_deactivated, $pos - 1, 1);
            }
        }

        $request['page']->info(_('Calculated: ').count($this->to_be_created)._(' creates ').count($this->to_be_updated)._(' updates ').(count($this->to_be_deactivated) + $allready_deactive)._(' deletes ').$skips._(' skips'), _('processing'));
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
        $socs = $this->server->SchemaObjectClasses('user');
        $skip = array('objectclass', 'userpassword');
        foreach ($classes as $class) {
            foreach ($socs[strtolower($class)]->getMustAttrs(true) as $attr) {
                if (!in_array($attr->getName(), $skip)) {
                    $total_attrs[$attr->getName()] = $attr->getName(false);
                    //var_dump($attr);
                }
            }
            foreach ($socs[strtolower($class)]->getMayAttrs(true) as $attr) {
                if (!in_array($attr->getName(), $skip)) {
                    $total_attrs[$attr->getName()] = $attr->getName(false);
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
            //$query = $this->server->query(array('base' => $base, 'filter' => "(&(objectclass=mlepperson)($id=*))"), 'user');
            $query = $this->server->query(array('base' => $base, 'filter' => "(|(objectclass=user)(objectclass=inetorgperson)(objectclass=mlepperson))"), 'user');
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

        // Set our timelimit in case we have a lot of importing to do
        set_time_limit(0);

        if ($this->cfg['enabled'] != 'checked') {
            $request['page']->error(_('Processing is not enabled - check configuration'), _('processing'));
            return false;
        }

        // must do deletes first - as there might be rename issues
        if ($result && $this->cfg['ignore_deletes'] != 'checked') {
            $result = $this->processDeactivations();
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
     * Calculate the CN for a user
     *
     * @param array $account
     * @return String CN
     */
    protected function makeCN ($account) {
        $cn = '';
        if (isset($account['cn'])) {
            $cn = $account['cn'];
        }
        else {
            $cn = $account['mlepFirstName'].' '.$account['mlepLastName'];
        }
        // normalise spaces
        $cn = preg_replace('/\s\s+/', ' ', $cn);
        // get rid of bad characters
//        $cn = preg_replace('/\\\\/', '\\\\', $cn);
        foreach (array('\\', '#', '^', '$', '+', '"', '<', '>', ';', '/') as $char) {
//            $cn = preg_replace('/\\'.$char.'/', '\\'.$char, $cn);
            $cn = preg_replace('/\\'.$char.'/', '', $cn);
        }
        return $cn;
    }

    /**
     * Calculate the uid for a user
     *
     * @param array $account
     * @return String uid
     */
    protected function makeUid ($account) {
        $uid = '';
        if (isset($account['uid'])) {
            $uid = $account['uid'];
        }
        else {
            $uid = $account['mlepUsername'];
        }
//        $uid = preg_replace('/\\\\/', '\\\\', $uid);
//        foreach (array('#', '^', '$', '+', '"', '<', '>', ';', '/') as $char) {
        foreach (array('\\', '#', '^', '$', '+', '"', '<', '>', ';') as $char) {
//            $uid = preg_replace('/\\'.$char.'/', '\\'.$char, $uid);
            $uid = preg_replace('/\\'.$char.'/', '', $uid);
        }
        return $uid;
    }

    /**
     * Calculate the group membership
     *
     * @param array $account
     * @return String membership string
     */
    protected function makeGroupMembership ($account) {
        $group_membership = false;
        // find the group memberships
        if (isset($account['mlepgroupmembership'])) {
            $group_membership = $account['mlepgroupmembership'];
        }
        else if (isset($account['mlepGroupMembership'])) {
            $group_membership = $account['mlepGroupMembership'];
        }

        // add on the home groups
        if (isset($account['mlephomegroup']) && !empty($account['mlephomegroup'])) {
            $group_membership = implode('#', array($group_membership, $account['mlephomegroup']));
        }
        else if (isset($account['mlepHomeGroup']) && !empty($account['mlepHomeGroup'])) {
            $group_membership = implode('#', array($group_membership, $account['mlepHomeGroup']));
        }

        // combine role and groupmembership
        if (isset($account['mleprole']) && is_array($account['mleprole'])) {
            $group_membership = implode('#', array($account['mleprole'][0], $group_membership));
        }
        else if (isset($account['mlepRole'])) {
            $group_membership =  implode('#', array($account['mlepRole'], $group_membership));
        }

        return implode('#', array_unique(explode('#', $group_membership)));
    }

    /**
     * Calculate the Account container
     *
     * @param array $group_membership
     * @return String membership string
     */
    protected function makeAccountContainer ($group_membership) {
        $user_container = $this->cfg['create_in'];
        if (empty($this->container_mappings)) {
            $cfg_container_mappings = $this->udiconfig->getContainerMappings();
            $this->container_mappings = array();
            foreach ($cfg_container_mappings as $mapping) {
                $this->container_mappings[$mapping['source']] = $mapping['target'];
            }
        }
        if ($group_membership) {
            $groups = explode('#', $group_membership);
            foreach ($groups as $group) {
                if (isset($this->container_mappings[$group])) {
                    $user_container = $this->container_mappings[$group];
                    break;
                }
            }
        }
        return $user_container;
    }


    /**
     * Calculate the DN
     *
     * @param array $account
     * @return String membership string
     */
    protected function makeDN ($user_container, $cn, $uid) {
        $dn = '';
        switch ($this->cfg['dn_attribute']) {
            case 'uid':
                $dn = 'uid='.$uid.','.$user_container;
                break;
//                case 'cn':
            default:
                $dn = 'cn='.$cn.','.$user_container;
                break;
        }
        return $dn;
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
            // guard against numeric value source keys
            if (preg_match('/^\d+$/', $mapping['source'])) {
                $mapping['source'] = '_constant:'.$mapping['source'];
            }
            $field_mappings[$mapping['source']] = $mapping['targets'];
        }

        // userid and passwd algorythms
        $algols = $this->getUserPassAlgorythms();

        // Initialise any account create before algorithms
        udi_run_hook('account_create_before_init', array($this->server, $this->udiconfig));

        // create the missing
        foreach ($this->to_be_created as $account) {

            // inject object classes
            // if we are using AD, then we have to remove securityPrincipal even
            // though we use it for sAMAccountName
            if ($this->cfg['server_type'] == 'ad') {
                // AD is strange - there is only one objectClass - all Auxiliary classes
                // appear to be automagically available through 'user'
                $account['objectclass'] = array('user');
            }
            else {
                $account['objectclass'] = $this->udiconfig->getObjectClasses();
            }

            // start building up the creation template
            $template = new Template($this->server->getIndex(),null,null,'add', null, true);

            // sort out the common name
            $cn = $this->makeCN($account);

            // sort out uid
            $uid = $this->makeUid($account);

            // store the mlepGroupMembership
            $group_membership = $this->makeGroupMembership($account);

            // determine the target container
            $user_container = $this->makeAccountContainer($group_membership);

            // sort out what the dn attribute is
            $dn = $this->makeDN($user_container, $cn, $uid);

            $rdn = get_rdn($dn);
            $container = $this->server->getContainer($dn);
            $template->setContainer($container);
            $template->accept(false, 'user');

            // run all other account_create_before hooks
            $hooks = isset($_SESSION[APPCONFIG]) ? $_SESSION[APPCONFIG]->hooks : array();
            while (list($key,$hook) = each($hooks['account_create_before'])) {
                // ignore the active userid algorythm
                if (in_array($hook['hook_function'], $algols)) {
                    continue;
                }
                // run each hook
                $result = udi_run_hook('account_create_before',array($this->server, $this->udiconfig, $account), $hook['hook_function']);
                if (is_array($result)) {
                    $result = array_pop($result);
                    if (is_array($result)) {
                        $account = $result;
                    }
                }
            }
            // run userid hook
            if (!isset($this->cfg['ignore_userids']) || $this->cfg['ignore_userids'] != 'checked') {
                $result = udi_run_hook('account_create_before',array($this->server, $this->udiconfig, $account), $this->cfg['userid_algo']);
                if (is_array($result)) {
                    $result = array_pop($result);
                    if (is_array($result)) {
                        $account = $result;
                    }
                }
            }

            // run passwd hook
            if (!isset($this->cfg['ignore_passwds']) || $this->cfg['ignore_passwds'] != 'checked') {
                $result = udi_run_hook('passwd_algorithm',array($this->server, $this->udiconfig, $account, $this->cfg['passwd_parameters']), $this->cfg['passwd_algo']);
                if (is_array($result)) {
                    $result = array_pop($result);
                    if (!empty($result)) {
                        $account['userPassword'] = $result;
                    }
                }
            }

            // encrypt the passwords
            if (isset($account['userPassword']) && $this->cfg['encrypt_passwd'] != 'none') {
                $account['raw_passwd'] = $account['userPassword'];
                $account['userPassword'] = password_hash($account['userPassword'], $this->cfg['encrypt_passwd']);
            }
            else {
                $account['raw_passwd'] = isset($account['userPassword']) ? $account['userPassword'] : '';
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
                if (strtolower($attr) == 'mlepgroupmembership' ||
                    strtolower($attr) == 'mlephomegroup') {
                    continue;
                }

                if ($attr != 'objectclass') {
                    $value = trim($value);
                }

                // split the multi-value attributes
                if (strtolower($attr) == 'mlepassociatednsn') {
                    $value = empty($value) ? array() : explode('#', $value);
                    $value = array_unique($value);
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
                        $total_fields[$target] = $value;
                        $this->addAttribute($template, $target, $value);
                    }
                }
                else {
                    // dont allow doubling up
                    if (!isset($total_fields[$attr])) {
                        $total_fields[$attr] = $value;
                        $this->addAttribute($template, $attr, $value);
                    }
                }
            }

            // if we are using AD, then we have to actually disable the user
            // and set the password later
            if ($this->cfg['server_type'] == 'ad') {
                // Special AD password handling
                $this->addAttribute($template, 'useraccountcontrol', array(514));
//                if (isset($account['userPassword'])) {
//                    // set accounts with password to active
//                    // // 32 + 512 + 65536 = 66080
//                    // 1 + 32 + 512 + 65536 = 66081
//                    // 1 = run login script
//                    // 32 = passwd not required
//                    // 64 = passwd can't change
//                    // 512 = normal account
//                    // 65536 = dont expire password
//                    $this->addAttribute($template, 'useraccountcontrol', array(514));
////                    $this->addAttribute($template, 'useraccountcontrol', array(66081));
////                    $this->addAttribute($template, 'useraccountcontrol', array(545));
////                    $this->addAttribute($template, 'pwdLastSet', array(strtotime('+0 days')));
////                    $this->addAttribute($template, 'unicodePwd', array(mb_convert_encoding('"' . $account['raw_passwd'] . '"', 'UCS-2LE', 'UTF-8')));
//                }
//                else {
//                    // all new AD accounts must be deactive first
//                    $this->addAttribute($template, 'useraccountcontrol', array(514));
//                }
            }

            // now do expression substitutions
            foreach ($field_mappings as $source => $targets) {
                if (preg_match('/\%\[.+\]/', $source)) {
                    // do the expansion then map to fields
                    $value = $source;
                    // find the substitutions
                    if (preg_match_all('/\%\[(.+?)\]/', $source, $matches)) {
                        foreach ($matches[1] as $match) {
                            $parts = explode(':', $match);
                            $attr = array_shift($parts);
                            if (strtolower($attr) == 'mlephomegroup') {
                                continue;  // this is concatenated with mlepGroupMembership
                            }
                            if (strtolower($attr) == 'mlepgroupmembership') {
                                $element = empty($parts) ? 1 : (int)array_shift($parts);
                                $groups = explode('#', $group_membership);
                                $part = isset($groups[$element]) ? $groups[$element] : '';
                            }
                            else {
                                $part = isset($total_fields[$attr]) ? $total_fields[$attr] : '';
                            }
                            $length = empty($parts) ? 0 : (int)array_shift($parts);
                            $length = (int)$length;
                            if ($length > 0) {
                                $part = substr($part, 0, $length);
                            }
                            $value = preg_replace('/\%\['.preg_quote($match).'\]/', $part, $value, 1);
                        }
                        // handle \a - bell character \c and \d - there maybe others, but not sure yet
                        foreach (array("a", "b", "c", "d", "e") as $chr) {
                            $value = preg_replace('/'.preg_quote("\\".$chr).'/', "\\\\\\\\".dechex(ord($chr)), $value);
                        }
                    }
                    // do the mapping
                    foreach ($targets as $target) {
                        // dont allow doubling up
                        if (isset($total_fields[$target])) {
                            continue;
                        }
                        $total_fields[$target] = $value;
                        $this->addAttribute($template, $target, $value);
                    }

                }
                else {
                    // find all the constants that aren't attribute names
                    if (preg_match('/^\_constant:(\d+)$/', $source, $matches)) {
                        // unpack the real source
                        $source = $matches[1];
                    }

                    // setup value correctly
                    $value = (!empty($source) || $source === '0') ? array($source) : array();

                    foreach ($targets as $target) {
                        // dont allow doubling up
                        if (isset($total_fields[$target])) {
                            continue;
                        }
                        $total_fields[$target] = $source;
                        $this->addAttribute($template, $target, $value);
                    }
                }
            }

            // ensure the sanity fields are set
            if (!isset($total_fields['cn'])) {
                $this->addAttribute($template, 'cn', array($cn));
            }

            $template->setRDNAttributes($rdn);

            // set the CN
            // var_dump($template->getLDAPadd());
            // continue;
            $result = $this->server->add($dn, $template->getLDAPadd(), 'user');
            if (!$result) {
//                var_dump($dn);
//                var_dump($template->getLDAPadd());
                $request['page']->error(_('Could not create: ').$dn, _('processing'));
                return $result;
            }
            else {
                // stash in the dn cache
                $this->dn_cache[get_canonical_name($dn)] = array('dn' => $dn);

                // now - if this was on an AD directory and it had a password set
                // then we must now separately set the passwd and activate the account
                if ($this->cfg['server_type'] == 'ad' && isset($account['userPassword'])) {
//                    $adduserAD['unicodepwd'] = mb_convert_encoding('"' . $account['raw_passwd'] . '"', 'UCS-2LE', 'UTF-8');
//                    $result = @ldap_modify($this->server->connect('user'), $dn, $adduserAD);
//                    var_dump($result);
                    $template = new Template($this->server->getIndex(),null,null,'modify', null, true);
                    $template->setDN($dn);
                    $template->accept(false, 'user');
                    // Do not on any account - change the objectclass list - this only fills it
                    // and does not flag it as changed
                    if (is_null($attribute = $template->getAttribute('objectclass'))) {
                        $attribute = $template->addAttribute('objectclass', array('values'=> array('top', 'person', 'organizationalPerson', 'user')));
                    }
                    // add the account control in here and only do two steps to
                    // leave account in password change on first login state
                    if (isset($this->cfg['passwd_reset_state']) && $this->cfg['passwd_reset_state'] == 'checked') {
                        // This is 512 + 1
                        // 512 - Normal account
                        // 1 - no logon script
                        $this->addAttribute($template, 'useraccountcontrol', array(513));
                        // pwdLastSet = 0 should force password change on first login
                        $this->addAttribute($template, 'pwdlastset', array(0));
                    }
                    $this->addAttribute($template, 'unicodePwd', array(mb_convert_encoding('"' . $account['raw_passwd'] . '"', 'UCS-2LE', 'UTF-8')));
                    $result = $this->server->modify($dn, $template->getLDAPmodify(), 'user');
                    if (!$result) {
                        $request['page']->error(_('Could not update the account to active: ').$dn, _('processing'));
                        return $result;
                    }

                    // 3rd step to leave account fully active
                    if (!isset($this->cfg['passwd_reset_state']) || $this->cfg['passwd_reset_state'] != 'checked') {
                        $template = new Template($this->server->getIndex(),null,null,'modify', null, true);
                        $template->setDN($dn);
                        $template->accept(false, 'user');
                        // Do not on any account - change the objectclass list - this only fills it
                        // and does not flag it as changed
                        if (is_null($attribute = $template->getAttribute('objectclass'))) {
                            $attribute = $template->addAttribute('objectclass', array('values'=> array('top', 'person', 'organizationalPerson', 'user')));
                        }
                        $this->addAttribute($template, 'useraccountcontrol', array(513));
                        $result = $this->server->modify($dn, $template->getLDAPmodify(), 'user');
                        if (!$result) {
                            $request['page']->error(_('Could not update the account to active: ').$dn, _('processing'));
                            return $result;
                        }
                    }
                }

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
                var_dump($group_membership);
                if (!$this->replaceGroupMembership(false, $uid, $group_membership, $dn)) {
                    return false;
                }
            }
            // now access for reporting, or user created callbacks
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
     * Check for changes in LDAP values
     *
     * @param array $old
     * @param array $new
     */
    public function changedValue($old, $new) {

        sort($old);
        sort($new);
        $diff = array_diff($old, $new);
        if (empty($diff)) {
            $diff = array_diff($new, $old);
            if (empty($diff)) {
                return false;
            }
            else {
                return true;
            }
        }
        else {
            return true;
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

        $field_mappings = array();
        foreach ($cfg_mappings as $mapping) {
            // guard against numeric value source keys
            if (preg_match('/^\d+$/', $mapping['source'])) {
                $mapping['source'] = '_constant:'.$mapping['source'];
            }
            $field_mappings[$mapping['source']] = $mapping['targets'];
        }

        // process the updates
        foreach ($this->to_be_updated as $account) {

            $dn = $account['dn'];

            // get Ignore for update attributes
            $ignore_attrs = array();
            foreach ($this->udiconfig->getIgnoreAttrs() as $attr) {
                $ignore_attrs[strtolower($attr)] = $attr;
            }

            // find the existing one
            $existing_account = $this->check_user_dn($dn);
            $user_total_classes = array_unique(array_merge($existing_account['objectclass'], $objectclass));
            $old_uid = false;
            if (isset($existing_account['uid']) && !empty($existing_account['uid'][0])) {
                $old_uid = $existing_account['uid'][0];
            }
            else if (isset($existing_account['mlepusername']) && !empty($existing_account['mlepusername'][0])) {
                $old_uid = $existing_account['mlepusername'][0];
            }

            // start building up the modification template
            $template = new Template($this->server->getIndex(),null,null,'modify', null, true);

            $rdn = get_rdn($dn);
            $template->setDN($dn);
            $template->accept(false, 'user');

            // run before update hooks
            $result = udi_run_hook('account_update_before',array($this->server, $this->udiconfig, $account));
            if (is_array($result)) {
                $result = array_pop($result);
                if (is_array($result)) {
                    $account = $result;
                }
            }

            // check object classes
            if ($this->cfg['server_type'] == 'ad') {
                // Do not on any account - change the objectclass list - this only fills it
                // and does not flag it as changed
                if (is_null($attribute = $template->getAttribute('objectclass'))) {
                    $attribute = $template->addAttribute('objectclass', array('values'=> array('top', 'person', 'organizationalPerson', 'user')));
                }
            }
            else {
                // check that atleast these object classes exist
                $final_classes = $existing_account['objectclass'];
                foreach ($user_total_classes as $class) {
                    if (!in_array($class, $existing_account['objectclass'])) {
                        // update user object classes
                        $final_classes[]= $class;
                    }
                }
                // update user object classes
                if (count($final_classes) != count($existing_account['objectclass'])) {
                    $this->modifyAttribute($template, 'objectclass', $final_classes);
                }
            }


            // store the mlepGroupMembership
            $group_membership = $this->makeGroupMembership($account);

            $uid = false;
            $mlepusername = false;

            if (isset($ignore_attrs['uid'])) {
                // if ignore - try and override with existing
                if (isset($existing_account['uid']) && !empty($existing_account['uid'][0])) {
                    $uid = $existing_account['uid'][0];
                }
            }
            if (isset($ignore_attrs['mlepusername'])) {
                // if ignore - try and override with existing
                if (isset($existing_account['mlepusername']) && !empty($existing_account['mlepusername'][0])) {
                    $mlepusername = $existing_account['mlepusername'][0];
                }
            }

            // count and compare the changes against the dn_cache
            // if there are none then don't do an update XXX
            $changed = false;
            foreach ($account as $attr => $value) {
                // ignore the dn
                if ($attr == 'dn') {
                    continue;
                }
                // skip the mlepgroupmembership
                if (strtolower($attr) == 'mlepgroupmembership' ||
                    strtolower($attr) == 'mlephomegroup') {
                    continue;
                }

                $value = trim($value);

                // store UserId candidates
                if (strtolower($attr) == 'mlepusername' && !isset($ignore_attrs['mlepusername'])) {
                    // default to incoming
                    $mlepusername = $value;
                }
                if(strtolower($attr) == 'uid' && !isset($ignore_attrs['uid'])) {
                    // default to incoming
                    $uid = $value;
                }

                // split the multi-value attributes
                if (strtolower($attr) == 'mlepassociatednsn') {
                    // these values must be unique ???? - or AD barfs
                    $value = empty($value) ? array() : explode('#', $value);
                    $value = array_unique($value);
                }

                // map attributes here
                if (!is_array($value)) {
                    $value = !empty($value) ? array($value) : array();
                }
                if (isset($field_mappings[$attr])) {
                    foreach ($field_mappings[$attr] as $target) {
                        // check ignore attrs
                        if (isset($ignore_attrs[strtolower($target)]) || $this->cfg['dn_attribute'] == $target) {
                            continue;
                        }
                        $ignore_attrs[strtolower($target)] = $target;
                        if (isset($existing_account[strtolower($target)])) {
                            // check for change
                            if ($this->changedValue($existing_account[strtolower($target)], $value)) {
                                $this->modifyAttribute($template, $target, $value);
                                $existing_account[strtolower($target)] = $value;
                                $changed = true;
                            }
                        }
                        // a new attribute
                        else if (!empty($value)) {
                            $this->modifyAttribute($template, $target, $value);
                            $existing_account[strtolower($target)] = $value;
                            $changed = true;
                        }
                    }
                }
                else {
                    // check ignore attrs
                    if (!isset($ignore_attrs[strtolower($attr)]) && $this->cfg['dn_attribute'] != $attr) {
                        $ignore_attrs[strtolower($attr)] = $attr;
                        if (isset($existing_account[strtolower($attr)])) {
                            // check for change
                            if ($this->changedValue($existing_account[strtolower($attr)], $value)) {
                                $this->modifyAttribute($template, $attr, $value);
                                $existing_account[strtolower($attr)] = $value;
                                $changed = true;
                            }
                        }
                        // a new attribute
                        else if (!empty($value)) {
                            $this->modifyAttribute($template, $attr, $value);
                            $existing_account[strtolower($attr)] = $value;
                            $changed = true;
                        }
                    }
                }
            }

            // now do expression substitutions
            foreach ($field_mappings as $source => $targets) {

                if (preg_match('/\%\[.+\]/', $source)) {
                    // do the expansion then map to fields
                    // find the substitutions
                    $value = $source;
                    if (preg_match_all('/\%\[(.+?)\]/', $source, $matches)) {
                        foreach ($matches[1] as $match) {
                            $parts = explode(':', $match);
                            $attr = array_shift($parts);
                            if (strtolower($attr) == 'mlepgroupmembership') {
                                $element = empty($parts) ? 1 : (int)array_shift($parts);
                                $groups = explode('#', $group_membership);
                                $part = isset($groups[$element]) ? $groups[$element] : '';
                            }
                            else {
                                $part = isset($existing_account[strtolower($attr)]) ? $existing_account[strtolower($attr)][0] : '';
                            }
                            $length = empty($parts) ? 0 : (int)array_shift($parts);
                            $length = (int)$length;
                            if ($length > 0) {
                                $part = substr($part, 0, $length);
                            }
                            $value = preg_replace('/\%\['.preg_quote($attr).'\]/', $part, $value, 1);
                        }
                        // handle \a - bell character \c and \d - there maybe others, but not sure yet
                        foreach (array("a", "b", "c", "d", "e") as $chr) {
                            $value = preg_replace('/'.preg_quote("\\".$chr).'/', "\\\\\\\\".dechex(ord($chr)), $value);
                        }
                    }
                    if (!is_array($value)) {
                        $value = !empty($value) ? array($value) : array();
                    }

                    // do the mapping
                    foreach ($targets as $target) {
                        // check ignore attrs
                        if (isset($ignore_attrs[strtolower($target)]) || $this->cfg['dn_attribute'] == $target) {
                            continue;
                        }
                        $ignore_attrs[strtolower($target)] = $target;
                        if (isset($existing_account[strtolower($target)])) {
                            // check for change
                            if ($this->changedValue($existing_account[strtolower($target)], $value)) {
                                $this->modifyAttribute($template, $target, $value);
                                $existing_account[strtolower($target)] = $value;
                                $changed = true;
                            }
                        }
                        // a new attribute
                        else if (!empty($value)) {
                            $this->modifyAttribute($template, $target, $value);
                            $existing_account[strtolower($target)] = $value;
                            $changed = true;
                        }
                    }

                }
                else {
                    // find all the constants that aren't attribute names
                    if (preg_match('/^\_constant:(\d+)$/', $source, $matches)) {
                        // unpack the real source
                        $source = $matches[1];
                    }

                    // setup value correctly
                    $value = (!empty($source) || $source === '0') ? array($source) : array();

                    foreach ($targets as $target) {
                        // check ignore attrs
                        if (isset($ignore_attrs[strtolower($target)]) || $this->cfg['dn_attribute'] == $target) {
                            continue;
                        }
                        $ignore_attrs[strtolower($target)] = $target;
                        if (isset($existing_account[strtolower($target)])) {
                            // check for change
                            if ($this->changedValue($existing_account[strtolower($target)], $value)) {
                                $this->modifyAttribute($template, $target, $value);
                                $existing_account[strtolower($target)] = $value;
                                $changed = true;
                            }
                        }
                        // a new attribute
                        else if (!empty($value)) {
                            $this->modifyAttribute($template, $target, $value);
                            $existing_account[strtolower($target)] = $value;
                            $changed = true;
                        }
                    }
                }
            }

            // make sure item exists in the tree
            $this->addTreeItem($dn);

            // if ! changed then skip XXX
            if ($changed) {
                //var_dump($dn);
                //var_dump($template->getLDAPmodify());
                //continue;
                $result = $this->server->modify($dn, $template->getLDAPmodify(), 'user');
                if (!$result) {
//                    var_dump($dn);
//                    var_dump($template->getLDAPmodify());
                    $request['page']->error(_('Could not update: ').$dn, _('processing'));
                    return $result;
                }
            }

            // now - do we actually want to change the group membership
            if (isset($this->cfg['ignore_membership_updates']) && $this->cfg['ignore_membership_updates'] == 'checked') {
                continue;
            }

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
                // check if this is a deactivated account
                if (isset($account['labelleduri'])) {
                    $labeleduri = preg_grep('/^udi_deactivated:/', $account['labeleduri']);
                    $labeleduri = array_shift($labeleduri);
                    list($discard, $new_uid) = explode('udi_deactivated:', $labeleduri, 2);
                }
                $old_uid = $new_uid;
            }
            if (!$this->replaceGroupMembership($old_uid, $new_uid, $group_membership, $dn)) {
                return false;
            }
            // now access for reporting, or user created callbacks
            udi_run_hook('account_update_after', array($this->server, $this->udiconfig, $account));
        }

        return true;
    }

    /**
     * Build the group cache
     *
     * @param String $group group membership DN
     * @return array group membership
     */
    private function group_cache($group) {
        $group_attr = strtolower($this->cfg['group_attr']);
        $group = get_canonical_name($group);

        if (!isset($this->group_cache[$group])) {
            // cache group
            $query = $this->server->query(array('base' => $group), 'user');
            $this->group_cache[$group] = array();
            if (!empty($query)) {
                $query = array_shift($query);
                if (isset($query[$group_attr])) {
                    foreach($query[$group_attr] as $member) {
                        // ensure that the uid is reduced to common terms
                        if ($group_attr != 'memberuid') {
                            $this->group_cache[$group][]= $member;
                        }
                        else {
                            $this->group_cache[$group][]= strtolower($member);
                        }
                    }
                }
            }
            else {
                // group does not exist
                $this->group_cache[$group] = false;
            }
        }
        return $this->group_cache[$group];
    }

    /**
     * Use a cache lookup to determine if a user is allready in a group
     *
     * @param String $uid user id - either uid or dn
     * @param String $group dn of the group of membership
     * @return bool true if exists in group
     */
    private function userInGroup($uid, $group) {
        $group_attr = strtolower($this->cfg['group_attr']);

        // ensure that the uid is reduced to common terms
        if ($group_attr != 'memberuid') {
            $uid = get_canonical_name($uid);
        }
        else {
            $uid = strtolower($uid);
        }

        // and the group
        $group = get_canonical_name($group);
        // check the cache exists first
        $this->group_cache($group);

        // now check the cache
        if ($group_attr != 'memberuid') {
            foreach ($this->group_cache[$group] as $member) {
                if ($uid == get_canonical_name($member)) {
                    return true;
                }
            }
            return false;
        }
        else {
            return in_array($uid, $this->group_cache[$group]);
        }

    }

    /**
     * Replace a users group membership
     *
     * @param String $uid userid relative to the configured user attribute
     * @param String $group_membership as per mlepGroupMembership export schema definition
     *
     * @return bool true on success
     */
    private function removeGroupMembership($uid, $skip=array()) {
        global $request;

        // must have a user id
        if (!$uid) {
            return false;
        }

        // cache the groups to deal with
        $this->cacheGroups();

        // hunt for existing group membership and remove
        $group_attr = strtolower($this->cfg['group_attr']);

        // calculate skips
        $total_skips = array();

        foreach ($skip as $membership) {
            if (isset($this->group_mappings[$membership])) {
                $total_skips = array_merge($total_skips, $this->group_mappings[$membership]);
            }
        }
        $total_skips = array_unique($total_skips);

        foreach ($this->total_groups as $group) {
            if (in_array($group, $total_skips)) {
                // the user is staying in this group - just check that the
                // targets haven't changed
                //echo "saved an update!<br/>";
                continue;
            }
            // check and delete from group
            if ($this->userInGroup($uid, $group)) {
                // user exists in group
                $values = $this->group_cache($group);

                // remove user from membership attribute and then save again
                $values = array_merge(preg_grep('/^'.$uid.'$/', $values, PREG_GREP_INVERT), array());

                // user was removed
                $template = $this->createModifyTemplate($group);

                $this->modifyAttribute($template, $group_attr, $values);
                // add a dummy objectclass attribute
                if (is_null($attribute = $template->getAttribute('objectClass'))) {
                    $attribute = $template->addAttribute('objectClass',array('values'=> explode(';', $this->cfg['objectclasses'])));
                }

                // Perform the modification
                $this->addTreeItem($group);
                $result = $this->server->modify($group, $template->getLDAPmodify(), 'user');
                if (!$result) {
                    return $request['page']->error(_('Could not remove user from group: ').$uid.'/'.$group, _('processing'));
                }
                // update the group cache
                $this->group_cache[get_canonical_name($group)] = $values;
            }
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
//        var_dump($this->total_groups);
//        var_dump($this->group_mappings);
        //exit(0);
        return true;
    }

    /**
     * cache User DNs into the processor cache
     *
     * @param String $dn a LDAP DN
     * @return bool true is exists
     */
    private function check_user_dn($dn) {
        $dn = get_canonical_name($dn);
        if (!isset($this->dn_cache[$dn])) {
            $query = $this->server->query(array('base' => $dn), 'user');
            if (empty($query)) {
                $this->dn_cache[$dn] = false;
            }
            else {
                $query = array_shift($query);
//                $this->dn_cache[$dn] = $query['dn'];
                $this->dn_cache[$dn] = $query;
            }
        }
        return $this->dn_cache[$dn];
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

        //echo "old: $old_uid  new: $new_uid groups: $group_membership dn: $user_dn\n";

        // don't process group membership if disabled in the config
        if (!isset($this->cfg['groups_enabled']) || $this->cfg['groups_enabled'] != 'checked') {
            return true;
        }

        // must have a user id
        if (!$new_uid) {
            return $request['page']->error(_('No uid passed, so cannot alter membership: ').$user_dn, _('processing'));
        }

        $group_attr = strtolower($this->cfg['group_attr']);
        $groups = array();
        if ($group_membership) {
            $groups = explode('#', $group_membership);
        }

        // cache the groups to deal with
        $this->cacheGroups();

        // at this point, it would be good to calculate the delta of group membership
        // if it hasn't changed then why bother carrying on
        if ($old_uid != $new_uid) {
            // definitely remove from groups
            $this->removeGroupMembership($old_uid);
        }
        else {
            // are there any that they nolonger belong to?
            $this->removeGroupMembership($old_uid, $groups);
        }

        // no point in coninuing if the group membership is empty
        if (empty($group_membership)) {
            return true;
        }

        // then re add memberships
        foreach ($groups as $group) {
            if (isset($this->group_mappings[$group])) {
                foreach($this->group_mappings[$group] as $mapping) {
                    // insert mlepUsername in to the group from here
                    $template = $this->createModifyTemplate($mapping);
                    $values = $this->group_cache($mapping);
                    if ($values === false) {
                        // group does not exist
                        return $request['page']->error(_('Membership group does not exist: ').$mapping, _('processing'));
                    }

                    // don't attempt to add them if they are allready there
                    if (!$this->userInGroup($new_uid, $mapping)) {
                        if ($group_attr != 'memberuid') {
//                            $values[] = $this->check_user_dn($new_uid);
                            $entry = $this->check_user_dn($new_uid);
                            $values[] = $entry['dn'];
                        }
                        else {
                            $values[] = strtolower($new_uid);
                        }
                        $this->modifyAttribute($template, $group_attr, $values);
                        // add a dummy objectclass attribute
                        if (is_null($attribute = $template->getAttribute('objectClass'))) {
                            $attribute = $template->addAttribute('objectClass',array('values'=> explode(';', $this->cfg['objectclasses'])));
                        }

                        # Perform the modification
                        $this->addTreeItem($mapping);
                        $result = $this->server->modify($mapping,$template->getLDAPmodify(), 'user');
                        if (!$result) {
                            return $request['page']->error(_('Could not add user to group: ').$new_uid.'/'.$mapping, _('processing'));
                        }
                        // update the group cache
                        $this->group_cache[get_canonical_name($mapping)] = $values;
                    }
                }
            }
        }
        return true;
    }


    /**
     * validate the user reactivation request
     *
     * @return bool true on success
     */
    public function validateReactivation($base=false) {
        global $request;
        $result = true;

        $children = array();
        if ($base) {
            $children []= $base;
        }
        else {
            $children = $this->server->getContainerContents($this->cfg['move_to'], 'user', 0, '(objectClass=*)', LDAP_DEREF_NEVER);
        }
        foreach ($children as $child) {
            $query = $this->server->query(array('base' => $child), 'user');
            if (empty($query)) {
                return $request['page']->error(_('Account not found: ').$child);
            }
            $account = array_shift($query);
            // check that there isnt a duplicate there already
            $deactive_dn = $account['dn'];
            if (!isset($account['labeleduri'])) {
                $request['page']->info(_('Deactivated account does not have old DN - cannot restore: ').$deactive_dn, _('processing'));
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
                    $query = $this->server->query(array('base' => $old_dn), 'user');
                    if (!empty($query)) {
                        $existing_account = array_shift($query);
                        $request['page']->info(_('Deactivated account ').$deactive_dn._(' cannot be restored over: ').$old_dn, _('processing'));
                        $result = false;
                    }
                    // check that the target container exists
                    $container = $this->server->getContainer($old_dn);
                    $query = $this->server->query(array('base' => $container), 'user');
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
    public function reactivate($base=false) {
        global $request;
        $result = true;

        $children = array();
        if ($base) {
            $children []= $base;
        }
        else {
            $children = $this->server->getContainerContents($this->cfg['move_to'], 'user', 0, '(objectClass=*)', LDAP_DEREF_NEVER);
        }
        foreach ($children as $child) {
            $query = $this->server->query(array('base' => $child), 'user');
            if (empty($query)) {
                return $request['page']->error(_('Account not found: ').$child);
            }
            $account = array_shift($query);
            // check that there isnt a duplicate there already
            $deactive_dn = $account['dn'];
            if (!isset($account['labeleduri'])) {
                $request['page']->info(_('Deactivated account does not have old DN - cannot restore: ').$deactive_dn, _('processing'));
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
                    $template = new Template($this->server->getIndex(),null,null,'modrdn', null, true);
                    $rdn = get_rdn($old_dn);
                    $template->setDN($deactive_dn);
                    $template->accept(false, 'user');
                    $attrs = array();
                    $attrs['newrdn'] = $rdn;
                    $attrs['deleteoldrdn'] = '1';
                    $attrs['newsuperior'] = $container;
                    $template->modrdn = $attrs;
                    $this->addTreeItem($deactive_dn);
                    $result = $this->server->rename($deactive_dn, $template->modrdn['newrdn'], $template->modrdn['newsuperior'], $template->modrdn['deleteoldrdn'], 'user');
                    if (!$result) {
                        $request['page']->error(_('Could not resurect (rename): ').$deactive_dn, _('processing'));
                        return $result;
                    }

                    // sort out the label
                    $template = new Template($this->server->getIndex(),null,null,'modify', null, true);
                    $rdn = get_rdn($old_dn);
                    $template->setDN($old_dn);
                    $template->accept(false, 'user');
                    // add a dummy objectclass attribute
                    if (is_null($attribute = $template->getAttribute('objectClass'))) {
                        $attribute = $template->addAttribute('objectClass',array('values'=> explode(';', $this->cfg['objectclasses'])));
                    }
                    $this->modifyAttribute($template, 'labeleduri', $labeleduri);
                    // if we are using AD, then we have to actually unlock the user too
                    // cannot do this if the account has no password
                    if ($this->cfg['server_type'] == 'ad' && isset($account['pwdlastset']) && $account['pwdlastset'][0] > 1) {
                        $this->modifyAttribute($template, 'useraccountcontrol', array(512));
                    }
                    $result = $this->server->modify($old_dn, $template->getLDAPmodify(), 'user');
                    if (!$result) {
                        $request['page']->error(_('Could not modify: ').$old_dn, _('processing'));
                        return $result;
                    }
                    // now access for reporting, or user created callbacks
                    udi_run_hook('account_reactivate_after', array($this->server, $this->udiconfig, $account));
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

        $children = $this->server->getContainerContents($this->cfg['move_to'], 'user', 0, '(objectClass=*)', LDAP_DEREF_NEVER);
        foreach ($children as $child) {
            $query = $this->server->query(array('base' => $child), 'user');
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
            $result = $this->server->delete($deactive_dn, 'user');
            if (!$result) {
                $request['page']->error(_('Could not completely delete: ').$deactive_dn, _('processing'));
                return $result;
            }
            // now access for reporting, or user created callbacks
            udi_run_hook('account_delete_after', array($this->server, $this->udiconfig, $account));

        }

        $request['page']->info(_('Processed: ').count($children)._(' accounts completely deleted'), _('processing'));
        return $result;
    }



    /**
     * Process user delete records
     *
     * @return bool true on success
     */
    public function processDeactivations() {
        global $request;

        // final check of config
        if (isset($this->cfg['ignore_deletes']) && $this->cfg['ignore_deletes'] == 'checked') {
            // deletes are disabled
            return false;
        }
        if (!isset($this->cfg['move_on_delete']) || $this->cfg['move_on_delete'] != 'checked') {
            // no moving on delete
            return false;
        }

        if (!isset($this->cfg['move_to']) || empty($this->cfg['move_to'])) {
            // must have a target to relocate to
            return $request['page']->error(_('Move on delete to target is not specified in config - aborting'), _('processing'));
        }


        // process the deletes, which are really moves
        foreach ($this->to_be_deactivated as $account) {

            $dn = $account['dn'];

            // First flag accounts where they come from - accounts can be
            // resurected with this later
            $template = new Template($this->server->getIndex(),null,null,'modify', null, true);
            $rdn = get_rdn($dn);
            $template->setDN($dn);
            $template->accept(false, 'user');
            // add a dummy objectclass attribute
            if (is_null($attribute = $template->getAttribute('objectClass'))) {
                $attribute = $template->addAttribute('objectClass',array('values'=> explode(';', $this->cfg['objectclasses'])));
            }
            $values = isset($account['labeleduri']) ? $account['labeleduri'] : array();
            $values []= 'udi_deactivated:'.$dn;
            $this->modifyAttribute($template, 'labeleduri', $values);
            // if we are using AD, then we have to actually lock the user too
            if ($this->cfg['server_type'] == 'ad') {
                $this->modifyAttribute($template, 'useraccountcontrol', array(514));
            }

            $result = $this->server->modify($dn, $template->getLDAPmodify(), 'user');
            if (!$result) {
                $request['page']->error(_('Could not modify: ').$dn, _('processing'));
                return $result;
            }

            // start building up the move template
            $template = new Template($this->server->getIndex(),null,null,'modrdn', null, true);
            $rdn = get_rdn($dn);
            $template->setDN($dn);
            $template->accept(false, 'user');
            $attrs = array();
            $attrs['newrdn'] = $rdn;
            $attrs['deleteoldrdn'] = '1';
            $attrs['newsuperior'] = $this->cfg['move_to'];
            $template->modrdn = $attrs;

            // DN must exist
            if (! $this->server->dnExists($dn, 'user')) {
                return $request['page']->error(sprintf('%s %s',_('DN does not exist'),$dn), _('processing'));
            }

            // might not be able to rename branches
            if (! $this->server->isBranchRenameEnabled()) {
                // We search all children, not only the visible children in the tree
                $children = $this->server->getContainerContents($dn, 'user', 0, '(objectClass=*)', LDAP_DEREF_NEVER);

                if (count($children) > 0) {
                    return $request['page']->error(_('You cannot rename an entry which has children entries (eg, the rename operation is not allowed on non-leaf entries)'), _('processing'));
                }
           }

           // make sure that this rename wont attempt an overwrite
           $query = $this->server->query(array('base' => $rdn.','.$this->cfg['move_to']), 'user');
            if (!empty($query)) {
                // group does not exist
                $request['page']->warning(_('Target DN allready exists for deactivate of : ').$dn, _('processing'));
                continue;
            }

            // make sure that the existing dn is in the tree
            $this->addTreeItem($dn);
            $result = $this->server->rename($dn, $template->modrdn['newrdn'], $template->modrdn['newsuperior'], $template->modrdn['deleteoldrdn'], 'user');
            if (!$result) {
                $request['page']->error(_('Could not delete (rename): ').$dn, _('processing'));
                return $result;
            }
            // now access for reporting, or user created callbacks
            udi_run_hook('account_deactivate_after', array($this->server, $this->udiconfig, $account));
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
        $template = new Template($this->server->getIndex(),null,null,'modify', null, true);
        $rdn = get_rdn($dn);
        $container = $this->server->getContainer($dn);
        $template->setDN($dn);
        $template->accept(false, 'user');
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
        if (empty($value) || (empty($value[0]) && $value[0] !== '0')) {
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