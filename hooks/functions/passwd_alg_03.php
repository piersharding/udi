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
function passwd_alg_03_passwd_algorithm_label() {
	$args = func_get_args();

    return array('name' => 'passwd_alg_03_passwd_algorithm', 
                 'title' => _('Randomly generate complex passwords'),
                  'description' => 'generate passwords based on a specified length from all visible ascii characters available.<br/>  
                  The parameter string is used to pass options - example: length=6<br/>
                  '
    );
}
add_hook('passwd_algorithm_label','passwd_alg_03_passwd_algorithm_label');


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
function passwd_alg_03_passwd_algorithm() {
    $args = func_get_args();
    global $request;
    list($server, $udiconfig, $account, $parameter) = func_get_args();
    
    // parse out parameter string
    $length = 10;
    $parm = explode('=', $parameter);
    if (count($parm) == 2) {
        switch (strtolower($parm[0])) {
            case 'length':
                $length = (int)($parm[1]);
                break;
            default:
                $request['page']->error(_('Invalid parameters for password generator: ').$parameter, _('processing'));
                break;
        }
    }
    if ($length > 20 || $length < 3) {
        $length = 10;
    }
    // alternative password generator
    return substr(md5(rand().rand()), 0, $length);
}
add_hook('passwd_algorithm','passwd_alg_03_passwd_algorithm');

?>
