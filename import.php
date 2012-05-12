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
$listFile = '/media/STORAGE/foxel/Music/New Year 2012.m3u8';
$listName = 'New Year 2012';

$tracks = file($listFile);

$ids = array();

foreach ($tracks as $trackFile) {
    if ($track = reset($mpc->lsAll(preg_replace('#^\./#', '', trim($trackFile)), FMPC::ITEM_FILE))) {
        var_dump($track);
        $ids[] = TrackBase::getTrackId($track);
    }
}

var_dump($ids);

if ($listId = TrackBase::findTrackListId($listName)) {
    $insert = array();
    foreach ($ids as $order => $id) {
        $insert[] = array(
            'tracklist_id' => $listId,
            'track_id'     => $id,
            'order_id'     => $order,
        );
    }

    // storing items
    F()->DBase->doDelete('tracklist_item', array('tracklist_id' => $listId));
    F()->DBase->doInsert('tracklist_item', $insert, false, FDataBase::SQL_MULINSERT);
}
