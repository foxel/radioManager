<?

class Core_MPDFill 
{
    const NO_REPEAT_TIME = 1800;
    public static $tz = 0;

    public static function init(FDataBase $db = null)
    {
        Core_Schedule::init($db);
        TrackBase::init($db);
    }

    public static function getTrackForFilling($time = false, array $bannedTracks = array())
    {
        if (!$time)
            $time = time() + self::$tz*3600;

        $show = Core_Schedule::getCurrentShow($time);
        $list = TrackBase::getTrackList($show['tracklist_id']);
        $tracks = array();
        if ($list['tracks']) {
            $tracks = TrackBase::getTracks(array(
                'id' => $list['tracks']
            ));
        } elseif ($list['filter_id']) {
            $tracks = TrackBase::getTracksByFilter($list['filter_id']);
        }

        mt_srand(time());
        $selectedTracks = array();
        $countWeWant = min(20, (int) (count($tracks)/2));
        $tries = 30;
        $timeEdge = time() - self::NO_REPEAT_TIME;
        do {
            $sel = mt_rand(0, count($tracks) -1);
            list($track) = array_splice($tracks, $sel, 1);
            if ($track['lastplay'] < $timeEdge 
                && !in_array($track['id'], $bannedTracks) 
                && !in_array($track['uri'], $bannedTracks)) 
            {
                $selectedTracks[] = $track;
            } else {
                $tries--;
            }
        } while (count($selectedTracks) < $countWeWant && $tries);

        $track = array_pop($selectedTracks);
        while ($moreTrack = array_pop($selectedTracks)) {
            if ($moreTrack['rate'] > $track['rate'])
                $track = $moreTrack;
        }

        return $track;
    }

}
