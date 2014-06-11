<?php

/** {{{ heading block
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * /home/chris/src/php/cca-php/Shell/background.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Wednesday 28 January 2009, 01:20:21
 * Version:
 * Last Modified: Wednesday 11 June 2014, 10:42:33
 *
 * $Id: background.class.php 303 2010-07-13 10:33:08Z chris $
 */
// }}}

require_once "Unix/processtable.class.php";
require_once "Unix/process.class.php";
require_once "file.php";

class BackgroundCommand
{
    private $outputfile="";
    private $errorfile="";
    private $pid=0;
    private $proc=false;
    private $cmd="";
    private $nice=0;
    private $outputnames="phpbackground";

    public function __construct($cmd="",$nice=0,$outputnames="phpbackground")
    {
        $this->outputfile=tempnam("/tmp","TVd-output");
        $this->errorfile=tempnam("/tmp","TVd-error");
        $this->outputnames=$outputnames;
        $this->setCmd($cmd);
        $this->setNice($nice);
    }
    public function getCmd()
    {
        return $this->cmd;
    }
    public function getExecCmd()
    {
        return $this->proc->getCmdline();
    }
    public function setCmd($cmd="")
    {
        if(is_string($cmd)){
            $this->cmd=$cmd;
        }
    }
    public function getNice()
    {
        return $this->nice;
    }
    public function setNice($nice=0)
    {
        if(is_int($nice) && $nice>-20 && $nice<20){
            $this->nice=$nice;
        }
    }
    public function run($sleep=0,$pidfile="")
    {
        $tmp="";
        if($pidfile && (false!==($pid=getFile($pidfile,CCA_FILE_ASSTRING)))){
            $this->pid=$pid+0;
            $this->setProc();
        }else{
            if(strlen($this->cmd)){
                if($this->nice){
                    $tmp="nice -" . $this->nice . " ";
                }
                $tmp.=$this->cmd . " >" . $this->outputfile . " 2>" . $this->errorfile . " & echo \$!";
                $this->pid=exec($tmp);
                $this->setProc();
            }
        }
        if(($sleep+0)>0 && $this->pid>0){
            sleep($sleep);
        }
    }
    private function setProc()
    {
        if($this->pid){
            $this->proc=new Process($this->pid);
        }
    }
    public function isRunning()
    {
        return is_object($this->proc)?$this->proc->exists():false;
    }
    public function isRunningTime()
    {
        if(is_object($this->proc)){
            if($this->proc->exists()){
                return $this->proc->getStartTime();
            }
        }
        return false;
    }
    public function stop()
    {
        if($this->isRunning()){
            $this->proc->killMe();
            $count=1;
            while($this->isRunning()){
                $killed=$this->proc->killMe();
                sleep($count);
                $count++;
                if($count>5){
                    $this->proc->forceKill();
                    break;
                }
            }
        }
        return !$this->isRunning();
    }
    public function hasErrors()
    {
        return filesize($this->errorfile)?true:false;
    }
    public function hasOutput()
    {
        return filesize($this->outputfile)?true:false;
    }
    public function outputSize()/*{{{*/
    {
        clearstatcache($this->outputfile);
        return filesize($this->outputfile);
    }/*}}}*/
    public function getoutput()
    {
        return file_get_contents($this->outputfile);
    }
    public function getError()
    {
        return file_get_contents($this->errorfile);
    }
    public function __destruct()
    {
        $this->saveOutFile("output");
        $this->saveOutFile("error");
        /*
        clearstatcache();
        if(file_exists($this->outputfile)){
            if(filesize($this->outputfile)){
                noClobberFileMove($this->outputfile,THEBIN);
            }
            unlink($this->outputfile);
        }
        if(file_exists($this->errorfile)){
            unlink($this->errorfile);
        }
         */
    }
    private function saveOutFile($type="output")
    {
        $fn=$this->outputfile;
        $nn="/tmp/" . $this->outputnames . ".output";
        switch($type){
        case "output":
            $fn=$this->outputfile;
            $nn="/tmp/" . $this->outputnames . ".output";
            break;
        case "error":
            $fn=$this->errorfile;
            $nn="/tmp/" . $this->outputnames . ".error";
            break;
        }
        if(file_exists($fn)){
            if(filesize($fn)){
                if(rename($fn,$nn)){
                    noClobberFileMove($nn,THEBIN);
                }
            }else{
                unlink($fn);
            }
        }
    }
    public function getMplexOP()
    {
        $op=false;
        if($this->hasErrors()){
            $cmd="grep completed " . $this->errorfile;
            $ll=exec($cmd,$op,$ret);
        }
        return $op;
    }
}
?>
