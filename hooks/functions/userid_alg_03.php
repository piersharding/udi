<?php
/**
 * An example of a User Id generator
 *
 * Functions should return true on success and false on failure.
 * If a function returns false it will trigger the rollback to be executed.
 *
 * @author Piers Harding
 * @package phpLDAPadmin
 */

/**
 * Generates the User Id based on the LastName concatenated with the FirstName
 * initials.
 *
 * @package phpLDAPadmin
 * @subpackage Functions
 */

/**
 * The userid_algorithm function is called from within the admin_form to determine the name
 * of a registered User Id algorithm
 *
 * No arguments are passed to userid_algorithm_label.
 */
function userid_alg_03_userid_algorithm_label() {
	$args = func_get_args();

	return array('name' => 'userid_alg_03_userid_algorithm', 'title' => _('Compose from CSV columns'),
	             'description' => 'generates the User Id based on an edit mask created from
  the CSV column headings similar to sprintf. eg:
  <span class=\'tiny\'>STU%[YearGroup]%[mlepFirstName]%[mlepLastName]</span> would create an User Id of stu10daisyduck.<br/>
 There is a special value %[Initials] that substitutes in the intials of the users first name.  
 %[UniqueNo] substitutes an auto-generated unique number.
 <br/>
 All substitutions can be given an optional length specfier which will truncate accordingly eg: <span class=\'tiny\'>%[Initials].%[mlepLastName:3].%[UniqueNo:5]</span>
  would give d.duc.00001.');
}
add_hook('userid_algorithm_label','userid_alg_03_userid_algorithm_label');


/**
 * The account_create_before function is called from within the udi_import Processor class
 * to allow the custom generation of User Id
 * It is expected that the result of this call will update mlepUsername on the 
 * import file structure, which will then be available to the mapping process for
 * distribution of the value to the directory
 *
 * Arguments that are passed are:
 *  - LDAP directory server object
 *  - UDIConfig object for access to complete UDI configuration settings
 *  - user record from file - array keyed by csv column headings
 *  - return the $account array if you want it modfied else false
 *  
 *  YOU MUST MODIFY mlepUsername to SET the NEW User Id !!!!
 *  
 *  This callback generates the User Id based on an edit mask created from
 *  the CSV column headings similar to sprintf. eg:
 *  STU%[YearGroup]%[mlepFirstName]%[mlepLastName]
 *  There is the special value %[Initials] that substitutes in the intials of 
 *  the users first name
 *  %[UniqueNo] - substituted next sequential number for userid generation
 *  Also need to consider legnth eg: put a length specifier on fields such as
 *  %[mlepFirstName:4] gives the first 4 chars of mlepFirstname.
 *  
 */
global $USERID_ALG_03_CACHE;
$USERID_ALG_03_CACHE = array();

function userid_alg_03_userid_algorithm() {
    list($server, $udiconfig, $account) = func_get_args();

    // Dont overwrite the proposed mlepUsername if it exists
    if (empty($account['mlepUsername'])) {
        $cfg = $udiconfig->getConfig();
        $uid = $cfg['userid_parameters'];
        // find the substitutions
        if (preg_match_all('/\%\[(.+?)\]/', $cfg['userid_parameters'], $matches)) {
            foreach ($matches[1] as $match) {
                $parts = explode(':', $match);
                $attr = array_shift($parts);
                $length = empty($parts) ? 0 : (int)array_shift($parts);
                $length = (int)$length;
                
                if (strtolower($attr) == 'uniqueno') {
                    $next = $udiconfig->nextNumber();
                    $value = $length > 0 ? sprintf("%0".$length."d", $next) : $next;
                }
                else if (strtolower($attr) == 'initials') {
                    $value = trim(preg_replace('/\s+/', ' ', $account['mlepFirstName']));
                    $value = implode('', array_map(create_function('$a', 'return substr($a, 0, 1);'), explode(' ', $value)));
                }
                else {
                    $value = isset($account[$attr]) ? $account[$attr] : '';
                    if ($length > 0) {
                        $value = substr($value, 0, $length);
                    }
                }
                $uid = preg_replace('/\%\['.preg_quote($match).'\]/', $value, $uid, 1);
            }
        }
        // now squash the result
        $uid = strtolower(preg_replace('/\s+/', '', $uid));
        if ($uid) {
            // determine uniqueness
            $counter = 0;
            $test = $uid;
            while (1) {
                if (!isset($USERID_ALG_03_CACHE[$test])) {
                    $query = $server->query(array('base' => $udiconfig->getBaseDN(), 'filter' => "(mlepUsername=$test)", 'attrs' => array('dn')), 'user');
                    if (empty($query)) {
                        $uid = $test;
                        break;
                    }
                }
                $counter++;
                $test = $uid . $counter;
            }
            $account['mlepUsername'] = $uid;
            $USERID_ALG_03_CACHE[$uid] = $uid;
            return $account;
        }
    }
    return false;
}
add_hook('account_create_before','userid_alg_03_userid_algorithm');

?>