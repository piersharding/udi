<a href="cmd.php?cmd=udi&amp;udi_nav=mapping&amp;server_id=<?php echo $app['server']->getIndex();?>"
 title="Configure the UDI" onclick="return ajDISPLAY('BODY','cmd=udi&amp;udi_nav=mapping&amp;server_id=1&amp;','Configuration');"><h3>Help: Mapping</h3></a>
<p>
</p>
<p>  
This is the UDI import file to User Directory mapping utility.  Here, you can specify the names that relate to the source columns of the import CSV file, and then define target LDAP attributes that exist in the connected LDAP directories schema, to import the values to.
  Multiple import targets can be specified, and the order in which the source columns are specfied do not matter.
  <br/>
  Any import columns not specified in the map, default to mapping to the LDAP attribute with the same name.
</p>
