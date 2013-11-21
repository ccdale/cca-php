<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * previousrecorded.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Saturday 12 December 2009, 17:08:57
 * Last Modified: Sunday 18 September 2011, 11:57:21
 * Version: $Id: previousrecorded.class.php 713 2011-09-18 10:57:45Z chris $
 */

require_once "DB/mysql.class.php";
require_once "DB/data.class.php";

class PreviousRecorded extends Data
{
    private $log=false;
    private $canlog=false;
    private $config=false;

    public function __construct($parr,$log=false,$config=false)
    {
        $this->config=$config;
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
        $this->Data("previousrecorded","id",0,false,$log);
        if(is_array($parr)){
            if(isset($parr["id"])){
                unset($parr["id"]);
            }
            if(isset($parr["record"])){
                unset($parr["record"]);
            }
            $res=false;
            if(false!==$this->config){
                if(isset($this->config["noprevious"]) && is_array($this->config["noprevious"])){
                    foreach($this->config["noprevious"] as $pattern){
                        $res=preg_match($pattern,$parr["title"]);
                        if($res){
                            break;
                        }
                    }
                }
            }
            if(!$res){
                $this->arr=$parr;
                $this->updateDB();
                if($this->canlog){
                    $this->log->message("PreviousRecorded::__construct: adding record for " . $parr["title"],LOG_DEBUG);
                }
            }
        }
    }
    public function __destruct()
    {
    }
}
?>
