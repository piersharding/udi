<?php
$cfg = $udiconfig->getConfig();
$action = (get_request('cancel') ? 'cancel' : '').
          (get_request('process') ? 'process' : '').
          (get_request('validate') ? 'validate' : '').
          (get_request('reactivate') ? 'reactivate' : '').
          (get_request('delete') ? 'delete' : '');
          
// Set our timelimit in case we have a lot of importing to do
@set_time_limit(0);

// up the memory limit as this could be big
ini_set('memory_limit', '512M');

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
        break;
        
    case 'process':
       // validate config
        if (!$udiconfig->validate()) {
            break;
        }
        
        // really process the file now
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
            $processor->purge();
            $processor->import();
        }
        $request['page']->info(_('File processing finished'));
        break;
        
    case 'reactivate':
       // validate config
        if (!$udiconfig->validate()) {
            break;
        }
        
        // really process the file now
        $request['page']->info(_('Reactivation processing started'));
        // do validation, and then jump to a confirm/cancel screen
        $processor = new Processor($app['server']);
        if ($processor->validateReactivation()) {
            $confirm = get_request('confirm');
            if ($confirm == 'yes') {
                $processor->purge();
                $processor->reactivate();
                $request['page']->info(_('User reactivation completed'));
            }
            else if ($confirm == 'no') {
                $request['page']->info(_('User reactivation cancelled'));
            }
        }
        break;
        
    case 'delete':
       // validate config
        if (!$udiconfig->validate()) {
            break;
        }
        
        // really process the file now
        $request['page']->info(_('Deletion processing started'));
        // do validation, and then jump to a confirm/cancel screen
        $processor = new Processor($app['server']);
        $confirm = get_request('confirm');
        if ($confirm == 'yes') {
            $processor->purge();
            $processor->deleteDeactivated();
            $request['page']->info(_('User deletion completed'));
        }
        else if ($confirm == 'no') {
            $request['page']->info(_('User deletion cancelled'));
        }
        break;
        
    default:
        break;
}

?>
