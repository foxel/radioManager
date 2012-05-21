<?php

class RadioMan_Service_TrackDb
{
    /** @var FDataBase */
    protected $db;

    /**
     * @static
     * @param FDataBase|K3_Config $db
     * @throws FException
     */
    public function __construct(FDataBase $db = null)
    {
        if ($db instanceof FDataBase) {
            /** @var $db FDataBase */
            if (!$db->check()) {
                throw new FException('Invalid DB');
            }
            $this->db = $db;
        } elseif ($db instanceof K3_Config) {
            $this->db = new FDataBase();
            $this->db->connect(
                array(
                    'dbname' => $db->database,
                    'host'   => $db->host,
                ),
                $db->username,
                $db->password,
                $db->prefix
            );
        } else {
            $this->db = F()->DBase;
        }
    }

    public function getTrackId(array $mpdTrack = null)
    {
        if (empty($mpdTrack) || !$mpdTrack['file'])
            return false;

        // search by hash
        if ($id = $this->findTrackPathId($mpdTrack['file'])) {
            return $id;
        }

        $track = RadioMan_Model_Track::fromMPDInfo($mpdTrack);

        // search by info_hash (in case the file was moved inside MPD lib dir)
        if ($id = $this->db->doSelect('track', 'id', RadioMan_Model_Track::mapFilter(array('infoHash' => $track->infoHash), false))) {
            $track->id = $id;
            $this->db->doUpdate('track', $track->mapToDb(), array('id' => $id));
            return $id;
        }

        // not found - need to add
        $id = $this->db->doInsert('track', $track->mapToDb());
        $track->id = $id;

        return $id;
    }

    public function findTrackPathId($trackPath)
    {
        if (empty($trackPath))
            return false;

        $hash = md5($trackPath);
        // search by hash
        if ($id = $this->db->doSelect('track', 'id', RadioMan_Model_Track::mapFilter(array('uriHash' => $hash), false))) {
            return $id;
        }

        return false;
    }

    public function setTrackPlay($trackId, $time, $listeners)
    {
        if (!$trackId) {
            return;
        }

        if ($trackId instanceof RadioMan_Model_Track) {
            $trackId = $trackId->id;
        }

        if ($play = $this->getTrackPlay($trackId, $time)) {
            if ($play['listeners'] < $listeners)
                $this->db->doUpdate('trackplay', array('listeners' => (int) $listeners), array('id' => $play['id']));
        }
        else {
            $this->db->doInsert('trackplay', array(
                'track_id'  => (int) $trackId,
                'time'      => (int) $time,
                'listeners' => (int) $listeners,
            ));
        }
    }

    public function getTrackPlay($trackId, $time)
    {
        if (!$trackId) {
            return null;
        }

        if ($trackId instanceof RadioMan_Model_Track) {
            $trackId = $trackId->id;
        }

        return $this->db->select('trackplay')
            ->where('track_id', (int) $trackId)
            ->where('time', '>= '.((int) $time - 2))
            ->where('time', '<= '.((int) $time + 2))
            ->fetchOne(FDataBase::SQL_USEFUNCS);
    }

    public function getTrackLastPlay($trackId)
    {
        if (!$trackId) {
            return null;
        }

        if ($trackId instanceof RadioMan_Model_Track) {
            $trackId = $trackId->id;
        }

        return $this->db->select('trackplay')
            ->where('track_id', (int) $trackId)
            ->order('time', true)
            ->fetchOne();
    }

    public function voteForTrack($trackId, $rate, $time = false, $ip = false)
    {
        if (!$trackId) {
            return null;
        }

        if ($trackId instanceof RadioMan_Model_Track) {
            $trackId = $trackId->id;
        }

        if (!is_int($time)) {
            $time = time();
        }
        if (!is_numeric($ip)) {
            $ip = F()->HTTP->IPInt;
        }
        $uid = FStr::shortUID();

        $data = array(
            'uid'      => $uid,
            'track_id' => (int) $trackId,
            'rate'     => max(0, min((int) $rate, 5)),
            'time'     => $time,
            'ip'       => $ip,
        );
            
        if ($this->db->doInsert('trackvote', $data)) {
            return $uid;
        }

        return null;
    }

    public function getVote($uid)
    {
        return $this->db->select('trackvote')
            ->where('uid', $uid)
            ->fetchOne();
    }

    public function getTrackList($id)
    {
        $select = $this->db->select('tracklist', 'l')
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
            $list['filter'] = $this->getTrackFilter($list['filter_id']);
        }

        return $list;
    }

    public function findTrackListId($name)
    {
        $select = $this->db->select('tracklist', 'l', array('id'))
            ->where('l.name', $name);

        return $select->fetchOne();
    }

    public function getTrackFilter($id)
    {
        $select = $this->db->select('trackfilter', 'f')
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

    public function getTracksByFilter($filterId, $order = 'lastplay', $orderDesc = true)
    {
        $filter = $this->getTrackFilter($filterId);

        if (!$filter)
            return array();

        $select = $this->db->select('track', 't')
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

    public function getTracks($where, $order = 'lastplay', $orderDesc = true)
    {
        if (!is_array($where)) {
            $where = array('id' => $where);
        }

        $select = $this->db->select('track', 't')
            ->join('trackstat', array('track_id' => 'id'), 's', array('lastplay', 'rate'));

        foreach ($where as $key => $value) {
            $select->where($key, $value);
        }
        $select->order($order, (bool) $orderDesc);

        $items = $select->fetchAll();

        return $items;
    }

    const SQL_INFOHASH_UPDATE = 'update track set info_hash = md5(concat_ws("|"
        , ifnull(title, "")
        , ifnull(artist, "")
        , ifnull(album, "")
        , ifnull(genre, "")
        , SUBSTRING_INDEX(uri, "/", -1)))';
}

?>
