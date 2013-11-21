<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * rprog.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Tuesday  8 December 2009, 09:47:41
 * Last Modified: Saturday 17 September 2011, 21:48:48
 * Version: $Id: rprog.class.php 710 2011-09-17 20:55:53Z chris $
 */

require_once "DB/mysql.class.php";
require_once "DB/data.class.php";
require_once "DVB/rfile.class.php";
require_once "DVB/channel.class.php";
require_once "DVB/previousrecorded.class.php";

class RProg extends Data
{
    private $log=false;
    private $canlog=false;
    private $files=array();
    private $numfiles=0;
    private $remux=array();
    private $remuxing=false;
    private $numremux=0;
    private $autoremux=false;
    private $amrecording=false;
    private $programtableid=0;
    private $isradio=false;
    private $currentmux=0;
    private $directory="";

    public function __construct($id=0,$log=false)
    {
        $this->Data("recorded","id",$id,false,$log);
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
        if($id){
            $c=new Channel($this->getData("channel"),$this->log);
            if($c->getData("type")=="radio"){
                $this->isradio=true;
            }
            // $this->autoremux=$c->getData("autoremux")=='y'?true:false;
            $this->autoremux=true;
            /*
            $files=$this->mx->getAllResults("*","rfile","rid",$id," order by fnum",MYSQL_ASSOC);
            if(is_array($files) && ($this->numfiles=count($files))){
                foreach($files as $file){
                    $this->files[]=new RFile($file["id"],$log,$this->isradio);
                }
                $this->setDirectory($this->files[0]->getDirectory());
            }
             */
            $this->getFiles();
        }
        /*
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
         */
    }
    public function __destruct()
    {
    }
    public function setByPid($pid=0)
    {
        if($pid){
            if(0!==($rid=$this->mx->getField("id","recorded","pid",$pid))){
                if($this->canlog){
                    $this->log->message("RProg: setByPid: setting my id to $rid",LOG_DEBUG);
                }
                $this->__construct($rid,$this->log);
            }
        }
    }
    private function message($str="",$func="",$level=LOG_INFO)
    {
        if($this->canlog){
            $this->log->message("RFile::$func: $str",$level);
        }elseif(isset($_SERVER["TERM"])){
            $dt=date("d/m/y H:i:s");
            print "$dt RProg::$func: " . CCA_CWhite . $str . CCA_COff . "\n";
        }
    }
    private function getFiles()
    {
        $files=$this->mx->getAllResults("*","rfile","rid",$this->data_id," order by fnum",MYSQL_ASSOC);
        if(is_array($files) && ($this->numfiles=count($files))){
            foreach($files as $file){
                $this->files[]=new RFile($file["id"],$this->log,$this->isradio);
            }
            $this->setDirectory($this->files[0]->getDirectory());
        }
    }
    public function amRecording()
    {
        return $this->amrecording;
    }
    public function currentSize()
    {
        if($this->amRecording()){
            $cnt=count($this->files);
            if($cnt){
                return $this->files[$cnt-1]->tsFileSize();
            }
        }
        return false;
    }
    public function newRecording($rarr)
    {
        $parr=$rarr;
        $this->programtableid=$rarr["id"];
        $rarr["pid"]=$rarr["id"];
        $this->currentmux=$rarr["mux"];
        unset($parr["record"]);
        unset($rarr["id"]);
        unset($rarr["mux"]);
        unset($rarr["record"]);
        $this->arr=$rarr;
        $this->updateDB();
        $c=new Channel($rarr["channel"],$this->log);
        if($c->getData("type")=="radio"){
            $this->isradio=true;
            $this->files[0]=new RFile(0,$this->log,true);
        }else{
            $this->files[0]=new RFile(0,$this->log);
        }
        $this->numfiles=1;
        if(false!==($this->files[0]->newTsFile($this->data_id,1,$rarr["title"],$c->getName()))){
            $this->amrecording=true;
            $this->setData("edit","r");
            $this->setDirectory($this->files[0]->getDirectory());
            $pr=new PreviousRecorded($parr,$this->log);
            $pr=null;
            if($this->canlog){
                $this->log->message("Started recording " . $rarr["title"] . " from " . $c->getName() . " finishes at " . date("H:i",$rarr["stop"]),LOG_INFO);
            }
        }else{
            if($this->canlog){
                $this->log->message("Failed to start recording of " . $rarr["title"] . " from " . $c->getName(),LOG_INFO);
            }
        }
    }
    public function stopRecording()
    {
        if($this->amrecording){
            $cn=count($this->files);
            $cn--;
            $this->files[$cn]->stopRecording();
            $this->amrecording=false;
            // if($this->autoremux){
                $this->numremux=array_push($this->remux,$this->files[$cn]);
            // }
            $p=new Program($this->programtableid,$this->log);
            $p->setData("record","n");
            $p=null;
            $this->setData("edit","t");
            if($this->canlog){
                $this->log->message("Stopped recording " . $this->getData("title"),LOG_INFO);
            }
        }
    }
    public function checkTSFile()
    {
        /* checks to see if file has grown to be near MAXFILESIZE
         * if it has then file is split
         * and true is returned
         * else false is returned
         */
        $ret=false;
        if($this->amrecording){
            $cn=count($this->files);
            $cn--;
            $fs=$this->files[$cn]->tsFileSize();
            $percent=intval((float)(($fs/MAXFILESIZE)*100));
            if($this->canlog){
                $this->log->message("RProg::checkTSFile: " . $this->files[$cn]->getFilename() . " is " . printableFilesize($fs) . " ($percent%)",LOG_DEBUG);
            }
            if($percent>MAXPERCENTAGE){
                $this->addFile();
                $ret=true;
                // $this->numremux=array_push($this->remux,$this->files[$cn]);
                if($this->canlog){
                    $this->log->message("Split recording of " . $this->getData("title"),LOG_INFO);
                    $this->log->message("RProg::checkTSFile: " . $this->files[$cn]->getFilename() . ": " . printableFilesize($fs),LOG_DEBUG);
                    $this->log->message("RProg::checkTSFile: " . $this->files[$cn+1]->getFilename() . " is new file.",LOG_DEBUG);
                }
            }
        }
        return $ret;
    }
    public function addFile()
    {
        $cnt=count($this->files);
        if($cnt){
            if(false!==($nfn=$this->files[$cnt-1]->splitRecording())){
                if($this->isradio){
                    $this->files[$cnt]=new RFile(0,$this->log,true);
                }else{
                    $this->files[$cnt]=new RFile(0,$this->log);
                }
                $this->files[$cnt]->setRid($this->data_id);
                $this->files[$cnt]->setPosition($this->files[$cnt-1]->getPosition()+1);
                $this->files[$cnt]->setFilename(basename($nfn));
                $this->files[$cnt]->setDirectory($this->files[$cnt-1]->getDirectory());
                $this->numremux=array_push($this->remux,$this->files[$cnt-1]);
            }
        }
    }
    public function countRemux()
    {
        return $this->numremux;
    }
    public function nextRemux()
    {
        if($this->numremux){
            $this->remuxing=array_shift($this->remux);
            if($this->canlog){
                $this->log->message("Starting remuxing of " . $this->remuxing->getFilename(),LOG_INFO);
            }
            $this->numremux=count($this->remux);
            $this->remuxing->remux();
            return true;
        }
        $this->numfiles=count($this->files);
        return false;
    }
    public function amRemuxing()
    {
        if(is_object($this->remuxing) && $this->remuxing->isRunning()){
            return true;
        }else{
            if(is_object($this->remuxing)){
                $pc=$this->remuxing->remuxCompleted();
                $this->remuxing=false;
            }
        }
        return false;
    }
    public function hasTsFiles()
    {
        $ret=false;
        $cn=count($this->files);
        if($cn){
            foreach($this->files as $rfile){
                if($rfile->isTsFile()){
                    $ret=true;
                    break;
                }
            }
        }
        return $ret;
    }
    public function canRemux()
    {
        return $this->autoremux && $this->numremux?true:false;
        /*
        return $ret;
        $cn=count($this->remux);
        if($cn){
            return true;
        }else{
            return false;
        }
         */
    }
    public function firstScFile()
    {
        return SCSERVER . $this->files[0]->scFn();
    }
    public function cliFiles()
    {
        $ret=false;
        $op="";
        if(is_array($this->files) && 0!==($cn=count($this->files))){
            foreach($this->files as $file){
                $op.=" " . SCMOUNT . $file->scFn();
            }
            if(strlen($op)){
                $ret=$op;
                $w=$this->getData("watched");
                $w++;
                $this->setData("watched",$w);
            }
            if($this->getData("edit")=="r"){
                $this->setData("watched",0);
            }
        }
        return $ret;
    }
    public function scFiles()
    {
        $ret=false;
        $op="";
        if(is_array($this->files) && 0!==($cn=count($this->files))){
            $title=$this->getData("title");
            foreach($this->files as $file){
                if($cn==1){
                    $op="$title|0|0|" . SCSERVER . $file->scFn() . "|";
                    break;
                }else{
                    $part=$file->getPosition();
                    $op.=$title . " ($part of $cn)|0|0|" . SCSERVER . $file->scFn() . "|";
                }
            }
            if(strlen($op)){
                $ret=$op;
                $w=$this->getData("watched");
                $w++;
                $this->setData("watched",$w);
            }
            if($this->getData("edit")=="r"){
                $this->setData("watched",0);
            }
        }
        return $ret;
    }
    public function getCurrentMux()
    {
        return $this->currentmux;
    }
    public function allRecorded()
    {
        return $this->mx->getAllResults("*","recorded",1,0," and watched=0 order by start desc",MYSQL_ASSOC);
    }
    public function allRecordedTv()
    {
        return $this->mx->getAllResults("*","recorded",1,0," and watched=0 and channel in (select id from channel where type = 'tv') order by start desc",MYSQL_ASSOC);
    }
    public function allRecordedRadio()
    {
        $now=mktime();
        $now+=(15*60);
        return $this->mx->getAllResults("*","recorded",1,0," and stop<$now and watched=0 and channel in (select id from channel where type = 'radio') order by start desc",MYSQL_ASSOC);
    }
    public function allRecordedWatched()
    {
        return $this->mx->getAllResults("*","recorded",1,0," and watched>0 order by start desc",MYSQL_ASSOC);
    }
    public function allRecordedByTitle()
    {
        return $this->mx->getAllResults("distinct title","recorded",1,0," order by title",MYSQL_ASSOC);
    }
    public function titlePrograms($title)
    {
        return $this->mx->getAllResults("*","recorded","title",$title," order by start desc",MYSQL_ASSOC);
    }
    public function allRecordedByEditFlag($ef='t')
    {
        if(is_string($ef) && strlen($ef)==1){
            if($ef=="t"){
                return $this->mx->getAllResults("*","recorded",1,0,"and edit='$ef' order by start asc",MYSQL_ASSOC);
            }elseif($ef=="a"){
                return $this->mx->getAllResults("*","recorded",1,0,"and edit='$ef' and id in (select distinct rid from rfile where filename like '%mpg') order by start asc",MYSQL_ASSOC);
            }else{
                return $this->mx->getAllResults("*","recorded",1,0,"and edit='$ef' and channel in (select id from channel where type='tv') order by start asc",MYSQL_ASSOC);
            }
        }else{
            return false;
        }
    }
    public function allRecordedAvi()
    {
        return $this->mx->getAllResults("*","recorded",1,0,"and edit='a' and id in (select distinct rid from rfile where filename like '%avi') order by start asc",MYSQL_ASSOC);
    }
    public function allRecordedTSBBC()
    {
        if(is_string($ef) && strlen($ef)==1){
            return $this->mx->getAllResults("*","recorded",1,0,"and edit='t' and channel in (select id from channel where name like 'bbc%' and type='tv') order by start asc",MYSQL_ASSOC);
        }else{
            return false;
        }
    }
    public function allRecordedTSRadio()
    {
        if(is_string($ef) && strlen($ef)==1){
            return $this->mx->getAllResults("*","recorded",1,0,"and edit='t' and channel in (select id from channel where type='radio') order by start asc",MYSQL_ASSOC);
        }else{
            return false;
        }
    }
    public function deleteThisProgram()
    {
        $dir="";
        if(count($this->files)){
            foreach($this->files as $f){
                if($dir==""){
                    $dir=$f->getData("directory");
                }
                $f->deleteThisFile();
            }
            if($dir!="" && file_exists(unixPath(TVDIR) . $dir)){
                rmdir(unixPath(TVDIR) . $dir);
            }
            // rmdir(unixPath(TVDIR) . $this->getDirectory());
            $this->deleteMe();
        }
    }
    public function checkAllFilesExist()
    {
        $ret=true;
        if(count($this->files)){
            foreach($this->files as $f){
                if(!$f->thisFileExists()){
                    $ret=false;
                    break;
                }
            }
        }
        return $ret;
    }
    public function getDirectory()
    {
        return $this->directory;
    }
    public function setDirectory($directory="")
    {
        if(is_string($directory)){
            $this->directory=$directory;
        }
    } 
    public function channelName()
    {
        $c=new Channel($this->getData("channel"));
        return $c->getData("name");
    }
    public function getVlen()
    {
        $tvlen=0;
        if($this->numfiles){
            foreach($this->files as $file){
                $tvlen+=hmsToSec($file->getVlen());
            }
        }
        return secToHMS($tvlen);
    }
    public function setEditMpg()
    {
        if($this->data_id){
            $this->setData("edit","m");
        }
    }
    public function setEditEdit()
    {
        if($this->data_id){
            $this->setData("edit","e");
        }
    }
    public function isRadio()
    {
        return $this->isradio;
    }
    public function manualRemux($edit=false)
    {
        if(count($this->files)){
            foreach($this->files as $f){
                $f->doFileOperations($edit);
            }
            $m=$edit?"e":"m";
            $this->setData("edit",$m);
        }else{
            $this->message("this program has no files associated with it.\n","manualRemux");
        }
    }
    public function mpgEdit()
    {
        if(count($this->files)){
            foreach($this->files as $f){
                $vlen=$f->getVlen();
                if(0==strlen($vlen)){
                    $f->calcVlen();
                    $vlen=$f->getVlen();
                }
                $vl=intval(hmsToSec($vlen));
                if($vl && $vl<FIFTEENMINUTES){
                    $this->message("This part, " . $f->getFilename() . ", is " . secToHMS($vl) . " long, do you want to add it or delete it");
                    $s=cliInput("A/d ");
                    if($s=="d"){
                        $f->deleteThisFile();
                        $this->getFiles();
                        break;
                    }else{
                        $f->mpgEdit();
                    }
                }else{
                    $f->mpgEdit();
                }
            }
            // $m=$edit?"e":"m";
            $this->setData("edit","e");
        }else{
            $this->message("this program has no files associated with it.\n","mpgEdit");
        }
    }
    public function makeAvi()
    {
        $continue=false;
        if($this->numfiles){
            if($this->numfiles>1){
                $this->message("Joining MPG's","makeAvi");
                if($this->joinMpg()){
                    $this->message("Join completed successfully.","makeAvi");
                    $continue=true;
                }else{
                    $this->message("failed to successfully join MPG's","makeAvi");
                }
            }else{
                $continue=true;
            }
            if($continue && $this->files[0]->makeAvi()){
                $this->message("AVI creation completed.","makeAvi");
                return true;
            }else{
                $this->message("failed to successfully build AVI","makeAvi");
            }
        }else{
            $this->message("this program has no files associated with it.\n","makeAvi");
        }
        return false;
    }
    public function joinMpg()
    {
        $opt="";
        $this->cleanMpgDir();
        if($this->numfiles && $this->numfiles>1 && $this->checkAllFilesExist()){
            for($x=0;$x<$this->numfiles;$x++){
                if(strlen($opt)){
                    $opt.=" " . $this->files[$x]->fqFn();
                }else{
                    $opt=$this->files[$x]->fqFn();
                }
            }
            if(strlen($opt)){
                $opt="-out " . MPGDIR . " $opt";
                $cmd=PROJECTX . " $opt";
                $this->runCommandInTerminal($cmd);
                $pi=pathinfo($this->files[0]->fqFn());
                $dir=unixPath($pi["dirname"]);
                $m2v=unixPath(MPGDIR) . $pi["filename"] . ".m2v";
                $mp2=unixPath(MPGDIR) . $pi["filename"] . ".mp2";
                $jmpg=$dir . $pi["filename"] . ".j.mpg";
                if(checkFile($m2v,CCA_FILE_EXIST)){
                    if(checkFile($mp2,CCA_FILE_EXIST)){
                        if(checkFile($jmpg,CCA_FILE_NOTEXIST)){
                            $cmd=MPLEX . " -f 8 -o $jmpg $m2v $mp2";
                            $this->runCommandInTerminal($cmd);
                            if(checkFile($jmpg,CCA_FILE_EXIST)){
                                if($nfn=noClobberFileMove($this->files[0]->fqFn(),THEBIN)){
                                    $this->message("moved " . $this->files[0]->fqFn() . " to $nfn","joinMpg");
                                }else{
                                    $this->message("unable to move " . $this->files[0]->fqFn() . " to " . THEBIN,"joinMpg");
                                }
                                $this->files[0]->setFilename(basename($jmpg));
                                $this->files[0]->calcVlen();
                                $this->files[0]->setData("stop",(intval($this->files[0]->getData("start")))+intval(hmsToSec($this->files[0]->getVlen())));
                                for($x=1;$x<$this->numfiles;$x++){
                                    $this->files[$x]->deleteThisFile();
                                }
                                $this->getFiles();
                                return true;
                            }
                        }else{
                            $this->message("output file $jmpg already exists.","joinMpg");
                        }
                    }else{
                        $this->message("mp2 file $mp2 missing","joinMpg");
                    }
                }else{
                    $this->message("m2v file $m2v missing","joinMpg");
                }
            }
        }
        return false;
    }
    private function runCommandInTerminal($cmd)
    {
        if(is_string($cmd) && strlen($cmd)){
            $this->message("Running $cmd.","runCommandInTerminal");
            exec(ATERM . $cmd);
            return true;
        }else{
            return false;
        }
    }
    private function cleanMpgDir()
    {
        $d=directoryRead(MPGDIR);
        if(is_array($d) && is_array($d["files"])){
            $cn=count($d["files"]);
            for($x=0;$x<$cn;$x++){
                if($nfn=noClobberFileMove(unixPath(MPGDIR) . $d["files"][$x],THEBIN)){
                    $this->message("moved " . $d["files"][$x] . " to $nfn.","cleanMpgDir");
                }else{
                    $this->message("unable to move " . $d["files"][$x],"cleanMpgDir");
                }
            }
        }
    }
    public function getNumFiles()
    {
        return $this->numfiles;
    }
    public function getFqFnAsArray()
    {
        $ret=array();
        foreach($this->files as $rf){
            $ret[]=$rf->fqFn();
        }
        return $ret;
    }
    public function aviFileSize()
    {
        return realFilesize($this->files[0]->fqFn());
    }
    public function getFilesAsObjects()
    {
        return $this->files;
    }
    public function makeOneFile($filename)
    {
        if($this->numfiles>1){
            for($x=1;$x<$this->numfiles;$x++){
                $this->files[$x]->deleteMe();
            }
        }
        $this->files[0]->setFilename($filename);
    }
    public function currentlyRecording()
    {
        return $this->mx->getAllResults("*","recorded","edit","r","order by start",MYSQL_ASSOC);
    }
}
?>
