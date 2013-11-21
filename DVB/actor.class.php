<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Thursday  4 March 2010, 15:15:20
 * Last Modified: Saturday 17 September 2011, 21:52:25
 * Version: $Id: actor.class.php 710 2011-09-17 20:55:53Z chris $
 */

class Actor extends Data
{
    private $programs=false;
    private $numprogs=0;
    private $log=false;
    private $canlog=false;

    public function __construct($name="",$log=false)
    {
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
        if(is_string($name) && strlen($name)){
            $id=intval($name);
            if($id>0){
                $this->Data("actor","id",$id,false,$log);
            }else{
                $this->Data("actor","name",addslashes($name),false,$log);
                if(is_array($this->arr)){
                    if(isset($this->arr["id"])){
                        $this->Data("actor","id",$this->arr["id"],false,$log);
                    }else{
                        $this->Data("actor","id",0,false,$log);
                    }
                }
                if($this->data_id==0){
                    $this->setName($name);
                    if($this->canlog){
                        $this->log->message("Actor::_construct:Created $name",LOG_DEBUG);
                    }
                }else{
                    if($this->canlog){
                        $this->log->message("Actor::_construct:Found $name existing",LOG_DEBUG);
                    }
                }
            }
        }elseif(is_int($name) && $name){
            $this->Data("actor","id",$name,false,$log);
        }else{
            $this->Data("actor","id",0,false,$log);
        }
    }
    public function __destruct()
    {
    }
    public function setName($name="")
    {
        if(is_string($name) && strlen($name)){
            $this->setData("name",$name);
        }
    }
    public function getName()
    {
        return $this->getData("name");
    }
    public function addPid($pid=0)
    {
        if(is_int($pid) && $pid && $this->data_id){
            $arr=$this->mx->getAllResults("*","progactormap","aid",$this->data_id,"and pid=$pid",MYSQL_ASSOC);
            if(false==$arr){
                $sql="insert into progactormap set aid=" . $this->data_id . ", pid=$pid";
                $this->mx->query($sql);
                if($this->mx->_insert_id){
                    return true;
                }
            }else{
                // record already exists
                return true;
            }
        }
        if($this->canlog){
            $this->log->message("Actor::addPid:pid:$pid,data_id:" . $this->data_id,LOG_DEBUG);
        }
        return false;
    }
    public function getProgramIds()
    {
        $sql="and id in (select pid from progactormap where aid=" . $this->data_id . ") order by title";
        return $this->mx->getAllResults("id","program",1,0,$sql,MYSQL_ASSOC);
    }
    public function toggleFavourite()
    {
        $fav=$this->getData("favourite");
        if($fav=="n"){
            $this->setData("favourite","y");
            return "y";
        }else{
            $this->setData("favourite","n");
            return "n";
        }
    }
    public function actorsByLetter($letter="a")
    {
        $ret=false;
        if(is_string($letter) && (1==strlen($letter))){
            $search=$letter . "%";
            $sql="and name like '$search' order by name";
            $aarr=$this->mx->getAllResults("*","actor",1,0,$sql,MYSQL_ASSOC);
            if(is_array($aarr) && count($aarr)){
                $ret=$aarr;
            }
        }
        return $ret;
    }
    public function actorPrograms()
    {
        $rarr=false;
        $sql="select p.id as id,c.name as channel,p.start as start,p.title as title from program p, channel c, ";
        $sql.="(select pid from progactormap where aid=" . $this->data_id . ") as dummy ";
        $sql.="where c.id=p.channel and p.id=dummy.pid order by start";
        $this->mx->query($sql);
        if($this->mx->numRows()){
            $rarr=array();
            while(false!==($arr=$this->mx->getRow(MYSQL_ASSOC))){
                $rarr[]=$arr;
            }
        }
        return $rarr;
    }
    public function activeActors()
    {
        $ret=false;
        $sql="and id in (select distinct aid from progactormap) order by name";
        $arr=$this->mx->getAllResults("*","actor",1,0,$sql,MYSQL_ASSOC);
        if(is_array($arr) && count($arr)){
            $ret=$arr;
        }
        return $ret;
    }
    public function activeLetter()
    {
        $ret=false;
        if(false!==($arr=$this->activeActors())){
            $op=array();
            foreach($arr as $actor){
                $tmp=strtolower(substr($actor["name"],0,1));
                $op[$tmp][]=$actor;
            }
            $ret=$op;
        }
        return $ret;
    }
}
?>
