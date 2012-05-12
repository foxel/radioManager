<?php

class IcecastInfo
{
    public static function getStreamStats($url)
    {
        list($res) = self::getStats($url);
        return $res;
    }

    public static function getListenersCount(array $stations) 
    {
        $count_s = 0; // source stations listeners count
        $count_r = 0; // relay listeners count
        $stats = self::getStats($stations);
        foreach ($stats as $name => $stat) {
            if (empty($stat) || !isset($stat['CurrentListeners']))
                continue;
            if (strtolower($name[0]) == 'r') { // all relays have name starting with 'r'
                $count_r+= $stat['CurrentListeners'];
                $count_s--; // each relay is connected to some station and is counted as listener so we'll decrease stations listeners count
            }
            else
                $count_s+= $stat['CurrentListeners'];

        }
        return (max($count_s, 0) + $count_r);
    }

    const URL_SUFFIX = '.xspf';

    protected static function getStats($urls)
    {
        if (empty($urls))
            return null;
        $urls = (array) $urls;
        $res = array();
        if (function_exists('curl_multi_init')) {
            $master = curl_multi_init();
            $curl_arr = array();
            foreach ($urls as $key => $url) {
                $curl = curl_init($url.self::URL_SUFFIX);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
                curl_multi_add_handle($master, $curl);
                $curl_arr[$key] = $curl;
            }
            do {
                curl_multi_exec($master, $running);
            } while ($running > 0);

            foreach($curl_arr as $key => $curl)
            {
                $info = curl_getinfo($curl);
                if ($info['http_code'] == 200 && $info = curl_multi_getcontent($curl))
                    $res[$key] = self::parseIcecastResponse($info);
            }
        }
        else {
            foreach ($urls as $key => $url) {
                if ($info = file_get_contents($url.self::URL_SUFFIX))
                    $res[$key] = self::parseIcecastResponse($info);
            }
        }
        //print_r($res);
        return $res;
    }

    protected static function parseIcecastResponse($in)
    {
        $res = array();
        $in = preg_replace('#(xml)?ns\s*=\s*"[^"]*"#i', '', $in);
        $x = new SimpleXMLElement($in);
        list($t) = $x->xpath('/playlist/trackList/track');
        $s = (string) $t->annotation;
        $s = explode("\n", trim($s, "\n"));
        foreach ($s as &$l) {
            list($key, $val) = explode(':', $l, 2);
            $res[str_replace(' ', '', $key)] = trim($val);
        }
        return $res;
    }

    protected function __construct() {}
}

?>
