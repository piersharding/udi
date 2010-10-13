<?php
/**
 * An example of a Posix uidNumber generator
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

// disable this plugin - comment out to enable
//return false;


/**
 * The account_create_before function is called from within the udi_import Processor class
 * to allow the custom generation of User Id
 * It is expected that the result of this call will update uidNumber on the 
 * import file structure, which will then be available to the mapping process for
 * distribution of the value to the directory
 *
 * Arguments that are passed are:
 *  - LDAP directory server object
 *  - UDIConfig object for access to complete UDI configuration settings
 *  - user record from file - array keyed by csv column headings
 *  - return the $account array if you want it modfied else false
 *  
 */
global $POSIX_NEXT_UID;
$POSIX_NEXT_UID = false;

function posix_uidNumber_generator_01() {
    list($server, $udiconfig, $account) = func_get_args();
    global $POSIX_NEXT_UID;
    
    // do nothing if uidNumber field doesn't exist
    if (!isset($account['uidNumber'])) {
        return false;
    }

    // if we allready have one except for 99999 the bail
    if (!empty($account['uidNumber']) && $account['uidNumber'] != 99999) {
        return false;
    }
    
    if (!$POSIX_NEXT_UID) {
        // find the current high water mark
       $query = $server->query(array('base' => $udiconfig->getBaseDN(), 'filter' => "(uidNumber=*)", 'attrs' => array('uidNumber')), 'user');
        $uids = array();
        foreach ($query as $dn => $data) {
            if (isset($data['uidnumber']) && !empty($data['uidnumber'])) {
                foreach ($data['uidnumber'] as $uid) {
                    $uids[]= $uid;
                }
            }
        }
        $uids = array_unique($uids);
        sort($uids);
        if (!empty($uids)) {
            $POSIX_NEXT_UID = (int)array_pop($uids);
        }
        else {
            $POSIX_NEXT_UID = 10000;
        }
    }
    $POSIX_NEXT_UID++;
    
    $account['uidNumber'] = $POSIX_NEXT_UID;
    return $account;
}
add_hook('account_create_before','posix_uidNumber_generator_01');

?>
