<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 :
 *
 * mediawalker.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Sunday  6 October 2013, 10:33:27
 * Last Modified: Sunday 13 October 2013, 03:59:41
 * Revision: $Id$
 * Version: 0.00
 */

require_once "Media/ffprobe.class.php";

class MediaWalker extends Base
{
    private $mx;
    private $ffprobe;
    // private $ffprobeop;
    // private $ffprobefqf;
    // private $duration;
    // private $hasvideo;
    // private $hasaudio;
    // private $ismedia;

    public function __construct($logg=false)
    {
        parent::__construct($logg);
        $this->mx=new MySql($logg,"localhost","mediawalker","spider","media");
        if(!$this->mx->amOK()){
            $this->error("Failure in building a DB connection, cannot really continue");
            $this->mx=null;
        }
        // set up the ffprobe class for later use
        $this->ffprobe=new Ffprobe($logg);
        /*
        $this->readdir();
        $this->info($this->currentdir);
        $cn=count($this->directories);
        $this->info("dirs: $cn");
        $cn=count($this->files);
        $this->info("files: $cn");
        print "\n\n";
        print_r($this->files);
        print "\n\n";
         */
    }
    public function __destruct()
    {
        $this->mx=null;
        parent::__destruct();
    }
    // private function resetffprobe()
    // {
    //     $this->ffprobeop=false;
    //     $this->ffprobefqf=false;
    //     $this->duration=false;
    //     $this->hasvideo=false;
    //     $this->hasaudio=false;
    //     $this->ismedia=false;
    // }
    // private function ffprobe($fqf)
    // {
    //     $this->resetffprobe();
    //     $cmd="/usr/bin/ffprobe \"$fqf\" 2>&1";
    //     $ll=exec($cmd,$op,$ret);
    //     if($ret==0){
    //         $this->ffprobeop=$op;
    //         $this->ffprobefqf=$fqf;
    //         if($this->ValidArray($op)){
    //             foreach($op as $line){
    //                 $line=trim($line);
    //                 $tmp=substr($line,0,4);
    //                 switch($tmp){
    //                 case "Stre":
    //                     $stmp=explode(":",$line);
    //                     $stream=trim($stmp[1]);
    //                     switch($stream){
    //                     case "Video":
    //                         $this->debug("video stream found in $fqf");
    //                         $this->hasvideo=true;
    //                         break;
    //                     case "Audio":
    //                         $this->hasaudio=true;
    //                         $this->debug("Audio stream found in $fqf");
    //                         break;
    //                     default:
    //                         break;
    //                     }
    //                     break;
    //                 case "Dura":
    //                     $stmp=explode(":",$line);
    //                     $hrs=trim($stmp[1]);
    //                     $mins=$stmp[2];
    //                     $secs=substr($stmp[3],0,2);
    //                     $this->duration=$this->hmsToSec("$hrs:$mins:$secs");
    //                     $this->debug("$fqf: $hrs:$mins:$secs");
    //                     break;
    //                 default:
    //                     break;
    //                 }
    //             }
    //             if($this->duration>0){
    //                 $this->ismedia=true;
    //                 $this->debug("media file detected: $fqf");
    //             }
    //         }
    //     }
    //     return $this->ismedia;
    // }
    // private function videoDuration($fqf) // {{{
    // {
    //     $ret=false;
    //     if($this->ffprobefqf!==false && $this->ValidArray($this->ffprobeop)){
    //         foreach($this->ffprobeop as $line){
    //             $line=trim($line);
    //         }
    //         // if(checkFile($fqf,CCA_FILE_EXIST)){
    //         //     $cmd="dat=`/usr/bin/ffprobe \"" . $fqf . "\" 2>&1 |grep Duration |cut -d\" \" -f4`; dat=\${dat%%.*}; echo \$dat";
    //         //     $tmp=exec($cmd);
    //         //     $ret=$this->hmsToSec($tmp);
    //         // }
    //     }
    //     return $ret;
    // } // }}}
    private function md5sum($fqf)
    {
        $ret=false;
        if(checkFile($fqf,CCA_FILE_EXIST)){
            $cmd="/usr/bin/md5sum \"$fqf\" 2>/dev/null |cut -d\" \" -f1 2>/dev/null";
            $ret=exec($cmd);
        }
        return $ret;
    }
    private function getPathId($path)
    {
        $pathid=0;
        $sql="select id from paths where path='$path'";
        if(false==($tarr=$this->mx->arrayQuery($sql))){
            $tmp=$this->mx->insertQuery("insert into paths set path='$path'");
            $tmp=intval($tmp);
            if($tmp){
                $pathid=$tmp;
            }
        }else{
            $tmp=intval($tarr[0]["id"]);
            if($tmp){
                $pathid=$tmp;
            }
        }
        return $pathid;
    }
    private function fettleFile($path,$file,$pathid=0)
    {
        $fqf=unixPath($path) . $file;
        // $this->ffprobe($fqf);
        $ismedia=$this->ffprobe->probe($fqf);
        if(false!==$ismedia){
            $dur=$this->ffprobe->getDuration();
            if($dur>0){
                /*
                // first check for a pathid
                // create one if it is not set yet
                if(0==$pathid){
                    $pathid=$this->getPathId($path);
                    $sql="select id from paths where path='$path'";
                    if(false==($tarr=$this->mx->arrayQuery($sql))){
                        $tmp=$this->mx->insertQuery("insert into paths set path='$path'");
                        $tmp=intval($tmp);
                        if($tmp){
                            $pathid=$tmp;
                        }
                    }else{
                        $tmp=intval($tarr[0]["id"]);
                        if($tmp){
                            $pathid=$tmp;
                        }
                    }
                }
                 */
                // check that the pathid has now been set ok
                if(0==$pathid){
                    $this->error("Cannot create/obtain pathid for path: $path");
                }else{
                    // we have the pathid and the duration
                    // lets pop this file into the db if it doesn't already exist
                    $sql="select id from files where file=\"$file\" and pathid=$pathid";
                    if(false==($tarr=$this->mx->arrayQuery($sql))){
                        if(false!==($md5=$this->md5sum($fqf))){
                            $video=$this->ffprobe->getVideo()?"y":"n";
                            $tmp=$this->mx->insertQuery("insert into files (pathid,file,md5,duration,video) values ($pathid,\"$file\",\"$md5\",$dur,\"$video\")");
                            if($tmp){
                                $this->notice("inserted $file into db ok.");
                            }else{
                                $this->warning("Failed to insert $file into db. path: $path");
                            }
                        }else{
                            $this->warning("Failed to compute md5 sum for file $fqf at path: $path");
                        }
                    }else{
                        $this->debug("$file already exists in db at path: $path");
                    }
                }
            }
        }
        return $pathid;
    }
    private function compareDbToDir($path,$pathid,$filesarr)
    {
        $deletearr=array();
        $deletenames=array();
        if($pathid>0){
            $dbarr=$this->mx->arrayQuery("select * from files where pathid=$pathid");
            if(false!==($cn=$this->ValidArray($dbarr))){
                if($cn>0){
                    $this->debug("$cn files to check in directory: $path");
                    foreach($dbarr as $dbfile){
                        if(in_array($dbfile["file"],$filesarr)){
                            $this->debug("file in db appears on disk ok.");
                        }else{
                            $this->warning("file in db is no longer on disk: " . $dbfile["file"]);
                            $deletearr[]=$dbfile["id"];
                            $deletenames[]=$dbfile["file"];
                        }
                    }
                }else{
                    $this->debug("No files in db for path: $path");
                }
            }else{
                $this->debug("Invalid array returned from db query for path: $path, pathid: $pathid");
            }
        }else{
            $this->debug("Pathid not set: $pathid for path: $path");
        }
        if(false!==($cn=$this->ValidArray($deletearr))){
            if($cn>0){
                $this->notice("$cn files in db to remove");
                $tmsg=implode(",",$deletenames);
                $this->notice("Removing files from db at path: $path, $tmsg");
                $tlist=implode(",",$deletearr);
                $sql="delete from files where pathid=$pathid and id in ($tlist)";
                if(true==($rs=$this->mx->query($sql))){
                    $this->warning("$cn files removed successfully.");
                }else{
                    $this->error("failed to remove $cn files from db.");
                }
            }
        }
    }
    public function walk($path)
    {
        $pathid=$this->getPathId($path);
        if($this->ValidStr($path) && file_exists($path) && is_dir($path)){
            $this->info("Moving into $path");
            if(false!==($arr=directoryRead($path))){
                if($this->ValidArray($arr["directories"])){
                    foreach($arr["directories"] as $dir){
                        // recurse through the tree
                        $this->walk(unixPath($path) . $dir);
                    }
                }
                if($this->ValidArray($arr["files"])){
                    // check if any files have been deleted
                    $this->compareDbToDir($path,$pathid,$arr["files"]);
                    // add any new files to the db
                    foreach($arr["files"] as $fn){
                        $pathid=$this->fettleFile($path,$fn,$pathid);
                    }
                }
            }else{
                $this->warning("Failed to read directory: $path");
            }
        }else{
            $tmp=print_r($path,true);
            $this->warning("Invalid string passed to walk method: $tmp");
        }
    }
    private function readdir()
    {
        if($this->ValidStr($this->startdir)){
            $this->debug("startdir is a valid string");
            $arr=directoryRead($this->startdir);
            if($this->ValidArray($arr)){
                if(isset($arr["directories"]) && $this->ValidArray($arr["directories"])){
                    foreach($arr["directories"] as $dir){
                        $this->directories[]=unixPath($this->currentdir) . $dir;
                    }
                }
                if(isset($arr["files"]) && $this->ValidArray($arr["files"])){
                    foreach($arr["files"] as $fn){
                        $this->files[]=unixPath($this->currentdir) . $fn;
                    }
                }
            }
        }else{
            $this->debug("startdir is not a valid string");
        }
    }
}

?>
