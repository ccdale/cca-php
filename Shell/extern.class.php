<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Monday 13 June 2011, 11:51:02
 * Last Modified: Monday 22 August 2011, 13:29:41
 * Version: $Id: extern.class.php 710 2011-09-17 20:55:53Z chris $
 */

/*
 * $log is a logging object
 * $progs is an array of external program names as the array keys
 * (the values will be automatically filled in as the path to the
 * external programs)
 * $loglevel the minimum log level to log
 * $toconsole send all external program output to console rather 
 * than capturing it.
 */
require_once "Shell/background.class.php";

class Extern
{
    protected $log=false;
    private $canlog=false;
    private $lastop=array();
    private $lastret=0;
    protected $progs=false;
    private $loglevel=LOG_DEBUG;
    private $toconsole=false;
    private $cmd="";
    private $captureerrors=true;
    private $logname="Extern";

    public function __construct($cmd="",$log=false,$progs=false,$loglevel=LOG_DEBUG,$toconsole=false,$captureerrors=true)
    {
        $this->captureerrors=$captureerrors;
        $this->loglevel=$loglevel;
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
            $this->logg("__construct()",LOG_DEBUG);
        }
        if(false!=$progs){
            $this->progs=$progs;
            $this->findExternalPrograms();
        }
        if($this->checkArray($this->progs)){
            foreach($this->progs as $prog=>$junk){
                if($this->checkString($prog)){
                    $this->logname=$prog;
                    break;
                }
            }
        }
    }
    public function __destruct()
    {
        $this->logg("_destruct()",LOG_DEBUG);
    }
    private function extractLogName()
    {
        $pso=strpos(trim($this->cmd)," ");
        if($pso){
            $cmd=basename(substr($this->cmd,0,$pso));
            if($this->checkString($cmd)){
                $this->logname=$cmd;
            }
        }
    }
    protected function logg($msg,$level=LOG_DEBUG)
    {
        if($this->canlog && $level<=$this->loglevel){
            $this->log->message($this->logname . ": $msg",$level);
        }
    }
    protected function checkString($str)
    {
        if(is_string($str) && strlen($str)){
            return true;
        }
        return false;
    }
    protected function checkArray($arr)
    {
        if(is_array($arr) && ($cn=count($arr))){
            return $cn;
        }
        return false;
    }
    private function runCmd($background=false)
    {
        if($this->checkString($this->cmd)){
            $this->extractLogName();
            $this->lastop=array();
            if($this->captureerrors){
                $this->cmd.=" 2>&1";
            }
            $this->cmd="nice -19 " . $this->cmd;
            $this->logg("runCmd: $this->cmd");
            if($this->toconsole){
                passthru($this->cmd,$this->lastret);
            }else{
                exec($this->cmd,$this->lastop,$this->lastret);
            }
            if($this->lastret==0){
                return true;
            }
        }
        return false;
    }
    protected function findExternalPrograms()
    {
        if($this->checkArray($this->progs)){
            $tmp=array();
            foreach($this->progs as $prog=>$junk){
                $tmp[$prog]=false;
                $this->logg("Checking $prog",LOG_DEBUG);
                $cmd="which $prog";
                if($this->Run($cmd)){
                    if(isset($this->lastop[0])){
                        $tmp[$prog]=$this->lastop[0];
                    }
                }
                if($tmp[$prog]==false){
                    $this->logg("Cannot find executable for $prog",LOG_WARNING);
                    $this->logg("Cmd op: " . $this->getOutput(),LOG_DEBUG);
                }else{
                    $this->logg("Executable for $prog found: " . $tmp[$prog]);
                }
            }
            $this->progs=$tmp;
            unset($tmp);
        }
    }
    public function getCommand()
    {
        return $this->cmd;
    }
    public function setCommand($command="")
    {
        if($this->checkString($command)){
            $this->cmd=$command;
        }
    }
    public function Run($cmd="",$background=false)
    {
        $this->setCommand($cmd);
        return $this->runCmd($background);
    }
    public function getOutput()
    {
        return implode("\n",$this->lastop);
    }
    public function getOutputA()
    {
        return $this->lastop;
    }
}
?>
