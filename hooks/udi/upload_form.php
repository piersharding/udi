<?php

printf('%s</td>',_('Select an LDIF file'));
echo '<td>';
echo '<input type="file" name="ldif_file" />';
echo '</td></tr>';

printf('<tr><td>&nbsp;</td><td class="small"><b>%s %s</b></td></tr>',_('Maximum file size'),ini_get('upload_max_filesize'));

echo '<tr><td colspan=2>&nbsp;</td></tr>';
printf('<tr><td>%s</td></tr>',_('Or paste your LDIF here'));
echo '<tr><td colspan=2><textarea name="ldif" rows="20" cols="100"></textarea></td></tr>';
echo '<tr><td colspan=2>&nbsp;</td></tr>';
printf('<tr><td>&nbsp;</td><td class="small"><input type="checkbox" name="continuous_mode" value="1" />%s</td></tr>',
	_("Don't stop on errors"));
printf('<tr><td>&nbsp;</td><td><input type="submit" value="%s" />',_('Proceed >>'));

?>
