<?php
/**
 *
 * @author  Piers Harding  piers@catalyst.net.nz
 * @version 0.0.1
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package local
 *
 */
global $LDAP_USER, $LDAP_PASSWD;

define('TEST_LOG_FILE', '/tmp/phpldapadmin.log');

require_once(dirname(__FILE__).'/../test_config.php');

// how to search
// ldapsearch -x -LLL -s sub -b "ou=New People,dc=example,dc=com" -D "cn=admin,dc=example,dc=com" -w letmein '(&(objectclass=mlpeperson)(uid=*))'

require_once 'PHPUnit/Framework.php';

class UDITestBase extends PHPUnit_Framework_TestCase {

    /**
     * setup the LDAP directory - reload base data
     */
    public function setUp() {
        global $LDAP_USER, $LDAP_PASSWD;
        global $UDI_PATH, $TEST_INIT;
        if ($TEST_INIT != get_class($this)) {
            echo "\nRunning ". get_class($this)."\n";
            $TEST_INIT = get_class($this);
        }

        // reload LDAP directory
        $dir = dirname(__FILE__);
        chdir($dir.'/../');
        self::call_exec(self::format_cmd('sudo ./reload.sh'));
    }


    /**
     * test that there are no accounts present 
     */
    public function empty_accounts() {
        global $UDI_RC;
    
        // we start with no people
        $result = self::ldap_search('ou=New People, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))');
        $this->assertTrue($UDI_RC == true, 'Empty accounts serach succeeded'); // search succeeded
        $this->assertTrue($result[0] == 1, 'There are no accounts at the beginning'); // failed to find any accounts
    }

    /**
     * test that there are no deactivated accounts present 
     */
    public function empty_deactivated_accounts() {
        global $UDI_RC;
    
        // we start with no people
        $result = self::ldap_search('ou=Deactivated Accounts, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))');
        $this->assertTrue($UDI_RC == true, 'Empty accounts serach succeeded'); // search succeeded
        $this->assertTrue($result[0] == 1, 'There are no accounts at the beginning'); // failed to find any accounts
    }
    

    /**
     * test that the posix groups are empty
     */
    public function empty_posix_groups() {
        global $UDI_RC;
    
        foreach (array('Board of trustees posix', 'LMS Access posix', 'Parents posix', 'Staff posix', 'Students posix') as $group) {
            $result = preg_grep('/\w/', self::ldap_search('cn='.$group.',ou=Services, dc=example,dc=com', '(objectclass=posixGroup)', 'memberUid'));
            $this->assertTrue($UDI_RC == true, 'Search for group succeeded'); // search succeeded
            array_shift($result); // git rid of dn:
            $this->assertTrue(count($result) == 0, 'Group('.$group.') is empty of memberUids'); // failed to find any members in groups
        }
    }
    

    /**
     * test that the member groups are empty
     */
    public function empty_member_groups() {
        global $UDI_RC;
    
        foreach (array('Board of trustees member', 'LMS Access member', 'Parents member', 'Staff member', 'Students member') as $group) {
            $result = preg_grep('/\w/', self::ldap_search('cn='.$group.',ou=Services, dc=example,dc=com', '(objectclass=groupOfNames)', 'member'));
            $this->assertTrue($UDI_RC == true, 'Search for group succeeded'); // search succeeded
            array_shift($result); // git rid of dn:
            // member attribute is not allowed to be empty so I put 1 in there by default
            $this->assertTrue(count($result) == 1, 'Group('.$group.') is empty of members'); // failed to find any members in groups
        }
    }
    

    /**
     * check the LDAP posix groups against an expected results file
     * @param string $file
     */
    public function check_posix_groups($file) {
        global $UDI_RC;

        echo "checking posix groups against file: $file\n";
        $result = self::ldap_search('ou=Services, dc=example,dc=com', '(objectclass=posixGroup)', 'memberUid');
        $this->assertTrue($UDI_RC == true, 'serach for posix groups succeeded'); // search succeeded
        $this->assertTrue(count($result) > 10, 'posix Groups found'); 
        $data = self::get_data($file);
        $this->assertTrue(count($data) > 10, 'group comparison data found'); 
        $this->assertTrue(count(array_diff($result, $data)) == 0, 'posix groups and check data correct');
    }

   

