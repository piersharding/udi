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
function userid_alg_02_userid_algorithm_label() {
	$args = func_get_args();

	return array('name' => 'userid_alg_02_userid_algorithm', 'title' => _('Use <Lastname><Initials>'),
	             'description' => 'This generates a User Id based on the mlepLastName and Initials of the mlepFirstName.  
If this User Id already exists then the next unused sequential number is added as a suffix eg: Daisy Duck already exists and the generated Id is duckd1.');
}
add_hook('userid_algorithm_label','userid_alg_02_userid_algorithm_label');


/**
 * The useridd_algorithm function is called from within the udi_import Processor class
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
 *  This callback generates the User Id based on <LastName><Initials>
 *  
 */
function userid_alg_02_userid_algorithm() {
    list($server, $udiconfig, $account) = func_get_args();

    // Dont overwrite the proposed mlepUsername if it exists
    if (empty($account['mlepUsername'])) {
        $lastname = preg_replace('/\s+/', '', $account['mlepLastName']);
        $firstname = trim(preg_replace('/\s+/', ' ', $account['mlepFirstName']));
        $firstname = implode('', array_map(create_function('$a', 'return substr($a, 0, 1);'), explode(' ', $firstname)));
        $uid = strtolower($lastname.$firstname);
        if ($uid) {
            // determine uniqueness
            $counter = 0;
            $test = $uid;
            while (1) {
                $query = $server->query(array('base' => $udiconfig->getBaseDN(), 'filter' => "(mlepUsername=$test)", 'attrs' => array('dn')), 'login');
                if (empty($query)) {
                    $uid = $test;
                    break;
                }
                $counter++;
                $test = $uid . $counter;
            }
            $account['mlepUsername'] = $uid;
            return $account;
        }
    }
    return false;
}
add_hook('userid_algorithm','userid_alg_02_userid_algorithm');

?>