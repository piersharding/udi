<a href="cmd.php?cmd=udi&amp;udi_nav=admin&amp;server_id=<?php echo $app['server']->getIndex();?>"
 title="Configure the UDI UserId & Passwds" onclick="return ajDISPLAY('BODY','cmd=udi&amp;udi_nav=userpass&amp;server_id=1&amp;','UserId & Passwd');"><h3>Help: User Ids & Passwords</h3></a>
 
 <?php 
 $result = udi_run_hook('userid_algorithm_label',array());
$userid_help = array();
if (!empty($result)) {
    foreach ($result as $algo) {
        $userid_help[]= htmlspecialchars($algo['title']).': '.$algo['description'];
    }
}                
$result = udi_run_hook('passwd_algorithm_label',array());
$passwd_help = array();
if (!empty($result)) {
    foreach ($result as $algo) {
        $passwd_help[]= htmlspecialchars($algo['title']).': '.$algo['description'];
    }
}                
$result = udi_run_hook('passwd_policy_algorithm_label',array());
$passwd_policy_help = array();
if (!empty($result)) {
    foreach ($result as $algo) {
        $passwd_policy_help[]= htmlspecialchars($algo['title']).': '.$algo['description'];
    }
}                
?>
<p>
</p>
<p>  
This is the UDI administration panel for User Ids and Passwords.  Here, you can specify the various parameters that control aspects of User Id and Password generation.
</p>
<p>
<h4>User Id Generation</h4>
User Id Algorithm: enables selection of an algorithm to generate a new User Id (only for user creation).  
This populates the mlepUsername field, and will not run if there is allready a value present.  The User Id generation parameters field is passed through where an algorithm requires configured input.
<br/>
<p>
unselected: no User Id generation.
</p>
<?php          
foreach ($userid_help as $algo) {
    echo '<p>'.$algo.'</p>';
}
?>
</p>
<p>
<h4>Password Generation</h4>
Similar to User Id generation - Password algorithm controls the generation of passwords.
<br/>
<?php          
foreach ($passwd_help as $algo) {
    echo '<p>'.$algo.'</p>';
}
?>
</p>
<p>
<h4>Kiosk Password Policy</h4>
Password Policy algorithm controls the acceptance for new passwords.
<br/>
<?php          
foreach ($passwd_policy_help as $algo) {
    echo '<p>'.$algo.'</p>';
}
?>
</p>