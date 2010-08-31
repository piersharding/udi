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

class MoveGroupsTest extends UDITestBase {

    public function testMoveGroups1_moving_groups_posix() {
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
        
        // process moving groups
        $file = getcwd().'/data/udi_import_move_groups.csv';
        $result = self::cron_process($file);
        
        // check all groups are populated correctly
        foreach (array('Board of trustees posix' => 1, 'LMS Access posix' => 7, 'Parents posix' => 1, 'Staff posix' => 0, 'Students posix' => 7) as $group => $cnt) {
            $result = preg_grep('/\w/', self::ldap_search('cn='.$group.',ou=Services, dc=example,dc=com', '(objectclass=posixGroup)', 'memberUid'));
            $this->assertTrue($UDI_RC == true, 'Search for posix group succeeded'); // search succeeded
            array_shift($result); // git rid of dn:
            $this->assertTrue(count($result) == $cnt, 'posix Group('.$group.') is NOT empty of memberUids'); // failed to find any members in groups
        }
    }

    public function testMoveGroups2_moving_groups_member() {
        global $UDI_RC;
        // test that groups move correctly
        
        // load UDI Config
        $file = getcwd().'/ldap/udiconfig_member.ldif';
        $result = self::ldap_add($file);
        
        // we start with no people
        $this->empty_accounts();

        // check all groups are empty
        $this->empty_posix_groups();
        
        // process basic users
        echo "Process basic groupOfNames member user import\n";
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
        foreach (array('Board of trustees member' => 1, 'LMS Access member' => 6, 'Parents member' => 1, 'Staff member' => 3, 'Students member' => 6) as $group => $cnt) {
            $result = preg_grep('/\w/', self::ldap_search('cn='.$group.',ou=Services, dc=example,dc=com', '(objectclass=groupOfNames)', 'member'));
            $this->assertTrue($UDI_RC == true, 'Search for posix group succeeded'); // search succeeded
            array_shift($result); // git rid of dn:
            $this->assertTrue(count($result) == ($cnt + 1), 'member Group('.$group.') is NOT empty of members'); // failed to find any members in groups
        }
                
        // process moving groups
        $file = getcwd().'/data/udi_import_move_groups.csv';
        $result = self::cron_process($file);
        
        // check all groups are populated correctly
        foreach (array('Board of trustees member' => 1, 'LMS Access member' => 7, 'Parents member' => 1, 'Staff member' => 0, 'Students member' => 7) as $group => $cnt) {
            $result = preg_grep('/\w/', self::ldap_search('cn='.$group.',ou=Services, dc=example,dc=com', '(objectclass=groupOfNames)', 'member'));
            $this->assertTrue($UDI_RC == true, 'Search for posix group succeeded'); // search succeeded
            array_shift($result); // git rid of dn:
            $this->assertTrue(count($result) == ($cnt + 1), 'member Group('.$group.') is NOT empty of members'); // failed to find any members in groups
        }
    }
    
}
?>
