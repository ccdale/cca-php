#!/usr/bin/env php
<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * dvbstreamer.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Sunday  1 June 2014, 08:50:32
 * Last Modified: Sunday  1 June 2014, 09:25:41
 */

require_once "base.class.php";
require_once "Unix/process.class.php";

class DvbStreamer extends Base
{
    private $adaptor=0;
    private $dvbsdir;
    private $cmd;
    private $pid=false;
    private $pidfile;
    private $user;
    private $pass;

    public function __construct($logg=false,$adaptor=0,$user="tvc",$pass="tvc")/*{{{*/
    {
        parent::__construct($logg);
        $this->adaptor=$adaptor;
        $this->user=$user;
        $this->pass=$pass;
        $home=getenv("HOME");
        $this->dvbsdir=$this->unixPath($home) . ".dvbstreamer";
        $this->pidfile=$this->unixPath($this->dvbsdir) . "dvbstreamer-" . $this->adaptor . ".pid";
        $this->cmd="/usr/bin/dvbstreamer -a " . $this->adaptor . " -u " . $this->user . " -p " . $this->pass . " -d";
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    public function startDvbstreamer()/*{{{*/
    {
        $pidfile=$this->unixPath($this->dvbsdir) . "dvbstreamer-$adaptor.pid";
        if(file_exists($pidfile)){
            $cpid=file_get_contents($pidfile);
            $proc=new Process($cpid);
            $pcmd=$proc->getCmdline();
        }
    }/*}}}*/
    private function isRunning()/*{{{*/
    {
        $ret=false;
        if(file_exists($this->pidfile)){
            $this->debug("pidfile exists");
            $cpid=file_get_contents($this->pidfile);
            if(false!=($junk=$proc=new Process($cpid))){
                $this->debug("pid process exists");
                $pcmd=$proc->getCmdline();
                $this->debug("process cmdline: $pcmd");
                if($pcmd==$this->cmd){
                    $ret=true;
                    $this->debug("this process is already running");
                }else{
                    $this->debug("This isn't our process");
                }
            }
        }
        return $ret;
    }/*}}}*/
}
?>
