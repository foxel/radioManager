<?php
define('STARTED', true);
define('F_SITE_ROOT', dirname(__FILE__).DIRECTORY_SEPARATOR);

require_once('kernel3.php');
require_once('/home/foxel/hosts/dev/sandbox.dev/www/radio/core/icecast.php');
require_once('/home/foxel/hosts/dev/sandbox.dev/www/radio/core/trackbase.php');
require_once('/home/foxel/hosts/dev/sandbox.dev/www/radio/core/schedule.php');

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
TrackBase::init(F()->DBase);
Core_Schedule::init(F()->DBase);

// current & next track
$mpc = F()->MPC('localhost');
if ($curTrack = $mpc->playlist[$mpc->curTrack])
    TrackBase::prepMpdTrack($curTrack);
if ($nextTrack = $mpc->playlist[$mpc->nextTrack]) {
    TrackBase::prepMpdTrack($nextTrack);
}

$trackId = ($curTrack) 
    ? TrackBase::getTrackId($curTrack)
    : 0;

// top lists
$top10 = F()->DBase->select('trackstat', 's', array('lastplay', 'listened', 'played', 'voterate', 'rate'))
    ->join('track', array('id' => 'track_id'), 't')
    ->order('rate', true, 's')
    ->order('lastplay', true, 's')
    ->limit(10)
    ->fetchAll();
$last10 = F()->DBase->select('trackplay', 'p', array('time', 'listeners'))
    ->join('track', array('id' => 'track_id'), 't')
    ->order('time', true, 'p')
    ->limit(10)
    ->fetchAll();

// schedule
$curShow = Core_Schedule::getCurrentShow(time() + 7*3600);
$weekSchedule = Core_Schedule::loadSchedule(time() + 7*3600, time() + 7*3600 + 6*24*3600);

// voting
$lVoteUid = F()->appEnv->request->getString('lastVote', K3_Request::COOKIE, FStr::HEX);
$lVote = TrackBase::getVote($lVoteUid);
$voteBlocked = $lVote['track_id'] == $trackId;

if (!is_null($rate = F()->appEnv->request->getNumber('vote'))) {
    if (!$voteBlocked) {
        $nVoteUid = TrackBase::voteForTrack($trackId, $rate);
        F()->appEnv->client->setCookie('lastVote', $nVoteUid);
    }
    if (F()->appEnv->request->isAjax) {
        F()->appEnv->response->write('OK')->sendBuffer(false, 'text/plain');
    }
    F()->appEnv->response->sendRedirect(F()->appEnv->server->rootUrl.F_SITE_INDEX);
}

$pageRelodTimer = ($mpc->curTrackPos <= $curTrack['time'])
    ? min($curTrack['time'] - $mpc->curTrackPos + 1, 30)
    : 30;

ob_start();
//print_r($top10);
?>
<html>
<head>
 <title>Top-10 test</title>
 <!--Meta-Content-Type-->
 <meta http-equiv="refresh" content="<?=$pageRelodTimer?>">
 <style type="text/css">
  span.votestars a {
    white-space: nowrap;
  }
  span.votestars a {
    color: inherit!important;
    font-weight: bold;
    text-decoration: none!important;
  }
  span.votestars span {
    color: darkblue;
  }
  span.votestars span:hover {
    color: red;
  }
 </style>
