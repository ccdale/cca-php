<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Monday 13 June 2011, 22:32:46
 * Last Modified: Wednesday 13 July 2011, 14:19:49
 * Version: $Id: projectx.class.php 693 2011-08-12 21:09:12Z chris $
 */

require_once "Shell/extern.class.php";

class ProjectX extends Extern
{
    private $config=false;
    private $cutfile="";
    private $pids="";
    private $workdir="";
    private $files="";

    public function __construct($log=false,$config=false)
    {
        parent::__construct("",$log,array("projectx"=>false));
        if($this->progs["projectx"]!==false){
            $this->logg("__construct(): Projectx binary found ok",LOG_DEBUG);
            return true;
        }
        $this->logg("__construct(): Projectx binary not found",LOG_WARNING);
        return false;
    }
    public function __destruct()
    {
        $this->logg("__destruct(): Projectx",LOG_DEBUG);
        parent::__destruct();
    }
    public function getWorkdir()
    {
        return $this->workdir;
    }
    public function setWorkdir($workdir="")
    {
        if($this->checkString($workdir)){
            $this->workdir=$workdir;
        }
    } 
    public function getCutfile()
    {
        return $this->cutfile;
    }
    public function setCutfile($cutfile="")
    {
        if($this->checkString($cutfile)){
            $this->cutfile=$cutfile;
        }
    }
    public function deleteCutFile()
    {
        $this->cutfile="";
    }
    public function getPids()
    {
        return $this->pids;
    }
    public function setPids($pids="")
    {
        if($this->checkString($pids)){
            $this->pids=$pids;
        }
    } 
    public function getFiles()
    {
        return $this->files;
    }
    public function setFiles($files="")
    {
        if($this->checkString($files)){
            $this->files=$files;
        }
    } 
    public function addFile($file="")
    {
        if($this->checkString($file)){
            $this->files.=" $file";
        }else{
            $this->files=$file;
        }
    }
    public function checkInputFiles()
    {
        $ret=true;
        if($this->checkString($this->files)){
            $tmp=explode(" ",trim($this->files));
            $cn=count($tmp);
            if($cn){
                for($x=0;$x<$cn;$x++){
                    if(false==checkFile(trim($tmp[$x]),CCA_FILE_EXIST)){
                        $ret=false;
                        $this->logg("File " . $tmp[$x] . " does not exist",LOG_ERR);
                        break;
                    }else{
                        $this->logg("File " . $tmp[$x] . " OK.",LOG_ERR);
                    }
                }
            }else{
                $ret=false;
                $this->logg("No input files to demux defined.",LOG_ERR);
            }
        }else{
            $ret=false;
            $this->logg("No input files to demux defined.",LOG_ERR);
        }
        return $ret;
    }
    private function emptyWorkDir()
    {
        if(is_dir($this->workdir)){
            $d=dir($this->workdir);
            while(false!==($entry=$d->read())){
                if($entry!="." || $entry!=".."){
                    $nfn=noClobberFileMove(unixPath($this->workdir) . $entry,$this->config["thebin"]);
                    $this->logg("Moved $entry to $nfn",LOG_DEBUG);
                }
            }
            $d->close();
        }
    }
    public function demuxFiles($background=false)
    {
        $cmd="";
        if($this->checkInputFiles()){
            if($this->checkString($this->cutfile)){
                $cmd.=" -cut " . $this->cutfile;
            }
            if($this->checkString($this->workdir)){
                $cmd.=" -out " . $this->workdir;
                $this->emptyWorkDir();
            }
            if($this->checkString($this->pids)){
                $cmd.=" -id " . $this->pids;
            }
            if($this->checkString($cmd)){
                $cmd.=" " . $this->files;
            }else{
                $cmd=$this->files;
            }
            return $this->Run($this->progs["projectx"] . $cmd);
        }
        return false;
    }
    public function editFiles()
    {
        $cmd="";
        if($this->checkInputFiles()){
            return $this->Run($this->progs["projectx"] . " -gui " . $this->files);
        }
        return false;
    }
    public function demuxSubtitles($spid="")
    {
        $cmd="";
        if($this->checkString($spid)){
            if($this->checkInputFiles()){
                if($this->checkString($this->cutfile)){
                    $cmd.=" -cut " . $this->cutfile;
                }
                if($this->checkString($this->workdir)){
                    $cmd.=" -out " . $this->workdir;
                }
                $cmd.=" -id $spid";
                $cmd.=" " . $this->files;
                return $this->Run($this->progs["projectx"] . $cmd);
            }
        }
        return false;
    }
}
?>
