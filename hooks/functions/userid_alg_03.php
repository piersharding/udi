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
 %[UniqueNo] substitutes an auto-generated unique number.  %[RolePrefix] substitutes a 2 char label for each mlepRole type (eg. Student = st, TeachingStaff = ts).
 <br/>
 All substitutions can be given an optional length specfier which will truncate accordingly eg: <span class=\'tiny\'>%[Initials].%[mlepLastName:3].%[UniqueNo:5]</span>
  would give d.duc.00001.
  <br/>
  Additionally, the substitutions can be specified per mlepRole using the following syntax style:
  <span class=\'tiny\'>{Student=STU%[UniqueNo:5]; TeachiingStaff=%[Initials]%[mlepLastName]; NonTeachingStaff=%[Initials]%[mlepLastName]}</span>
  ');
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
 *  mlepRole split determiniation eg:
 *  {Student=STU%[UniqueNo:5];TeachiingStaff=%[Initials]%[mlepLastName];NonTeachingStaff=%[Initials]%[mlepLastName]}
 *
 *  * must have curly braces
 *  * each role delimited by ';'
 *  * role=<mask>
 */
global $USERID_ALG_03_CACHE;
$USERID_ALG_03_CACHE = array();

global $USERID_ALG_03_ROLES;
$USERID_ALG_03_ROLES = array('Student' => 'st', 'TeachingStaff' => 'ts', 'NonTeachingStaff' => 'nt', 'ParentCaregiver' => 'pc', 'Alumni' => 'al');


function userid_alg_03_userid_algorithm() {
    list($server, $udiconfig, $account) = func_get_args();
    global $USERID_ALG_03_CACHE, $USERID_ALG_03_ROLES;

    // Dont overwrite the proposed mlepUsername if it exists
    if (empty($account['mlepUsername'])) {
        $cfg = $udiconfig->getConfig();
        $uid = $cfg['userid_parameters'];

        // first check if this is mlepRole split
        $pattern = $cfg['userid_parameters'];
        if (preg_match('/^\{(.*?)\}$/', $pattern, $matches)) {
            // now find the particular role pattern
            $splits = explode(';', $matches[1]);
            $roles = array();
            foreach ($splits as $split) {
                if (preg_match('/=/', $split)) {
                    list($role, $mask) = explode('=', $split, 2);
                    $roles[$role] = $mask;
                }
            }
            // right - do we have a pattern for our role?
            if (isset($roles[$account['mlepRole']])) {
                $pattern = $roles[$account['mlepRole']];
                $uid = $pattern;
            }
            else {
                // the patterns must be broken - so jump out here
                return false;
            }
        }

        // find the substitutions
        if (preg_match_all('/\%\[(.+?)\]/', $pattern, $matches)) {
            foreach ($matches[1] as $match) {
                $parts = explode(':', $match);
                $attr = array_shift($parts);
                $length = empty($parts) ? 0 : (int)array_shift($parts);
                $length = (int)$length;

                // special UniqueNo tag
                if (strtolower($attr) == 'uniqueno') {
                    $next = $udiconfig->nextNumber();
                    $value = $length > 0 ? sprintf("%0".$length."d", $next) : $next;
                }
                // special RolePrefix tag
                else if (strtolower($attr) == 'roleprefix') {
                    $value = '';
                    if (isset($USERID_ALG_03_ROLES[$account['mlepRole']])) {
                        $value = $USERID_ALG_03_ROLES[$account['mlepRole']];
                    }
                }
                // special Initials tag
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


/**
 * intialise the userid cache between
 * the validation run and the live run
*/
function userid_alg_03_userid_algorithm_init() {
    list($server, $udiconfig) = func_get_args();
    global $USERID_ALG_03_CACHE;
    $USERID_ALG_03_CACHE = array();
}
add_hook('account_create_before_init','userid_alg_03_userid_algorithm_init');

?>
