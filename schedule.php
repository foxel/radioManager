<?php
define('STARTED', true);
require_once('kernel3.php');
require_once('core/trackbase.php');
require_once('core/schedule.php');

$config = new FDataPool($c = (array)FMisc::loadDatafile(F_DATA_ROOT.DIRECTORY_SEPARATOR.'db.cfg.php', FMisc::DF_SLINE));
// preparing DB
F()->DBase->connect(
    array(
        'dbname' => $config['db.database'],
        'host'   => $config['db.host'],
    ),
    $config['db.username'],
    $config['db.password'],
    $config['db.prefix']
);

TrackBase::init(F()->DBase);
Core_Schedule::init(F()->DBase);

print_r(Core_Schedule::getSchedule(Core_Schedule::MODE_WEEK, time() + 7*3600));
$curShow = Core_Schedule::getCurrentShow(time() + (7)*3600);
print_r($curShow);
$curList = TrackBase::getTrackList($curShow['tracklist_id']);
print_r($curList);
if ($curList['filter_id']) {
    $tracks = TrackBase::getTracksByFilter($curList['filter_id']);
    print_r($tracks);
}

?>
