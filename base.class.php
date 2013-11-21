<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 * 
 * base.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Friday 24 May 2013, 23:41:08
 * Last Modified: Wednesday  9 October 2013, 10:25:59
 * Revision: $Id: base.class.php 7515 2013-06-25 12:25:34Z chris.allison $
 * Version: 0.00
 */

class Base
{
    private $log=false;
    private $canlog=false;

    public function __construct($log=false)/*{{{*/
    {
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
    }/*}}}*/
    public function logg($msg,$level=LOG_INFO) /*{{{*/
    {
        if($this->canlog){
            $this->log->message($msg,$level);
        }
    }/*}}}*/
    public function ValidFile($fqf)/*{{{*/
    {
        $ret=false;
        if($this->ValidStr($fqf)){
            if(file_exists($fqf)){
                $ret=true;
            }
        }
        return $ret;
    }/*}}}*/
    public function ValidStr($str) /*{{{*/
    {
        if(is_string($str)){
            return strlen($str);
        }else{
            return false;
        }
    }/*}}}*/
    public function ValidArray($arr)/*{{{*/
    {
        if(is_array($arr)){
            return count($arr);
        }else{
            return false;
        }
    }/*}}}*/
    public function hmsToSec($hms) // {{{
    {
        if($this->ValidStr($hms)){
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
        }else{
            return false;
        }
    } // }}}
    public function secToHMS($sec,$showdays=false) // {{{
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
    private function loghelper($msg,$level)/*{{{*/
    {
        if($level==LOG_DEBUG){
            $class=get_class($this);
            $msg="Class " . $class . ": " . $msg;
        }
        $this->logg($msg,$level);
    }/*}}}*/
    protected function info($msg) // {{{
    {
        $this->loghelper($msg,LOG_INFO);
    } // }}}
    protected function debug($msg) // {{{
    {
        $this->loghelper($msg,LOG_DEBUG);
    } // }}}
    protected function notice($msg)/*{{{*/
    {
        $this->loghelper($msg,LOG_NOTICE);
    }/*}}}*/
    protected function warn($msg) // {{{
    {
        $this->loghelper($msg,LOG_WARNING);
    } // }}}
    protected function warning($msg) // {{{
    {
        $this->loghelper($msg,LOG_WARNING);
    } // }}}
    protected function error($msg) // {{{
    {
        $this->loghelper($msg,LOG_ERR);
    } // }}}
}
?>
