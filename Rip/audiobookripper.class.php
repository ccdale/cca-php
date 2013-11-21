<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Tuesday 12 April 2011, 02:22:54
 * Last Modified: Friday 22 April 2011, 05:55:01
 * Version: $Id: audiobookripper.class.php 538 2011-04-22 04:56:19Z chris $
 */

require_once "Rip/cdripper.class.php";
require_once "Rip/lame.class.php";
require_once "file.php";
require_once "cli.php";

class AudioBookRipper
{
    private $title="";
    private $author="";
    private $numdisks=0;
    private $ripq=array();
    private $encq=array();
    private $ripper=false;
    private $encoder=false;
    private $numtracks=0;
    private $currentdisk=0;
    private $crtrack=0; // current rip track
    private $cetrack=0; // current encoder track
    private $crfn=""; // current rip filename
    private $rootdir="";
    private $workingdir="";
    private $filedir="";
    private $outputdir="";
    private $filenamestub="";
    private $currenc=false;
    private $startat=1;
    private $finishat=0;
    private $diskstartat=1;
    private $noparanoia=false;

    public function __construct($rootdir,$startat=1,$finishat=0,$diskstartat=1,$title="",$author="",$numdisks=0,$noparanoia=false)
    {
        $this->rootdir=$rootdir;
        $this->title=$title;
        $this->author=$author;
        $this->numdisks=$numdisks;
        $this->startat=$startat;
        $this->finishat=$finishat;
        $this->diskstartat=$diskstartat;
        $this->noparanoia=$noparanoia;
        $this->init();
    }
    public function __destruct()
    {
        $this->ripper=null;
        $this->encoder=null;
    }
    private function msg($str="")
    {
        echo "$str\n";
    }
    private function init()
    {
        $this->workingdir=unixPath($this->rootdir) . "tmp";
        if(!file_exists($this->workingdir)){
            mkdir($this->workingdir,0700,true);
        }
        if(!is_string($this->author) || 0==($tmp=strlen($this->author))){
            $this->author=cliInput("Book author");
        }
        if(!is_string($this->title) || 0==($tmp=strlen($this->title))){
            $this->title=cliInput("Book title");
        }
        if(!is_int($this->numdisks) || 0==$this->numdisks){
            $this->numdisks=intval(cliInput("number of disks"));
        }
        $ta=makeSensibleFilename($this->author);
        $tt=makeSensibleFilename($this->title);
        $this->filenamestub=$ta . "_" . $tt . "-";
        $tmp=unixPath($this->rootdir) . $ta;
        $this->filedir=unixPath($tmp) . $tt;
        $this->mainLoop();
    }
    private function mainLoop()
    {
        for($this->currentdisk=$this->diskstartat;$this->currentdisk<=$this->numdisks;$this->currentdisk++){
            if(!$this->makeOutputdir()){
                $this->msg("error making output dir " . $this->outputdir);
                break;
            }
            exec("eject");
            if("n"==($tmp=strtolower(cliInput("Insert disk " . $this->currentdisk . " and press enter (n to abort)")))){
                $this->msg("Aborting");
                break; //exit due to abort
            }
            sleep(10); // disk settle time
            $this->ripper=new CDRipper($this->noparanoia);
            if($this->ripper->isReady()){
                $this->numtracks=$this->ripper->getNumTracks();
                if($this->finishat){
                    $this->numtracks=$this->finishat;
                }
                if($this->numtracks<$this->startat){
                    $this->numtracks=$this->startat;
                }
                for($tmp=$this->startat;$tmp<=$this->numtracks;$tmp++){
                    $this->ripq[]=array("ripfile"=>"D" . padString($this->currentdisk,"0",2) . "-T" . padString($tmp,"0",2) . ".wav","tracknum"=>$tmp,"numtracks"=>$this->numtracks,"disknum"=>$this->currentdisk,"outputdir"=>$this->outputdir);
                }
                $this->msg("Encoding to " . $this->outputdir);
                $this->processQ();
            }else{
                $this->msg("CD drive is not ready, aborting");
                break;
            }
        }
        $this->processQ(true);
    }
    private function makeOutputdir()
    {
        if(!file_exists($this->filedir)){
            $tmp=mkdir($this->filedir,0700,true);
            if(false==$tmp){
                return false;
            }
        }
        if(is_dir($this->filedir)){
            $this->outputdir=unixPath($this->filedir) . "disk" . padString($this->currentdisk,"0",2);
            if(file_exists($this->outputdir)){
                if(is_dir($this->outputdir)){
                    return true;
                }else{
                    return false;
                }
            }
            $tmp=mkdir($this->outputdir,0700,true);
            if(false==$tmp){
                return false;
            }else{
                return true;
            }
        }
        return false;
    }
    private function processQ($ignoreripq=false){
        $currip=false;
        // $this->currenc=false;
        $amripping=false;
        $amencoding=false;
        while(true){
            if(!$ignoreripq){
                // ripping
                if(false===$this->ripper->isRunning()){
                    $amripping=false;
                    if(is_array($currip)){
                        $this->msg("Finished ripping Disk " . $currip["disknum"] . " Track " . $currip["tracknum"] . " to " . basename($currip["fqfile"]));
                        $this->encq[]=array("wavfile"=>$currip["fqfile"],"mp3file"=>unixPath($currip["outputdir"]) . basename($currip["fqfile"],".wav") . ".mp3","tracknum"=>$currip["tracknum"],"numtracks"=>$currip["numtracks"],"disknum"=>$currip["disknum"]);
                        $currip=false;
                    }
                    $cn=count($this->ripq);
                    if($cn){
                        $this->msg("$cn tracks left to rip");
                        $currip=array_shift($this->ripq);
                        $currip["fqfile"]=unixPath($this->workingdir) . $currip["ripfile"];
                        $this->msg("Starting to rip Disk " . $currip["disknum"] . " Track " . $currip["tracknum"] . " to " . basename($currip["fqfile"]));
                        $this->ripper->ripTrackBg($currip["tracknum"],$currip["fqfile"]);
                        $amripping=true;
                    }else{
                        break;
                    }
                }
            }
            // encoding
            if(is_object($this->encoder) && $this->encoder->isRunning()){
                $amencoding=true;
            }else{
                $amencoding=false;
            }
            if(!$amencoding){
                if(is_array($this->currenc)){
                    $this->msg("Finished encoding " . basename($this->currenc["mp3file"]));
                    $this->msg("Deleting " . basename($this->currenc["wavfile"]));
                    unlink($this->currenc["wavfile"]);
                    $this->currenc=false;
                }
                $cn=count($this->encq);
                if($cn){
                    $this->msg("$cn files to encode");
                    $this->currenc=array_shift($this->encq);
                    $this->encoder=new Lame($this->currenc["wavfile"],$this->currenc["mp3file"],$this->title,$this->author,"Disk " . $this->currenc["disknum"],$this->currenc["disknum"],$this->currenc["tracknum"],$this->currenc["numtracks"]);
                    if(false===($encrunning=$this->encoder->encode())){
                        $this->msg("Failed to start encoding " . basename($this->currenc["mp3file"]));
                        $amencoding=false;
                        $this->encq[]=$this->currenc;
                        $this->currenc=false;
                    }else{
                        $this->msg("Encoding " . basename($this->currenc["mp3file"]));
                        $amencoding=true;
                    }
                }
            }
            // $this->msg("sleeping");
            if(false===$this->currenc && false===$currip){
                break;
            }
            sleep(10);
        }
    }
}
?>
