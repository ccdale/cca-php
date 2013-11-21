<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Friday 20 May 2011, 12:30:58
 * Last Modified: Saturday 17 September 2011, 21:49:44
 * Version: $Id: tsfile.class.php 710 2011-09-17 20:55:53Z chris $
 */

require_once "file.php";
require_once "DB/mysql.class.php";
require_once "DB/data.class.php";
require_once "DVB/program.class.php";
require_once "DVB/channel.class.php";
require_once "DVB/dvbctrl.class.php";

class TSFile extends Data
{
    private $log=false;
    private $canlog=false;
    private $fqfn="";
    private $channel=false;
    private $config=false;
    private $amrecording=false;
    private $dvbc=false;
    private $p=false;
    private $c=false;
    private $lastfilesize=0.00;
    private $filenumber=1;
    private $amprocessing=false;

    public function __construct($id=0,$log=false,$config=false,$progid=0,$filenumber=1)
    {
        $this->config=$config;
        $id=intval($id);
        $progid=intval($progid);
        $filenumber=intval($filenumber);
        $this->Data("tsfile","id",$id,$this->config,$log);
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
            $this->logg("__construct()",LOG_DEBUG);
        }
        if($progid){
            return $this->startRecordingProgram($progid,$filenumber);
        }
        if($this->data_id){
            $this->p=new Program($this->arr["pid"],$this->log,$this->config);
            $this->c=new Channel($this->p->getData("channel"),$this->log);
            $this->fqfn=$this->arr["fqfn"];
        }
        return false;
    }
    public function __destruct()
    {
        if($this->isRecording()){
            $this->stopRecordingFile();
        }
        $this->p=null;
        $this->c=null;
        $this->dvbc=null;
        $this->logg("_destruct()",LOG_DEBUG);
    }
    private function logg($msg,$level=LOG_DEBUG)
    {
        if($this->canlog){
            $did=$this->data_id?" id:" . $this->data_id . ":":"";
            $this->log->message("TSFILE: id: $did: $msg",$level);
        }
    }
    private function startRecordingProgram($pid=0,$filenumber=1)
    {
        $pid=intval($pid);
        if($pid){
            $this->p=new Program($pid,$this->log,$this->config);
            $tmp=intval($this->p->getData("id"));
            if($tmp===$pid){
                $this->c=new Channel($this->p->getData("channel"),$this->log);
                $ptitle=$this->p->getData("title");
                $sfn=makeSensibleFilename($ptitle);
                if(is_string($sfn) && strlen($sfn)){
                    if(false!==($dfn=noClobberDir(unixPath($this->config["tvdir"]) . $sfn))){
                        if(false!==mkdir($dfn,0755)){
                            $this->fqfn=unixPath($dfn) . $sfn . ".ts";
                            if($this->startRecordingFile()){
                                $this->arr["fqfn"]=$this->fqfn;
                                $now=time();
                                $this->arr["start"]=$now;
                                $this->arr["stop"]=0;
                                $this->arr["pid"]=$pid;
                                $this->arr["filenumber"]=intval($filenumber);
                                $this->updateDB();
                                $rid=intval($this->data_id);
                                if($rid){
                                    $this->p->setData("record","r");
                                    $this->logg("New Recording of $ptitle started");
                                    $this->logg("New TSFILE record id: $rid");
                                    $this->logg("Due to stop at " . date("D d H:i",$this->p->getData("stop")));
                                    return true;
                                }else{
                                    $this->stopRecordingFile();
                                    $this->logg("Error in updating database.  Could not insert new record for $ptitle");
                                }
                            }else{
                                $this->logg("Failed to start recording $ptitle");
                            }
                        }else{
                            $this->logg("Error making directory: $dfn");
                        }
                    }else{
                        $this->logg("Error creating directory for file.");
                        $this->logg("TVDir: " . $this->config["tvdir"]);
                        $this->logg("noClobberDir returned false for name: $sfn");
                    }
                }else{
                    $this->logg("Cannot make sensible filename for title: $ptitle, returned: $sfn");
                }
            }else{
                $this->logg("Cannot find program record for id: $pid");
            }
        }else{
            $this->logg("Program ID is not valid: $pid");
        }
        return false;
    }
    private function dvbcCleanUp()
    {
        if(is_object($this->dvbc)){
            $this->logg("Starting dvbstreamer filter clean up.");
            $ra=$this->dvbc->cleanupServiceFilters();
            if(is_array($ra) && count($ra)){
                foreach($ra as $cf){
                    $this->logg("removed $cf filter.");
                }
            }
            $this->logg("dvbstreamer filter clean up completed.");
        }
    }
    private function checkStillRecording()
    {
        $fs=(float)realFilesize($this->fqfn);
        if($fs>$this->lastfilesize){
            $this->lastfilesize=$fs;
            return true;
        }
        return false;
    }
    private function checkP()
    {
        if(is_object($this->p) && get_class($this->p)=="Program"){
            return true;
        }
        return false;
    }
    private function checkC()
    {
        if(is_object($this->c) && get_class($this->c)=="Channel"){
            return true;
        }
        return false;
    }
    public function startRecordingFile()
    {
        $ret=false;
        if(is_string($this->fqfn) && strlen($this->fqfn) && checkFile($this->fqfn,CCA_FILE_NOTEXIST)){
            $cname=$this->c->getName();
            if(is_string($cname) && strlen($cname)){
                $this->dvbc=new DVBCtrl();
                if($this->dvbc->connect()){
                    $this->dvbc->setFavsonlyOn();
                    if($this->dvbc->recordNewService($cname,$this->fqfn)){
                        $this->logg("Recording start. Waiting for data from dvbstreamer");
                        $this->amrecording=true;
                        $iteration=0;
                        $amr=false;
                        while(!$amr){
                            sleep(5);
                            $amr=$this->isRecording();
                            $iteration++;
                            if($iteration>3){
                                break;
                            }
                        }
                        if($amr){
                            $this->logg("Recording started ok.");
                            $ret=true;
                        }else{
                            $this->logg("Recording failed to start.  Iteration timeout");
                            $this->logg("Failed to start recording.  No data from dvbstreamer.");
                        }
                    }else{
                        $this->logg("dvbstreamer cannot start new recording service");
                    }
                }else{
                    $this->logg("Failed to connect to dvbstreamer, cannot start recording " . $this->fqfn);
                }
            }else{
                $this->logg("Failed to get channel name");
            }
        }else{
            $this->logg("Cannot start recording file, either file exists or fqfn not set. fqfn: " . $this->fqfn);
        }
        $this->dvbc=false;
        return $ret;
    }
    public function stopRecordingFile()
    {
        $ret=false;
        if(is_string($this->fqfn) && strlen($this->fqfn) && checkFile($this->fqfn,CCA_FILE_EXIST)){
            $this->dvbc=new DVBCtrl();
            if($this->dvbc->connect()){
                if($this->dvbc->stopRecording($this->fqfn)){
                    $this->setData("stop",time());
                    $this->p->setData("record","n");
                    $this->logg("dvbstreamer has stopped recording file. " . $this->fqfn);
                    $this->amrecording=false;
                    $this->dvbcCleanUp();
                    $ret=true;
                }else{
                    $this->logg("dvbstreamer failed to find file to stop. " . $this->fqfn);
                }
            }else{
                $this->logg("Failed to connect to dvbstreamer, cannot stop recording " . $this->fqfn);
            }
        }else{
            $this->logg("cannot stop recording, file does not exist or fqfn is inconsistant: " . $this->fqfn);
        }
        $this->dvbc=false;
        return $ret;
    }
    public function isRecording($logfilesize=false)
    {
        if($this->amrecording){
            if($this->checkStillRecording()){
                if($logfilesize){
                    $pfs=printableFilesize($this->lastfilesize);
                    $this->logg($this->fqfn . ": " . printableFilesize($this->lastfilesize));
                }
                return true;
            }else{
                $this->stopRecordingFile();
            }
        }
        return false;
    }
    public function getProgramTitle()
    {
        $ret="";
        if($this->checkP()){
            $ret=$this->p->getData("title");
        }
        return $ret;
    }
    public function getFqfn()
    {
        return $this->fqfn;
    }
    public function getStopTime()
    {
        $ret=0;
        if($this->checkP()){
            $ret=$this->p->getData("stop");
        }
        return $ret;
    }
    public function deleteRecordAndFile()
    {
        $id=$this->data_id;
        if($this->deleteFile()){
            $this->logg($this->fqfn . " deleted ok.");
        }else{
            $this->logg("Failed to delete " . $this->fqfn);
        }
        if($this->deleteMe()){
            $this->logg("Deleted this record ok: $id");
        }else{
            $this->logg("Failed to delete this record: $id");
        }
    }
    public function deleteFile()
    {
        if(checkFile($this->fqfn,CCA_FILE_EXIST)){
            $nfn=noClobberFileMove($this->fqfn,$this->config["thebin"]);
            $this->logg("Deleting file, moved to $nfn");
            return true;
        }
        return true;
        // i've returned true from here because I can't be bothered to 
        // sort out why I put the call for this method into an if/else thingy
    }
    public function getCurrentMux()
    {
        if($this->checkC()){
            return $this->c->getData("mux");
        }
        return 0;
    }
    public function getChannelId()
    {
        if($this->checkC()){
            return $this->c->getData("id");
        }
        return 0;
    }
    public function getChannelType()
    {
        if($this->checkC()){
            return $this->c->getData("type");
        }
        return false;
    }
    public function getLastFilesize()
    {
        return $this->lastfilesize;
    }
    public function allTsFiles()
    {
        return $this->mx->getAllResults("*","tsfile",1,0,"order by pid,start",MYSQL_ASSOC);
    }
    public function orphanRecordings()
    {
        $sql="select * from tsfile where start>0 and stop=0";
        $res=$this->mx->queryResult($sql);
        if(is_array($res) && ($cn=count($res))){
            $this->logg("Found $cn orphaned recordings");
            return $res;
        }
        $this->logg("No orphaned recordings found");
        return false;
    }
    public function orphanDBCheck()
    {
        /*
        if($this->data_id && isset($this->arr["pid"]) && $this->arr["pid"]){
            $sql="select * from progrec where id=" . $this->arr["pid"];
            $res=$this->mx->queryResult($sql);
            if(is_array($res) && count($res)){
                $this->logg("DB is consistent for orpaned recordings");
                return true;
            }else{
                $sql="select * from program where ";
            }
        }
         */
        if(false===($ret=$this->updateProgRec())){
            $this->logg("Orphan check. DB is inconsistant");
            $this->logg("mysql error: " . $this->mx->error_text);
        }else{
            $this->logg("Orphan check. DB is consistant");
        }
        return $ret;
    }
    public function updateProgRec()
    {
        if($this->data_id && isset($this->arr["pid"]) && $this->arr["pid"]){
            $sql="select * from progrec where id=" . $this->arr["pid"];
            $res=$this->mx->queryResult($sql);
            if(is_array($res) && count($res)){
                $this->logg("progrec record exists ok. for this recording of " . $this->getProgramTitle());
                return true;
            }else{
                $sql="insert into progrec select * from program where id=" . $this->arr["pid"];
                $res=$this->mx->queryResult($sql);
                $res=intval($res);
                if($res==$this->arr["pid"]){
                    $this->logg("progrec record created OK for this recording of " . $this->getProgramTitle());
                    return true;
                }else{
                    $this->logg("insert id is incorrect for new progrec record: insert id: $res program id: " . $this->arr["pid"]);
                }
            }
        }else{
            $this->logg("Cannot update progrec table, data_id: " . $this->data_id . " program id: " . $this->arr["pid"]);
        }
        return false;
    }
    public function setAmRecording()
    {
        $this->amrecording=true;
    }
    public function processTsFile()
    {
        $this->amprocessing=true;
        $isradio=false;
        if($this->config && isset($this->config["mintsfilesize"])){
            if(!$this->isRecording()){
                if($this->lastfilesize>$this->config["mintsfilesize"]){
                    $pi=pathinfo($this->fqfn);
                    $probe=new Ffprobe($this->log);
                    $px=new Projectx($this->log,$this->config);
                    $pidsa=$probe->probeFile($this->fqfn);
                    if($this->checkString($pidsa["vpid"])){
                        $pids=$pidsa["vpid"] . "," . $pidsa["apid"];
                    }else{
                        $pids=$pidsa["apid"];
                        $isradio=true;
                    }
                    $px->setPids($pids);
                    $px->addFile($this->fqfn);
                    $px->setWorkdir($this->config["remuxdir"]);
                    if(false!=$px->demuxFiles()){
                        if($isradio){
                            // radio processing here.
                        }else{
                            $mpx=new Mplex($this->log,$this->config);
                            $mpx->setVideofile(unixPath($this->config["remuxdir"]) . $pi["filename"] . ".m2v");
                            $mpx->setAudiofile(unixPath($this->config["remuxdir"]) . $pi["filename"] . ".mp2");
                            $mpx->setOutputfile(unixPath($pi["dirname"]) . $pi["filename"] . ".mpg");
                            if(false!=($farr=$mpx->multiplex())){
                                if(false!==($cn=$this->checkArray($farr))){
                                }else{
                                    $this->logg("multiplex return failed array check.",LOG_DEBUG);
                                    $tmp=print_r($farr,true);
                                    $this->logg("return value: $tmp",LOG_DEBUG);
                                }
                                $this->amprocessing=false;
                            }else{
                                $this->logg("Failed to multiplex files.",LOG_DEBUG);
                            }
                        }
                    }
                }else{
                    $this->logg("TS filesize too small: " . printableFilesize($this->lastfilesize),LOG_DEBUG);
                }
            }else{
                $this->logg("Cannot process. Still recording.",LOG_DEBUG);
            }
        }else{
            $this->logg("Cannot process ts file as config array not set",LOG_DEBUG);
        }
        $this->amprocessing=false;
        return false;
    }
    public function isProcessing()
    {
        return $this->amProcessing;
    }
}
?>
