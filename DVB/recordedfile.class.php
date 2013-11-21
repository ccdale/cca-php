<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Wednesday 14 July 2010, 04:12:46
 * Last Modified: Saturday 17 September 2011, 21:47:50
 *
 * $Id: recordedfile.class.php 710 2011-09-17 20:55:53Z chris $
 */

require_once "DB/data.class.php";
require_once "DVB/stream.class.php";
require_once "video.php";

class RecordedFile extends Data
{
    private $log=false;
    private $canlog=false;
    private $config=false;

    public function __construct($id=0,$log=false,$config=false,$filename="",$directory="",$rid=0,$fnum=0,$type="tv")
    {
        $this->Data("rfile","id",$id,$config,$log);
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
        $this->config=$config;
        if($rid){
            return $this->newFile($filename,$directory,$rid,$fnum,$type);
        }
        return false;
    }
    public function __destruct()
    {
        $this->finishFile();
        $this->logg("RecordedFile: __destruct: " . $this->fqfn(),LOG_DEBUG);
    }
    private function logg($msg,$level=LOG_INFO)
    {
        if($this->canlog){
            $this->log->message($msg,$level);
        }
    }
    public function newFile($filename,$directory,$rid,$fnum,$type)
    {
        if(is_string($filename) && $filename){
            if(is_string($directory) && $directory){
                $rid=intval($rid);
                if($rid){
                    $fnum=intval($fnum);
                    if($fnum){
                        $this->arr["filename"]=$filename;
                        $this->arr["directory"]=$directory;
                        $this->arr["rid"]=$rid;
                        $this->arr["fnum"]=$fnum;
                        $this->arr["type"]=$type;
                        $this->setData("start",time()); // setData auto calls updateDB so record is created
                        if($this->data_id){
                            $this->logg("RecordedFile: newFile: new file entered into db as id: {$this->data_id}",LOG_DEBUG);
                            return true;
                        }else{
                            $this->logg("RecordedFile: newFile: failed to enter data into db",LOG_WARNING);
                        }
                    }else{
                        $this->logg("RecordedFile newFile: " . "invalid fnum: $fnum",LOG_WARNING);
                    }
                }else{
                    $this->logg("RecordedFile newFile: " . "invalid rid $rid",LOG_WARNING);
                }
            }else{
                $this->logg("RecordedFile newFile: " . "invalid directory: $directory",LOG_WARNING);
            }
        }else{
            $this->logg("RecordedFile newFile: " . "invalid filename: $filename",LOG_WARNING);
        }
        return false;
    }
    public function finishFile()
    {
        $this->setData("stop",time());
        // $this->setData("vlen",secToHMS($this->getData("stop")-$this->getData("start")));
        $this->setData("vlen",videoDuration($this->fqfn()));
        $this->logg("RecordedFile: finishFile: video duration: " . $this->getData("vlen"),LOG_DEBUG);
        /*
        $vdur=videoDuration($this->fqfn());
        if(false!==$vdur){
            $this->setData("vlen",secToHMS($vdur));
        }else{
            $this->logg("RecordedFile: finishFile: error setting video duration for " . $this->fqfn(),LOG_DEBUG);
        }
         */
    }
    public function fqfn($showcentre=false)
    {
        // $tvdir=$showcentre?$this->config["scdir"]:$this->config["tvdir"];
        if($showcentre){
            $tvdir=$this->config["scdir"];
        }else{
            $tvdir=$this->config["tvdir"];
        }
        return unixPath(unixPath($tvdir) . $this->getData("directory")) . $this->getData("filename");
    }
    public function deleteThisFile()
    {
        if(false!==checkFile($this->fqfn(),CCA_FILE_EXIST)){
            if(false!==($nfn=noClobberFileMove($this->fqfn(),$this->config["thebin"]))){
                $this->logg("RecordedFile: deleteThisFile: " . $this->fqfn() . " moved to $nfn",LOG_DEBUG);
            }else{
                $this->logg("RecordedFile: deleteThisFile: Failed to move " . $this->fqfn() . " to the bin.",LOG_WARNING);
            }
        }
        $this->deleteMe();
    }
}
?>
