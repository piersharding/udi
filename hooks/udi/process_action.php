<?php
$cfg = $udiconfig->getConfig();
$action = (get_request('cancel') ? 'cancel' : '').
          (get_request('process') ? 'process' : '').
          (get_request('validate') ? 'validate' : '');
          
# Set our timelimit in case we have a lot of importing to do
@set_time_limit(0);

switch ($action) {
    case 'cancel':
        // we are canceling the upload
//        unset($_SESSION['udi_import_file']);
        $request['page']->info(_('File uploaded has been cancelled'));
        break;
    
    case 'validate':
        // validate config
        if (!$udiconfig->validate()) {
            break;
        }
        
        // validate the file now
//        $request['page']->info(_('File validation started'));
        if (isset($_SESSION['udi_import_file'])) {
            // validate the in memory file
            $processor = new Processor($app['server'], $_SESSION['udi_import_file']);
        }
        else {
            // validate the file specified in the config
            $import = new ImportCSV($app['server']->getIndex(), $cfg['filepath']);
            $import->accept(',');
            $header = $import->getCSVHeader();
            $rows = array();
            while ($entry = $import->readEntry()) {
                $rows []= $entry;
            }
            $processor = new Processor($app['server'], array('header' => $header, 'contents' => $rows));
        }
        // validate file contents
        $processor->validate();
        $request['page']->info(_('File validation finished'));
        break;
        
    case 'process':
       // validate config
        if (!$udiconfig->validate()) {
            break;
        }
        
        // really process the file now
        $request['page']->info(_('File processing started'));
        if (isset($_SESSION['udi_import_file'])) {
            // process the in memory file
            $processor = new Processor($app['server'], $_SESSION['udi_import_file']);
        }
        else {
            // process the file specified in the config
            // validate the file specified in the config
            $import = new ImportCSV($app['server']->getIndex(), $cfg['filepath']);
            $import->accept(',');
            $header = $import->getCSVHeader();
            $rows = array();
            while ($entry = $import->readEntry()) {
                $rows []= $entry;
            }
            $processor = new Processor($app['server'], array('header' => $header, 'contents' => $rows));
        }
        if ($processor->validate()) {
            $processor->import();
        }
        $request['page']->info(_('File processing finished'));
        break;
        
    default:
        break;
}

?>
