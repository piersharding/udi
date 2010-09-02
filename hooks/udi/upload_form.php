<?php
// here - we deal with all the direct hits of the AJAX DHTML grid control API
if (get_request('udi_decoration', 'REQUEST') == 'none') {
//    header('Content-type: text/plain');
//    echo 'here';
//    var_dump($_GET);
//    var_dump($_POST);
//    var_dump($_SESSION['udi_import_file']['contents']);
    switch (get_request('udi_action', 'REQUEST')) {
        case 'loader':
            if (isset($_SESSION['udi_import_file'])) {
                //reorder keys
                $import = array();
                foreach ($_SESSION['udi_import_file']['contents'] as $entry) {
                    if (!empty($entry)) {
                        $import []= $entry;
                    }
                }
                $_SESSION['udi_import_file']['contents'] = $import;
                if ( stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml")) {
                    header("Content-type: application/xhtml+xml");
                 }
                 else {
                    header("Content-type: text/xml");
                }
                echo("<?xml version='1.0' encoding='iso-8859-1'?>\n");
                $count = count($_SESSION['udi_import_file']['contents']);
                echo '<rows>';
                for($i=0; $i<$count; $i++){
                    echo "<row id='r".($i+1)."'>";
                    $row = $_SESSION['udi_import_file']['contents'][$i];
                    foreach ($row as $cell) {
                        echo "<cell>".$cell."</cell>";
                    }
                    echo "</row>";
                }
                echo '</rows>';
            }
        break;
    case 'updater':
       if ( stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml")) {
            header("Content-type: application/xhtml+xml");
         }
         else {
            header("Content-type: text/xml");
        }
        echo("<?xml version='1.0' encoding='iso-8859-1'?>\n");
        $mode = get_request("!nativeeditor_status", 'REQUEST'); //get request mode
        $rowId = get_request("gr_id", 'REQUEST'); //id or row which was updated 
        $newId = get_request("gr_id", 'REQUEST'); //will be used for insert operation
        $row = (int)preg_replace('/^r/', '', $rowId);

//        echo "row is: $row";
        //output update results
        echo "<data>";
        $socs = $app['server']->SchemaObjectClasses('user');
        $mlepPerson = $socs['mlepperson'];
        $must = array();
        foreach ($mlepPerson->getMustAttrs() as $attr) {
            $must[$attr->getName(false)] = $attr->getName(false);
        }
        if ($mode == 'updated') {
            $header_cnt = 0;
            $no_errors = true;
            foreach ($_SESSION['udi_import_file']['header'] as $header) {
                $header_cnt++;
                $value =  get_request($header, 'REQUEST');
                if (isset($must[$header]) && empty($value)) {
                    echo "<action type='error' sid='".$rowId."' tid='".$newId."'>".$header._(' is a mandatory value - please enter a valid value')."</action>";
                    $no_errors = false;
                    break;
                }
                else {
                    $_SESSION['udi_import_file']['contents'][$row - 1][$header_cnt - 1] = $value;
//                    echo "cell $header is now: ".$_SESSION['udi_import_file']['contents'][$row - 1][$header_cnt - 1];
                }
            }
            if ($no_errors) {
                echo "<action type='update' sid='".$rowId."' tid='".$newId."'/>";
            }
        }
        else if ($mode == 'inserted') {
            $insert = array();
            foreach ($_SESSION['udi_import_file']['header'] as $header) {
                $insert[]= get_request($header, 'REQUEST');
            }
            $_SESSION['udi_import_file']['contents'][$row - 1]= $insert;
            echo "<action type='insert' sid='".$rowId."' tid='".$newId."'/>";
        }
        else if ($mode == 'deleted') {
            if (isset($_SESSION['udi_import_file']['contents'][$row - 1])) {
                unset($_SESSION['udi_import_file']['contents'][$row - 1]);
                echo "<action type='delete' sid='".$rowId."' tid='".$newId."'/>";
            }
        }
        echo "</data>";
        
        break;
    default:
        break;
    }
    die();
}

echo '<div class="udiform">';
echo '<div class="item-list">';

echo '<h2 class="shrink">'._('Import UDI File:').'</h2>';

$file_name = '';
if (isset($_FILES['csv_file']) && is_array($_FILES['csv_file']) && isset($_FILES['csv_file']['name'])) {
    $file_name = $_FILES['csv_file']['name'];
}
$delimiters = array(',' => new ObjectClass_ObjectClassAttribute(",", ","), 
                    'Tab' => new ObjectClass_ObjectClassAttribute("Tab", "Tab"),
                    ';' => new ObjectClass_ObjectClassAttribute(";", ";"),
                    '|' => new ObjectClass_ObjectClassAttribute("|", "|"),
                    );

