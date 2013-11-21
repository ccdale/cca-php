<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * channel.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Sunday  6 December 2009, 09:06:51
 * Last Modified: Saturday 17 September 2011, 21:57:45
 * Version: $Id: channel.class.php 711 2011-09-17 20:58:11Z chris $
 */

require_once "DB/mysql.class.php";
require_once "DB/data.class.php";

class Channel extends Data
{
    private $log=false;
    private $canlog=false;

    public function __construct($id=0,$log=false)
    {
        $this->Data("channel","id",$id,false,$log);
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
    }
    public function __destruct()
    {
    }
    private function logg($msg,$level=LOG_INFO)
    {
        if($this->canlog){
            $this->log->message($msg,$level);
        }
    }
    public function channelUpdateInfo($chaninfo)
    {
        if(is_array($chaninfo) && isset($chaninfo["name"])){
            $rec=$this->mx->getRecord("channel","name",$chaninfo["name"],MYSQL_ASSOC);
            if(is_array($rec) && isset($rec["id"])){
                $this->Data("channel","id",$rec["id"]);
                // $this->data_id=$rec["id"];
                unset($rec["id"]);
                $oldinfo=$this->mx->whereClause($this->arr);
                $oldinfo=str_replace(" and ",",",$oldinfo);
                $chaninfo=array_merge($rec,$chaninfo);
            }
            $this->arr=$chaninfo;
            $this->updateDB();
            if($this->canlog){
                $this->log->message("Updating channel " . $this->getName(),LOG_DEBUG);
                $this->log->message("Old info: " . $oldinfo,LOG_DEBUG);
                $newinfo=$this->mx->whereClause($this->arr);
                $newinfo=str_replace(" and ",",",$newinfo);
                $this->log->message("New info: " . $newinfo,LOG_DEBUG);
            }
        }
    }
    public function allChannels($favourites=false,$withencrypted=false)
    {
        $ret=false;
        $ext=$favourites?"and favourite='y'":"and favourite='n'";
        if($withencrypted){
            $arec=$this->mx->getAllResults("*","channel",1,0,$ext,MYSQL_ASSOC);
        }else{
            $arec=$this->mx->getAllResults("*","channel","access",'y',$ext,MYSQL_ASSOC);
        }
        if(is_array($arec) && count($arec)){
            $ret=$arec;
        }
        return $ret;
    }
    public function tvChannels($favourites=true,$withencrypted=false)
    {
        $ret=false;
        $ext=0;
        if($withencrypted && $favourites){
            $ext="and favourite='y'";
        }else{
            if($favourites){
                $ext="and access='y' and favourite='y' and getdata='y'";
            }else{
                $ext="and access='y' and favourite='n' and getdata='y'";
            }
        }
        if($ext===0){
            $ext="order by mux,position";
        }else{
            $ext.=" order by mux,position";
        }
        $arec=$this->mx->getAllResults("*","channel","type","tv",$ext,MYSQL_BOTH);
        if(is_array($arec) && count($arec)){
            $ret=$arec;
        }
        return $ret;
    }
    public function favChannels($favs=true)
    {
        $ret=false;
        $ext="and access='y' and getdata='y' order by mux,position";
        if($favs){
            $arec=$this->mx->getAllResults("*","channel","favourite","y",$ext,MYSQL_ASSOC);
        }else{
            $arec=$this->mx->getAllResults("*","channel","favourite","n",$ext,MYSQL_ASSOC);
        }
        if(is_array($arec) && count($arec)){
            $ret=$arec;
        }
        return $ret;
    }
    public function radioChannels($favourites=true,$withencrypted=false)
    {
        $ret=false;
        $ext=0;
        if($withencrypted && $favourites){
            $ext="and favourite='y'";
        }else{
            if($favourites){
                $ext="and access='y' and favourite='y' and getdata='y'";
            }else{
                $ext="and access='y' and favourite='n' and getdata='y'";
            }
        }
        if($ext===0){
            $ext="order by mux,position";
        }else{
            $ext.=" order by mux,position";
        }
        $arec=$this->mx->getAllResults("*","channel","type","radio",$ext,MYSQL_BOTH);
        if(is_array($arec) && count($arec)){
            $ret=$arec;
        }
        return $ret;
    }
    public function byMux($mux,$favs=true,$withencrypted=false,$type="tv")
    {
        $ret=false;
        $ext=0;
        if($withencrypted && $favs){
            $ext="and favourite='y'";
        }else{
            if($favs){
                $ext="and access='y' and favourite='y'";
            }else{
                $ext="and access='y' and favourite='n'";
            }
        }
        if($ext===0){
            $ext="order by position";
        }else{
            $ext.=" order by position";
        }
        $ext="and mux='$mux' $ext";
        $arec=$this->mx->getAllResults("*","channel","type",$type,$ext,MYSQL_BOTH);
        if(is_array($arec) && count($arec)){
            $ret=$arec;
        }
        return $ret;
    }
    public function byName($channelname="",$favs=true,$withencrypted=false)
    {
        $id=$this->setByName($channelname);
        $id+=0;
        if(is_int($id) && $id){
            $mux=$this->getData("mux");
            $tvarr=$this->byMux($mux,$favs,$withencrypted,"tv");
            $radioarr=$this->byMux($mux,$favs,$withencrypted,"radio");
            if(!is_array($tvarr)){
                $tvarr=array();
            }
            if(!is_array($radioarr)){
                $radioarr=array();
            }
            if(is_array($tvarr) && is_array($radioarr)){
                return array("tv"=>$tvarr,"radio"=>$radioarr);
            }else{
                $this->logg("Channel: byName: byMux didn't return arrays: tv: $tvarr, radio: $radioarr",LOG_DEBUG);
            }
        }else{
            $this->logg("Channel: byName: failed to set id by name: $id",LOG_DEBUG);
        }
        return false;
    }
    public function setByName($channelname="")
    {
        if(is_string($channelname) && $channelname){
            $arr=$this->mx->getSingleRow($this->table,"name",$channelname);
            if(is_array($arr)){
                $this->__construct($arr["id"],$this->log);
            }else{
                $this->logg("Channel: setByName: getSingleRow returned non-array: $arr",LOG_DEBUG);
            }
        }else{
            $this->logg("Channel: setByName: invalid channel name $channelname",LOG_DEBUG);
        }
        return $this->data_id;
    }
    public function nonRTChannels()
    {
        $ext="and rtid=0";
        $arec=$this->mx->getAllResults("*","channel","getdata","y",$ext,MYSQL_ASSOC);
        if(is_array($arec) && count($arec)){
            return $arec;
        }
        return false;
    }
    public function rtChannels()
    {
        $ext="and rtid!=0";
        $arec=$this->mx->getAllResults("*","channel","getdata","y",$ext,MYSQL_ASSOC);
        if(is_array($arec) && count($arec)){
            return $arec;
        }
        return false;
    }
    public function toggleFavourite()
    {
        if($this->data_id){
            $fav=$this->getData("favourite")=="y"?"n":"y";
            $this->setData("favourite",$fav);
            if($this->canlog){
                $this->log->message("favourites set to $fav for channel " . $this->getName());
            }
        }
    }
    public function toggleGetData()
    {
        if($this->data_id){
            $gd=$this->getData("getdata")=="y"?"n":"y";
            $this->setData("getdata",$gd);
            if($this->canlog){
                $this->log->message("Get data set to $gd for channel " . $this->getName());
            }
        }
    }
    public function getName()
    {
        return $this->getData("name");
    }
    public function nowNext()
    {
        if($this->data_id){
            $now=time();
            $ext="and stop>$now order by start limit 0,2";
            if(false!==($rows=$this->mx->getAllResults("*","program","channel",$this->data_id,$ext,MYSQL_ASSOC))){
                return $rows;
            }
        }else{
            if($this->canlog){
                $this->log->message("nowNext: Channel id not set",LOG_WARNING);
            }
        }
        return false;
    }
    public function progNow()
    {
        if($this->data_id){
            $now=time();
            $ext="and start<=$now and stop>$now";
            if(false!==($row=$this->mx->getAllResults("*","program","channel",$this->data_id,$ext,MYSQL_ASSOC))){
                return $row[0];
            }else{
                if($this->canlog){
                    $this->log->message("progNow: no data returned",LOG_DEBUG);
                }
            }
        }else{
            if($this->canlog){
                $this->log->message("progNow: channel id not set",LOG_WARNING);
            }
        }
        return false;
    }
    public function progNext()
    {
        if(false!==($row=$this->progNow())){
            $ext="and start>=" . $row["stop"] . " order by start limit 0,1";
            if(false!==($row=$this->mx->getAllResults("*","program","channel",$this->data_id,$ext,MYSQL_ASSOC))){
                return $row[0];
            }else{
                if($this->canlog){
                    $this->log->message("progNext: no data returned",LOG_DEBUG);
                }
            }
        }else{
            if($this->data_id){
                $now=time();
                $ext="and start>$now order by start limit 0,1";
                if(false!==($row=$this->mx->getAllResults("*","program","channel",$this->data_id,$ext,MYSQL_ASSOC))){
                    return $row[0];
                }else{
                    if($this->canlog){
                        $this->log->message("progNext: no data returned",LOG_DEBUG);
                    }
                }
            }else{
                if($this->canlog){
                    $this->log->message("progNext: channel id not set",LOG_WARNING);
                }
            }
        }
        return false;
    }
    public function checkPids()
    {
        if($this->data_id){
            $type=$this->getData("type");
            $aid=intval($this->getData("aid"));
            $vid=intval($this->getData("vid"));
            switch($type){
            case "radio":
                if($aid>0){
                    return true;
                }
                break;
            case "tv":
                if($aid>0 && $vid>0){
                    return true;
                }
                break;
            }
        }
        return false;
    }
    public function muxList()
    {
        return $this->mx->getAllResults("distinct mux","channel",1,0,"order by mux");
    }
}
?>
