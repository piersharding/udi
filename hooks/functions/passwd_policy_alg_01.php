<?php
/**
 * An example of a pasword policy generator
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

/**
 * The passwd_policy_algorithm_label function is called from within the userpass_form to determine the name
 * of a registered password policy algorithm
 *
 * No arguments are passed to passwd_policy_algorithm_label.
 */
function passwd_alg_01_passwd_policy_algorithm_label() {
	$args = func_get_args();

    return array('name' => 'passwd_alg_01_passwd_policy_algorithm', 'title' => _('Check against regular expression'),
                 'description' => 'uses the input parameter as a regular expression for testing the validity of a new password.
                  The regular expression is for matching characters that are allowed.<br/>
                  The default policy parameter regular expression requires a minimum of 3 characters, and allows most visible key board characters.');
}
add_hook('passwd_policy_algorithm_label','passwd_alg_01_passwd_policy_algorithm_label');


/**
 * The passwd_policy_algorithm function is called from the kiosk to validate a new password
 *
 * Arguments that are passed are:
 *  - LDAP directory server object
 *  - UDIConfig object for access to complete UDI configuration settings
 *  - new password value
 *  - configured password parameter value
 *  
 *  returns true on valid password
 */
function passwd_alg_01_passwd_policy_algorithm() {
    $args = func_get_args();
    list($server, $udiconfig, $password, $parameter) = func_get_args();
    
    // test the regular expresion - if it matches then it is a valid 
    // password
    if (preg_match($parameter, $password)) {
        return true;
    }
    return false;
}
add_hook('passwd_policy_algorithm','passwd_alg_01_passwd_policy_algorithm');

?>
