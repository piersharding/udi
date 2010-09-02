<?php
/**
 * Displays a form to allow the user to upload and import
 * an LDIF file.
 *
 * @package phpLDAPadmin
 * @subpackage Page
 */

/**
 */

require_once './common.php';
require_once HOOKSDIR.'udi/UdiConfig.php';
require_once HOOKSDIR.'udi/UdiRender.php';
require_once HOOKSDIR.'udi/udi_functions.php';


if (! ini_get('file_uploads'))
	error(_('Your PHP.INI does not have file_uploads = ON. Please enable file uploads in PHP.'),'error','index.php');

// get the UDI config
$udiconfig = new UdiConfig($app['server']);
$request['udiconfig'] = $udiconfig->getConfig();
$udiconfigdn = $udiconfig->getBaseDN();
//var_dump($_POST);
//var_dump(get_request('udi_decoration', 'REQUEST'));
//exit(0);

// setup the page renderer - if not already there - it maybe there because of the POST action
if (!isset($request['page'])) {
    $request['page'] = new UdiRender($app['server']->getIndex(),get_request('template','REQUEST',false,'none'));
}

// set the headings
$request['page']->setContainer($udiconfigdn);
$request['page']->accept();

// sort out which UDI navigation page we should be on
$udi_nav = get_request('udi_nav','REQUEST');
if (empty($udi_nav) && isset($_SESSION['udi_nav'])) {
    $udi_nav = $_SESSION['udi_nav'];
}
if (!in_array($udi_nav, array('admin', 'userpass', 'mapping', 'upload', 'process', 'reporting', 'help'))) {
    $udi_nav = 'admin';
}

//echo "nav is: $udi_nav";
//var_dump($request['udiconfig']);

if (get_request('udi_decoration', 'REQUEST') != 'none') {
    $request['page']->drawTitle(sprintf('<b>%s</b>',_('UDI')));
    $request['page']->drawSubTitle(sprintf('%s: <b>%s</b>',_('Server'),$app['server']->getName()));
    // include JS for form actions
    printf('<script type="text/javascript" language="javascript" src="%sdnChooserPopup.js"></script>',JSDIR);
    printf('<script type="text/javascript" language="javascript" src="%sform_field_toggle_enable.js"></script>',JSDIR);
    echo '
    <script id="post-click" type="text/JavaScript">
    function post_to_url(path, params, method) {
        method = method || "post";
        var form = document.createElement("form");
        form.setAttribute("method", method);
        form.setAttribute("action", path);
    
        var all_params = {\'server_id\': \''.$app['server']->getIndex().'\', \'cmd\': \'udi\', \'udi_nav\': \''.$udi_nav.'\'};
        for (attr in params) { all_params[attr] = params[attr]; }
    
        for(var key in all_params) {
            var hiddenField = document.createElement("input");
            hiddenField.setAttribute("type", "hidden");
            hiddenField.setAttribute("name", key);
            hiddenField.setAttribute("value", all_params[key]);
    
            form.appendChild(hiddenField);
        }
    
        document.body.appendChild(form);
        form.submit();
    }
    
    function safeGetElement(doc, el) {
        return doc.ids ? doc.ids[el] : doc.getElementById ? doc.getElementById(el) : doc.all[el];
    }
    
    function GetelementsByPrefix(inPrefix,inRoot){ 
        var elem_array = new Array; 
        if(inRoot && typeof inRoot.firstChild!= "undefined"){ 
            var elem = inRoot.firstChild; 
            while (elem!= null){ 
                if(typeof elem.firstChild!= "undefined"){ 
                    elem_array = elem_array.concat(GetelementsByPrefix(inPrefix,elem)); 
                } 
                if(typeof elem.id!= "undefined"){ 
                    var reg = new RegExp ( "^"+inPrefix+".*" ); 
                    if(elem.id.match(reg)){ 
                        elem_array.push(elem); 
                    } 
                } 
                elem = elem.nextSibling; 
            } 
        } 
        return elem_array; 
    }
    
    function Displayelements(in_elem_array){ 
        if(in_elem_array.length){ 
            for(var c=0; c<in_elem_array.length; c++){ 
                alert(in_elem_array[c].id); 
            } 
        } 
    } 
    
    function udi_report_toggle(idprefix) {
        inRoot = document.getElementById(idprefix);
        els = GetelementsByPrefix(idprefix, inRoot);
        if(els.length){ 
            for(var c=0; c<els.length; c++){ 
                if (els[c].style.display == "none") {
                    els[c].style.display = "table-row";
                }
                else {
                    els[c].style.display = "none";
                } 
            } 
        } 
        var collapse = document.getElementById(idprefix + "-collapse");
        if (collapse.style.display == "none") {
            collapse.style.display = "block";
        }
        else {
            collapse.style.display = "none";
        }
        return false;
    }
    </script>
    ';
    
    // frame up the page - set the menus
    echo '<center>';
    echo '<table id="udi-nav" class="no-border"><tr><td>';
    echo $request['page']->getMenu($udi_nav);
    echo '</td></tr></table>';
    echo '<form name="udi_form" action="cmd.php" method="post" class="new_value" enctype="multipart/form-data">';
    printf('<input type="hidden" name="server_id" value="%s" />',$app['server']->getIndex());
    echo '<input type="hidden" name="cmd" value="udi" />';
    echo '<input type="hidden" name="udi_nav" value="'.$udi_nav.'" />';
    
    echo '<table class="forminput" border=0>';
    
    echo '<tr><td>';
//    echo $request['page']->outputMessages();
    echo '</td></tr>';
    //echo '<tr><td colspan=2>&nbsp;</td></tr>';
    echo '<tr>';
    echo '<td>';
}

$socs = $app['server']->SchemaObjectClasses();
// wont find mlepperson if the schema is not installed - do install check, and then load up if possible
if (!isset($socs['mlepperson'])) {
    $request['page']->error(_('The mlepPerson LDAP Schema is not installed in this directory - please ensure that it is installed before continuing'));
}
else {
    // get the specific template for this panel
    require_once HOOKSDIR.'udi/'.$udi_nav.'_form.php';
}

if (get_request('udi_decoration', 'REQUEST') != 'none') {
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '</form>';
    echo '</center>';
}
?>
