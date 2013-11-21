<?php

/** {{{ heading block
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * video.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Thursday  5 February 2009, 05:45:24
 * Version: 
 * Last Modified: Saturday 10 December 2011, 12:35:06
 *
 * $Id: video.php 714 2011-12-08 00:50:27Z chris $
 */
// }}}

require_once "file.php";

function videoDuration($file) // {{{
{
    $ret=false;
    if(checkFile($file,CCA_FILE_EXIST)){
        $cmd="dat=`/usr/bin/ffmpeg -i '" . $file . "' 2>&1 |grep Duration |cut -d\" \" -f4`; dat=\${dat%%.*}; echo \$dat";
        $tmp=exec($cmd);
        $ret=hmsToSec($tmp);
    }
    return $ret;
} // }}}
function videoDurationHMS($file)
{
    return secToHMS(videoDuration($file));
}
function hmsToSec($hms) // {{{
{
    $i=0;
    if(strpos($hms,".")!==false){
        $tarr=explode(".",$hms);
        $ii=intval($tarr[1]);
        if($ii>499){
            $i=1;
        }
    }
    $arr=explode(":",$hms);
    $cn=0;
    if(is_array($arr) && (3==($cn=count($arr)))){
        return ($arr[0]*3600)+($arr[1]*60)+$arr[2]+$i;
    }elseif(2==$cn){
        return ($arr[0]*60)+$arr[1]+$i;
    }else{
        return "";
    }
} // }}}
function secToHMS($sec,$showdays=false) // {{{
{
    $days=0;
    if($showdays){
        $days=intval($sec/86400);
        $sec=$sec%86400;
    }
    $hrs=intval($sec/3600);
    $rem=$sec%3600;
    $mins=intval($rem/60);
    $rem=$rem%60;
    if($days==1){
        $daysstring="day";
    }else{
        $daysstring="days";
    }
    if($showdays){
        $tmp=sprintf("%d $daysstring, %02d:%02d:%02d",$days,$hrs,$mins,$rem);
    }else{
        $tmp=sprintf("%02d:%02d:%02d",$hrs,$mins,$rem);
    }
    return $tmp;
} // }}}
?>
