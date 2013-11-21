<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Saturday 21 August 2010, 20:00:58
 * Last Modified: Sunday 18 September 2011, 11:57:13
 *
 * $Id: channelrecording.class.php 713 2011-09-18 10:57:45Z chris $
 */

require_once "DB/data.class.php";
require_once "DVB/recordedfile.class.php";
require_once "DVB/channel.class.php";
require_once "DVB/program.class.php";
require_once "DVB/stream.class.php";

class ChannelRecording extends Data
{
    private $dbtable="recorded";
    private $log=false;
    private $canlog=false;
    private $config=false;
    private $channelid=0;
    private $channel=false;
    private $files=array(); // 1-based array
    private $numfiles=0;
    private $streams=array(); // 0-based array
    private $numstreams=0;
    private $amrecording=false;
    private $program=false;
    private $nextstreamnumber=0;
    private $lastsplit=0;
    private $isradio=false;

    public function __construct($id=0,$log=false,$config=false,$channel=0)
    {
        $this->config=$config;
        $this->Data($this->dbtable,"id",$id,false,$log);
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
        $this->channelid=intval($channel);
        if($this->channelid){
            return $this->sRC();
        }else{
            return $this->data_id;
        }
    }
    public function __destruct()
    {
    }
    private function logging($msg,$level=LOG_INFO)
    {
        if($this->canlog){
            $this->log->message($msg,$level);
        }
    }
    public function startRecordingChannel($channel=0)
    {
        $this->channelid=intval($channel);
        $this->sRC();
    }
    private function sRC()
    {
        if($this->channelid){
        }else{
            $this->logging("ChannelRecord: sRC: channel id not set",LOG_WARNING);
        }
        return false;
    }
    public function amRecording()
    {
        return $this->amrecording;
    }
}
?>
