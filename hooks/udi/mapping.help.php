<a href="cmd.php?cmd=udi&amp;udi_nav=mapping&amp;server_id=<?php echo $app['server']->getIndex();?>"
 title="Configure the UDI" onclick="return ajDISPLAY('BODY','cmd=udi&amp;udi_nav=mapping&amp;server_id=1&amp;','Configuration');"><h3>Help: Mapping</h3></a>
<p>
</p>
<p>
<h2>Import File Mappings</h2>  
This is the UDI import file to User Directory mapping utility.  Here, you can specify the names that relate to the source columns of the import CSV file, and then define target LDAP attributes that exist in the connected LDAP directories schema, to import the values to.
  Multiple import targets can be specified, and the order in which the source columns are specfied do not matter.
  <br/>
  Any import columns not specified in the map, default to mapping to the LDAP attribute with the same name.
  
  <br/>
  Special source fields can be specified that are calculated based on previously defined columns.  This works on a field substitution syntax.  
  The following defines an email address based on a static character string with the mlepUsername embedded in it:
  <span class=\'tiny\'>%[mlepFirstName]@hogwarts.school.nz</span> would create an email address of daisy@hogwarts.school.nz where mlepUsername = 'daisy'.<br/>
 All substitutions can be given an optional length specfier which will truncate accordingly eg: <span class=\'tiny\'>%[mlepfirstName].%[mlepLastName:3]</span>
  would give daisy.duc <br/>
  There is a special case for mlepGroupMembership and mlepHomeGroup which are multi-value colums.  This can be addressed as:<br/> <span class=\'tiny\'>%[mlepGroupMembership:1:3]</span> where 1 is the item in the multi-value to choose, and 3 is the length specifier.
  </p>
<p>
<h2>Group Membership Mappings</h2>
Groups are mapped from the tags specfied in the mlepMembership, mlepRole, and mlepHomeGroup fields.  The tags are mapped to one or more Directory group containers.
</p>
