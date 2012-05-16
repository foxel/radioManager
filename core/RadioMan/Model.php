<?php

class RadioMan_Model extends FBaseClass
{
    protected static $_dbMap = array();

    /**
     * @param string $name
     * @param mixed $val
     * @return mixed
     */
    public function __set($name, $val)
    {
        $setter = 'set'.ucfirst($name);
        if (method_exists($this, $setter)) {
            $this->$setter($val);
        } elseif (isset($this->pool[$name]) && is_scalar($this->pool[$name])) {
            settype($val, gettype($this->pool[$name]));
            $this->pool[$name] = $val;
        }
        return $val;
    }

    /**
     * @param int $newId
     * @return RadioMan_Model
     */
    public function setId($newId)
    {
        $this->pool['id'] = (int) $newId;
        return $this;
    }

    /**
     * @return array
     */
    public function getDbMap()
    {
        return static::$_dbMap;
    }
}
