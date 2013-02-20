<?php
$upload = get_request('upload');
$process = get_request('process');
$cancel = get_request('cancel');
if (!empty($cancel)) {
    // we are canceling the upload
    unset($_SESSION['udi_import_file']);
    $request['page']->info(_('File uploaded has been cancelled'));
}
else if (!empty($upload)) {
    // we are uploading a file
    $delimiter = get_request('delimiter');
    $delimiters = array(',', ';', '|', 'Tab');
    if (!in_array($delimiter, $delimiters)) {
        $delimiter = ',';
    }
    if ($delimiter == 'Tab') {
        $delimiter = "\t";
    }
    $request['importer'] = new Importer($app['server']->getIndex(),'CSV', $delimiter);
    $request['udi'] = $request['importer']->getTemplate();
    
    $request['continuous_mode'] = get_request('continuous_mode') ? true : false;
    
    $type = $request['udi']->getType();
    
    # Set our timelimit in case we have a lot of importing to do
    @set_time_limit(0);
    
    // process all the import records
    $header = $request['udi']->getCSVHeader();
    
    $rows = array();
    while ($entry = $request['udi']->readEntry()) {
        $rows []= $entry;
    }
    
    // stash file in session for real processing
    // error_log('uploaded version: '.$request['udi']->getFileVersion());
    $_SESSION['udi_import_file'] = array('header' => $header, 'contents' => $rows, 'version' => $request['udi']->getFileVersion());
    
    $request['page']->info(sprintf(_('File uploaded and ready for processing.  File contained %s rows'), count($rows)));
}
else if (!empty($process)) {
    // we are processing the currently uploaded file
    
var_dump($_POST);
exit(0);
    
}

?>