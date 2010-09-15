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

class BasicsTest extends UDITestBase {

    public function testBasics1_load_new_accounts_posix_groups() {
        global $UDI_RC;

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
        $result = self::ldap_search('ou=New People, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))', 'dn');
        $result = preg_grep('/\w/', $result);
        $this->assertTrue(count($result) == 10, '10 accounts have been created');
        
        $result = preg_grep('/^userPassword\:/', self::ldap_search('ou=New People, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))'), PREG_GREP_INVERT);
        $this->assertTrue($UDI_RC == true, 'serach for user accounts succeeded'); // search succeeded
        $this->assertTrue(count($result) == 234, 'account search has the right number of records'); 
        $data = self::get_data('./data/01_10_accounts.ldif');
        $this->assertTrue(count($data) == 234, 'comparison data found'); 
        $this->assertTrue(count(array_diff($result, $data)) == 0, 'new accounts and check data correct');
//        $this->assertRegExp('/teststring/', $result);

        // check all groups are populated correctly
        foreach (array('Board of trustees posix' => 1, 'LMS Access posix' => 6, 'Parents posix' => 1, 'Staff posix' => 3, 'Students posix' => 6) as $group => $cnt) {
            $result = preg_grep('/\w/', self::ldap_search('cn='.$group.',ou=Services, dc=example,dc=com', '(objectclass=posixGroup)', 'memberUid'));
            $this->assertTrue($UDI_RC == true, 'Search for posix group succeeded'); // search succeeded
            array_shift($result); // git rid of dn:
            $this->assertTrue(count($result) == $cnt, 'posix Group('.$group.') is NOT empty of memberUids'); // failed to find any members in groups
        }

        // have the groups been populated correctly
        $this->check_posix_groups('./data/01_full_posix_groups.ldif');

        // deactivate accounts - but still remain in groups - check labeledURI
        echo "deactivate accounts\n";
        $this->empty_deactivated_accounts();
        self::cron_deactivate();
        $this->empty_accounts();
        // deactivated accounts
        $result = preg_grep('/^userPassword\:/', self::ldap_search('ou=Deactivated Accounts, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))'), PREG_GREP_INVERT);
        $this->assertTrue($UDI_RC == true, 'serach for user accounts succeeded'); // search succeeded
        $this->assertTrue(count($result) == 254, 'account search has the right number of records'); 
        $data = self::get_data('./data/01_10_deactivated_accounts.ldif');
        $this->assertTrue(count($data) == 254, 'comparison data found'); 
        $this->assertTrue(count(array_diff($result, $data)) == 0, 'new accounts and check data correct');
        // check all groups are populated correctly
        foreach (array('Board of trustees posix' => 1, 'LMS Access posix' => 6, 'Parents posix' => 1, 'Staff posix' => 3, 'Students posix' => 6) as $group => $cnt) {
            $result = preg_grep('/\w/', self::ldap_search('cn='.$group.',ou=Services, dc=example,dc=com', '(objectclass=posixGroup)', 'memberUid'));
            $this->assertTrue($UDI_RC == true, 'Search for posix group succeeded'); // search succeeded
            array_shift($result); // git rid of dn:
            $this->assertTrue(count($result) == $cnt, 'posix Group('.$group.') is NOT empty of memberUids'); // failed to find any members in groups
        }

        // have the groups been populated correctly
        $this->check_posix_groups('./data/01_full_posix_groups.ldif');        
        // reactivate accounts - check again
        echo "reactivate accounts\n";
        self::cron_reactivate();
        $this->empty_deactivated_accounts();
        $result = self::ldap_search('ou=New People, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))', 'dn');
        $result = preg_grep('/\w/', $result);
        $this->assertTrue(count($result) == 10, '10 accounts have been created');
        
        $result = preg_grep('/^userPassword\:/', self::ldap_search('ou=New People, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))'), PREG_GREP_INVERT);
        $this->assertTrue($UDI_RC == true, 'serach for user accounts succeeded'); // search succeeded
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

        // have the groups been populated correctly
        $this->check_posix_groups('./data/01_full_posix_groups.ldif');
        
        // deactivate
        echo "deactivate accounts again\n";
        self::cron_deactivate();
        $this->empty_accounts();
        echo "delete accounts\n";
        self::cron_delete();
        
        // delete - check all is gone - including groups
        $this->empty_accounts();
        $this->empty_posix_groups();
        
    }
    
    
    public function testBasics2_load_new_accounts_member_groups() {
        global $UDI_RC;
        // test same again but for member style groups

        // load UDI Config
        $file = getcwd().'/ldap/udiconfig_member.ldif';
        $result = self::ldap_add($file);
        
        // we start with no people
        $this->empty_accounts();

        // check all groups are empty
        $this->empty_member_groups();
        
        // process basic users
        echo "Process basic groupOfNames member user import\n";
        $file = getcwd().'/data/udi_import.csv';
        $result = self::cron_process($file);

        // we start with number of people
        $result = self::ldap_search('ou=New People, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))', 'dn');
        $result = preg_grep('/\w/', $result);
        $this->assertTrue(count($result) == 10, '10 accounts have been created');
        
        $result = preg_grep('/^userPassword\:/', self::ldap_search('ou=New People, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))'), PREG_GREP_INVERT);
        $this->assertTrue($UDI_RC == true, 'serach for user accounts succeeded'); // search succeeded
        $this->assertTrue(count($result) == 234, 'account search has the right number of records'); 
        $data = self::get_data('./data/01_10_accounts.ldif');
        $this->assertTrue(count($data) == 234, 'comparison data found'); 
        $this->assertTrue(count(array_diff($result, $data)) == 0, 'new accounts and check data correct');

        // check all groups are populated correctly
        foreach (array('Board of trustees member' => 1, 'LMS Access member' => 6, 'Parents member' => 1, 'Staff member' => 3, 'Students member' => 6) as $group => $cnt) {
            $result = preg_grep('/\w/', self::ldap_search('cn='.$group.',ou=Services, dc=example,dc=com', '(objectclass=groupOfNames)', 'member'));
            $this->assertTrue($UDI_RC == true, 'Search for posix group succeeded'); // search succeeded
            array_shift($result); // git rid of dn:
            $this->assertTrue(count($result) == ($cnt + 1), 'member Group('.$group.') is NOT empty of members'); // failed to find any members in groups
        }

        // have the groups been populated correctly
        $this->check_member_groups('./data/01_full_member_groups.ldif');

        // deactivate accounts - but still remain in groups - check labeledURI
        echo "deactivate accounts\n";
        $this->empty_deactivated_accounts();
        self::cron_deactivate();
        $this->empty_accounts();
        // deactivated accounts
        $result = preg_grep('/^userPassword\:/', self::ldap_search('ou=Deactivated Accounts, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))'), PREG_GREP_INVERT);
        $this->assertTrue($UDI_RC == true, 'serach for user accounts succeeded'); // search succeeded
        $this->assertTrue(count($result) == 254, 'account search has the right number of records'); 
        $data = self::get_data('./data/01_10_deactivated_accounts.ldif');
        $this->assertTrue(count($data) == 254, 'comparison data found'); 
        $this->assertTrue(count(array_diff($result, $data)) == 0, 'new accounts and check data correct');
        // check all groups are populated correctly
        foreach (array('Board of trustees member' => 1, 'LMS Access member' => 6, 'Parents member' => 1, 'Staff member' => 3, 'Students member' => 6) as $group => $cnt) {
            $result = preg_grep('/\w/', self::ldap_search('cn='.$group.',ou=Services, dc=example,dc=com', '(objectclass=groupOfNames)', 'member'));
            $this->assertTrue($UDI_RC == true, 'Search for posix group succeeded'); // search succeeded
            array_shift($result); // git rid of dn:
            $this->assertTrue(count($result) == ($cnt + 1), 'member Group('.$group.') is NOT empty of members'); // failed to find any members in groups
        }

        // have the groups been populated correctly
        $this->check_member_groups('./data/01_full_member_groups.ldif');

        // reactivate accounts - check again
        echo "reactivate accounts\n";
        self::cron_reactivate();
        $this->empty_deactivated_accounts();
        $result = self::ldap_search('ou=New People, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))', 'dn');
        $result = preg_grep('/\w/', $result);
        $this->assertTrue(count($result) == 10, '10 accounts have been created');
        
        $result = preg_grep('/^userPassword\:/', self::ldap_search('ou=New People, dc=example,dc=com', '(&(objectclass=mlepperson)(uid=*))'), PREG_GREP_INVERT);
        $this->assertTrue($UDI_RC == true, 'serach for user accounts succeeded'); // search succeeded
        $this->assertTrue(count($result) == 234, 'account search has the right number of records'); 
        $data = self::get_data('./data/01_10_accounts.ldif');
        $this->assertTrue(count($data) == 234, 'comparison data found'); 
        $this->assertTrue(count(array_diff($result, $data)) == 0, 'new accounts and check data correct');

        // check all groups are populated correctly
        foreach (array('Board of trustees member' => 1, 'LMS Access member' => 6, 'Parents member' => 1, 'Staff member' => 3, 'Students member' => 6) as $group => $cnt) {
            $result = preg_grep('/\w/', self::ldap_search('cn='.$group.',ou=Services, dc=example,dc=com', '(objectclass=groupOfNames)', 'member'));
            $this->assertTrue($UDI_RC == true, 'Search for posix group succeeded'); // search succeeded
            array_shift($result); // git rid of dn:
            $this->assertTrue(count($result) == ($cnt + 1), 'member Group('.$group.') is NOT empty of members'); // failed to find any members in groups
        }

        // have the groups been populated correctly
        $this->check_member_groups('./data/01_full_member_groups.ldif');
        
        // deactivate
        echo "deactivate accounts again\n";
        self::cron_deactivate();
        $this->empty_accounts();
        echo "delete accounts\n";
        self::cron_delete();
        
        // delete - check all is gone - including groups
        $this->empty_accounts();
        $this->empty_member_groups();
        
    }
}
?>
