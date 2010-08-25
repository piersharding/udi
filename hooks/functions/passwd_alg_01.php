<?php
/**
 * An example of a pasword generator
 *
 * Functions should return true on success and false on failure.
 * If a function returns false it will trigger the rollback to be executed.
 *
 * @author Piers Harding
 * @package phpLDAPadmin
 */

/**
 * This example hooks implementation shows how to implement a a custom password 
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
 * The passwd_algorithm function is called from within the admin_form to determine the name
 * of a registered password algorithm
 *
 * No arguments are passed to passwd_algorithm_label.
 */
function passwd_alg_01_passwd_algorithm_label() {
	$args = func_get_args();

    return array('name' => 'passwd_alg_01_passwd_algorithm', 'title' => _('Set constant password'));
}
add_hook('passwd_algorithm_label','passwd_alg_01_passwd_algorithm_label');


/**
 * The passwd_algorithm function is called from within the admin_form to determine the name
 * of a registered password algorithm
 *
 * Arguments that are passed are:
 *  - LDAP directory server object
 *  - UDIConfig object for access to complete UDI configuration settings
 *  - user record from file - array keyed by csv column headings
 *  - configured password parameter value
 */
function passwd_alg_01_passwd_algorithm() {
    $args = func_get_args();
    list($server, $udiconfig, $account, $parameter) = func_get_args();
    
    return $parameter;
}
add_hook('passwd_algorithm','passwd_alg_01_passwd_algorithm');

?>