    /**
     * check the LDAP member groups against an expected results file
     * @param string $file
     */
    public function check_member_groups($file) {
        global $UDI_RC;

        echo "checking posix groups against file: $file\n";
        $result = self::ldap_search('ou=Services, dc=example,dc=com', '(objectclass=groupOfNames)', 'member');
        $this->assertTrue($UDI_RC == true, 'search for member groups succeeded'); // search succeeded
        $this->assertTrue(count($result) > 10, 'member Groups found'); 
        $data = self::get_data($file);
        $this->assertTrue(count($data) > 10, 'group comparison data found'); 
        $this->assertTrue(count(array_diff($result, $data)) == 0, 'member groups and check data correct');
    }
    
    
    // Global error container
    static protected $last_error = false;

    /**
     * return the last error
     *
     * @return string last error message || false
     */
    static function last_error_message() {
        if (is_array(self::$last_error) && isset(self::$last_error['message'])) {
            return self::$last_error['message'];
        }
        return false;
    }

    /**
     * return the last error message
     *
     * @return array last error values
     */
    static function last_error() {
        return self::$last_error;
    }

   /**
     * output logging messages for the udi layer
     *
     * @param string $msg message to log
     * @return boolean true
     */
    static function add_to_log($msg) {
     //   if (getenv('UDI_LOG')) {
            $fp = fopen(TEST_LOG_FILE, 'a');
            fwrite($fp, $msg."\n");
            fflush($fp);
            fclose($fp);
      //  }
        return true;
    }


    /**
     * get ldif data for comparision
     *
     * @param string $filename filename to process with
     * @return string of ldif data
     */
    static function get_data ($filename) {
        $data = preg_grep('/^userPassword\:/', explode("\n", file_get_contents($filename)), PREG_GREP_INVERT);
        array_pop($data);
        return $data;
    }
    

    /**
     * call udi in command line options mode
     *
     * @param string $opts a list of options/parameters for the action
     * @return string of result
     */
    static function cron ($opts) {
        global $UDI_RC;
        $cmd = 'php ../tools/cron.php --server="Seagull LDAP Server" ' .$opts;
        $result = self::call_exec($cmd);
        return explode("\n", $result);
    }
    

    /**
     * run the background process step for file 
     *
     * @param string file name
     * @return string of result
     */
    static function cron_process ($file) {
        global $UDI_RC;

        return self::cron('--process --file='.$file);
    }


    /**
     * run against an empty file to deactivate all accounts
     *
     * @return string of result
     */
    static function cron_deactivate () {
        global $UDI_RC;

        $file = getcwd().'/data/udi_import_empty.csv';
        return self::cron('--process --file='.$file);
    }
    

    /**
     * reactivate accounts again
     *
     * @return string of result
     */
    static function cron_reactivate () {
        global $UDI_RC;

        return self::cron('--reactivate --yes');
    }
    

    /**
     * completely delete deactivated accounts
     *
     * @return string of result
     */
    static function cron_delete () {
        global $UDI_RC;

        return self::cron('--delete --yes');
    }
    
    
    /**
     * call ldapsearch
     *
     * @param string $base the search base
     * @param string $query the query parameters
     * @param string option list of attributes to limit search results by
     * @return string of result
     */
    static function ldap_search ($base, $query, $attrs='') {
        global $UDI_RC, $LDAP_USER, $LDAP_PASSWD;
        $cmd = 'ldapsearch -x -LLL -s sub -b "' .$base.'" -D "'.$LDAP_USER.'" -w '.$LDAP_PASSWD.' \''.$query.'\' '.$attrs.' ';
        $result = self::call_exec($cmd);
        return explode("\n", $result);
    }
    
    
    /**
     * call ldapadd
     *
     * @param string $file the ldif file
     * @return string of result
     */
    static function ldap_add ($file) {
        global $UDI_RC, $LDAP_USER, $LDAP_PASSWD;
        $cmd = 'ldapadd -x -c -D "'.$LDAP_USER.'" -w '.$LDAP_PASSWD.' -f '.$file.' ';
        $result = self::call_exec($cmd);
        return explode("\n", $result);
    }
    
    /**
     * call udi in command line options mode
     *
     * @param $cmd the shell command to call
     * @return string of result
     */
    static function call_exec ($cmd) {
        global $UDI_RC;
        $cmd = self::format_cmd($cmd);
        self::add_to_log('EXEC: ' . $cmd);
        exec($cmd, $output, $rc);
        $rc = $rc == 0 ? true : false;
        self::add_to_log('RC: ' . $rc . "\nRESULT: ".implode("\n", $output));
        $UDI_RC = $rc;
        return count($output) ? implode("\n", $output) : $rc;
    }


    /**
     * format the command line parameters for a udi call
     *
     * @param string $cmd command to formated
     * @return string of the command line call
     */
    static function format_cmd ($cmd) {
        //return escapeshellcmd($cmd . ' 2>&1');
        return $cmd . ' 2>&1';
    }
}
