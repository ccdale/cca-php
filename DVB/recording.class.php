<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Wednesday 14 July 2010, 03:48:20
 * Last Modified: Saturday 17 September 2011, 21:48:05
 *
 * $Id: recording.class.php 710 2011-09-17 20:55:53Z chris $
 */

require_once "DB/data.class.php";
require_once "DVB/recordedfile.class.php";
require_once "DVB/channel.class.php";
require_once "DVB/program.class.php";
require_once "DVB/stream.class.php";

class Recording extends Data
{
    private $log=false;
    private $canlog=false;
    private $files=array(); // 1-based array
    private $numfiles=0;
    private $streams=array(); // 0-based array
    private $numstreams=0;
    private $channel=false;
    private $config=false;
    private $copyarray=false;
    private $amrecording=false;
    private $program=false;
    private $nextstreamnumber=0;
    private $lastsplit=0;
    private $isradio=false;

    public function __construct($id=0,$log=false,$config=false,$nextstreamnumber=0,$progid=0)
    {
        $this->config=$config;
        $id=intval($id);
        $progid=intval($progid);
        $this->Data("recorded","id",$id,$this->config,$log);
        if($id){
            $this->updateFileList();
            $this->updateChannel();
        }
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
        $this->copyarray=array(
            "title",
            "start",
            "stop",
            "channel",
            "description",
            "programid",
            "seriesid"
        );
        $this->setNextStreamNumber($nextstreamnumber);
        if($progid){
            return $this->startRecordingProgram($progid);
        }
        return false;
    }
    public function __destruct()
    {
        $this->logg("Recording: __destruct:",LOG_DEBUG);
        if($this->amRecording()){
            $this->stopRecording();
        }
    }
    private function logg($msg,$level=LOG_INFO)
    {
        if($this->canlog){
            $this->log->message($msg,$level);
        }
    }
    private function updateFileList()
    {
        if($this->data_id){
            $sql="and rid=" . $this->data_id . " order by fnum";
            $arr=$this->mx->getAllResults("id,fnum","rfile",1,0,$sql,MYSQL_ASSOC);
            if(is_array($arr) && 0!==($cn=count($arr))){
                foreach($arr as $rfile){
                    $this->files[$rfile["fnum"]]=new RecordedFile($rfile["id"],$this->log);
                }
                $this->numfiles=count($this->files);
            }
        }
    }
    public function numFiles()
    {
        return $this->numfiles;
    }
    public function getFile($filenum)
    {
        $filenum=intval($filenum);
        if($filenum>0 && $filenum<=$this->numfiles){
            return $this->files[$filenum];
        }
        return false;
    }
    private function updateChannel()
    {
        if($this->data_id){
            $this->channel=new Channel($this->getData("channel"),$this->log);
            $tmp=$this->channel->getData("type");
            if($tmp=="radio"){
                $this->isradio=true;
            }
        }
    }
    private function checkPids()
    {
        if($this->channel){
            return $this->channel->checkPids();
        }
        return false;
    }
    public function getCurrentMux()
    {
        return $this->channel->getData("mux");
    }
    public function amRecording()
    {
        $tmp=$this->getData("edit");
        $this->amrecording=$tmp=="r"?true:false;
        return $this->amrecording;
    }
    public function newStream()
    {
        $streamnumber=$this->nextstreamnumber;
        $this->streams[$this->numstreams]=new Stream(0,$this->log,$streamnumber,$this->config);
        if(false!==($streamid=$this->streams[$this->numstreams]->newStreamSetup($this->arr["channel"],$this->program->arr))){
            if(false!==$this->streams[$this->numstreams]->startStream()){
                $this->logg("Recording: startRecordingProgram: started streaming {$this->arr["title"]} to dir: " . $this->streams[$this->numstreams]->getData("directory") . " and file: " . $this->streams[$this->numstreams]->getData("filename"),LOG_DEBUG);
                $this->numstreams=count($this->streams);
                return true;
            }else{
                $this->logg("Recording: newStream: failed to start streaming {$this->arr["title"]}",LOG_DEBUG);
            }
        }else{
            $this->logg("Recording: newStream: failed to setup new stream",LOG_WARNING);
        }
        return false;
    }
    public function newFile()
    {
        $fnum=$this->numfiles+1;
        $streamnumber=$this->numstreams-1;
        $ret=$this->files[$fnum]=new RecordedFile(
            0,
            $this->log,
            $this->config,
            $this->streams[$streamnumber]->getData("filename"),
            $this->streams[$streamnumber]->getData("directory"),
            $this->data_id,
            $fnum,
            $this->streams[$streamnumber]->streamType()
        );
        $this->numfiles=count($this->files);
        return $ret;
    }
    public function startRecordingProgram($progid=0)
    {
        $pid=intval($progid);
        if($pid){
            $this->program=new Program($pid,$this->log,$this->config);
            foreach($this->copyarray as $caval){
                $this->arr[$caval]=$this->program->arr[$caval];
            }
            $this->arr["pid"]=$pid;
            $this->updateDB();
            if($this->data_id){
                $this->updateChannel();
                if($this->checkPids()){
                    if(false!==$this->newStream()){
                        $this->amrecording=true;
                        $this->setData("edit","r");
                        if(false!==$this->newFile()){
                            $this->program->setData("record","r");
                            $this->logg("Started to record {$this->arr["title"]}",LOG_INFO);
                            // $this->updateFileList();
                            $this->lastsplit=time();
                            return true;
                        }else{
                            $this->logg("Recording: startRecordingProgram: failed to create a new file entry for {$this->arr["title"]}",LOG_DEBUG);
                            $this->stopRecording(true);
                        }
                    }else{
                        $this->logg("Recording: startRecordingProgram: failed to create a new stream for {$this->arr["title"]}",LOG_DEBUG);
                        $this->stopRecording(true);
                    }
                }else{
                    $this->logg("Recording: startRecordingProgram: pid check failed: " . $this->channel->getName(),LOG_WARNING);
                }
            }else{
                $this->logg("Recording: startRecordingProgram: failed to create db entry for recording of programid: $pid ({$this->arr["title"]})",LOG_WARNING);
            }
        }else{
            $this->logg("Recording: startRecordingProgram: invalid progid: $progid",LOG_WARNING);
        }
        return false;
    }
    public function checkFileSize($nextstreamnumber)
    {
        $xtest=$this->checkLength($nextstreamnumber);
        if($xtest){
            return true;
        }else{
            $currentfnum=$this->numfiles;
            if($currentfnum){
                $fqfn=$this->files[$currentfnum]->fqfn();
                $fs=realFilesize($fqfn);
                $ffn=basename($fqfn);
                if($this->config["maxfilesize"]<$fs){
                    $this->logg("Recording: checkFileSize: $ffn: " . printableFilesize($fs) . ", splitting recording.",LOG_DEBUG);
                    if($this->splitRecording($nextstreamnumber)){
                        return true;
                    }
                }else{
                    $this->logg("Recording: checkFileSize: $ffn: " . printableFilesize($fs),LOG_DEBUG);
                }
            }
            return false;
        }
    }
    public function checkLength($nextstreamnumber)
    {
        // don't bother to split radio programs at the 
        // 30 minute mark.
        if(!$this->isradio){
            if(isset($this->config["maxproglengthbeforesplit"])){
                $now=time();
                $length=$this->lastsplit;
                $this->logg("checkLength: lastsplit: " . date("H:i",$this->lastsplit),LOG_DEBUG);
                $length+=$this->config["maxproglengthbeforesplit"];
                $this->logg("checkLength: length: " . date("H:i",$length),LOG_DEBUG);
                // if $now > $this->getData("stop") then we are in
                // padding time so ignore and don't split.
                // and if we are within the maxproglengthoveride
                // limit (default 5 mins) of stopping anyway
                // don't split
                $tmpnow=$now+$this->config["maxproglengthoveride"];
                if($length<$now && $tmpnow<$this->getData("stop")){
                    if($this->splitRecording($nextstreamnumber)){
                        $this->lastsplit=$now;
                        return true;
                    }
                }
            }
        }
        return false;
    }
    public function splitRecording($nextstreamnumber)
    {
        $this->nextstreamnumber=$nextstreamnumber;
        $currentstreamindex=$this->numstreams-1;
        if(false!==$this->newStream()){
            $newstreamindex=$this->numstreams-1;
            // sanity check
            if($currentstreamindex!=$newstreamindex){
                $currentfnum=$this->numfiles;
                if(false!==$this->newFile()){
                    // allow the new mencoder time to settle down
                    sleep($this->config["splitoverlaptime"]);
                    if($currentstreamindex>-1){
                        $this->streams[$currentstreamindex]->stopStream();
                        $this->files[$currentfnum]->finishFile();
                    }
                    return true;
                }else{
                    $this->logg("Recording: splitRecording: failed to create the new file.",LOG_WARNING);
                }
            }else{
                $this->logg("Recording: splitRecording: new stream and current stream appear to be the same stream.",LOG_WARNING);
            }
        }else{
            $this->logg("Recording: splitRecording: failed to start new stream to split recording.",LOG_WARNING);
        }
        return false;
    }
    public function stopRecording($deletealldata=false)
    {
        foreach($this->streams as $stream){
            $stream->stopStream();
            $stream->deleteMe();
        }
        $this->streams=array();
        $this->numstreams=0;
        if($this->numfiles){
            $this->files[$this->numfiles]->finishFile();
        }
        $this->setData("edit","m");
        $this->program->setData("record","n");
        $this->amrecording=false;
        $this->logg("Stopped recording " . $this->getData("title"),LOG_INFO);
        if($deletealldata){
            $this->deleteThisRecording();
        }
    }
    public function setNextStreamNumber($nextstreamnumber)
    {
        $this->nextstreamnumber=$nextstreamnumber;
    }
    public function deleteThisRecording()
    {
        foreach($this->files as $rfile){
            $rfile->deleteThisFile();
        }
        $this->deleteMe();
    }
    public function allCurrentlyRecording($outputstatus=false)
    {
        $op="";
        if(false!==($arr=$this->mx->getAllResults("*","recorded","edit","r",0,MYSQL_ASSOC))){
            if($outputstatus){
                foreach($arr as $rr){
                    $r=new Recording($rr["id"],$this->log);
                    if(false!==($tmp=$r->logCurrentlyRecording())){
                        $op.=$tmp;
                    }
                }
                print $op;
            }
        }
        return $arr;
    }
    public function logCurrentlyRecording()
    {
        if(false!==$this->channel){
            $cname=$this->channel->getData("name");
            $op=$this->getData("title") . " on $cname. Finishes: " . date("H;i",$this->getData("stop"));
            $this->logg($op,LOG_DEBUG);
            $op.="\n";
            return $op;
        }
        return false;
    }
}
?>