</head>
<body>
 <script type="text/javascript" src="/js/jquery-1.7.1.js" ></script>
 <script type="text/javascript">
 // JavaScript Starts Here <![CDATA[
  $(document).ready(function() {
    $('#votestars a').click(function() {
      $.get(this.href);
      $('#voteblock').hide();
      return false;
    });
  });
 //]]> JavaScript ends here
 </script>
 <h1>Foxel's home radio</h1>
 <h2>Сейчас играет:</h2>
 <p>
  <b>Эфир: </b>
  <? print $curShow['name']; ?>
 </p>
 <p>
  <b>Композиция: </b>
  <? 
  if (preg_match('#^https?://#', $curTrack['file']))
      print $curTrack['name'];
  else
      print "{$curTrack['artist']} - {$curTrack['title']} ({$curTrack['album']})";
  ?>
  [ <span title="Статистика обновляется раз в минуту">композицию слушают: <? print (int) FRegistry::get('current.listeners'); ?></span> 
  | <a href="http://radio-server.imfurry.ru:8000/foxel.ogg.m3u">слушать</a> 
  <? if (!$voteBlocked) { ?>
  <span id="voteblock">| голосовать: 
  <span id="votestars" class="votestars">
   <span><a href="top.php?vote=1">&#x2605;</a><span><a href="top.php?vote=2">&#x2605;</a><span><a href="top.php?vote=3">&#x2605;</a><span><a href="top.php?vote=4">&#x2605;</a><span><a href="top.php?vote=5">&#x2605;</a></span></span></span></span></span>
  </span></span>
  <? } ?>
   ]
 </p>
 <h2>Далее в эфире:</h2>
 <p><? print ($nextTrack && !preg_match('#^https?://#', $curTrack['file']))
    ? F()->LNG->timeFormat(time() - $mpc->curTrackPos + $curTrack['time'], false, 6).": {$nextTrack['artist']} - {$nextTrack['title']} ({$nextTrack['album']})"
    : 'Еще неизвестно :)'; ?></p>
 <h2>ТОП-10:</h2>
 <table style="width: 100%; font-size: 12px;">
  <tr>
   <th>Название</th>
   <th>Исполнитель</th>
   <th>Альбом</th>
   <th>Воспр.</th>
   <th>Слуш.</th>
   <th>Голос.</th>
   <th>Последний раз на радио</th>
  </tr>
<?php
foreach($top10 as $item)
    print "
  <tr>
   <td>".($item['title'] ? $item['title'] : FStr::basename($item['uri']))."</td>
   <td>{$item['artist']}</td>
   <td>{$item['album']}</td>
   <td style=\"text-align: center;\">{$item['played']}</td>
   <td style=\"text-align: center;\">{$item['listened']}</td>
   <td style=\"text-align: center;\">{$item['voterate']}</td>
   <td style=\"white-space: nowrap;\">".F()->LNG->timeFormat($item['lastplay'], false, 6)."</td>
  </tr>";
?>
 </table>
 <h2>Последние 10:</h2>
 <table style="width: 100%; font-size: 12px;">
  <tr>
   <th>Название</th>
   <th>Исполнитель</th>
   <th>Альбом</th>
   <th>Время</th>
   <th>Слушатели</th>
  </tr>
<?php
foreach($last10 as $item)
    print "
  <tr>
   <td>".($item['title'] ? $item['title'] : FStr::basename($item['uri']))."</td>
   <td>{$item['artist']}</td>
   <td>{$item['album']}</td>
   <td style=\"white-space: nowrap;\">".F()->LNG->timeFormat($item['time'], false, 6)."</td>
   <td style=\"text-align: center;\">{$item['listeners']}</td>
  </tr>";
?>
 </table>

<h2>Расписание</h2>
<?
    foreach ($weekSchedule as $date => $shows) {
        $date = F()->LNG->timeFormat(strtotime($date.' GMT'), 'd M Y (D)', 0, true);
        print '<h3>'.$date.'</h3>
        <table>';
        foreach ($shows as $show) {
            print "<tr".($show['is_backfill'] ? ' style="color: gray;"' : '').">
             <td>{$show['time']}</td>
             <td>{$show['name']}</td>
            </tr>";
        }
        print '</table>';
    }
?>
 <div style="height: 30px;">&nbsp;</div>
 <div style="width: 100%; height: 25px; position: fixed; bottom: 0; right: 0; background-color: white; border-top: 1px solid gray;">
  <p style="text-align: right; margin: 0; padding: 2px 20px;">Часовой пояс станции: GMT+6, Томск</p>
 </div>
</body>
</html>
<?php
 F()->appEnv->response->write(ob_get_clean())->sendBuffer();
?>
