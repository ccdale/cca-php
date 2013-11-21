<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Wednesday 15 June 2011, 11:03:49
 * Last Modified: Saturday 17 September 2011, 21:44:12
 * Version: $Id: tsprocessor.class.php 710 2011-09-17 20:55:53Z chris $
 */

/*
 * this object requires a progrec record
 *
 * it will then bring up the projectx gui for editing if required
 * the cut file path and name is echoed to the screen
 *
 * when the doProcess method is called:
 *      1. the first ts file is probed for pids
 *      2. all relevent ts files are demuxed into the working directory using the cut file if required
 *      3. the ts files are then binned, recording their new names
 *      4. the output directory is then emptied (it should already be empty)
 *      5. the demuxed files are then muxed into the output files
 *      6. the work directory is then emptied
 *      7. a new record is created in the recorded table
 *      8. new records are created in the rfile table
 *      9. the tsfile records are deleted
 *      10. the progrec record is deleted
 */
require_once "DVB/tsfile.class.php";
require_once "DVB/ffprobe.class.php";
require_once "DVB/projectx.class.php";
require_once "DVB/mplex.class.php";
require_once "DVB/lame.class.php";
require_once "DVB/dvbctrl.class.php";
require_once "DB/mysql.class.php";
require_once "DB/data.class.php";
require_once "video.php";

class TsProcessor
{
    private $log=false;
    private $canlog=false;
    private $loglevel=LOG_DEBUG;
    private $config=false;
    private $progrec=false;
    private $tsdataa=false;
    private $mx=false;
    private $pids=false;
    private $cut=false;
    private $projectx=false;
    private $fnbase=false;
    private $cname="";

