<?php
class RadioMan_Model_Track_Stat extends RadioMan_Model
{
    /**
     * @param array $data
     */
    public function __construct(array $data = array())
    {
        $this->pool = array(
            'trackId'  => isset($data['trackId'])  ? (int) $data['trackId']  : null,
            'lastPlay' => isset($data['lastPlay']) ? (int) $data['lastPlay'] : null,
            'listened' => isset($data['listened']) ? (int) $data['listened'] : 0,
            'played'   => isset($data['played'])   ? (int) $data['played']   : 0,
            'lastVote' => isset($data['lastVote']) ? (int) $data['lastVote'] : null,
            'votes'    => isset($data['votes'])    ? (int) $data['votes']    : 0,
            'playRate' => isset($data['playRate']) ? (int) $data['playRate'] : 0,
            'voteRate' => isset($data['voteRate']) ? (int) $data['voteRate'] : 0,
            'rate'     => isset($data['rate'])     ? (int) $data['rate']     : 0,
        );

    }
}
