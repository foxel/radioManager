<?php

define('STARTED', true);
require_once('kernel3.php');
require_once('core/trackbase.php');

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

$mpc = F()->MPC('localhost');
$all = $mpc->lsAll('By Artist', FMPC::ITEM_FILE);
foreach ($all as $track)
    TrackBase::getTrackId($track);
print_r($all);
$all = $mpc->lsAll('BEST', FMPC::ITEM_FILE);
foreach ($all as $track)
    TrackBase::getTrackId($track);
print_r($all);
$all = $mpc->lsAll('Soundtracs', FMPC::ITEM_FILE);
foreach ($all as $track)
    TrackBase::getTrackId($track);
print_r($all);
$all = $mpc->lsAll('Mixeds', FMPC::ITEM_FILE);
foreach ($all as $track)
    TrackBase::getTrackId($track);
print_r($all);
?>

