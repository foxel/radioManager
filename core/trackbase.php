<?php

class TrackBase 
{
    protected static $db;

    public static function init(FDataBase $db = null)
    {
        if ($db && $db->check())
            self::$db = $db;
        else {
            $params = FMisc::loadDatafile('conf/database.cnf', FMisc::DF_SLINE);
            self::$db = new FDataBase();
            self::$db->connect(Array('dbname' => $params['database']), $params['username'], $params['password']);
        }
    }

    public static function prepMpdTrack(array &$mpdTrack)
    {
        if (preg_match('#^https?://#', $mpdTrack['file'])) {
            $mpdTrack['title'] = (isset($mpdTrack['name']) ? $mpdTrack['name'] : FStr::basename($mpdTrack['file'])).' (stream)';
            $mpdTrack['artist'] = $mpdTrack['album'] = null;
            $mpdTrack['genre'] = 'stream';
            $mpdTrack['is_stream'] = true;
        }

        return $mpdTrack;
    }

    public static function getTrackId(array $mpdTrack = null)
    {
        if (empty($mpdTrack) || !$mpdTrack['file'])
            return false;

        // search by hash
        if ($id = self::findTrackPathId($mpdTrack['file'])) {
            return $id;
        }

        $uriHash = md5($mpdTrack['file']);

        self::prepMpdTrack($mpdTrack);

        $infoHash = md5(implode('|', array(
            $mpdTrack['title'], 
            $mpdTrack['artist'], 
            $mpdTrack['album'], 
            $mpdTrack['genre'],
            FStr::basename($mpdTrack['file']),
            )));
        // search by info_hash (in case the file was moved inside MPD lib dir)
        if ($id = self::$db->doSelect('track', 'id', array('info_hash' => $infoHash))) {
            self::$db->doUpdate('track', array('uri_hash' => $uriHash), array('id' => $id));
            return $id;
        }

        // not found - need to add
        $id = self::$db->doInsert('track', array(
            'uri_hash' => $uriHash,
            'uri'      => isset($mpdTrack['file']) ? $mpdTrack['file'] : null,
            'title'    => isset($mpdTrack['title']) ? $mpdTrack['title'] : null,
            'artist'   => isset($mpdTrack['artist']) ? $mpdTrack['artist'] : null,
            'album'    => isset($mpdTrack['album']) ? $mpdTrack['album'] : null,
            'genre'    => isset($mpdTrack['genre']) ? $mpdTrack['genre'] : null,
            'a_track'  => isset($mpdTrack['track']) ? $mpdTrack['track'] : null,
            'is_stream' => isset($mpdTrack['is_stream']) ? (int) $mpdTrack['is_stream'] : 0,
            'info_hash' => $infoHash,
            ));

        return $id;
    }

    public static function findTrackPathId($trackPath)
    {
        if (empty($trackPath))
            return false;

        $hash = md5($trackPath);
        // search by hash
        if ($id = self::$db->doSelect('track', 'id', array('uri_hash' => $hash))) {
            return $id;
        }

        return false;
    }

    public static function setTrackPlay($trackId, $time, $listeners)
    {
        if (!$trackId)
            return;

        if ($play = self::getTrackPlay($trackId, $time)) {
            if ($play['listeners'] < $listeners)
                self::$db->doUpdate('trackplay', array('listeners' => (int) $listeners), array('id' => $play['id']));
        }
        else {
            self::$db->doInsert('trackplay', array(
                'track_id' => (int) $trackId,
                'time'     => (int) $time,
                'listeners' => (int) $listeners,
                ));
        }
    }

    public static function getTrackPlay($trackId, $time)
    {
        return self::$db->select('trackplay')
            ->where('track_id', (int) $trackId)
            ->where('time', '>= '.((int) $time - 2))
            ->where('time', '<= '.((int) $time + 2))
            ->fetchOne(FDataBase::SQL_USEFUNCS);
    }

