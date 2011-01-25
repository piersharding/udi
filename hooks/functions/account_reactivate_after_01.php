<?php
/**
 * An example of an account reactivate callback
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
 * The account_reactivate_after function is called from within the udi_import Processor class
 * to allow access to each account after it has been reactivated
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

function account_reactivate_after_01() {
    global $request, $account_reactivate_after_logging;
    list($server, $udiconfig, $account) = func_get_args();
    $fields = array('uid', 'sn', 'givenName', 'l', 'o', 'cn', 'mail', 'labeledURI');
    $report = array();
    foreach ($account as $field => $values) {
        if (in_array($field, $fields) || preg_match('/^mlep/', $field)) {
            if (is_array($values)) { 
                $report[$field] = implode(",", $values);
            }
            else {
                $report[$field] = $values;
            }
        }
    }
    
    $request['page']->log_to_file('Users Reactivated', preg_replace('/\n/', '', var_export($report, true)));
    return true;
}
add_hook('account_reactivate_after','account_reactivate_after_01');

?>
