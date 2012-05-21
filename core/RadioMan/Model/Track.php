<?php

/**
 * @property string $uri
 * @property string $title
 * @property string $artist
 * @property string $album
 * @property string $genre
 * @property bool $isStream
 * @property int $albumTrack
 * @property string $uriHash
 * @property string $infoHash
 */
class RadioMan_Model_Track extends RadioMan_Model
{
    protected static $_dbMap = array(
        'id'         => 'id',
        'uri'        => 'uri',
        'title'      => 'title',
        'artist'     => 'artist',
        'album'      => 'album',
        'genre'      => 'genre',
        'albumTrack' => 'a_track',
        'isStream'   => 'is_stream',
        'uriHash'    => 'uri_hash',
        'infoHash'   => 'info_hash',
    );
    
    public static function fromMPDInfo(array $mpdTrack) {
        $mapped = array(
            'uri'        => isset($mpdTrack['file'])   ? $mpdTrack['file']   : null,
            'title'      => isset($mpdTrack['title'])  ? $mpdTrack['title']  : null,
            'artist'     => isset($mpdTrack['artist']) ? $mpdTrack['artist'] : null,
            'album'      => isset($mpdTrack['album'])  ? $mpdTrack['album']  : null,
            'genre'      => isset($mpdTrack['genre'])  ? $mpdTrack['genre']  : null,
            'albumTrack' => isset($mpdTrack['track'])  ? $mpdTrack['track']  : null,
        );

        $mapped['uriHash'] = md5($mapped['uri']);

        if (preg_match('#^https?://#', $mpdTrack['file'])) {
            $mapped['title']    = (isset($mpdTrack['name']) ? $mpdTrack['name'] : FStr::basename($mpdTrack['file'])).' (stream)';
            $mapped['artist']   = $mapped['album'] = null;
            $mapped['genre']    = 'stream';
            $mapped['isStream'] = true;
        }

        $track = new self($mapped);
        $track->updateInfoHash();
        return $track;
    }

    /**
     * @param array $data
     */
    public function __construct(array $data = array())
    {
        $this->pool = array(
            'id'         => isset($data['id']) ? (int)$data['id'] : null,
            'uri'        => isset($data['uri']) ? (string)$data['uri'] : '',
            'title'      => isset($data['title']) ? (string)$data['title'] : '',
            'artist'     => isset($data['artist']) ? (string)$data['artist'] : '',
            'album'      => isset($data['album']) ? (string)$data['album'] : '',
            'genre'      => isset($data['genre']) ? (string)$data['genre'] : '',
            'isStream'   => isset($data['isStream']) ? (bool)$data['isStream'] : false,
            'albumTrack' => isset($data['albumTrack']) ? (int)$data['albumTrack'] : 0,
            'uriHash'    => isset($data['uriHash']) ? (string)$data['uriHash'] : '',
            'infoHash'   => isset($data['infoHash']) ? (string)$data['infoHash'] : '',
        );

        $this->updateInfoHash();
    }

    /**
     * @param string $name
     * @param mixed $val
     * @return mixed
     */
    public function __set($name, $val)
    {
        parent::__set($name, $val);
        $this->updateInfoHash();
        return $val;
    }

    /**
     * @param string $newUri
     * @return RadioMan_Model_Track
     */
    public function setUri($newUri)
    {
        $this->pool['uri']     = (string) $newUri;
        $this->pool['uriHash'] = md5($this->pool['uri']);
        return $this;
    }

    /**
     * @return RadioMan_Model_Track
     */
    public function updateInfoHash()
    {
        $this->pool['infoHash'] = md5(implode('|', array(
            $this->pool['title'],
            $this->pool['artist'],
            $this->pool['album'],
            $this->pool['genre'],
            FStr::basename($this->pool['uri']),
        )));

        return $this;
    }

    /**
     * @param $newUriHash
     * @throws Exception
     */
    protected function setUriHash($newUriHash)
    {
        throw new Exception('Trying to set conditional field');
    }

    /**
     * @param $newInfoHash
     * @throws Exception
     */
    protected function setInfoHash($newInfoHash)
    {
        throw new Exception('Trying to set conditional field');
    }
}
