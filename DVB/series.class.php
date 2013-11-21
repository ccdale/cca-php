<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * series.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Saturday  9 January 2010, 10:37:40
 * Last Modified: Saturday 17 September 2011, 21:49:08
 * Version: $Id: series.class.php 710 2011-09-17 20:55:53Z chris $
 */


require_once "DVB/program.class.php";

class Series extends Data
{
    private $log=false;
    private $canlog=false;
    private $config=false;

    public function __construct($id=0,$log=false,$config=false)
    {
        $this->config=$config;
        if(!defined("PADSTART")){
            if(false==$this->config){
                define("PADSTART",2*60);
            }else{
                define("PADSTART",$this->config["padstart"]);
            }
        }
        $this->Data("futurerecord","id",$id,false,$log);
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
    }
    public function __destruct()
    {
    }
    public function selectByProgramId($programid="")
    {
        if(is_string($programid) && strlen($programid)){
            $rec=$this->mx->getAllResults("id","futurerecord","programid",$programid);
            if(is_array($rec) && count($rec)){
                if(isset($rec[0]) && isset($rec[0]["id"]) && $rec[0]["id"]){
                    $this->Data("futurerecord","id",$rec[0]["id"]);
                }
            }
        }
    }
    public function selectBySeriesId($seriesid="")
    {
        if(is_string($seriesid) && strlen($seriesid)){
            $rec=$this->mx->getAllResults("id","futurerecord","seriesid",$seriesid);
            if(is_array($rec) && count($rec)){
                if(isset($rec[0]) && isset($rec[0]["id"]) && $rec[0]["id"]){
                    $this->Data("futurerecord","id",$rec[0]["id"]);
                }
            }
        }
    }
    public function setByProgramId($programid="")
    {
        if(is_string($programid) && strlen($programid)){
            $rec=$this->mx->getAllResults("id","futurerecord","programid",$programid);
            if(is_array($rec) && count($rec)){
                if(isset($rec[0]) && isset($rec[0]["id"]) && $rec[0]["id"]){
                    $this->Data("futurerecord","id",$rec[0]["id"]);
                }
            }
            if(!$this->data_id){
                $this->setData("programid",$programid);
            }
        }
    }
    public function setBySeriesId($seriesid="")
    {
        if(is_string($seriesid) && strlen($seriesid)){
            $rec=$this->mx->getAllResults("id","futurerecord","seriesid",$seriesid);
            if(is_array($rec) && count($rec)){
                if(isset($rec[0]) && isset($rec[0]["id"]) && $rec[0]["id"]){
                    $this->Data("futurerecord","id",$rec[0]["id"]);
                }
            }
            if(!$this->data_id){
                $this->setData("seriesid",$seriesid);
            }
        }
    }
    public function setByTitleandChannel($title="",$channel=0)
    {
        if(is_string($title) && strlen($title)){
            if(is_int($channel) && $channel){
                $rec=$this->mx->getAllResults("id","futurerecord","title",$title,"and channel=$channel and seriesid is NULL and programid is NULL",MYSQL_ASSOC);
            }else{
                $rec=$this->mx->getAllResults("id","futurerecord","title",$title,"and seriesid is NULL and programid is NULL",MYSQL_ASSOC);
            }
            if(is_array($rec) && count($rec)){
                if(isset($rec[0]) && isset($rec[0]["id"]) && $rec[0]["id"]){
                    $this->Data("futurerecord","id",$rec[0]["id"]);
                }
            }
        }
        return $this->isInDB();
    }
    public function setNextSeries()
    {
        if($this->canlog){
            $this->log->message("SERIESRECORD: checking pid: " . $this->getData("programid") . ", sid: " . $this->getData("seriesid") . ", " . $this->getData("title"), LOG_DEBUG);
        }
        $now=time() + PADSTART;
        /* one shot record */
        $programid=$this->getData("programid");
        if(is_string($programid) && strlen($programid)){
            $rec=$this->mx->getAllResults("id","program","programid",$programid," and start>$now order by start",MYSQL_ASSOC);
            if(is_array($rec) && count($rec)){
                if(isset($rec[0]) && isset($rec[0]["id"]) && $rec[0]["id"]){
                    $p=new Program($rec[0]["id"]);
                    $p->recordThisProgram();
                    /*
                    $rr=$p->getData("record");
                    switch($rr){
                        case "n":
                        case "c":
                            $p->toggleRecord();
                            break;
                    }
                     */
                    if($p->getData("record")=="y"){
                        if($this->canlog){
                            $this->log->message("SERIESRECORD: single shot recording set " . $p->getData("title") . " at " . date("D d H:i",$p->getData("start")));
                        }
                        $this->deleteMe();
                    }
                }
            }
        }
        /* series record */
        $seriesid=$this->getData("seriesid");
        if(is_string($seriesid) && strlen($seriesid)){
            $rec=$this->mx->getAllResults("id","program","seriesid",$seriesid," and start>$now order by start",MYSQL_ASSOC);
            if(is_array($rec) && count($rec)){
                if(isset($rec[0]) && isset($rec[0]["id"]) && $rec[0]["id"]){
                    $p=new Program($rec[0]["id"]);
                    $rr=$p->getData("record");
                    $p->recordThisProgram();
                    /*
                    switch($rr){
                        case "n":
                        case "c":
                            $p->toggleRecord();
                            break;
                    }
                     */
                    $rr=$p->getData("record");
                    if($rr=="y"){
                        if($this->canlog){
                            $this->log->message("SERIESRECORD: " . $p->getData("title") . " set to be recorded at " . date("D d H:i",$p->getData("start")));
                        }
                    }elseif($rr=="l"){
                        $rec=$this->mx->getAllResults("id","program","record","y"," and seriesid='$seriesid' order by start",MYSQL_ASSOC);
                        if(is_array($rec) && count($rec)){
                            if(isset($rec[0]) && isset($rec[0]["id"]) && $rec[0]["id"]){
                                $p=new Program($rec[0]["id"]);
                                $rr=$p->getData("record");
                                if($rr=="y"){
                                    if($this->canlog){
                                        $this->log->message("SERIESRECORD: " . $p->getData("title") . " set to be recorded at " . date("D d H:i",$p->getData("start")));
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    public function isInDB()
    {
        if($this->data_id){
            return true;
        }else{
            return false;
        }
    }
    public function getAllSeriesData()
    {
        return $this->mx->getAllResults("*","futurerecord",1,0,"",MYSQL_ASSOC);
    }
    public function oneShot()
    {
        return $this->mx->getAllResults("*","futurerecord",1,0,"and programid is not NULL order by title",MYSQL_ASSOC);
    }
    public function getSeries()
    {
        return $this->mx->getAllResults("*","futurerecord",1,0,"and seriesid is not NULL order by title",MYSQL_ASSOC);
    }
    public function byTitleandChannel()
    {
        return $this->mx->getAllResults("*","futurerecord",1,0,"and seriesid is NULL and programid is null order by title",MYSQL_ASSOC);
    }
}
?>
