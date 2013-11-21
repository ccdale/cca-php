<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * rfile.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Tuesday  8 December 2009, 10:14:54
 * Last Modified: Saturday 17 September 2011, 21:48:26
 * Version: $Id: rfile.class.php 710 2011-09-17 20:55:53Z chris $
 */

require_once "DB/mysql.class.php";
require_once "DB/data.class.php";
require_once "video.php";
require_once "file.php";
require_once "Shell/background.class.php";
require_once "DVB/dvbctrl.class.php";

class RFile extends Data/*{{{*/
{
    private $log=false;
    private $canlog=false;
    private $bcmd=false;
    private $channelname="";
    private $tsfile=false;
    private $isradio=false;
    private $pids="";
    private $stub="rfile";

    public function __construct($id=0,$log=false,$isradio=false)/*{{{*/
    {
        $this->Data("rfile","id",$id,false,$log);
        $this->isradio=$isradio;
        $this->testThisFileType();
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
    }/*}}}*/
    public function __destruct()
    {
    }
    private function testThisFileType()/*{{{*/
    {
        $pi=pathinfo($this->getFilename());
        if(is_array($pi) && isset($pi["extension"]) && $pi["extension"]=="ts"){
            $this->tsfile=true;
        }
    }/*}}}*/
    public function deleteThisFile()/*{{{*/
    {
        $this->deleteBookmark();
        $fn=$this->isTsFile()?$this->tsFqFn():$this->fqFn();
        if($this->thisFileExists()){
            if($nfn=noClobberFileMove($fn,THEBIN)){
                $this->message("$fn moved to $nfn","deleteThisFile");
                return $this->deleteMe();
            }else{
                $this->message("Error moving $fn to " . THEBIN,"deleteThisFile");
            }
            // if(unlink($fn)){
                // return $this->deleteMe();
            // }
        }else{
            $this->deleteMe();
        }
        return false;
    }/*}}}*/
    public function deleteBookmark()/*{{{*/
    {
        $fn=$this->fqFn();
        $bfn="$fn.bookmark";
        if(file_exists($bfn)){
            unlink($bfn);
        }
        $dfn=dirname($fn);
        $mfn=unixPath($dfn) . "mplayer.bmark";
        if(file_exists($mfn)){
            unlink($dfn);
        }
    }/*}}}*/
    public function readBookmark()/*{{{*/
    {
        $fn=$this->fqFn();
        $bfn="$fn.bookmark";
        if(false!==($sf=getFile($bfn,CCA_FILE_ASSTRING))){
            $this->setData("bookmark",intval($sf));
            $this->deleteBookmark();
        }
    }/*}}}*/
    public function tsFqFn()/*{{{*/
    {
        return unixPath(TSDIR) . $this->getFilename();
    }/*}}}*/
    public function fqFn()/*{{{*/
    {
        return unixPath(unixPath(TVDIR) . $this->getDirectory()) . $this->getFilename();
    }/*}}}*/
    public function scFn()/*{{{*/
    {
        return unixPath(unixPath(SCDIR) . $this->getDirectory()) . $this->getFilename();
    }/*}}}*/
    public function newTsFile($rid,$position,$title,$channelname)/*{{{*/
    {
        $this->setRid($rid);
        $this->setPosition($position);
        $stub=makeSensibleFilename($title);
        $this->stub=$stub;
        $filename=unixPath(TSDIR) . $stub . ".ts";
        $this->setFilename(basename(noClobberFile($filename)));
        $filename=$this->getFilename();
        $this->setDirectory(basename(noClobberDir(unixPath(TVDIR) . $stub)));
        if(mkdir(unixPath(TVDIR) . $this->getDirectory(),0755)){
            if($this->canlog){
                $this->log->message("Created directory " . unixPath(TVDIR) . $this->getDirectory(),LOG_DEBUG);
            }
        }else{
            if($this->canlog){
                $this->log->message("Failed to create Directory " . unixPath(TVDIR) . $this->getDirectory(),LOG_DEBUG);
            }
        }
        $stub=$this->getDirectory();
        $this->channelname=$channelname;
        if($this->canlog){
            $this->log->message("RFile::newTsFile: rid:$rid,position:$position,filename:$filename,directory:$stub,channelname:$channelname",LOG_DEBUG);
        }
        $dvb=new DVBCtrl(DVBHOST,DVBUSER,DVBPASS);
        if(false!==($ret=$dvb->connect())){
            $this->setData("start",time());
            if($dvb->recordNewService($this->channelname,$this->tsFqFn())){
                $this->tsfile=true;
                if($this->canlog){
                    $this->log->message("RFile::newTsFile: Started to record new service.",LOG_DEBUG);
                    $this->log->message("RFile::newTsFile: " . $this->tsFqFn() . " on " . $this->channelname . ".",LOG_DEBUG);
                }
                return true;
            }else{
                if($this->canlog){
                    $this->log->message("RFile::newTsFile: failed to start to record new service.",LOG_DEBUG);
                }
                return false;
            }
        }else{
            if($this->canlog){
                $this->log->message("RFile::newTsFile: failed to connect to dvbstreamer.",LOG_DEBUG);
            }
            return false;
        }
    }/*}}}*/
    public function stopRecording()/*{{{*/
    {
        $dvb=new DVBCtrl(DVBHOST,DVBUSER,DVBPASS);
        if(false!==($rec=$dvb->connect())){
            if($this->canlog){
                $this->log->message("RFile::stopRecording: Asking dvbstreamer to stop recording.",LOG_DEBUG);
            }
            $tmp=$dvb->stopRecording($this->tsFqFn());
            if($tmp){
                $this->setData("stop",time());
            }
            if($this->canlog){
                if($tmp){
                    $this->log->message("RFile::stopRecording: stopped ok.",LOG_DEBUG);
                }else{
                    $this->log->message("RFile::stopRecording: failed to stop.",LOG_DEBUG);
                }
            }
        }else{
            if($this->canlog){
                $this->log->message("RFile::stopRecording: failed to connect to dvbstreamer.",LOG_DEBUG);
            }
        }
    }/*}}}*/
    public function splitRecording()/*{{{*/
    {
        $nfn=noClobberFile($this->tsFqFn());
        $dvb=new DVBCtrl(DVBHOST,DVBUSER,DVBPASS);
        if(false!==($rec=$dvb->connect())){
            if(DVBSPLIT_OK==($rr=$dvb->split($this->tsFqFn(),$nfn))){
                if($this->canlog){
                    $this->log->message("RFile::splitRecording: split ok: new file name: $nfn.",LOG_DEBUG);
                }
                return $nfn;
            }else{
                if($this->canlog){
                    $msg="RFile::splitRecording: failed: ";
                    switch($rr)
                    {
                    case DVBSPLIT_NEW_NOT_GROWING:
                        $this->log->message($msg . "new file not growing.",LOG_DEBUG);
                        break;
                    case DVBSPLIT_START_NEW_SERVICE_FAILED:
                        $this->log->message($msg . "start new service failed.",LOG_DEBUG);
                        break;
                    case DVBSPLIT_ERROR_GETTING_SERVICE:
                        $this->log->message($msg . "error getting service.",LOG_DEBUG);
                        break;
                    }
                }
                return false;
            }
        }else{
            if($this->canlog){
                $this->log->message("RFile::splitRecording: failed to connect to dvbstreamer.",LOG_DEBUG);
            }
            return false;
        }
    }/*}}}*/
    public function getType()/*{{{*/
    {
        return $this->getData("type");
    }/*}}}*/
    public function setType($type="tv")/*{{{*/
    {
        switch($type){
            case "tv":
            case "radio":
            case "music":
            case "download":
            case "other":
                $this->setData("type",$type);
                break;
            default:
        }
    } /*}}}*/
    public function getRid()/*{{{*/
    {
        return $this->getData("rid");
    }/*}}}*/
    public function setRid($rid="")/*{{{*/
    {
        if(is_int($rid)){
            $this->setData("rid",$rid);
        }
    } /*}}}*/
    public function getPosition()/*{{{*/
    {
        return $this->getData("fnum");
    }/*}}}*/
    public function setPosition($position="")/*{{{*/
    {
        if(is_int($position)){
            $this->setData("fnum",$position);
        }
    } /*}}}*/
    public function getDirectory()/*{{{*/
    {
        return $this->getData("directory");
    }/*}}}*/
    public function setDirectory($directory="")/*{{{*/
    {
        if(is_string($directory)){
            $this->setData("directory",$directory);
        }
    } /*}}}*/
    public function getFilename()/*{{{*/
    {
        return $this->getData("filename");
    }/*}}}*/
    public function setFilename($filename="")/*{{{*/
    {
        if(is_string($filename)){
            $this->setData("filename",$filename);
        }
    } /*}}}*/
    public function getVlen()/*{{{*/
    {
        return $this->getData("vlen");
    }/*}}}*/
    public function setVlen($vlen="")/*{{{*/
    {
        if(is_string($vlen)){
            $this->setData("vlen",$vlen);
        }
    } /*}}}*/
    public function calcVlen()/*{{{*/
    {
        $this->setVlen(secToHMS(videoDuration($this->fqFn())));
    }/*}}}*/
    public function remux()/*{{{*/
    {
        $cmd=TSTOPS . " " . $this->tsFqFn() . " " . $this->getDirectory();
        if($this->canlog){
            // $this->log->message("Starting to remux " . $this->getFilename(),LOG_INFO);
            $this->log->message("RFile::remux: $cmd.",LOG_DEBUG);
        }
        $this->bcmd=new BackgroundCommand($cmd,0,$this->stub);
        $this->bcmd->run();
    }/*}}}*/
    public function isRunning()/*{{{*/
    {
        return $this->bcmd->isRunning();
    }/*}}}*/
    public function remuxCompleted()/*{{{*/
    {
        $vid=$aud=array();
        $remuxerrors=false;
        $mplexoparr=$this->bcmd->getMplexOP();
        $cn=count($mplexoparr);
        for($x=0;$x<$cn;$x++){
            if(false!==($pos=strpos($mplexoparr[$x],"Video e0: completed"))){
                $vid[]=$x;
            }elseif(false!==($pos=strpos($mplexoparr[$x],"Audio c0: completed"))){
                $aud[]=$x;
            }
        }
        if(isset($aud[0]) && isset($vid[0]) && $aud[0]<$vid[0]){
            $this->log->message("Audio finished before Video",LOG_ERROR);
            $remuxerrors=true;
        }
        $this->bcmd=null;
        $filets=$this->getFilename();
        $pi=pathinfo($filets);
        $m2v=$pi["filename"] . ".m2v";
        $mp2=$pi["filename"] . ".mp2";
        $m2vlen=intval(videoDuration(unixPath(REMUXDIR) . $m2v));
        $mp2len=intval(videoDuration(unixPath(REMUXDIR) . $mp2));
        // $audiopc=intval(((float)$mp2len/(float)$m2vlen)*100);
        $audiopc=100;
        if($audiopc<AVMISMATCHPERCENT){
            if($this->canlog){
                $this->log->message("RFILE: Audio/Video duration mismatch, Video: $m2vlen, Audio: $mp2len, $audiopc%.",LOG_DEBUG);
                rename("/home/chris/ts/remux/$filets","/home/chris/ts/$filets");
            }
        }else{
            $vlents=videoDuration(unixPath(REMUXDIR) . $filets);
            if($this->isradio){
                $this->setFilename(str_replace(TSEXT,MP2EXT,$filets));
            }else{
                $this->setFilename(str_replace(TSEXT,MPGEXT,$filets));
            }
            $vlenmpg=videoDuration($this->fqFn());
            if($vlents>0 && $vlenmpg>0){
                $pc=((float)$vlents/(float)$vlenmpg)*100;
                if($pc<90){
                    if($this->canlog){
                        $this->log->message("RFILE: ts file len: $vlents, mpg file len: $vlenmpg. pecentage: $pc",LOG_DEBUG);
                    }
                    rename("/home/chris/ts/remux/$filets","/home/chris/ts/$filets");
                }else{
                    $this->log->message("RFILE: video length OK. $pc% of original.",LOG_DEBUG);
                }
            }else{
                if($this->canlog){
                    $this->log->message("RFILE: ts file len: $vlents, mpg file len: $vlenmpg.",LOG_DEBUG);
                }
                rename("/home/chris/ts/remux/$filets","/home/chris/ts/$filets");
            }
            $this->calcVlen();
            $this->tsfile=false;
            if($this->canlog){
                $this->log->message("Remuxing completed for " . $this->getFilename() . ". Video length: " . $this->getVlen() . ".",LOG_INFO);
            }
        }
    }/*}}}*/
    public function tsFileSize()/*{{{*/
    {
        clearstatcache();
        return filesize($this->tsFqFn());
    }/*}}}*/
    public function isTsFile()/*{{{*/
    {
        return $this->tsfile;
    }/*}}}*/
    public function thisFileExists()/*{{{*/
    {
        $fn=$this->isTsFile()?$this->tsFqFn():$this->fqFn();
        return checkFile($fn,CCA_FILE_EXIST);
    }/*}}}*/
    private function identTsFile()/*{{{*/
    {
        if($this->isTsFile()){
            $cmd=IDENTTS . " " . unixPath(TSDIR) . $this->getFilename();
            $this->pids=exec($cmd);
        }
    }/*}}}*/
    private function createCutPoints($ismpg=false)/*{{{*/
    {
        if(checkFile(CUTPOINTS,CCA_FILE_EXIST)){
            unlink(CUTPOINTS);
        }
        if($ismpg){
            $tfn=$this->fqFn();
        }else{
            $tfn=unixPath(TSDIR) . $this->getFilename();
        }
        $this->message("\n\n$tfn\n\n","createCutPoints",LOG_INFO);
        $this->message(CUTPOINTS . "\n\n","createCutPoints",LOG_INFO);
        $this->runCommandInTerminal(PROJECTX);
    }/*}}}*/
    private function remuxFile($ismpg=false)/*{{{*/
    {
        $undo=false;
        // unlink(unixPath(REMUXDIR) . "*");
        exec("rm " . unixPath(REMUXDIR) . "*");
        $fn=$this->getFilename();
        $dir=$this->getDirectory();
        if($ismpg){
            $tfn=unixPath(unixPath(TVDIR) . $dir) . $fn;
        }else{
            $tfn=unixPath(TSDIR) . $fn;
        }
        $rfn=unixPath(REMUXDIR) . $fn;
        if(checkFile($tfn,CCA_FILE_EXIST)){
            if(rename($tfn,$rfn)){
                $undo=true;
                $cut="";
                if(checkFile(CUTPOINTS,CCA_FILE_EXIST)){
                    $cut="-cut " . CUTPOINTS;
                }
                $id="";
                if(is_string($this->pids) && strlen($this->pids)){
                    $id="-id " . $this->pids;
                }
                $opt=" ";
                if(strlen($id) && strlen($cut)){
                    $opt=" $id $cut ";
                }elseif(strlen($id)){
                    $opt=" $id ";
                }elseif(strlen($cut)){
                    $opt=" $cut ";
                }
                $cmd=PROJECTX . $opt . $rfn;
                $this->runCommandInTerminal($cmd);
                $pi=pathinfo($rfn);
                $afn=unixPath($pi["dirname"]) . $pi["filename"] . ".mp2";
                $vfn=unixPath($pi["dirname"]) . $pi["filename"] . ".m2v";
                $opdir=unixPath(unixPath(TVDIR) . $this->getDirectory());
                if(!file_exists($opdir)){
                    mkdir($opdir,0770);
                }
                $omfn=$opdir . $pi["filename"] . ".mpg";
                $orfn=$opdir . $pi["filename"] . ".mp3";
                if(checkFile($afn,CCA_FILE_EXIST)){
                    if($this->isradio){
                        $cmd=LAME . " $afn $orfn";
                        $check=$orfn;
                    }else{
                        $cmd=MPLEX . " -f 8 -o $omfn $afn $vfn";
                        $check=$omfn;
                    }
                    $this->runCommandInTerminal($cmd);
                    if(checkFile($check,CCA_FILE_EXIST)){
                        $this->message("Output file $check created ok.\n","remuxFile",LOG_INFO);
                        if(checkFile(CUTPOINTS,CCA_FILE_EXIST)){
                            unlink(CUTPOINTS);
                        }
                        $undo=false;
                    }else{
                        $this->message("Output file $check does not exist.","remuxFile");
                    }
                }else{
                    $this->message("Audio file $afn does not exist.","remuxFile");
                }
                if($undo){
                    rename($rfn,$tfn);
                }else{
                    $pi=pathinfo($check);
                    $this->setData("filename",$pi["basename"]);
                    $this->tsfile=false;
                    $this->calcVlen();
                    $ret=noClobberFileMove($rfn,unixPath(THEBIN));
                    if(!$ret){
                        $this->message("failed to move $rfn to " . THEBIN,"remuxFile");
                    }else{
                        $this->message("Moved $rfn to $ret.","remuxFile");
                    }
                }
            }else{
                $this->message("cannot move $tfn to $rfn.","remuxFile");
            }
        }else{
            $this->message("$tfn does not exist.","remuxFile");
        }
    }/*}}}*/
    private function runCommandInTerminal($cmd)/*{{{*/
    {
        if(is_string($cmd) && strlen($cmd)){
            $this->message("Running $cmd.","runCommandInTerminal");
            exec(ATERM . $cmd);
            return true;
        }else{
            return false;
        }
    }/*}}}*/
    public function mpgEdit()/*{{{*/
    {
        $this->createCutPoints(true);
        $this->remuxFile(true);
    }/*}}}*/
    public function doFileOperations($edit=false)/*{{{*/
    {
        if($this->isTsFile()){
            $this->identTsFile();
            if($edit){
                $this->createCutPoints();
            }
            $this->remuxFile();
        }else{
            $this->message("This is not a TS file " . $this->tsFqFn(),"doFileOperations");
        }
    }/*}}}*/
    public function makeAvi()/*{{{*/
    {
        $fn=$this->fqFn();
        if(checkFile($fn,CCA_FILE_EXIST)){
            $pi=pathinfo($fn);
            $afn=unixPath($pi["dirname"]) . $pi["filename"] . ".avi";
            if(checkFile($afn,CCA_FILE_NOTEXIST)){
                $cmd=MENCODER . " $fn " . MENCOPTS . " -o $afn";
                $this->runCommandInTerminal($cmd);
                if(checkFile($afn,CCA_FILE_EXIST)){
                    if($nfn=noClobberFileMove($fn,THEBIN)){
                        $this->message("$fn moved to $nfn","makeAvi");
                        $this->setFilename($pi["filename"] . ".avi");
                        $this->calcVlen();
                        $this->message("$afn created ok.","makeAvi");
                        return true;
                    }else{
                        $this->message("unable to move $fn to " . THEBIN,"makeAvi");
                    }
                }else{
                    $this->message("output file $afn not created.","makeAvi");
                }
            }else{
                $this->message("this file $afn already exists.","makeAvi");
            }
        }else{
            $this->message("this file $fn does not exist.","makeAvi");
        }
        return false;
    }/*}}}*/
    private function message($str="",$func="",$level=LOG_INFO)/*{{{*/
    {
        if($this->canlog){
            $this->log->message("RFile::$func: $str",$level);
        }elseif(isset($_SERVER["TERM"])){
            $dt=date("d/m/y H:i:s");
            print "$dt RFile::$func: " . CCA_CWhite . $str . CCA_COff . "\n";
        }
    }/*}}}*/
}/*}}}*/
?>
