<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Thursday 25 March 2010, 17:55:03
 * Last Modified: Tuesday 19 April 2011, 11:31:36
 * Version: $Id: time.php 528 2011-04-19 10:33:01Z chris $
 */

function ttt($timestamp=0,$withseconds=false)
{
    $ts=intval($timestamp);
    if($withseconds){
        return date("H:i:s",$ts);
    }else{
        return date("H:i",$ts);
    }
}
function ttd($timestamp=0,$withmonth=false,$withyear=false)
{
    $ts=intval($timestamp);
    $dt=date("D d",$ts);
    if($withmonth){
        $dt.=" " . date("m",$ts);
    }
    if($withyear){
        $dt.=" " . date("Y",$ts);
    }
    return $dt;
}
function tdd($timestamp=0,$withseconds=false,$withmonth=false,$withyear=false)
{
    return ttd($timestamp,$withmonth,$withyear) . " " . ttt($timestamp,$withseconds);
}
?>
