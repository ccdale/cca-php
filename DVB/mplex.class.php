<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Tuesday 14 June 2011, 06:10:10
 * Last Modified: Wednesday 15 June 2011, 11:02:13
 * Version: $Id: mplex.class.php 649 2011-06-15 10:59:45Z chris $
 */

require_once "Shell/extern.class.php";
require_once "file.php";

class Mplex extends Extern
{
    private $outputfile="";
    private $outputa=array();
    private $audiofile="";
    private $videofile="";
    private $config=false;

    public function __construct($log=false,$config=false,$videofile="",$audiofile="",$outputfile="")
    {
        parent::__construct("",$log,array("mplex"=>false));
        $this->config=$config;
        $this->setVideofile($videofile);
        $this->setAudiofile($audiofile);
        $this->setOutputfile($outputfile);
        if($this->progs["mplex"]!==false){
            $this->logg("__construct(): Mplex binary found ok",LOG_DEBUG);
            return true;
        }
        $this->logg("__construct(): Mplex binary not found",LOG_WARNING);
        return false;
    }
    public function __destruct()
    {
        $this->logg("__destruct(): Mplex",LOG_DEBUG);
        parent::__destruct();
    }
    public function getOutputfile()
    {
        return $this->outputfile;
    }
    public function setOutputfile($outputfile="")
    {
        if($this->checkString($outputfile)){
            if(is_array($this->config) && isset($this->config["maxfilesize"])){
                $pi=pathinfo($outputfile);
                $this->outputfile=unixPath($pi["dirname"]) . $pi["filename"] . "_%d." . $pi["extension"];
            }else{
                $this->outputfile=$outputfile;
            }
            $this->outputa["fqdir"]=dirname($this->outputfile);
            $tmp=basename($this->outputa["fqdir"]);
            $this->outputa["directory"]=$tmp;
        }
    }
    public function getVideofile()
    {
        return $this->videofile;
    }
    public function setVideofile($videofile="")
    {
        if($this->checkString($videofile)){
            $this->videofile=$videofile;
        }
    }
    public function getAudiofile()
    {
        return $this->audiofile;
    }
    public function setAudiofile($audiofile="")
    {
        if($this->checkString($audiofile)){
            $this->audiofile=$audiofile;
        }
    }
    /**
     * if succesful returns an array otherwise returns false
     * returnarray["fqdir"] = fully qualified path to output directory
     * returnarray["directory"] = name of output directory for insertion in db
     * returnarray["numfiles"] = number of output files created
     * returnarray["files"] = sorted array of output files
     * The output directory must be empty before this function is run
     */
    public function multiplex()
    {
        if($this->checkString($this->outputfile)){
            if($this->checkString($this->videofile) && checkFile($this->videofile,CCA_FILE_EXIST)){
                if($this->checkString($this->audiofile) && checkFile($this->audiofile,CCA_FILE_EXIST)){
                    if(is_array($this->config) && isset($this->config["maxfilesize"])){
                        // mplex requires the size in MB
                        $maxfilesize=$this->config["maxfilesize"]/1000000;
                        $cmd=$this->progs["mplex"] . " -S " . $maxfilesize . " -f 9 -o " . $this->outputfile . " " . $this->videofile . " " . $this->audiofile;
                    }else{
                        $cmd=$this->progs["mplex"] . " -f 9 -o " . $this->outputfile . " " . $this->videofile . " " . $this->audiofile;
                    }
                    if($this->Run($cmd)){
                        if($this->findCreatedFiles()){
                            return $this->outputa;
                        }
                    }else{
                        $this->logg("Mplex: error.",LOG_WARNING);
                        $this->logg($this->getOutput(),LOG_WARNING);
                    }
                }else{
                    $this->logg("Mplex: Audio file not set or not exist: " . $this->audiofile,LOG_WARNING);
                }
            }else{
                $this->logg("Mplex: Video file not set or not exist: " . $this->videofile,LOG_WARNING);
            }
        }else{
            $this->logg("Mplex: Output file not set",LOG_WARNING);
        }
        return false;
    }
    public function findCreatedFiles()
    {
        if(isset($this->outputa["fqdir"])){
            $d=directoryRead($this->outputa["fqdir"]);
            if(is_array($d) && is_array($d["files"]) && ($cn=count($d["files"]))){
                $this->outputa["numfiles"]=$cn;
                $this->outputa["files"]=$d["files"];
                return true;
            }
        }
        return false;
    }
}
?>
