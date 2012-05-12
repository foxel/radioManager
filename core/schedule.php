<?php

class Core_Schedule 
{
    const MODE_DAY  = 1;
    const MODE_WEEK = 2;
    protected static $db;
    public static $tz = 0;

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

    public static function getCurrentShow($time = false)
    {
        if (!$time)
            $time = time() + self::$tz*3600;

        $schedule = self::loadSchedule($time - 24*3600, $time);
        $today = array_pop($schedule);

        $show = array();
        $ts   = gmdate('H:i:s', $time);
        foreach ($today as $item) {
            if ($item['time'] > $ts)
                break;
            else 
                $show = $item;
        }

        if (!$show) {
            $yeasterday = array_pop($schedule);
            $show = array_pop($yeasterday);
        }

        return $show;
    }

    public static function getSchedule($mode, $time = false)
    {
        if (!$time) 
            $time = time() + (int) self::$tz*3600;

        switch ($mode) {
            case self::MODE_WEEK: 
                $wday = gmdate('w', $time);
                $startTime = $time - ($wday ? ($wday-1)*3600*24 : 6*3600*24);
                $endTime = $startTime + 6*3600*24;
                $schedule = self::loadSchedule($startTime, $endTime);
                break;
            case self::MODE_DAY:
                $schedule = self::loadSchedule($time);
                $schedule = array_pop($schedule);
                break;
            default:
                $schedule = array();
        }

        return $schedule;
    }

    public static function loadSchedule($startTime, $endTime = false)
    {
        $startDate = gmdate('Y-m-d', $startTime);
        $startTime = intval($startTime/(3600*24)) * 3600 * 24;
        if (!$endTime || $endTime < $startTime) 
            $endTime = $startTime;
        $endDate   = gmdate('Y-m-d', $endTime);
        $endTime   = intval($endTime/(3600*24)) * 3600 * 24 + 3600;

        $select = self::$db->select('schedule', 's')
            ->join('show', array('id' => 's.show_id'), 'sh')
            ->where('s.start_date <= ? OR s.start_date IS NULL', $endDate)
            ->where('s.end_date >= ? OR s.end_date IS NULL', $startDate)
            ->order('s.start_date')
            ->order('sh.length');

        $data = $select->fetchAll();

        $schedule = array();
        $backfill = array();
        for ($time = $startTime; $time <= $endTime; $time += 3600 * 24) {
            $shday = array();
            $bfday = array();
            $date  = gmdate('Y-m-d', $time);
            $wday  = gmdate('D', $time);
            foreach ($data as $item) {
                if (($item['end_date'] && $item['end_date'] < $date)
                    || ($item['start_date'] && $item['start_date'] > $date)
                    || ($item['weekdays'] && !strstr($item['weekdays'], $wday)))
                    continue;

                $shday[$item['time']] = $item;
                if ($item['is_backfill']) {
                    $bfday[$item['time']] = $item;
                }
            }
            ksort($shday);
            ksort($bfday);
            $schedule[$date] = $shday;
            $backfill[$date] = $bfday;
        }

        $clearTo   = false;
        $lastItem  = false;
        $clearWith = false;
        //TODO: rebuild
        foreach ($schedule as $date => &$shday) {
            $bfday = $backfill[$date];
            $shDayNew = array();
            foreach ($shday as $time => $item) {
                $fTime = $date.' '.$time;
                if ($clearTo !== false && $clearWith) {
                    if ($fTime < $clearTo) {
                        $clearWith = $item;
                        continue;
                    } else {
                        if ($clearTo < $fTime) {
                            list(,$ctTime) = explode(' ', $clearTo);
                            $clearWith['time'] = $ctTime;
                            $shDayNew[$ctTime] = $clearWith;
                        }
                        $lastItem = $clearWith;
                        $clearTo = false;
                    }
                } 
                if ($item['length']) {
                    if ($clearTo === false) {
                        $clearWith = (isset($bfday[$time]))
                            ? $bfday[$time]
                            : $lastItem;
                    }
                    $clearTo   = date('Y-m-d H:i:s', strtotime($fTime) + (int) $item['length']);
                }
                $shDayNew[$time] = $item;

                $lastItem = $item;
            }
            ksort($shDayNew);
            $shday = $shDayNew;
        }

        return $schedule;
    }



}
