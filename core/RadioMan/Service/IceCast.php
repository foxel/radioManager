<?php

class RadioMan_Service_IceCast extends FEventDispatcher
{
    const RELAY_NAME_MATCH_REGEXP = '#^r_|Relay#';
    const URL_SUFFIX = '.xspf';

    /** @var array */
    protected $_streams = array();

    /**
     * @param array|string|null $streamsHash
     */
    public function __construct($streamsHash = null)
    {
        if (!empty($streamsHash)) {
            $this->_streams = (array) $streamsHash;
        }
    }

    /**
     * @return mixed
     */
    public function getListenersCount()
    {
        $sourcesCount = 0; // source stations listeners count
        $relaysCount  = 0; // relay listeners count

        $stats = self::getStats($this->_streams);
        foreach ($stats as $name => $stat) {
            if (empty($stat) || !isset($stat['CurrentListeners']))
                continue;

            if (preg_match(self::RELAY_NAME_MATCH_REGEXP, $name)) { // all relays names must match relay regexp
                $relaysCount += $stat['CurrentListeners'];
                $sourcesCount--; // each relay is connected to some station and is counted as listener so we'll decrease stations listeners count
            }
            else {
                $sourcesCount += $stat['CurrentListeners'];
            }

        }
        return (max($sourcesCount, 0) + $relaysCount);
    }

    /**
     * @static
     * @param string $url
     * @return mixed
     */
    public static function getStreamStats($url)
    {
        list($res) = self::getStats(array($url));
        return $res;
    }

    /**
     * @static
     * @param array $urls
     * @return array
     */
    protected static function getStats(array $urls)
    {
        if (empty($urls))
            return array();

        $res  = array();
        if (function_exists('curl_multi_init')) {
            $master   = curl_multi_init();
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

            foreach ($curl_arr as $key => $curl) {
                $info = curl_getinfo($curl);
                if ($info['http_code'] == 200 && $info = curl_multi_getcontent($curl)) {
                    $res[$key] = self::parseIcecastResponse($info);
                }
            }
        }
        else {
            foreach ($urls as $key => $url) {
                if ($info = file_get_contents($url.self::URL_SUFFIX)) {
                    $res[$key] = self::parseIcecastResponse($info);
                }
            }
        }

        return $res;
    }

    /**
     * @static
     * @param $response
     * @return array
     */
    protected static function parseIcecastResponse($response)
    {
        $res = array();
        $response  = preg_replace('#(xml)?ns\s*=\s*"[^"]*"#i', '', $response);
        $x   = new SimpleXMLElement($response);
        list($t) = $x->xpath('/playlist/trackList/track');
        $s = (string)$t->annotation;
        $s = explode("\n", trim($s, "\n"));
        foreach ($s as &$l) {
            list($key, $val) = explode(':', $l, 2);
            $res[str_replace(' ', '', $key)] = trim($val);
        }
        return $res;
    }
}

