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
 * The passwd_algorithm function is called from within the userpass_form to determine the name
 * of a registered password algorithm
 *
 * No arguments are passed to passwd_algorithm_label.
 */
function passwd_alg_02_passwd_algorithm_label() {
	$args = func_get_args();

    return array('name' => 'passwd_alg_02_passwd_algorithm', 
                 'title' => _('Randomly generate password'),
                  'description' => 'generate passwords based on a specified length and optional disallowed characters set.<br/>  
                  The parameter string is used to pass options - example: length=6,exclusions=01oOl
                  '
    );
}
add_hook('passwd_algorithm_label','passwd_alg_02_passwd_algorithm_label');


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
function passwd_alg_02_passwd_algorithm() {
    $args = func_get_args();
    global $request;
    list($server, $udiconfig, $account, $parameter) = func_get_args();
    
    // parse out parameter string
    $parts = explode(',', $parameter, 2);
    $length = 10;
    $exclusions = '';
    foreach ($parts as $part) {
        $parm = explode('=', $part);
        if (count($parm) == 2) {
            switch (strtolower($parm[0])) {
                case 'length':
                    $length = (int)($parm[1]);
                    break;
                case 'exclusions':
                    $exclusions = $parm[1];
                    break;
                default:
                    $request['page']->error(_('Invalid parameters for password generator: ').$parameter, _('processing'));
                    break;
            }
        }
    }
    if ($length > 10 || $length < 3) {
        $length = 10;
    }
    
    // This variable contains the list of allowable characters for the
    // password. Note that the number 0 and the letter 'O' have been
    // removed to avoid confusion between the two. The same is true
    // of 'I', 1, and l.
  
    // alternative password generator
    //    return substr(md5(rand().rand()), 0, $length);
    
    $allowable_characters = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    // apply the exclusions
    if (!empty($exclusions)) {
        $allowable_characters = preg_replace('/['.preg_quote($exclusions).']+?/', '', $allowable_characters);
    }
    
    // Zero-based count of characters in the allowable list:
    $len = strlen($allowable_characters) - 1;
    // Declare the password as a blank string.
    $pass = '';
    
    // Loop the number of times specified by $length.
    for ($i = 0; $i < $length; $i++) {
        // Each iteration, pick a random character from the
        // allowable string and append it to the password:
        $pass .= $allowable_characters[mt_rand(0, $len)];
    }
    
    return $pass;
}
add_hook('passwd_algorithm','passwd_alg_02_passwd_algorithm');

?>
