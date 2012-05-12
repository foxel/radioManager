<?php

define('STARTED', true);
define('F_SITE_ROOT', dirname(__FILE__).DIRECTORY_SEPARATOR);
chdir(F_SITE_ROOT);

require_once('kernel3.php');
require_once('core/icecast.php');
require_once('core/trackbase.php');
require_once('core/schedule.php');
require_once('core/trackfill.php');

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

FRegistry::setBackDB(F()->DBase);
Core_MPDFill::init(F()->DBase);

// getting stats

$icenet = FMisc::loadDatafile('conf/icenet.cnf', FMisc::DF_SLINE);
$mpc = F()->MPC('localhost');
$track = $mpc->playlist[$mpc->curTrack];
$time = time() - $mpc->curTrackPos;
$listeners = IcecastInfo::getListenersCount($icenet);
$trackId = TrackBase::getTrackId($track);
FRegistry::set('current.listeners', $listeners, true);
TrackBase::setTrackPlay($trackId, $time, $listeners);

// scheduled playback
// modes init
$mpc->setConsume(true);
$mpc->setRepeat(false);
$mpc->setRandom(false);

// if we need to add a song
if (!$mpc->nextTrack || !($nTrack = $mpc->playlist[$mpc->nextTrack+1]) || (!$mpc->playlist[$mpc->nextTrack+1] && $nTrack['time'] < 60)) {
    $bannedForFilling = array();
    $timeshift = 0;
    if ($curTrack = $mpc->playlist[$mpc->curTrack]) {
        $timeshift         += $curTrack['time'] - $mpc->curTrackPos;
        $bannedForFilling[] = $curTrack['file'];
    }
    for ($i = count($mpc->playlist) - 1; $i > $mpc->curTrack; $i--) {
        $timeshift         += $mpc->playlist[$i]['time'];
        $bannedForFilling[] = $mpc->playlist[$i]['file'];
    }

    $track = Core_MPDFill::getTrackForFilling(time() + $timeshift + 7*3600, $bannedForFilling);
    $mpc->add($track['uri']);
}
// the MPD is stopped
if ($mpc->curTrack < 0) 
    $mpc->play();

?>
