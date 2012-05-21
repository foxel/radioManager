<?php

class RadioMan_Service_MpdControl
{
    protected static $mpc;

    public function __destruct(K3_Config $config)
    {
        self::$mpc = new FMPC($config->host, $config->port, $config->password);
    }

    public static function mpc()
    {
        return self::$mpc;
    }


}
