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
        if (!$udiconfig->validate(true)) {
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
        $processor->validate(true);
        break;
        
    case 'process':
       // stash the config so that logging can work
        $request['page']->setConfig($udiconfig);
        $request['page']->log_header($action);
        
       // validate config
        if (!$udiconfig->validate(true)) {
            $request['page']->log_system_messages();
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
        if ($processor->validate(true)) {
            $processor->purge();
            $processor->import();
            $processor->purge();
        }
        $request['page']->log_system_messages();
        $request['page']->info(_('File processing finished'));
        $request['page']->log_footer();
        break;
        
    case 'reactivate':
       // stash the config so that logging can work
        $request['page']->setConfig($udiconfig);
        $request['page']->log_header($action);
        
        // validate config
        if (!$udiconfig->validate(true)) {
            $request['page']->log_system_messages();
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
                $request['page']->log_system_messages();
                $request['page']->info(_('User reactivation completed'));
            }
            else if ($confirm == 'no') {
                $request['page']->info(_('User reactivation cancelled'));
            }
        }
        $request['page']->log_footer();
        break;
        
    case 'delete':
       // stash the config so that logging can work
        $request['page']->setConfig($udiconfig);
        $request['page']->log_header($action);
        
        // validate config
        if (!$udiconfig->validate(true)) {
            $request['page']->log_system_messages();
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
            $request['page']->log_system_messages();
            $request['page']->info(_('User deletion completed'));
        }
        else if ($confirm == 'no') {
            $request['page']->info(_('User deletion cancelled'));
        }
        $request['page']->log_footer();
        break;
        
    default:
        break;
}

?>
