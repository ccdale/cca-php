<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * duplicatemedia.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Friday 11 October 2013, 11:23:49
 * Last Modified: Monday 28 October 2013, 01:24:38
 * Revision: $Id$
 * Version: 0.00
 */

require_once "base.class.php";
require_once "DB/simple-mysql.class.php";
require_once "Shell/cli.class.php";
require_once "Media/mediawalker.class.php";

class Duplicate extends Base
{
    private $mx;
    private $dupmd5;
    private $cli;
    private $mw;

    public function __construct($logg=false)/*{{{*/
    {
        parent::__construct($logg);
        $this->mx=new MySql($logg,"localhost","mediawalker","spider","media");
        if(!$this->mx->amOK()){
            $this->error("Failure in building a DB connection, cannot really continue");
            $this->mx=null;
        }else{
            $this->cli=new CLI($logg);
            $this->mw=new MediaWalker($logg);
            $this->generateDuplicateMD5List();
        }
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    private function generateDuplicateMD5List()/*{{{*/
    {
        $sql="select count(md5) as cn,md5 from files group by md5 having cn>1 order by cn desc"; 
        $this->dupmd5=$this->mx->arrayQuery($sql);
        if(false!==($cn=$this->ValidArray($this->dupmd5))){
            $this->notice("$cn duplicate records found");
        }
    }/*}}}*/
    private function fettleDuplicate($auto=false)/*{{{*/
    {
        if(false!==($cn=$this->ValidArray($this->dupmd5))){
            $dup=array_shift($this->dupmd5);
            $md5=$dup["md5"];
            $cn=$dup["cn"];
            $sql="select a.id,pathid,path,file,md5 from files a, paths b where md5='$md5' and a.pathid=b.id";
            $arr=$this->mx->arrayQuery($sql);
            if(false!==($acn=$this->ValidArray($arr))){
                if($auto){
                    $keep=1;
                }else{
                    $iter=1;
                    foreach($arr as $darr){
                        print "$iter. " . $darr["pathid"] . " " . $darr["file"] . "\n";
                        $iter++;
                    }
                    $keep=$this->cli->cliInput("Keep [1]");
                    if($keep==""){
                        // print "setting keep to 1\n";
                        $keep=1;
                    }
                    $keep=intval($keep);
                    if($keep==0){
                        // print "keep is zero so setting to 1\n";
                        $keep=1;
                    }
                }
                if($keep>0){
                    // print "keep is greater than zero: $keep\n";
                    $keep--;
                    // print "keep is now: $keep\n";
                    // print "acn is $acn\n";
                    $delarr=array();
                    for($x=0;$x<$acn;$x++){
                        if($x!=$keep){
                            $delarr[]=$arr[$x];
                        }
                    }
                    return $delarr;
                }else{
                    $this->warning("Invalid input: $keep, Aborting");
                }
            }
        }
        return false;
    }/*}}}*/
    public function autoDuplicates()/*{{{*/
    {
        $junk=true;
        while($junk){
            $junk=$this->doNextDuplicate(true);
        }
    }/*}}}*/
    public function doNextDuplicate($auto=false)/*{{{*/
    {
        $dirty=false;
        if(false!==($delarr=$this->fettleDuplicate($auto))){
            $dirty=true;
            if(false!==($cn=$this->ValidArray($delarr))){
                if($cn==1){
                    $ftxt="file";
                }else{
                    $ftxt="files";
                }
                $this->info("$cn $ftxt to delete");
                $mpath=array();
                $dfids=array();
                foreach($delarr as $dela){
                    $fqf=unixPath($dela["path"]) . $dela["file"];
                    if(file_exists($fqf)){
                        $this->info("Deleting $fqf");
                        if(false!==($junk=unlink($fqf))){
                            $this->info("Deleted $fqf ok.");
                            $mpath[$dela["pathid"]]=$dela["path"];
                        }else{
                            $this->warning("Failed to delete $fqf");
                        }
                    }
                    // remove it from the db anyway
                    // it'll get readded if it is really there
                    // the next time mediawalk runs
                    $dfids[]=$dela["id"];
                }
                if(false!==($cn=$this->ValidArray($dfids))){
                    if($cn>1){
                        $tmp=implode(",",$dfids);
                        $sql="delete from files where id in ($tmp)";
                    }elseif($cn==1){
                        $sql="delete from files where id=" . $dfids[0];
                    }else{
                        $sql=false;
                    }
                    if(false!=$sql){
                        $this->mx->query($sql);
                        // print "$sql\n";
                    }
                }
                /*
                if(false!==($cn=$this->ValidArray($mpath))){
                    if($cn>0){
                        if($cn==1){
                            $ptxt="path";
                        }else{
                            $ptxt="paths";
                        }
                        $this->info("$cn $ptxt to re-walk");
                        foreach($mpath as $pathid=>$path){
                            $this->mw->walk($path);
                        }
                    }
                }
                 */
            }
        }
        return $dirty;
    }/*}}}*/
}
?>
