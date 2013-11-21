<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Monday 12 July 2010, 11:44:15
 * Last Modified: Saturday 17 September 2011, 21:49:27
 *
 * $Id: stream.class.php 710 2011-09-17 20:55:53Z chris $
 */

require_once "DB/data.class.php";
require_once "DVB/channel.class.php";
require_once "Shell/background.class.php";
require_once "DVB/dvbctrl.class.php";
require_once "file.php";


class Stream extends Data
{
    private $log=false;
    private $canlog=false;
    private $progmap;
    private $streamnumber=0;
    private $bkg=false;
    private $config=false;
    private $streamtype="tv";
    private $streamstopped=true;
    private $nice=0;

    public function __construct($id=0,$log=false,$streamnumber=0,$config=false,$nice=0)/*{{{*/
    {
        $this->Data("streams","id",$id,$config,$log);
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
        if($streamnumber){
            $this->streamnumber=$streamnumber;
        }
        $this->progmap=array(
            "start"=>"start",
            "stop"=>"stop",
            "mux"=>"mux",
            "pid"=>"id",
            "cid"=>"channel");
        $this->config=$config;
        $this->nice=$nice;
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        if($this->isRunning()){
            $this->stopStream();
        }
        $this->logg("Stream: __destruct: " . $this->data_id,LOG_DEBUG);
        if($this->deleteMe()){
            $this->logg("Stream: __destruct: record deleted ok.",LOG_DEBUG);
        }else{
            $this->logg("Stream: __destruct: failed to delete record.",LOG_DEBUG);
        }
    }/*}}}*/
    private function logg($msg,$level=LOG_INFO)/*{{{*/
    {
        if($this->canlog){
            $this->log->message($msg,$level);
        }
    }/*}}}*/
    private function dvbConnect()/*{{{*/
    {
        $this->logg("dvbConnect: connecting to dvbstreamer",LOG_DEBUG);
        $dvbc=new DVBCtrl();
        if($dvbc->connect()){
            $this->logg("dvbConnect: connected.",LOG_DEBUG);
            return $dvbc;
        }
        $this->logg("dvbConnect: failed.",LOG_DEBUG);
        return false;
    }/*}}}*/
    public function isRunning()/*{{{*/
    {
        if($this->arr["filternumber"]!==false){
            return $this->bkg->isRunning();
        }
        return false;
    }/*}}}*/
    public function stopStream()/*{{{*/
    {
        $bkg=false;
        $stream=false;
        if($this->isRunning()){
            if(false==$this->bkg->stop()){
                $this->logg("stopStream: failed to stop background command " . $this->arr["cmd"],LOG_DEBUG);
            }else{
                $this->logg("stopStream: stopped background command " . $this->arr["cmd"],LOG_DEBUG);
                $bkg=true;
            }
        }else{
            $bkg=true;
        }
        if(!$this->streamstopped){
            if(false!==($dvbc=$this->dvbConnect())){
                if(true===($dvbc->stopByFilterNumber($this->arr["filternumber"]))){
                    $this->logg("stopStream: udp stream stopped.",LOG_DEBUG);
                    $stream=true;
                }else{
                    $this->logg("stopStream: failed to stop udp stream.",LOG_DEBUG);
                }
            }else{
                $this->logg("stopStream: failed to connect to dvbstreamer",LOG_DEBUG);
            }
            $dvbc=null;
        }else{
            $stream=true;
        }
        if($bkg && $stream){
            $this->streamstopped=true;
            return true;
        }
        return false;
    }/*}}}*/
    public function startStream()/*{{{*/
    {
        if(is_string($this->arr["channel"]) && strlen($this->arr["channel"])){
            if(false!==($dvbc=$this->dvbConnect())){
                if(false!==($this->arr["filternumber"]=$dvbc->streamNewService($this->arr["channel"],$this->arr["port"]))){
                    sleep($this->config["dvbstreamertuningtime"]);
                    $this->streamstopped=false;
                    $this->bkg=new BackgroundCommand($this->arr["cmd"],$this->nice,$this->arr["directory"]);
                    $this->bkg->run();
                    sleep($this->config["mencoderfailtime"]);
                    if($this->isRunning()){
                        $this->logg("startStream: stream started for channel " . $this->arr["channel"] . " as " . $this->config["streamaddress"] . $this->arr["port"],LOG_DEBUG);
                        $this->logg("startStream: " . $this->arr["cmd"],LOG_DEBUG);
                        $dvbc=null;
                        return true;
                    }else{
                        $this->logg("startStream: Mencoder/Mplayer failed to run.",LOG_WARNING);
                    }
                }else{
                    $this->logg("startStream: Failed to start stream, no filter number",LOG_DEBUG);
                }
            }else{
                $this->logg("startStream: Failed to connect to dvbstreamer",LOG_DEBUG);
            }
        }else{
            $this->logg("startStream: channel name not set, cannot start stream",LOG_WARNING);
        }
        $this->logg("startStream: failed to start stream for channel " . $this->arr["channel"] . " as " . $this->config["streamaddress"] . $this->arr["port"],LOG_DEBUG);
        $dvbc=null;
        return false;
    }/*}}}*/
    public function newStreamSetup($channel,$prog=false)/*{{{*/
    {
        $channel=intval($channel);
        if($channel){
            $c=new Channel($channel);
            if($channel==$c->getData("id") && ($cname=$c->getName()) && strlen($cname)){
                $this->arr["channel"]=$cname;
                if($prog===false){
                    $prog=$c->progNow();
                }
                if(false!==$prog){
                    if(isset($prog["title"])){
                        $sfn=makeSensibleFilename($prog["title"]);
                        if(strlen($sfn)){
                            if(false!==($dfn=noClobberDir(unixPath($this->config["tvdir"]) . $sfn))){
                                if(false!==mkdir($dfn,0755)){
                                    $this->arr["directory"]=basename($dfn);
                                    $this->arr["cmd"]=false;
                                    $this->arr["port"]=$this->config["streambase"]+$this->streamnumber;
                                    $this->streamtype=$c->getData("type");
                                    switch($this->streamtype){
                                    case "radio":
                                        $this->arr["cmd"]=$this->config["mplayer"] . " " . $this->config["streamaddress"] . $this->arr["port"] . " " . $this->config["mpopts"];
                                        $ext=".mp2";
                                        break;
                                    case "tv":
                                        $pids="";
                                        $aid=intval($c->getData("aid"));
                                        $vid=intval($c->getData("vid"));
                                        if($aid && $vid){
                                            $pids=" -aid $aid -vid $vid ";
                                        }
                                        if($this->config["usevlc"]){
                                            $this->arr["cmd"]=$this->config["vlc"] . " " . $this->config["vlcstreamaddress"] . $this->arr["port"] . " " . $this->config["vlcopts"];
                                        }else{
                                            $this->arr["cmd"]=$this->config["mencoder"] . " " . $this->config["streamaddress"] . $this->arr["port"] . $pids . $this->config["mencopts"];
                                        }
                                        $ext=".mpg";
                                        break;
                                    }
                                    if($this->arr["cmd"]!==false){
                                        // $this->arr["filename"]=$sfn . $ext;
                                        $this->arr["filename"]=basename(noClobberFile(unixPath($dfn) . $sfn . $ext));
                                        $this->arr["cmd"].=unixPath($dfn) . $this->arr["filename"];
                                        foreach($this->progmap as $key=>$val){
                                            $this->arr[$key]=$prog[$val];
                                        }
                                        $this->arr["streamnumber"]=$this->streamnumber;
                                        $this->updateDB();
                                        return $this->data_id;
                                    }else{
                                        $this->logg("newStreamSetup: Not a TV or Radio channel",LOG_WARNING);
                                    }
                                }else{
                                    $this->logg("newStreamSetup: cannot create directory $dfn",LOG_WARNING);
                                }
                            }else{
                                $this->logg("newStrem: cannot make unique directory from " . $prog["title"],LOG_WARNING);
                            }
                        }else{
                            $this->logg("newStreamSetup: cannot create sensible filename from " . $prog["title"],LOG_WARNING);
                        }
                    }else{
                        $this->logg("newStreamSetup: hmm, title not set",LOG_WARNING);
                    }
                }else{
                    $this->logg("newStreamSetup: Unable to determine current program information",LOG_WARNING);
                }
            }else{
                $this->logg("newStreamSetup: invalid channel ($channel)",LOG_WARNING);
            }
        }else{
            $this->logg("newStreamSetup: not a valid value for channel id ($channel)",LOG_WARNING);
        }
        return false;
    }/*}}}*/
    public function newChannelStreamSetup($channel,$stop)/*{{{*/
    {
        $channel=intval($channel);
        $stop=intval($stop);
        $now=time();
        if($channel){
            if($stop>$now){
                if($channel==$c->getData("id") && ($cname=$c->getName()) && strlen($cname)){
                    $this->arr["channel"]=$cname;
                    $sfn=makeSensibleFilename($cname);
                    if(strlen($sfn)){
                        if(false!==($dfn=noClobberDir(unixPath($this->config["tvdir"]) . $sfn))){
                            if(false!==mkdir($dfn,0755)){
                                $this->arr["directory"]=basename($dfn);
                                $this->arr["cmd"]=false;
                                $this->arr["port"]=$this->config["streambase"]+$this->streamnumber;
                                $this->streamtype=$c->getData("type");
                                switch($this->streamtype){
                                case "radio":
                                    $this->arr["cmd"]=$this->config["mplayer"] . " " . $this->config["streamaddress"] . $this->arr["port"] . " " . $this->config["mpopts"];
                                    $ext=".mp2";
                                    break;
                                case "tv":
                                    $aid=$c->getData("aid");
                                    $vid=$c->getData("vid");
                                    $this->arr["cmd"]=$this->config["mencoder"] . " " . $this->config["streamaddress"] . $this->arr["port"] . " -aid $aid -vid $vid " . $this->config["mencopts"];
                                    $ext=".mpg";
                                    break;
                                }
                                if($this->arr["cmd"]!==false){
                                    $this->arr["filename"]=basename(noClobberFile(unixPath($dfn) . $sfn . $ext));
                                    $this->arr["cmd"].=unixPath($dfn) . $this->arr["filename"];
                                    $this->arr["start"]=$now;
                                    $this->arr["stop"]=$stop;
                                    $this->arr["mux"]=$c->getData("mux");
                                    $this->arr["cid"]=$channel;
                                    $this->arr["pid"]=0;
                                    $this->arr["streamnumber"]=$this->streamnumber;
                                    $this->updateDB();
                                    return $this->data_id;
                                }else{
                                    $this->logg("newChannelStreamSetup: Not a TV or Radio channel $cname",LOG_WARNING);
                                }
                            }else{
                                $this->logg("newChannelStreamSetup: cannot create directory $dfn",LOG_WARNING);
                            }
                        }else{
                            $this->logg("newChannelStreamSetup: cannot make unique directory from $cname",LOG_WARNING);
                        }
                    }else{
                        $this->logg("newChannelStreamSetup: cannot create sensible filename from $cname",LOG_WARNING);
                    }
                }else{
                    $this->logg("newChannelStreamSetup: invalid channel ($channel)",LOG_WARNING);
                }
            }else{
                logg("newChannelStreamSetup: $stop is less than $now",LOG_WARNING);
            }
        }else{
            $this->logg("newChannelStreamSetup: not a valid value for channel id ($channel)",LOG_WARNING);
        }
        return false;
    }/*}}}*/
    public function streamType()/*{{{*/
    {
        return $this->streamtype;
    }/*}}}*/
}
?>
