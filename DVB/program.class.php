<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * program.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Friday  4 December 2009, 12:58:26
 * Last Modified: Sunday 18 September 2011, 11:56:25
 * Version: $Id: program.class.php 713 2011-09-18 10:57:45Z chris $
 */

require_once "DB/mysql.class.php";
require_once "DB/data.class.php";
require_once "DVB/series.class.php";

if(!defined("DAYSTARTTIME")){
    define("DAYSTARTTIME",6);
}
class Program extends Data
{
    private $log=false;
    private $canlog=false;
    private $actorids=false;
    private $numaids=0;
    private $config=false;
    private $channeltype="tv";

    public function __construct($id=0,$log=false,$config=false)
    {
        $this->Data("program","id",$id,false,$log);
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
        if($this->data_id){
            if(false!==($this->actorids=$this->mx->getAllResults("aid","progactormap","pid",$this->data_id,"",MYSQL_ASSOC))){
                $this->numaids=count($this->actorids);
            }else{
                $this->numaids=0;
            }
        }
        $this->config=$config;
    }
    public function __destruct()
    {
    }
    public function superCleanDB()
    {
        if($this->canlog){
            $sql="select count(*) as numprogs from program where record='n'";
            $this->mx->query($sql);
            if($this->mx->numRows()){
                $res=$this->mx->getRow(MYSQL_ASSOC);
                if(is_array($res) && isset($res["numprogs"])){
                    $numprogs=$res["numprogs"];
                    $this->log->message("SUPERCLEANDB: About to delete $numprogs from program table.",LOG_INFO);
                }
            }
        }
        $sql="delete from program where record='n'";
        $this->mx->query($sql);
        if($this->canlog){
            $numprogs=$this->mx->numRows();
            $this->log->message("SUPERCLEANDB: $numprogs deleted from program table.",LOG_INFO);
        }
        // $this->cleanDB();
        // $this->cleanActors();
    }
    public function cleanDB()
    {
        $stop=time();
        $stop=$stop-3600;
        if($this->canlog){
            $sql="select count(*) as numprogs from program where stop<$stop";
            $this->mx->query($sql);
            if($this->mx->numRows()){
                $res=$this->mx->getRow(MYSQL_ASSOC);
                if(is_array($res) && isset($res["numprogs"])){
                    $numprogs=$res["numprogs"];
                    $this->log->message("CLEANDB: About to delete $numprogs from program table.",LOG_INFO);
                }
            }
        }
        $sql="delete from program where stop<$stop";
        $this->mx->query($sql);
        if($this->canlog){
            $numprogs=$this->mx->numRows();
            $this->log->message("CLEANDB: $numprogs deleted from program table.",LOG_INFO);
        }
    }
    public function cleanActors()
    {
        if($this->canlog){
            $sql="select count(*) as numprogs from progactormap where pid not in (select id from program)";
            $this->mx->query($sql);
            if($this->mx->numRows()){
                $res=$this->mx->getRow(MYSQL_ASSOC);
                if(is_array($res) && isset($res["numprogs"])){
                    $numprogs=$res["numprogs"];
                    $this->log->message("CLEANDB: About to delete $numprogs from program/actor map table.",LOG_INFO);
                }
            }
        }
        $sql="delete from progactormap where pid not in (select id from program)";
        $this->mx->query($sql);
        if($this->canlog){
            $numprogs=$this->mx->numRows();
            $this->log->message("CLEANDB: $numprogs deleted from program/actor map table.",LOG_INFO);
        }
    }
    public function missedRecordings()
    {
        $now=time();
        $arr=$this->mx->getAllResults("id","program",1,0,"and record='y' and stop<$now",MYSQL_ASSOC);
        if(false!==$arr && is_array($arr)){
            foreach($arr as $ar){
                $this->__construct($ar["id"],$this->log);
                $this->toggleRecord();
                $this->addSingleShot();
                if($this->canlog){
                    $msg="Missed recording of ";
                    $msg.=$this->getData("title");
                    $msg.=" on ";
                    $msg.=$this->channelName();
                    $msg.=" at ";
                    $msg.=date("D d H:i",$this->getData("start"));
                    $this->log->message($msg,LOG_INFO);
                }
            }
        }
    }
    public function updateWithRTData($rtparr)
    {
        $ret=false;
        if(isset($rtparr["channel"])){
            if(isset($rtparr["id"])){
                unset($rtparr["id"]);
            }
            $sql="select * from program where channel='" . $rtparr["channel"] . "'";
            $sql.=" and stop>" . $rtparr["start"] . "";
            $sql.=" and start<" . $rtparr["stop"] . "";
            $this->mx->query($sql);
            $cnt=$this->mx->numRows();
            if($cnt>1){
                /*
                 * there is more than one program that this program 
                 * replaces.  Make a note of these other programs 
                 * and do nothing else.
                 */
                $altarr=false;
                while($ltarr=$this->mx->getRow(MYSQL_ASSOC)){
                    $altarr[]=$ltarr;
                }
                if(is_array($altarr) && count($altarr)){
                    $ret=array("overlaps"=>$altarr,"result"=>$cnt);
                }
            }elseif($cnt==1){
                /*
                 * this is what we expect to find, only one program
                 * so xml data and rt data are in sync
                 * check the titles and if they are the same
                 * overwrite the pertinent bits
                 */
                $ret=array("result"=>$cnt);
                $altarr=false;
                while($ltarr=$this->mx->getRow(MYSQL_ASSOC)){
                    foreach($ltarr as $key=>$data){
                        $ltarr[$key]=stripslashes($data);
                    }
                    $altarr[]=$ltarr;
                }
                if($altarr[0]["title"]!=$rtparr["title"]){
                    $ret["overlaps"]=$altarr;
                }else{
                    $this->Data("program","id",$altarr[0]["id"]);
                    foreach($rtparr as $key=>$data){
                        $this->setData($key,$data);
                    }
                }
            }else{
                /*
                 * hmm how odd there is no xml data covering
                 * this rt data. make a note and do nothing else
                 */
                $ret=array("result"=>$cnt);
            }
        }else{
            if($this->canlog){
                $this->log->message("Program: updateWithRTData: Channel not set",LOG_DEBUG);
            }
        }
        return $ret;
    }
    public function removeOverlaps($newprog)
    {
        /*
            * $newprog is an array with the new program details
            * this function checks if the program currently exists
            * and does nothing if it is exactly the same.
            * if this is a replacement then the program is replaced
            * note: this new program may overlap with a number of current progs
            * if this is the case then they are all removed and this one replaces them
            * */
        if(isset($newprog["channel"])){
            unset($newprog["id"]);
            $doupdate=true;
            $sql="select * from program where channel='" . $newprog["channel"] . "'";
            $sql.=" and stop>" . $newprog["start"] . "";
            $sql.=" and start<" . $newprog["stop"] . "";
            $this->mx->query($sql);
            $cnt=$this->mx->numRows();
            if($cnt>1){
                // there is more than one program that this newprogram replaces
                // so delete them all and insert this new program
                while($ltarr=$this->mx->getRow(MYSQL_ASSOC)){
                    $altarr[]=$ltarr;
                }
                if(is_array($altarr)){
                    if($altarrcount=count($altarr)){
                        if($this->canlog){
                            $this->log->message("this program (" . $newprog["title"] . ") replaces $altarrcount programs.",LOG_DEBUG);
                        }
                        reset($altarr);
                        while(list($altarrkey,$altarrval)=each($altarr) ){
                            // debug("removing",$altarrval);
                            if($this->canlog){
                                $this->log->message("Removing " . $altarrval["title"] . " from program table.",LOG_DEBUG);
                            }
                            $sql="delete from program where id=" . $altarrval["id"];
                            $this->mx->query($sql);
                        }
                    }
                }
                $this->arr=$newprog;
                $debugstr="Adding";
            }elseif($cnt==1){
                // there is only one program that this program replaces,
                // so update it with this new information
                $ltarr=$this->mx->getRow(MYSQL_ASSOC);
                $diff=false;
                foreach($newprog as $key=>$val){
                    if($val!==$ltarr[$key]){
                        $diff=true;
                        break;
                    }
                }
                if(!$diff){
                    $doupdate=false;
                }else{
                    if($this->canlog){
                        $this->log->message("Replacing " . $ltarr["title"] . " with updated information.",LOG_DEBUG);
                    }
                    $this->data_id=$ltarr["id"];
                    $this->arr=array_merge($ltarr,$newprog);
                    $debugstr="Updating";
                }
            }else{
                // this is a new program
                $this->arr=$newprog;
                $debugstr="Adding";
                if($this->canlog){
                    $this->log->message("Inserting " . $newprog["title"] . " into program table.",LOG_DEBUG);
                }
            }
            // // debug($debugstr,$this->arr);
            if($doupdate){
                $this->updateDB();
            }
            return $cnt;
        }else{
            return false;
        }
    }
    public function getPStartTime()
    {
        return date("H:i",$this->getData("start"));
    }
    public function getPStopTime()
    {
        return date("H:i",$this->getData("stop"));
    }
    public function getPStartDate()
    {
        return date("D d",$this->getData("start"));
    }
    public function channelName()
    {
        $c=new Channel($this->getData("channel"));
        return $c->getData("name");
    }
    public function getChannelType()
    {
        $c=new Channel($this->getData("channel"));
        return $c->getData("type");
    }
    public function toggleSeries()
    {
        $tmp=trim($this->getData("seriesid"));
        // debug("seriesid",$tmp);
        // debug("len",strlen($tmp));
        if(is_string($tmp) && $tmp){
            $rec=$this->mx->getSingleRow("futurerecord","seriesid",$tmp);
            // debug("record",print_r($rec,true));
            if(isset($rec["id"])){
                $id=$rec["id"];
                $sql="delete from futurerecord where id=$id";
            }else{
                $sql="insert into futurerecord (title,seriesid) values ('" . addslashes($this->getData("title")) . "','$tmp')";
                $this->recordThisProgram();
            }
            // debug("running this sql",$sql);
            if($this->canlog){
                $this->log->message("toggleSeries: $sql.",LOG_DEBUG);
            }
            $this->mx->query($sql);
        }
    }
    public function recordThisProgram()
    {
        $rr=$this->getData("record");
        if($rr!="y"){
            $this->toggleRecord();
        }
    }
    public function toggleRecord()
    {
        if($this->data_id){
            $rr=$this->getData("record");
            if($rr=="n" || $rr=="c" || $rr=="l"){
                if($this->wasPreviouslyRecorded()){
                    $this->setData("record","p");
                }else{
                    if($this->otherProgramsConflict()){
                        if(false===($arr=$this->laterShowings())){
                            $this->setData("record","c");
                        }else{
                            $this->setData("record","c");
                            if(is_array($arr) && count($arr)){
                                foreach($arr as $laterprog){
                                    $p=new Program($laterprog["id"],$this->log);
                                    if($p->getData("record")=="n"){
                                        $p->toggleRecord();
                                        if($p->getData("record")=="y"){
                                            $this->setData("record","l");
                                            break;
                                        }
                                    }
                                }
                                $p=null;
                            }
                        }
                    }else{
                        $this->setData("record","y");
                    }
                }
            }else{
                $this->setData("record","n");
            }
            if($this->canlog){
                $tmp=$this->getData("record");
                $this->log->message("toggleRecord: from $rr to $tmp.",LOG_DEBUG);
            }
            if($this->getData("record")=="c"){
                $this->addSingleShot();
            }
        }
    }
    public function addSingleShot()
    {
        $pid=trim($this->getData("programid"));
        if(is_string($pid) && strlen($pid)){
            $sr=new Series(0,$this->log);
            $sr->selectByProgramId($pid);
            if(!$sr->isInDB()){
                $this->mx->query("insert into futurerecord (programid,title) values ('$pid','" . addslashes($this->getData("title")) . "')");
            }
        }
    }
    public function otherProgramsConflict()
    {
        $ret=false;
        $sql="select * from program where stop>" . $this->arr["start"] . " and start<" . $this->arr["stop"] . " and mux!=" . $this->arr["mux"] . " and id!=" . $this->arr["id"] . " and record='y'";
        $this->mx->query($sql);
        if($this->mx->numRows()){
            $ret=true;
        }
        return $ret;
    }
    public function conflictingIDs()
    {
        $ret=false;
        $sql="select * from program where stop>" . $this->arr["start"] . " and start<" . $this->arr["stop"] . " and mux!=" . $this->arr["mux"] . " and id!=" . $this->arr["id"] . " and record='y'";
        $this->mx->query($sql);
        if($this->mx->numRows()){
            $ret=array();
            while($tmp=$this->mx->getRow(MYSQL_ASSOC)){
                $ret[]=$tmp;
            }
        }
        return $ret;
    }
    public function laterShowings($samechannel=true)
    {
        $ret=false;
        $c=new Channel($this->getData("channel"));
        if(0!==($p1=$c->getData("hasplusone"))){
            $csql=" and channel in (" . $this->arr["channel"] . ",$p1)";
        }else{
            $csql=" and channel=" . $this->arr["channel"];
        }
        $sql="select id,start from program where title='" . addslashes($this->arr["title"]) . "' and description='" . addslashes($this->arr["description"]) . "' and id!=" . $this->arr["id"];
        if($samechannel){
            // $sql.=" and channel=" . $this->arr["channel"];
            $sql.=$csql;
        }
        $sql.=" and start>unix_timestamp() order by start asc";
        $this->mx->query($sql);
        if($this->mx->numRows()){
            $ret=array();
            while($tmp=$this->mx->getRow(MYSQL_ASSOC)){
                $ret[]=$tmp;
            }
        }
        return $ret;
    }
    public function wasPreviouslyRecorded()
    {
        $ret=false;
        $programid=$this->getData("programid");
        if(is_string($programid) && strlen($programid)){
            $sql="select id from previousrecorded where programid='$programid'";
        }else{
            $sql="select id from previousrecorded where title='" . addslashes($this->arr["title"]) . "' and description='" . addslashes($this->arr["description"]) . "'";
        }
        $this->mx->query($sql);
        if($this->mx->numRows()){
            $ret=true;
        }
        return $ret;
    }
    public function previousIDs()
    {
        $ret=false;
        $sql="select id from previousrecorded where title='" . addslashes($this->arr["title"]) . "' and description='" . addslashes($this->arr["description"]) . "'";
        $this->mx->query($sql);
        if($this->mx->numRows()){
            $arr=array();
            while($row=$this->mx->getRow(MYSQL_ASSOC)){
                $arr[]=$row;
            }
            $ret=$arr;
        }
        return $ret;
    }
    public function schedule($paged=false,$offset=0)
    {
        if($paged){
            // $now=time();
            $now=mktime(0,0);
            $now+=$offset;
            $tomorrow=$now+(3600*24);
            // $where=" or (record='c' or record='l' or record='p' or record='r') and stop>$now and start<$tomorrow order by start";
            $sql="select * from program where start<$tomorrow and stop>$now and record!='n' order by start";
        }else{
            $sql="select * from program where record!='n' order by start";
            // $where=" or record='c' or record='l' or record='p' or record='r' order by start";
        }
        // return $this->mx->getAllResults("*","program","record","y",$where,MYSQL_ASSOC);
        return $this->mx->queryResult($sql);
    }
    public function nextRecordStart($outputstatus=false)
    {
        $now=time();
        $tmp=$this->mx->getAllResults("start,title,id,channel","program","record","y","and stop>$now order by start limit 0,1",MYSQL_ASSOC);
        if(is_array($tmp) && isset($tmp[0]) && isset($tmp[0]["start"])){
            $c=new Channel($tmp[0]["channel"],$this->log);
            $this->channeltype=$c->getData("type");
            if($outputstatus && $this->canlog){
                $cname=$c->getData("name");
                $c=null;
                $this->log->message("Next recording: " . $tmp[0]["title"] . " on $cname at " . date("H:i",$tmp[0]["start"]),LOG_INFO);
            }
            return $tmp[0]["start"];
        }else{
            return PHP_INT_MAX;
        }
    }
    public function nextRecordType()
    {
        return $this->channeltype;
    }
    public function nextRecordId()
    {
        $now=time();
        $where=" and stop>$now order by start limit 0,1";
        $tmp=$this->mx->getAllResults("id","program","record","y",$where,MYSQL_ASSOC);
        if(is_array($tmp) && isset($tmp[0]) && isset($tmp[0]["id"])){
            $this->Data("program","id",$tmp[0]["id"]);
            return true;
        }
        return false;
    }
    public function lastProgramStart()
    {
        $arr=$this->mx->getAllResults("max(start)","program");
        return $arr[0][0];
    }
    public function isInDB()
    {
        if($this->data_id){
            return true;
        }else{
            return false;
        }
    }
    public function allPreviousR()
    {
        return $this->mx->getAllResults("*","previousrecorded",1,0,"order by title",MYSQL_ASSOC);
    }
    public function deletePreviousRecorded($id=0)
    {
        if(is_int($id) && $id){
            $this->mx->query("delete from previousrecorded where id=$id");
        }
    }
    public function previousTitle($prid)
    {
        $parr=false;
        $prid=intval($prid);
        if($prid){
            $arr=$this->mx->getSingleRow("previousrecorded","id",$prid);
            if(is_array($arr) && isset($arr["title"])){
                $parr=$this->mx->getAllResults("*","previousrecorded","title",$arr["title"],"",MYSQL_ASSOC);
            }
        }
        return $parr;
    }
    public function films($offset)
    {
        // $now=time();
        $now=mktime(DAYSTARTTIME,0);
        $start=$now+$offset;
        $stop=$start+TWENTYFOURHOURS;
        // $sql="select id from program where stop>$start and start<$stop and description REGEXP '.*\[[1|2][0|9][0-9][0-9]\].*'";
        // debug($sql);
        // $this->mx->query($sql);
        $tarr=$this->mx->getAllResults("id","program",1,0,"and stop>$start and start<$stop and description REGEXP '.*[1|2][0|9][0-9][0-9].*' and channel in (select id from channel where type='tv') order by start",MYSQL_ASSOC);
        // $tarr=$this->mx->getAllResults("id","program",1,0,"and stop>$start and start<$stop and film='y' order by start",MYSQL_ASSOC);
        // while($arr=$this->mx->getRow(MYSQL_ASSOC)){
            // $tarr[]=$arr;
        // }
        return $tarr;
    }
    public function allGenres()
    {
        $sql="select distinct genre from program";
        return $this->mx->queryResult($sql);
    }
    public function channelPrograms($qvars)
    {
        if(!isset($qvars["channelid"])){
            $qvars["channelid"]=5; /* BBC TWO */
        }
        if(!isset($qvars["offset"])){
            $qvars["offset"]=0;
        }
        // $starttime=time()+$qvars["offset"];
        $starttime=mktime(DAYSTARTTIME,0)+$qvars["offset"];
        $stoptime=$starttime+(24*60*60);
        $ext="and stop>$starttime and start<$stoptime order by start";
        return $this->mx->getAllResults("*","program","channel",$qvars["channelid"],$ext,MYSQL_ASSOC);
    }
    public function timeslotChannelPrograms($starttime=0,$stoptime=0,$channelid=0)
    {
        if(!$starttime){
            $starttime=time();
        }
        if(!$stoptime){
            $stoptime=$starttime+(TABLEWIDTH*60);
        }
        if(!$channelid){
            $channelid=5; // BBC TWO
        }
        $ext="and stop>$starttime and start<$stoptime order by start";
        $select="id,mux,title,start,stop,(stop-start) as length,channel,description,programid,seriesid,record";
        return $this->mx->getAllResults($select,"program","channel",$channelid,$ext,MYSQL_ASSOC);
    }
    public function genrePrograms($qvars)
    {
        // $starttime=time()+$qvars["offset"];
        $starttime=mktime(DAYSTARTTIME,0)+$qvars["offset"];
        $stoptime=$starttime+(24*60*60);
        $ext="and genre='" . $qvars["genre"] . "' and stop>$starttime and start<$stoptime order by start";
        return $this->mx->getAllResults("*","program",1,0,$ext,MYSQL_ASSOC);
    }
    public function getActorsAsArray()
    {
        if($this->numaids){
            $tmp=array();
            foreach($this->actorids as $aid){
                $a=new Actor($aid["aid"]);
                $tmp[]=$a->getName();
            }
            return $tmp;
        }
        return false;
    }
    public function getActorsAsString($xg=false)
    {
        if(false!==($aarr=$this->getActorsAsArray())){
            $tmp="";
            $xg["act"]="showactorprograms";
            foreach($aarr as $aname){
                $taname="";
                if(is_array($xg) && isset($xg["act"])){
                    $a=new Actor($aname);
                    $fav=$a->getData("favourite");
                    if($fav=="y"){
                        $class="red";
                    }else{
                        $class="green";
                    }
                    $xg["aid"]=$a->getData("id");
                    $al=new ALink($xg,$a->getData("name"),"",$class);
                    $taname=$al->makeLink();
                }
                if(strlen($tmp)){
                    $tmp.=", $taname";
                }else{
                    $tmp=$taname;
                }
            }
            return $tmp;
        }
        return false;
    }
    public function rtChoicePrograms()
    {
        return $this->mx->getAllResults("*","program","choice","y","order by start",MYSQL_ASSOC);
    }
    public function rtNewSeries()
    {
        return $this->mx->getAllResults("*","program","newseries","y","order by start",MYSQL_ASSOC);
    }
    public function currentlyRecording()
    {
        return $this->mx->getAllResults("*",$this->table,"record","r","order by start",MYSQL_ASSOC);
    }
    public function allProgramsNowNF($mux=0)
    {
        $mux=intval($mux);
        if($mux){
            $sql="select p.id as pid,c.id as cid,c.name as name,c.mux as mux,p.start as start,p.stop as stop,p.title as title from program p,channel c where p.channel=c.id and c.favourite='n' and c.access='y' and p.start<unix_timestamp()  and p.stop>unix_timestamp() and c.mux=$mux and c.aid!=0 order by c.position, start, type DESC";
        }else{
            $sql="select p.id as pid,c.id as cid,c.name as name,c.mux as mux,p.start as start,p.stop as stop,p.title as title from program p,channel c where p.channel=c.id and c.favourite='n' and c.access='y' and p.start<unix_timestamp()  and p.stop>unix_timestamp() and c.aid!=0 order by c.position, start, type DESC";
        }
        return $this->mx->queryResult($sql);
    }
    public function allProgramsNow($mux=0)
    {
        $mux=intval($mux);
        if($mux){
            $sql="select p.id as pid,c.id as cid,c.name as name,c.mux as mux,p.start as start,p.stop as stop,p.title as title from program p,channel c where p.channel=c.id and c.favourite='y' and c.access='y' and p.start<unix_timestamp()  and p.stop>unix_timestamp() and c.mux=$mux and c.aid!=0 order by c.position, start,type DESC";
        }else{
            $sql="select p.id as pid,c.id as cid,c.name as name,c.mux as mux,p.start as start,p.stop as stop,p.title as title from program p,channel c where p.channel=c.id and c.favourite='y' and c.access='y' and p.start<unix_timestamp()  and p.stop>unix_timestamp() and c.aid!=0 order by c.position, start,type DESC";
        }
        return $this->mx->queryResult($sql);
    }
}
?>
