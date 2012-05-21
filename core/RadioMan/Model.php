<?php

/**
 * @property int $id
 */
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
    public function mapToDb($exclude = 'id')
    {
        return static::mapModelToDb($this, static::getDbMap(), $exclude);
    }

    /**
     * @return array
     */
    public static function getDbMap()
    {
        return static::$_dbMap;
    }

    /**
     * @static
     * @param string $tableAlias
     * @param array|null $dbMap
     * @return array
     */
    public static function mapDbToModel($tableAlias, array $dbMap = null)
    {
        if (!$dbMap) {
            $dbMap = static::getDbMap();
        }

        $map = array();
        foreach ($dbMap as $modelKey => $dbKey) {
            $map[$modelKey] = $tableAlias
                ? implode('.', array($tableAlias, $dbKey))
                : $dbKey;
        }

        return $map;
    }

    /**
     * @static
     * @param RadioMan_Model $model
     * @param array|null $dbMap
     * @param string|array $exclude
     * @return array
     */
    public static function mapModelToDb(RadioMan_Model $model, array $dbMap = null, $exclude = 'id')
    {
        if (!$dbMap) {
            $dbMap = static::getDbMap();
        }

        $exclude = (array)$exclude;

        $map = array();
        foreach ($dbMap as $modelKey => $dbKey) {
            if (in_array($modelKey, $exclude)) {
                continue;
            }

            $map[$dbKey] = $model->$modelKey;
        }

        return $map;
    }

    /**
     * @static
     * @param array      $filter
     * @param string     $tableAlias
     * @param array|null $dbMap
     * @return array
     */
    public static function mapFilter(array $filter, $tableAlias, array $dbMap = null)
    {
        if (!$dbMap) {
            $dbMap = static::getDbMap();
        }

        $map = array();
        foreach ($dbMap as $modelKey => $dbKey) {
            if (!isset($filter[$modelKey])) {
                continue;
            }

            if ($tableAlias) {
                $dbKey = implode('.', array($tableAlias, $dbKey));
            }

            $map[$dbKey] = $filter[$modelKey];
        }

        return $map;
    }
}