    public static function getTrackLastPlay($trackId)
    {
        return self::$db->select('trackplay')
            ->where('track_id', (int) $trackId)
            ->order('time', true)
            ->fetchOne();
    }

    public static function voteForTrack($trackId, $rate, $time = false, $ip = false)
    {
        if (!is_int($time))
            $time = time();
        if (!is_numeric($ip))
            $ip = F()->HTTP->IPInt;
        $uid = FStr::shortUID();

        $data = array(
            'uid'      => $uid,
            'track_id' => (int) $trackId,
            'rate'     => max(0, min((int) $rate, 5)),
            'time'     => $time,
            'ip'       => $ip,
        );
            
        if (self::$db->doInsert('trackvote', $data)) {
            return $uid;
        }

        return null;
    }

    public static function getVote($uid)
    {
        return self::$db->select('trackvote')
            ->where('uid', $uid)
            ->fetchOne();
    }

    public static function getTrackList($id) 
    {
        $select = self::$db->select('tracklist', 'l')
            ->joinLeft('tracklist_item', array('tracklist_id' => 'l.id'), 'i', array('track_id'))
            ->where('l.id', $id)
            ->order('i.order_id', true);
        
        $rows = $select->fetchAll();
        if (empty($rows))
            return null;

        $list = $rows[0] + array('tracks' => array());
        unset($list['track_id']);
        foreach ($rows as $row) {
            if ($row['track_id'])
                $list['tracks'][] = $row['track_id'];
        }

        if ($list['filter_id']) {
            $list['filter'] = self::getTrackFilter($list['filter_id']);
        }

        return $list;
    }

    public static function findTrackListId($name)
    {
        $select = self::$db->select('tracklist', 'l', array('id'))
            ->where('l.name', $name);

        return $select->fetchOne();
    }

    public static function getTrackFilter($id) 
    {
        $select = self::$db->select('trackfilter', 'f')
            ->join('trackfilter_item', array('filter_id' => 'f.id'), 'i', array('type', 'value'))
            ->where('f.id', $id);
        
        $rows = $select->fetchAll();
        if (empty($rows))
            return null;

        $filter = $rows[0] + array('items' => array());
        unset($filter['type'], $filter['value']);
        foreach ($rows as $row) {
            if (!isset($filter['items'][$row['type']])) {
                $filter['items'][$row['type']] = $row['value'];
            } else {
                $filter['items'][$row['type']] = array_merge((array)$filter['items'][$row['type']], array($row['value']));
            }
        }

        return $filter;
    }

    public static function getTracksByFilter($filterId, $order = 'lastplay', $orderDesc = true)
    {
        $filter = self::getTrackFilter($filterId);

        if (!$filter)
            return array();

        $select = self::$db->select('track', 't')
            ->join('trackstat', array('track_id' => 'id'), 's', array('lastplay', 'rate'));

        foreach ($filter['items'] as $key => $value) {
            if ($key == 'path')
                $select->where('t.uri', 'LIKE '.$value.'%')->addFlags(FDataBase::SQL_USEFUNCS);
            else
                $select->where('t.'.$key, $value);
        }
        $select->order($order, (bool) $orderDesc);

        $items = $select->fetchAll();

        return $items;
    }

    public static function getTracks($where, $order = 'lastplay', $orderDesc = true)
    {
        if (!is_array($where)) {
            $where = array('id' => $where);
        }

        $select = self::$db->select('track', 't')
            ->join('trackstat', array('track_id' => 'id'), 's', array('lastplay', 'rate'));

        foreach ($where as $key => $value) {
            $select->where($key, $value);
        }
        $select->order($order, (bool) $orderDesc);

        $items = $select->fetchAll();

        return $items;
    }

    protected function __construct() {}

    const SQL_INFOHASH_UPDATE = 'update track set info_hash = md5(concat_ws("|"
        , ifnull(title, "")
        , ifnull(artist, "")
        , ifnull(album, "")
        , ifnull(genre, "")
        , SUBSTRING_INDEX(uri, "/", -1)))';
}

?>
