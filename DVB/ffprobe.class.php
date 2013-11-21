<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Tuesday 14 June 2011, 05:29:46
 * Last Modified: Monday 27 June 2011, 06:28:19
 * Version: $Id: ffprobe.class.php 685 2011-06-27 05:28:49Z chris $
 */

require_once "Shell/extern.class.php";
require_once "string.php";

class Ffprobe extends Extern
{
    private $pids=array("vpid"=>false,"apid"=>false,"spid"=>false,"duration"=>"0","length"=>0);
    private $tmpfile="";

    public function __construct($log=false)
    {
        parent::__construct("",$log,array("ffprobe"=>false,"grep"=>false));
        if($this->progs["ffprobe"]!==false){
            $this->logg("__construct(): Ffprobe binary found ok",LOG_DEBUG);
            return true;
        }
        $this->logg("__construct(): Ffprobe binary not found",LOG_WARNING);
        return false;
    }
    public function __destruct()
    {
        if($this->checkString($this->tmpfile)){
            $this->logg("Removing temp file " . $this->tmpfile,LOG_DEBUG);
            unlink($this->tmpfile);
        }
        $this->logg("__destruct(): Ffprobe",LOG_DEBUG);
        parent::__destruct();
    }
    private function grepPids()
    {
        if($this->checkString($this->tmpfile)){
            if($this->progs["grep"]!=false){
                $this->grepVpid();
                $this->grepApid();
                $this->grepSpid();
                $this->grepDuration();
                return $this->pids;
            }
        }
        return false;
    }
    private function grepVpid()
    {
        $cmd=$this->progs["grep"] . " Video " . $this->tmpfile;
        if($this->Run($cmd)){
            $arr=textBetween($this->getOutput(),"[","]");
            if($this->checkString($arr["between"])){
                $this->pids["vpid"]=$arr["between"];
                $this->logg("vpid: " . $this->pids["vpid"],LOG_DEBUG);
            }
        }
    }
    private function grepApid()
    {
        $cmd=$this->progs["grep"] . " Audio " . $this->tmpfile . "|" . $this->progs["grep"] . " stereo";
        if($this->Run($cmd)){
            $arr=textBetween($this->getOutput(),"[","]");
            if($this->checkString($arr["between"])){
                $this->pids["apid"]=$arr["between"];
                $this->logg("apid: " . $this->pids["apid"],LOG_DEBUG);
            }
        }
    }
    private function grepSpid()
    {
        $cmd=$this->progs["grep"] . " Subtitle " . $this->tmpfile;
        if($this->Run($cmd)){
            $arr=textBetween($this->getOutput(),"[","]");
            if($this->checkString($arr["between"])){
                $this->pids["spid"]=$arr["between"];
                $this->logg("spid: " . $this->pids["spid"],LOG_DEBUG);
            }
        }
    }
    private function grepDuration()
    {
        $cmd=$this->progs["grep"] . " Duration " . $this->tmpfile;
        if($this->Run($cmd)){
            $arr=textBetween($this->getOutput(),"Duration:",".");
            if($this->checkString($arr["between"])){
                $tmp=trim($arr["between"]);
                $this->pids["duration"]=$tmp;
                $this->pids["length"]=hmsToSec($tmp);
                $this->logg("Duration: " . $this->pids["duration"] . ", " . $this->pids["length"],LOG_DEBUG);
            }
        }
    }
    public function probeFile($file="")
    {
        if($this->checkString($file)){
            $this->tmpfile=tempnam("/tmp","ffprobe");
            $cmd=$this->progs["ffprobe"] . " $file >" . $this->tmpfile;
            if($this->Run($cmd)){
                return $this->grepPids();
            }
        }
        return false;
    }
}
?>
