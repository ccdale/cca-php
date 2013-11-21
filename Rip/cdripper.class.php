<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Monday 11 April 2011, 14:54:32
 * Last Modified: Friday 22 April 2011, 05:53:18
 * Version: $Id: cdripper.class.php 538 2011-04-22 04:56:19Z chris $
 */

require_once "Shell/background.class.php";

class CDRipper
{
    private $gettoc="/usr/bin/cdparanoia -Q 2>&1";
    private $batch="/usr/bin/cdparanoia -B -q 2>&1";
    private $cdparanoia="/usr/bin/cdparanoia";
    private $toc=array();
    private $cmdop=array();
    private $cmdret=0;
    private $numtracks=0;
    private $ready=false;
    private $bgcmd=false;
    private $noparanoia=false;

    public function __construct($noparanoia=false)
    {
        $this->noparanoia=$noparanoia;
        $this->init();
    }
    public function __destruct()
    {
        if($this->isRunning()){
            $this->bgcmd->stop();
        }
        $this->bgcmd=null;
    }
    private function init()
    {
        $this->toc=array();
        $this->cmdop=array();
        $this->cmdret=0;
        $this->numtracks=0;
        $this->ready=false;
        $line=exec($this->gettoc,$this->toc,$this->cmdret);
        if($this->cmdret>0){
            $this->ready=false;
        }else{
            $this->ready=true;
            $this->getNumTracksFromToc();
        }
        if($this->noparanoia){
            $this->cdparanoia.=" -Z";
        }
    }
    private function getNumTracksFromToc()
    {
        $cn=count($this->toc);
        if($cn>0){
            for($cline=0;$cline<$cn;$cline++){
                if(substr($this->toc[$cline],0,5)=="TOTAL"){
                    break;
                }
            }
            if($cline<$cn){
                $cline--;
                $tmp=trim($this->toc[$cline]);
                $this->numtracks=intval($tmp);
            }
        }
    }
    public function getNumTracks()
    {
        return $this->numtracks;
    }
    public function isReady()
    {
        return $this->ready;
    }
    public function batchRip($background=true)
    {
        if($background){
            return $this->batchRipBg();
        }
        if($this->isReady()){
            $line=exec($this->batch,$this->cmdop,$this->cmdret);
            return $this->cmdret;
        }else{
            return false;
        }
    }
    public function batchRipBg()
    {
        if($this->isReady()){
            $this->bgcmd=new BackgroundCommand($this->cdparanoia . " -q -B",10,"phpripper");
            $this->bgcmd->run(2); // 2 second sleep to wait for cdparanoia start up
            return $this->bgcmd->isRunning();
        }
        return false;
    }
    public function ripTrack($tracknum,$background=true,$outfile="")
    {
        if($background){
            return $this->ripTrackBg($tracknum,$outfile);
        }
        if($this->isReady()){
            $tn=intval($tracknum);
            if($tn>0 && $tn<=$this->numtracks){
                $line=exec($this->cdparanoia . " -q $tn $outfile 2>&1",$this->cmdop,$this->cmdret);
                return $this->cmdret;
            }
        }
        return false;
    }
    public function ripTrackBg($tracknum,$outfile="")
    {
        if($this->isReady()){
            $tn=intval($tracknum);
            if($tn>0 && $tn<=$this->numtracks){
                $this->bgcmd=new BackgroundCommand($this->cdparanoia . " -q $tn $outfile",10,"phpripper");
                $this->bgcmd->run(2); // 2 second sleep at to wait for cdparanoia start up
                return $this->bgcmd->isRunning();
            }
        }
        return false;
    }
    public function isRunning()
    {
        if(is_object($this->bgcmd)){
            $tmp=$this->bgcmd->isRunning();
            if(!$tmp){
                $this->bgcmd=false;
            }
            return $tmp;
        }else{
            return false;
        }
    }
}
?>
