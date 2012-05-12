<?php

class Core_MpdControl 
{
    protected static $mpc;

    public static function init(FMPC $mpc = null)
    {
        self::$mpc = $mpc ? $mpc : F()->MPC('localhost');
    }

    public static function mpc()
    {
        return self::$mpc;
    }

}
