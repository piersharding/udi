<?php
/**
 *
 * @author  Piers Harding  piers@catalyst.net.nz
 * @version 0.0.1
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package local
 *
 */

require_once './UDITestBase.class.php';

class UpdatesTest extends UDITestBase {

    public function testUpdates1_changing_values() {
        global $UDI_RC;
        // test that groups move correctly
        
        // load UDI Config
        $file = getcwd().'/ldap/udiconfig_posix.ldif';
        $result = self::ldap_add($file);
        
        // we start with no people
        $this->empty_accounts();

        // check all groups are empty
        $this->empty_posix_groups();
        
        // process basic users
        echo "Process basic posix user import\n";
        $file = getcwd().'/data/udi_import.csv';
        $result = self::cron_process($file);

        // we start with number of people
        $result = preg_grep('/^userPassword\:/', self::ldap_search('ou=New People, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))'), PREG_GREP_INVERT);
        $this->assertTrue($UDI_RC == true, 'search for user accounts succeeded'); // search succeeded
        $this->assertTrue(count($result) == 234, 'account search has the right number of records'); 
        $data = self::get_data('./data/01_10_accounts.ldif');
        $this->assertTrue(count($data) == 234, 'comparison data found'); 
        $this->assertTrue(count(array_diff($result, $data)) == 0, 'new accounts and check data correct');
        // check all groups are populated correctly
        foreach (array('Board of trustees posix' => 1, 'LMS Access posix' => 6, 'Parents posix' => 1, 'Staff posix' => 3, 'Students posix' => 6) as $group => $cnt) {
            $result = preg_grep('/\w/', self::ldap_search('cn='.$group.',ou=Services, dc=example,dc=com', '(objectclass=posixGroup)', 'memberUid'));
            $this->assertTrue($UDI_RC == true, 'Search for posix group succeeded'); // search succeeded
            array_shift($result); // git rid of dn:
            $this->assertTrue(count($result) == $cnt, 'posix Group('.$group.') is NOT empty of memberUids'); // failed to find any members in groups
        }

        echo "Process basic posix user import AGAIN\n";
        $file = getcwd().'/data/udi_import.csv';
        $result = self::cron_process($file);

        // we start with number of people
        $result = preg_grep('/^userPassword\:/', self::ldap_search('ou=New People, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))'), PREG_GREP_INVERT);
        $this->assertTrue($UDI_RC == true, 'search for user accounts succeeded'); // search succeeded
        $this->assertTrue(count($result) == 234, 'account search has the right number of records'); 
        $data = self::get_data('./data/01_10_accounts.ldif');
        $this->assertTrue(count($data) == 234, 'comparison data found'); 
        $this->assertTrue(count(array_diff($result, $data)) == 0, 'new accounts and check data correct');
        // check all groups are populated correctly
        foreach (array('Board of trustees posix' => 1, 'LMS Access posix' => 6, 'Parents posix' => 1, 'Staff posix' => 3, 'Students posix' => 6) as $group => $cnt) {
            $result = preg_grep('/\w/', self::ldap_search('cn='.$group.',ou=Services, dc=example,dc=com', '(objectclass=posixGroup)', 'memberUid'));
            $this->assertTrue($UDI_RC == true, 'Search for posix group succeeded'); // search succeeded
            array_shift($result); // git rid of dn:
            $this->assertTrue(count($result) == $cnt, 'posix Group('.$group.') is NOT empty of memberUids'); // failed to find any members in groups
        }
        
        
        // process updates
        $file = getcwd().'/data/udi_import_update_values.csv';
        $result = self::cron_process($file);
        $errors = preg_grep('/^error: /', $result);
        $warnings = preg_grep('/^warn: /', $result);
//        $this->assertTrue(in_array('warn: User account is duplicate in directory for mlepUsername: dumpty', $warnings), 'check warning for duplicate for mlepUsername');
//        $this->assertTrue(in_array('error: Could not create: cn=Dumpty Humpty,ou=Students,ou=New People,dc=example,dc=com', $errors), 'Check error on creating user - duplicate');
        
        //RESULT: info: File processing started
        //warn: User account is duplicate in directory for mlepUsername: dumpty
        //warn: User account is duplicate in directory for uid: dumpty
        //info: Calculated: 1 creates 8 updates 2 deletes
        //warn: This update has been or will be cancelled, it would result in an attribute value not being unique. You might like to search the LDAP server for the offending entry. ( Search )
        //error: Could not create: cn=Dumpty Humpty,ou=Students,ou=New People,dc=example,dc=com
        //info: File processing finished

        $result = preg_grep('/^userPassword\:/', self::ldap_search('ou=New People, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))'), PREG_GREP_INVERT);
        $data = self::get_data('./data/03_10_updated_accounts.ldif');
        if (count(array_diff($result, $data)) != 0) {
            var_dump(array_diff($result, $data));
        }
        if (count(array_diff($data, $result)) != 0) {
            echo "other way round: \n";
            var_dump(array_diff($data, $result));
        }
        $this->assertTrue(count($data) == 208, 'comparison data found: '.count($data)); 
        $this->assertTrue(count(array_diff($result, $data)) == 0, 'new accounts and check data correct');

        // check deactivated accounts
        $result = preg_grep('/^userPassword\:/', self::ldap_search('ou=Deactivated Accounts, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))'), PREG_GREP_INVERT);
        $this->assertTrue($UDI_RC == true, 'search for deactivated user accounts succeeded'); // search succeeded
        $this->assertTrue(count($result) == 51, 'deactivated account search has the right number of records'); 
        $data = self::get_data('./data/03_2_deactivated_accounts.ldif');
        $this->assertTrue(count($data) == 51, 'deactivated comparison data found'); 
        $this->assertTrue(count(array_diff($result, $data)) == 0, 'deactivated accounts and check data correct');
        
    }
}
?>
