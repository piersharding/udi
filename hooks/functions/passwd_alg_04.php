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

/**
 * The passwd_algorithm function is called from within the userpass_form to determine the name
 * of a registered password algorithm
 *
 * No arguments are passed to passwd_algorithm_label.
 */
function passwd_alg_04_passwd_algorithm_label() {
	$args = func_get_args();

    return array('name' => 'passwd_alg_04_passwd_algorithm', 
                 'title' => _('Passwords based on dictionary'),
                  'description' => 'generate passwords based on a dictionary of 5-6 char words, wit random substitution of numbers and uppercase letters.<br/>  
                  The parameter string does not implement any features<br/>
                  '
    );
}
add_hook('passwd_algorithm_label','passwd_alg_04_passwd_algorithm_label');

global $PASSWD_DICTIONARY;
$PASSWD_DICTIONARY = preg_grep('/\w/', explode("\n", file_get_contents(dirname(__FILE__).'/passwd_alg_04_dictionary.txt')));


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
function passwd_alg_04_passwd_algorithm() {
    $args = func_get_args();
    global $request, $PASSWD_DICTIONARY;
    list($server, $udiconfig, $account, $parameter) = func_get_args();
    
    // grab a word at random
    $set = count($PASSWD_DICTIONARY) - 1;
    $word = $PASSWD_DICTIONARY[rand(0, $set)];
    
    // randomly ucase one letter
    $pos = rand(0, (strlen($word) - 1));
    $password = str_split(strtolower($word));
    $password[$pos] = strtoupper($password[$pos]);
    
    // randomly insert a number between 00 and 99
    $pos = rand(0, strlen($word));
    $number = sprintf('%02d', rand(0, 99));
    array_splice($password, $pos, 0, array($number));
    
    // put the password back together
    return implode('', $password);
}
add_hook('passwd_algorithm','passwd_alg_04_passwd_algorithm');

?>
