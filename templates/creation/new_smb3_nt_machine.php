<?php
// $Header: /cvsroot/phpldapadmin/phpldapadmin/templates/creation/new_smb3_nt_machine.php,v 1.7 2004/12/16 22:59:50 uugdave Exp $

// Common to all templates
$container = $_POST['container'];
$server_id = $_POST['server_id'];


// Unique to this template
$step = 1;
if( isset($_POST['step']) )
    $step = $_POST['step'];

// get the available domains (see template_connfig.php for customization)
$samba3_domains = get_samba3_domains();

$default_gid_number = 30000;
$default_acct_flags = '[W          ]';
$default_home_dir = '/dev/null';

check_server_id( $server_id ) or pla_error( "Bad server_id: " . htmlspecialchars( $server_id ) );
have_auth_info( $server_id ) or pla_error( "Not enough information to login to server. Please check your configuration." );

if( get_schema_objectclass( $server_id, 'sambaSamAccount' ) == null )
	pla_error( "Your LDAP server does not have schema support for the sambaSamAccount objectClass. Cannot continue." );

?>
<script language="javascript">

	function autoFillSambaRID( form ){
		var sambaSID;
		var uidNumber;

		uidNumber = form.uid_number.value;
		sambaSID = (2 * uidNumber) + 1000;

		form.samba3_rid.value = sambaSID;
	}
</script>


<center><h2>New Samba 3  NT Machine</h2></center>

<?php if( $step == 1 ) { ?>

<form action="creation_template.php" method="post" name="machine_form">
<input type="hidden" name="step" value="2" />
<input type="hidden" name="server_id" value="<?php echo $server_id; ?>" />
<input type="hidden" name="template" value="<?php echo htmlspecialchars( $_POST['template'] ); ?>" />

<center>
<table class="confirm">
<tr class="spacer"><td colspan="3"></td></tr>
<tr>
	<td><img src="images/server.png" /></td>
	<td class="heading">Machine Name:</td>
	<td><input type="text" name="machine_name" value="" /> <small>(hint: don't include "$" at the end)</small></td>
</tr>
<tr>
	<td></td>
	<td class="heading">UID Number:</td>
	<td><input type="text" name="uid_number" value="" onChange="autoFillSambaRID(this.form);" /></td>
</tr>
<tr>
	<td></td>
	<td class="heading">Sanba Sid:</td>
	<td><select name="samba3_domain_sid">
        <?php foreach($samba3_domains as $samba3_domain) ?>
        <option value="<?php echo $samba3_domain['sid'] ?>"><?php echo $samba3_domain['sid'] ?></option>
        </select> - <input type="text" name="samba3_rid" id="samba3_rid" value="" size="7"/></td>

</tr>
<tr>
	<td></td>
	<td class="heading">Container:</td>
	<td><input type="text" size="40" name="container" value="<?php echo htmlspecialchars( $container ); ?>" />
		<?php draw_chooser_link( 'machine_form.container' ); ?>
	</td>
</tr>
<tr>
	<td colspan="3" style="text-align: center"><br /><input type="submit" value="Proceed &gt;&gt;" />
		<br /><br /><br /><br /><br /><br /></td>
</tr>

<tr class="spacer"><td colspan="3"></td></tr>

<tr>
	<td colspan="3">
		This will create a new NT machine with:<br />
		<small>
		<ul>	
			<li>gidNumber <b><?php echo htmlspecialchars( $default_gid_number ); ?></b></li>
			<li>acctFlags <b><?php echo str_replace(' ', "&nbsp;", htmlspecialchars($default_acct_flags)); ?></b></li>
			<li>in container <b><?php echo htmlspecialchars( $container ); ?></b></li>
		</ul>
		To change these values, edit the template file: 
			<code>templates/creation/new_nt_machine.php</code><br />
		Note: You must have the samba schema installed on your LDAP server.
		</small>
	</td>
</tr>

</table>
</center>
</form>

<?php } elseif( $step == 2 ) {

	$machine_name = trim( $_POST['machine_name'] );
	$uid_number = trim( $_POST['uid_number'] );
        $samba3_domain_sid =  trim( $_POST['samba3_domain_sid'] );
	$samba3_computer_rid = trim( $_POST['samba3_rid'] );

	dn_exists( $server_id, $container ) or
		pla_error( "The container you specified (" . htmlspecialchars( $container ) . ") does not exist. " .
	       		       "Please go back and try again." );
	?>

	<form action="create.php" method="post">
	<input type="hidden" name="server_id" value="<?php echo $server_id; ?>" />
	<input type="hidden" name="new_dn" value="<?php echo htmlspecialchars( 'uid=' . $machine_name . '$,' . $container ); ?>" />

	<!-- ObjectClasses  -->
	<?php $object_classes = rawurlencode( serialize( array( 'top', 'sambaSamAccount', 'posixAccount', 'account' ) ) ); ?>

	<input type="hidden" name="object_classes" value="<?php echo $object_classes; ?>" />
		
	<!-- The array of attributes/values -->
	<input type="hidden" name="attrs[]" value="gidNumber" />
		<input type="hidden" name="vals[]" value="<?php echo htmlspecialchars($default_gid_number);?>" />
	<input type="hidden" name="attrs[]" value="uidNumber" />
		<input type="hidden" name="vals[]" value="<?php echo htmlspecialchars($uid_number);?>" />
	<input type="hidden" name="attrs[]" value="uid" />
		<input type="hidden" name="vals[]" value="<?php echo htmlspecialchars($machine_name . '$');?>" />
	<input type="hidden" name="attrs[]" value="sambaSid" />
		<input type="hidden" name="vals[]" value="<?php echo htmlspecialchars($samba3_domain_sid."-".$samba3_computer_rid);?>" />
	<input type="hidden" name="attrs[]" value="sambaAcctFlags" />
		<input type="hidden" name="vals[]" value="<?php echo htmlspecialchars($default_acct_flags);?>" />
	<input type="hidden" name="attrs[]" value="cn" />
		<input type="hidden" name="vals[]" value="<?php echo htmlspecialchars($machine_name);?>" />
	<input type="hidden" name="attrs[]" value="homeDirectory" />
		<input type="hidden" name="vals[]" value="<?php echo htmlspecialchars($default_home_dir);?>" />

	<center>
	Realy create this new Samba machine?<br />
	<br />
	<table class="confirm">
	<tr class="even"><td>Name</td><td><b><?php echo htmlspecialchars($machine_name); ?></b></td></tr>
	<tr class="odd"><td>UID number</td><td><b><?php echo htmlspecialchars($uid_number); ?></b></td></tr>
	<tr class="even"><td>SambaSid</td><td><b><?php echo htmlspecialchars($samba3_domain_sid."-".$samba3_computer_rid); ?></b></td></tr>
	<tr class="odd"><td>Container</td><td><b><?php echo htmlspecialchars( $container ); ?></b></td></tr>
	</table>
	<br /><input type="submit" value="Create Machine" />
	</center>

<?php } ?>