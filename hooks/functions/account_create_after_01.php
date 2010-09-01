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
 * The account_create_after function is called from within the udi_import Processor class
 * to allow access to each account after it has been created
 *
 * Arguments that are passed are:
 *  - LDAP directory server object
 *  - UDIConfig object for access to complete UDI configuration settings
 *  - user record from file - array keyed by csv column headings
 *  - there is no handling of return values
 *  
 *  This callback is part of the logging mechanism, so is part of core
 *  
 */

function account_create_after_01() {
    global $request, $account_create_after_logging;
    list($server, $udiconfig, $account) = func_get_args();

    $request['page']->log_to_file('Users Created', preg_replace('/\n/', '', var_export($account, true)));
    return false;
}
add_hook('account_create_after','account_create_after_01');

?>