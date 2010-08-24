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
 * This example hooks implementation shows how to implement a a custom User Id 
 * generator
 *
 * @package phpLDAPadmin
 * @subpackage Functions
 */

// basic check that user is logged in
if (!$_SESSION[APPCONFIG]) {
    return false;
}

/**
 * The userid_algorithm function is called from within the admin_form to determine the name
 * of a registered User Id algorithm
 *
 * No arguments are passed to userid_algorithm_label.
 */
function userid_alg_01_userid_algorithm_label() {
	$args = func_get_args();

	return array('name' => 'userid_alg_01_userid_algorithm', 'title' => _('Simple User Id Generator'));
}
add_hook('userid_algorithm_label','userid_alg_01_userid_algorithm_label');



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
 */
function userid_alg_01_passwd_algorithm() {
    list($server, $udiconfig, $account) = func_get_args();
    
//    $account['mlepUsername'] = $account['mlepUsername'] . "wahoo";
//    var_dump($account);
    
    return $account;
}
add_hook('userid_algorithm','userid_alg_01_passwd_algorithm');

?>
