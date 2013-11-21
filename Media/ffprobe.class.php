<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * /home/chris/src/cca-php/Media/ffprobe.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Wednesday  9 October 2013, 10:18:42
 * Last Modified: Wednesday  9 October 2013, 10:32:20
 * Revision: $Id$
 * Version: 0.00
 */

require_once "base.class.php";

class Ffprobe extends Base
{
    private $ffprobeop;
    private $ffprobefqf;
    private $duration;
    private $hasvideo;
    private $hasaudio;
    private $ismedia;

    public function __construct($logg=false)/*{{{*/
    {
        parent::__construct($logg);
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    private function resetffprobe()/*{{{*/
    {
        $this->ffprobeop=false;
        $this->ffprobefqf=false;
        $this->duration=false;
        $this->hasvideo=false;
        $this->hasaudio=false;
        $this->ismedia=false;
    }/*}}}*/
    private function ffprobe($fqf)/*{{{*/
    {
        $this->resetffprobe();
        $cmd="/usr/bin/ffprobe \"$fqf\" 2>&1";
        $ll=exec($cmd,$op,$ret);
        if($ret==0){
            $this->ffprobeop=$op;
            $this->ffprobefqf=$fqf;
            if($this->ValidArray($op)){
                foreach($op as $line){
                    $line=trim($line);
                    $tmp=substr($line,0,4);
                    switch($tmp){
                    case "Stre":
                        $stmp=explode(":",$line);
                        $stream=trim($stmp[1]);
                        switch($stream){
                        case "Video":
                            $this->debug("video stream found in $fqf");
                            $this->hasvideo=true;
                            break;
                        case "Audio":
                            $this->hasaudio=true;
                            $this->debug("Audio stream found in $fqf");
                            break;
                        default:
                            break;
                        }
                        break;
                    case "Dura":
                        $stmp=explode(":",$line);
                        $hrs=trim($stmp[1]);
                        $mins=$stmp[2];
                        $secs=substr($stmp[3],0,2);
                        $this->duration=$this->hmsToSec("$hrs:$mins:$secs");
                        $this->debug("$fqf: $hrs:$mins:$secs");
                        break;
                    default:
                        break;
                    }
                }
                if($this->duration>0){
                    $this->ismedia=true;
                    $this->debug("media file detected: $fqf");
                }
            }
        }
        return $this->ismedia;
    }/*}}}*/
    public function probe($fqf)/*{{{*/
    {
        $ret=false;
        if($this->ValidFile($fqf)){
            $ret=$this->ffprobe($fqf);
        }
        return $ret;
    }/*}}}*/
    public function getAudio()/*{{{*/
    {
        return $this->hasaudio;
    }/*}}}*/
    public function getVideo()/*{{{*/
    {
        return $this->hasvideo;
    }/*}}}*/
    public function getDuration()/*{{{*/
    {
        return $this->duration;
    }/*}}}*/
    public function isMedia()/*{{{*/
    {
        return $this->ismedia;
    }/*}}}*/
}
?>
