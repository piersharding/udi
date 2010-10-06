<a href="cmd.php?cmd=udi&amp;udi_nav=admin&amp;server_id=<?php echo $app['server']->getIndex();?>"
 title="Configure the UDI" onclick="return ajDISPLAY('BODY','cmd=udi&amp;udi_nav=admin&amp;server_id=1&amp;','Configuration');"><h3>Help: Configuration</h3></a>
 
<p>
For further help with installing and operating the UDI, please visit the <a href='http://gitorious.org/pla-udi/pages/Home'>Project Wiki</a>.
</p>
<p>  
This is the UDI administration and configuration console.  
Here, you can specify the various parameters that control aspects of user account creation, update, and deletion.
</p>
<p>
<h4>Search bases, and Default new accounts container</h4>
In most cases only a single search base should be supplied, and this search base will be the same as the default new accounts container
</p>
<p>
<h4>Which mlepRoles to Process</h4>
This enables the UDI to ignore the processing of specific roles so that the functioning of the UDI can be limited to portions of the user base.
</p>
<p>
<h4>Apply Strict Checks</h4>
Enable/disable strict checks on mlep values.  This can be used to enforce the mandatory values in the file, and do basic character type checking.  Any records 
that fail the checks will be logged and skipped.
</p>
<p>
<h4>DN Attribute</h4>
This should be 'cn' - most directory implementations name user entries like: cn=Daisy Duck,OU=New People,dc=example,dc=com
</p>
<p>
<h4>Map groups to containers</h4>
Based on the values found in the mlepGroupMembership column of the CSV file, you can map users to be created in different 
nodes/containers of the directory.  These containers must exists within one of the specified search bases.
</p>
<p>
<h4>Ignore attributes for updates</h4>
Some attributes do not make sense to update once an account has been created - these attributes can be specfied here.
  A typical attribute would be uid, or mlepUsername, or sAMAccountName.  These are usually static identifiers, and can be dynamically generated by the UDI.
   Note: an empty field in the CSV file is equivalent to 'delete this value', so any mapped attributes will be erased.
</p>
<p>
<h4>Move deleted to</h4>
Specify a container that all user accounts that are 'deleted' will be moved to.  The UDI performs a 'soft' delete where accounts are moved to a container, 
that should be outside the serach base for any applications using the Directory for authentication (this way, they will nto find the users moved there).
Additionally, Active Directory accounts are flagged as locked.
</p>