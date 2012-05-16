<?php

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read int $filterId
 * @property-read RadioMan_Model_TrackFilter|null $filter
 * @property-read int[] $trackIds
 */
class RadioMan_Model_TrackList extends RadioMan_Model
{
    /**
     * @param array $data
     */
    function __construct(array $data = array())
    {
        $this->pool = array(
            'id'       => isset($data['id']) ? (int)$data['id'] : null,
            'name'     => isset($data['name']) ? (string)$data['name'] : null,
            'filterId' => isset($data['filterId']) && !is_null($data['filterId']) ? (int)$data['filterId'] : null,
            'filter'   => null,
            'trackIds' => array(),
        );

        if (isset($data['trackIds'])) {
            $this->setTrackIds($data['trackIds']);
        }
    }

    /**
     * @param array $trackIds
     * @return RadioMan_Model_TrackList
     */
    public function setTrackIds(array $trackIds)
    {
        $this->pool['trackIds'] = array();
        if (!empty($trackIds)) {
            $this->addTrackIds($trackIds);
        }

        return $this;
    }

    /**
     * @param array $trackIds
     * @return RadioMan_Model_TrackList
     */
    public function addTrackIds(array $trackIds)
    {
        foreach ($trackIds as $value) {
            if (is_scalar($value)) {
                $this->pool['trackIds'][] = (int) $value;
            } elseif (is_array($value) && isset($value['trackId'])) {
                $this->pool['trackIds'][] = (int)$value['trackId'];
            }
        }

        return $this;
    }

    /**
     * @param RadioMan_Model_TrackFilter $filter
     */
    public function setFilter(RadioMan_Model_TrackFilter $filter)
    {
        $this->pool['filter']   = $filter;
        $this->pool['filterId'] = $filter->id;
        // resetting track ids list
        $this->pool['trackIds'] = array();
    }
}
