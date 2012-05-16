<?php

class RadioMan_Model_Track extends RadioMan_Model
{
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