    public function __construct($id=0,$log=false,$config=false)
    {
        $this->config=$config;
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
            $this->logg("__construct()",LOG_DEBUG);
        }
        $this->mx=new Mysql();
        $id=intval($id);
        $this->progrec=new Data("progrec","id",$id,$this->config,$log);
        $this->projectx=new ProjectX($this->log,$this->config);
        return $this->getTsFiles();
    }
    public function __destruct()
    {
        $this->logg("_destruct()",LOG_DEBUG);
    }
    private function logg($msg,$level=LOG_DEBUG)
    {
        if($this->canlog && $level<=$this->loglevel){
            $this->log->message("TSProcessor: $msg",$level);
        }
    }
    private function msg($str="")
    {
        if($this->checkString($str)){
            $this->logg($str,LOG_INFO);
            print "$str\n";
        }
    }
    private function getTsFiles()
    {
        if($this->progrec && $this->progrec->data_id){
            $sql="select * from tsfile where pid=" . $this->progrec->data_id . " order by filenumber";
            $this->logg($sql,LOG_DEBUG);
            $this->tsdataa=$this->mx->queryResult($sql,MYSQL_ASSOC);
            if(false!=($cn=$this->checkArray($this->tsdataa))){
            // if(is_array($this->tsdataa) && ($cn=count($this->tsdataa))){
                $this->logg("found $cn files",LOG_DEBUG);
                $this->buildFileList();
                return true;
            }else{
                // var_dump($cn);
                // var_dump($this->tsdataa);
                $tmp=print_r($cn,true);
                $this->logg("cn is: $tmp",LOG_DEBUG);
                $this->logg("tsfile data count: $cn",LOG_WARNING);
                $tmp=print_r($this->tsdataa,true);
                $this->logg("tsfile data is: $tmp",LOG_DEBUG);
            }
        }
        return false;
    }
    private function checkString($str="")
    {
        if(is_string($str) && strlen($str)){
            return true;
        }
        return false;
    }
    private function checkArray($arr)
    {
        if(is_array($arr) && ($cn=count($arr))){
            return $cn;
        }
        return false;
    }
    private function probePids()
    {
        if($this->config["fqreplace"]){
            $fqfn=str_replace($this->config["fqreplace"][0],$this->config["fqreplace"][1],$this->tsdataa[0]["fqfn"]);
            if(checkFile($fqfn,CCA_FILE_EXIST)){
                $fp=new Ffprobe($this->log);
                $this->pids=$fp->probeFile($fqfn);
                $fp=null;
            }else{
                $this->logg("probePids: file does not exist: $fqfn",LOG_WARNING);
            }
        }
    }
    private function buildFileList()
    {
        if($this->projectx){
            if($this->config["fqreplace"]){
                if(false!=($cn=$this->checkArray($this->tsdataa))){
                    reset($this->tsdataa);
                    for($x=0;$x<$cn;$x++){
                        $fqfn=str_replace($this->config["fqreplace"][0],$this->config["fqreplace"][1],$this->tsdataa[$x]["fqfn"]);
                        $this->tsdataa[$x]["fqfn"]=$fqfn;
                        $this->projectx->addFile($fqfn);
                        if($x==0){
                            $this->fnbase=basename($fqfn,".ts");
                        }
                    }
                }
            }
        }
    }
    private function binTsFiles()
    {
        $cn=count($this->tsdataa);
        for($x=0;$x<$cn;$x++){
            if(false!=($nfn=noClobberFileMove($this->tsdataa[$x]["fqfn"],$this->config["thebin"]))){
                $this->logg("Moved " . $this->tsdataa[$x]["fqfn"] . " to " . $nfn,LOG_DEBUG);
                $this->tsdataa[$x]["fqfn"]=$nfn;
            }else{
                $this->logg("Failed to move " . $this->tsdataa[$x]["fqfn"] . " to " . $this->config["thebin"],LOG_WARNING);
                return false;
            }
        }
        return true;
    }
    private function emptyOutputDir($outputdir)
    {
        if($this->checkString($outputdir)){
            $d=directoryRead($outputdir);
            if(isset($d["files"]) && count($d["files"])){
                $this->logg("Output directory: $outputdir is not empty",LOG_WARNING);
                $tmp=print_r($d["files"],true);
                $this->logg($tmp,LOG_WARNING);
                return false;
            }
        }
        return true;
    }
    private function cleanUp()
    {
        if($this->checkArray($this->config) && isset($this->config["mywork"])){
            $d=directoryRead($this->config["mywork"]);
            if($this->checkArray($d) && ($cn=$this->checkArray($d["files"]))){
                for($x=0;$x<$cn;$x++){
                    $fn=unixPath($this->config["mywork"]) . $d["files"][$x];
                    $nfn=noClobberFileMove($fn,$this->config["mybin"]);
                    $this->logg("Moved $fn to $nfn.",LOG_DEBUG);
                }
            }
        }
    }
    private function demuxVA()
    {
        $this->waitForDVB();

        /*
        exit;
         */


        $this->probePids();
        if($this->checkArray($this->pids)){
            if($this->pids["vpid"]){
                $pids=$this->pids["vpid"] . "," . $this->pids["apid"];
            }else{
                $pids=$this->pids["apid"];
            }
        }else{
            $pids="";
            $this->logg("no pids defined",LOG_WARNING);
        }
        if($this->projectx){
            $this->projectx->setWorkdir($this->config["mywork"]);
            $this->projectx->setPids($pids);
            if($this->projectx->demuxFiles()){
                $this->logg("Demux OK.",LOG_DEBUG);
                return true;
            }else{
                $this->logg("Projectx failed in demux.",LOG_WARNING);
            }
        }else{
            $this->logg("projectx is not an object",LOG_ERROR);
        }
        return false;
    }
    private function dvbConnect()
    {
        $this->logg("TSProcessor: dvbConnect: connecting to dvbstreamer",LOG_DEBUG);
        $cmd="ssh kaleb pgrep dvbstreamer";
        $pid=exec($cmd,$op,$ret);
        if($ret==0){
            $dvbc=new DVBCtrl();
            if($dvbc->connect()){
                $this->logg("TSProcessor: dvbConnect: connected.",LOG_DEBUG);
                return $dvbc;
            }
        }else{
            $this->logg("dvbstreamer is not currently running",LOG_DEBUG);
        }
        $this->logg("TSProcessor: dvbConnect: failed.",LOG_DEBUG);
        return false;
    }
    private function dvbRecording()
    {
        $this->logg("Checking whether dvbstreamer is active and currently recording",LOG_DEBUG);
        $ret=false;
        if(false!=($dvbc=$this->dvbConnect()) && is_object($dvbc) && get_class($dvbc)=="DVBCtrl"){
            $state=$dvbc->lsRecording(false);
            $tmp=print_r($state,true);
            $this->logg("State:",LOG_DEBUG);
            $this->logg($tmp,LOG_DEBUG);
            if($this->checkArray($state)){
                foreach($state as $filter=>$recording){
                    if($this->checkArray($recording) && isset($recording["type"]) && isset($recording["filename"]) && $this->checkString($recording["filename"])){
                        if($recording["type"]!="null"){
                            $ret=$recording["filename"];
                            break;
                        }
                    }
                }
            }
        }else{
            $this->logg("no, it is not",LOG_DEBUG);
        }
        return $ret;
    }
    private function waitForDVB()
    {
        /*
        $this->logg("in waitForDVB()",LOG_DEBUG);
        $file=true;
        while($file){
            if(false!=($file=$this->dvbRecording()) && $this->checkString($file)){
                $this->logg("checking file: $file",LOG_DEBUG);
                $sql="select p.stop+(15*60) as stop from tsfile t,progrec p where t.fqfn='$file' and t.pid=p.id";
                $sarr=$this->mx->queryResult($sql,MYSQL_ASSOC);
                $tmp=print_r($sarr,true);
                $this->logg("Query: $sql",LOG_DEBUG);
                $this->logg("Returned:",LOG_DEBUG);
                $this->logg($tmp,LOG_DEBUG);
                if($this->checkArray($sarr) && isset($sarr[0]) && isset($sarr[0]["stop"])){
                    $sleep=$sarr[0]["stop"]-time();
                    $this->logg("Sleep time calculates as $sleep",LOG_DEBUG);
                    if($sleep>1){
                        $msg="Recording $file, will sleep for " . secToHMS($sleep);
                        $this->msg($msg);
                        sleep($sleep);
                    }
                }
            }else{
                $tmp=print_r($file,true);
                $this->logg("dvbRecording() return $tmp",LOG_DEBUG);
            }
        }
         */
    }
    public function doEdit()
    {
        if($this->projectx){
            $title=$this->progrec->getData("title");
            $ftitle=makeSensibleFilename($title);
            $tmp=unixPath($this->config["mybin"]) . $ftitle . ".xcl";
            if(false!=($cutfile=noClobberFile($tmp))){
                $this->projectx->setCutfile($cutfile);
                print "\n\nCutfile: $cutfile\n\n\n";
                if(false!=$this->projectx->editFiles()){
                    if(false==checkFile($cutfile,CCA_FILE_EXIST)){
                        $this->projectx->deleteCutFile();
                        $this->logg("Cut file has not been generated",LOG_WARNING);
                    }
                    return true;
                }
            }else{
                $this->logg("Error generating cutfile name from $tmp",LOG_ERROR);
            }
        }else{
            $this->logg("projectx is not an object",LOG_ERROR);
        }
        return false;
    }
    public function doProcess()
    {
        $title=stripslashes($this->progrec->getData("title"));
        $this->msg("Start processing $title");
        if($this->demuxVA()){
            $outdir=dirname($this->tsdataa[0]["fqfn"]);
            $this->binTsFiles();
            if($this->emptyOutputDir($outdir)){
                $audiofile=unixPath($this->config["mywork"]) . $this->fnbase . ".mp2";
                $videofile=unixPath($this->config["mywork"]) . $this->fnbase . ".m2v";
                $c=new Channel($this->progrec->getData("channel"));
                $type=$c->getData("type");
                if($type=="tv"){
                    $outfile=unixPath($outdir) . $this->fnbase . ".mpg";
                    $m=new Mplex($this->log,$this->config,$videofile,$audiofile,$outfile);
                    $this->waitForDVB();
                    $outa=$m->multiplex();
                    if($this->checkArray($outa)){
                        $this->logg($outa["numfiles"] . " file(s) have been created.",LOG_INFO);
                        $tmp=print_r($outa,true);
                        $this->logg($tmp,LOG_DEBUG);
                        $this->cleanUp();
                        $sql="insert into recorded (title,description,start,stop,channel,pid,programid,seriesid) select title,description,start,stop,channel,id,programid,seriesid from progrec where id=" . $this->progrec->data_id;
                        $this->logg($sql,LOG_DEBUG);
                        $rid=$this->mx->queryResult($sql);
                        $start=$this->progrec->getData("start");
                        $stop=$this->progrec->getData("stop");
                        $vlen=secToHMS($stop-$start);
                        for($x=0;$x<$outa["numfiles"];$x++){
                            $sql="insert into rfile (rid,fnum,directory,filename,start,stop,vlen,type,bookmark) ";
                            $sql.="values ($rid," . ($x+1) . ",'" . basename($outdir) . "','" . $outa["files"][$x] . "',$start,$stop,'$vlen','$type',0)";
                            $tid=$this->mx->queryResult($sql);
                            $this->logg($sql,LOG_DEBUG);
                        }
                        $sql="delete from tsfile where pid=" . $this->progrec->data_id;
                        $this->mx->query($sql);
                        $this->progrec->deleteMe();
                        $this->msg("Finished processing $title");
                    }else{
                        $this->msg("Errors in processing $title");
                    }
                }else{
                    /*
                    $outfile=unixPath($outdir) . $this->fnbase . ".mp2";
                    $cmd="mv $audiofile $outfile";
                    passthru($cmd,$ret);
                    if($ret){
                        $this->logg("Failed to move $audiofile to $outfile.",LOG_WARNING);
                    }else{
                        $this->logg("Moved $audiofile to $outfile.",LOG_DEBUG);
                    }
                    $this->cleanUp();
                    $start=$this->progrec->getData("start");
                    $stop=$this->progrec->getData("stop");
                    $vlen=secToHMS($stop-$start);
                    $sql="insert into recorded (title,description,start,stop,channel,pid,programid,seriesid) select title,description,start,stop,channel,id,programid,seriesid from progrec where id=" . $this->progrec->data_id;
                    $this->logg($sql,LOG_DEBUG);
                    $rid=$this->mx->queryResult($sql);
                    $sql="insert into rfile (rid,fnum,directory,filename,start,stop,vlen,type,bookmark) ";
                    $sql.="values ($rid," . 1 . ",'" . basename($outdir) . "','" . $this->fnbase . ".mp2" . "',$start,$stop,'$vlen','$type',0)";
                    $tid=$this->mx->queryResult($sql);
                    $this->logg($sql,LOG_DEBUG);
                    if($this->progrec->data_id){
                        $sql="delete from tsfile where pid=" . $this->progrec->data_id;
                        $this->mx->query($sql);
                        $this->progrec->deleteMe();
                    }
                     */
                    if(isset($this->config["radiodir"]) && $this->checkString($this->config["radiodir"])){
                        $fntitle=makeSensibleFilename($title);
                        $fndir=unixPath($this->config["radiodir"]) . $fntitle;
                        if(!file_exists($fndir)){
                            if(mkdir($fndir)){
                                $continue=true;
                            }else{
                                $this->logg("Cannot make output directory: $fndir",LOG_ERR);
                                $continue=false;
                            }
                        }else{
                            if(is_dir($fndir)){
                                $this->logg("Directory exists OK: $fndir",LOG_DEBUG);
                                $continue=true;
                            }else{
                                $this->logg("$fndir exists but is not a directory",LOG_ERR);
                                $continue=false;
                            }
                        }
                        if($continue){
                            $bnfile=basename($audiofile,".mp2");
                            $description=stripslashes($this->progrec->getData("description"));
                            $pattern='/[Ee]pisode ([0-9]{1,}) of ([0-9]{1,})/';
                            $junk=preg_match($pattern,$description,$matches);
                            if(is_array($matches)){
                                $mcn=count($matches);
                                if($mcn>2){
                                    $episodenr=$matches[1];
                                    $totalepisodes=$matches[2];
                                    $bnfile.="_{$episodenr}_of_{$totalepisodes}";
                                    $this->logg("new title: $title",LOG_INFO);
                                }
                            }
                            $outputfile=noClobberFile(unixPath($fndir) . $bnfile . ".mp3");
                            $lx=new Lame($this->log,$this->config,$audiofile,$outputfile);
                            $vdur=videoDurationHMS($audiofile);
                            $description.=" $vdur";
                            $lx->setTitle($title);
                            $lx->setComment($description);
                            $lx->setAlbum(date("D d M Y H:i",$this->progrec->getData("start")));
                            $lx->setAuthor($this->cname);
                            if($lx->doLame()){
                                $this->logg("Radio program created OK as $outputfile.",LOG_INFO);
                                $sql="delete from tsfile where pid=" . $this->progrec->data_id;
                                $this->mx->query($sql);
                                $this->progrec->deleteMe();
                                $this->msg("Finished processing $title");
                            }else{
                                $this->logg("Error creating radio program $outputfile.",LOG_WARNING);
                            }
                        }
                    }else{
                        $this->logg("Radio directory not set, are we running on kaleb?",LOG_ERR);
                    }
                }
            }else{
                $this->logg("failed to empty output directory: $outdir",LOG_WARNING);
            }
        }
        return false;
    }
    public function setChannelName($cname="")
    {
        if($this->checkString($cname)){
            $this->cname=$cname;
        }
    }
}
?>