echo '<table><tr><td>'.
    $request['page']->configFieldLabel(
                        'csv_file', 
                        _('Select a CSV file')
                        ).'</td>'. 
    '<td>'.$request['page']->configField('csv_file', array('type' => 'file', 'value' => $file_name), array()). 
    '</td><td>'.
    $request['page']->configField('upload', array('type' => 'submit', 'value' => _('Upload')), array());
    if (isset($_SESSION['udi_import_file'])) {
        echo '&nbsp; &nbsp;<input type="button" value="'.('Process').
                        '" onClick="return ajDISPLAY(\'BODY\', \'cmd=udi&amp;udi_nav=process&amp;server_id='.
                        $app['server']->getIndex().'\', \''._('Processing').'\');">';
        echo '&nbsp; &nbsp;'.$request['page']->configField('cancel', array('type' => 'submit', 'value' => _('Cancel')), array());
    }
    echo '</td></tr><tr><td>'. _('Delimiter:').'</td><td>'.
    $request['page']->configSelect('delimiter', $delimiters, ',', 'udi-select');
echo '</td></tr></table>';
echo '</div>'; // end of item-list

if (isset($_SESSION['udi_import_file'])) {
    //https://seagull.local.net/phpldapadmin/cmd.php?cmd=udi_form&udi_nav=upload&server_id=1&udi_decoration=none
    
    $columns = implode(',', $_SESSION['udi_import_file']['header']);
    $colcount = count($_SESSION['udi_import_file']['header']);
//    $colcount = 1;
    $init_widths = rtrim(str_repeat("50,", $colcount), ',');
    $col_align   = rtrim(str_repeat("left,", $colcount), ',');
    $col_active  = rtrim(str_repeat("true,", $colcount), ',');
    $col_types   = rtrim(str_repeat("ed,", $colcount), ',');
    $col_sorting = rtrim(str_repeat("str,", $colcount), ',');
    $col_init    = rtrim(str_repeat("'x',", $colcount), ',');
    $total = count($_SESSION['udi_import_file']['contents']);
    
    echo '<div id="gridtableform">';
    echo '<form name="udi_upload_form" action="cmd.php" method="post" class="new_value" enctype="multipart/form-data">';
    printf('<input type="hidden" name="server_id" value="%s" />',$app['server']->getIndex());
    echo '<input type="hidden" name="cmd" value="udi" />';
    echo '<input type="hidden" name="udi_nav" value="upload" />';
    ?>
    
<br/>
<div id="gridbox" style="width:1024px;height:400px;background-color:white;"></div>
<script>
mygrid = new dhtmlXGridObject('gridbox');
mygrid.setImagePath("<?php echo JSDIR?>dhtmlx/codebase/imgs/");
mygrid.setColumnIds("<?php echo $columns?>");
mygrid.setHeader("<?php echo $columns?>");
mygrid.setColAlign("<?php echo $col_align?>")
mygrid.setColTypes("<?php echo $col_types?>");
mygrid.setColSorting("<?php echo $col_sorting?>")
mygrid.init();
mygrid.setSkin("dhx_skyblue")
mygrid.loadXML("cmd.php?cmd=udi_form&udi_nav=upload&server_id=<?php echo $app['server']->getIndex()?>&udi_decoration=none&udi_action=loader");
myDataProcessor = new dataProcessor("cmd.php?cmd=udi_form&udi_nav=upload&server_id=<?php echo $app['server']->getIndex()?>&udi_decoration=none&udi_action=updater");
myDataProcessor.enableDataNames(true);
myDataProcessor.setDataColumns([<?php echo $col_active?>]);
//myDataProcessor.setTransactionMode("POST", true);
//myDataProcessor.setUpdateMode("off");
myDataProcessor.styles = {
	    updated: "font-style:italic; color:green;",
	    inserted: "font-weight:bold; color:green;",
	    deleted: "font-weight:bold; color:red;",
	    invalid: "color:orange; text-decoration:underline;",
	    error: "color:red; text-decoration:underline;",
	    clear: "font-weight:normal;text-decoration:none;"
	};
myDataProcessor.init(mygrid);
myDataProcessor.defineAction("error", function(tag) {
    alert(tag.firstChild.nodeValue);
    return true;
});
</script>
<div class="fitem">
<div class="felement">
    <span style="white-space: nowrap;">
        <a href="javascript:void(0)" onclick="mygrid.addRow((new Date()).valueOf(),[<?php echo $col_init?>],mygrid.getRowIndex(mygrid.getSelectedId()))"><img src="images/udi/add.png" alt="<?php echo _('Add row'); ?>"/>&nbsp;<?php echo _('Add row'); ?></a>
        &nbsp; &nbsp;
        <a href="javascript:void(0)" onclick="mygrid.deleteSelectedItem()"><img src="images/udi/trash.png" alt="<?php echo _('Delete row'); ?>"/>&nbsp;<?php echo _('Delete row'); ?></a>
    </span>
    </div>
</div>
</form> 
</div>
<?php
}
echo '</div>'; // end of udiform
?>