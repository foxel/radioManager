<?php

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read array[] $items
 */
class RadioMan_Model_TrackFilter extends RadioMan_Model
{
    /**
     * @param array $data
     */
    function __construct(array $data = array())
    {
        $this->pool = array(
            'id'    => isset($data['id'])   ? (int)$data['id']      : null,
            'name'  => isset($data['name']) ? (string)$data['name'] : '',
            'items' => array(),
        );

        if (isset($data['items'])) {
            $this->setItems($data['items']);
        }
    }

    /**
     * @param array $items
     * @return RadioMan_Model_TrackFilter
     */
    public function setItems(array $items)
    {
        $this->pool['items'] = array();
        foreach ($items as $row) {
            if (is_array($row) && isset($row['type']) && isset($row['value'])) {
                if (!isset($this->pool['items'][$row['type']])) {
                    $this->pool['items'][$row['type']] = array((string) $row['value']);
                } else {
                    $this->pool['items'][$row['type']][] = (string) $row['value'];
                }
            }
        }

        return $this;
    }

    /**
     * @param string $type
     * @param string $value
     * @return RadioMan_Model_TrackFilter
     */
    public function addItem($type, $value)
    {
        if (!isset($this->pool['items'][$type])) {
            $this->pool['items'][$type] = array((string)$value);
        } else {
            $this->pool['items'][$type][] = (string)$value;
        }

        return $this;
    }
}
