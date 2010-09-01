<?php
exec('ps -aux', $output, $rc);
$running = preg_grep('/cron/', preg_grep('/php/', $output));
if (!empty($running)) {
    $state = '<span class="udi-report-complete">'._('cron running').'</span>';
}
else {
    $state = '<span class="udi-report-warning">'._('cron not running').'</span>';
}
echo '<h2 class="shrink">'._('Activity Reports: ').$state.'</h2>';
$process_reports = read_process_reports();
foreach ($process_reports as $report) {
    echo $request['page']->reportSummary($report);
}

?>
