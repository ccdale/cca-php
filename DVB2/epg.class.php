<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * epg.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Sunday 15 June 2014, 09:52:57
 * Last Modified: Sunday 15 June 2014, 10:46:29
 * Revision: $Id$
 * Version: 0.00
 */

require_once "DVB2/dvbctrl.class.php";

class EPG extends DVBCtrl
{
    private $epgcapturing=false;
    private $mx=false;
    private $numevents=0;
    private $xml=false;
    private $warningcn=0;

    public function __construct($logg=false,$host="",$user="",$pass="",$adaptor=0,$dvb=false,$mx=false,$truncate=true)/*{{{*/
    {
        parent::__construct($logg,$host,$user,$pass,$adaptor,$dvb);
        if(false===$mx){
            $this->mx=new Mysql($logg,"localhost","tvapp","tvapp","tv");
        }else{
            $this->mx=$mx;
        }
        if($truncate){
            $this->mx->query("truncate epg");
        }
        $this->xml=new XMLReader();
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        $this->xml=null;
        parent::__destruct();
    }/*}}}*/
    private function rcvEpgEvent()/*{{{*/
    {
        $ret=false;
        $msg="";
        $eventstarted=false;
        $incompleteevent=true;
        if($this->fp){
            while($incompleteevent){
                if($eventstarted){
                    $waitfor="</event>";
                }else{
                    $waitfor="<event ";
                }
                $tmp=fgets($this->fp);
                if(false!==($pos=strpos($tmp,$waitfor))){
                    if($eventstarted){
                        $msg.=$tmp;
                        $eventstarted=false;
                        $incompleteevent=false;
                    }else{
                        $msg=$tmp;
                        $eventstarted=true;
                    }
                }else{
                    if($eventstarted){
                        $msg.=$tmp;
                    }
                }
            }
            $ret=$msg;
        }
        return $ret;
    }/*}}}*/
    private function processTime($str)/*{{{*/
    {
        $tmp=explode(" ",$str);
        $date=trim($tmp[0]);
        $time=trim($tmp[1]);
        $tmp=explode("-",$date);
        $year=intval($tmp[0]);
        $month=intval($tmp[1]);
        $day=intval($tmp[2]);
        $tmp=explode(":",$time);
        $hour=intval($tmp[0]);
        $minute=intval($tmp[1]);
        $second=0;
        $ts=mktime($hour,$minute,$second,$month,$day,$year);
        $lastweek=time() - (7 * 86400);
        if($lastweek<$ts){
            return $ts;
        }else{
            return false;
        }
    }/*}}}*/
    private function processNew()/*{{{*/
    {
        $ret=false;
        $cn=$this->xml->attributeCount;
        if($cn==3){
            if($this->xml->getAttributeNo(2)=="no"){
                if(false===($start=$this->processTime($this->xml->getAttributeNo(0)))){
                    $this->warning("garbled start time for " . $this->xml->getAttributeNo(0));
                }
                if(false===($end=$this->processTime($this->xml->getAttributeNo(1)))){
                    $this->warning("garbled end time for " . $this->xml->getAttributeNo(1));
                }
                if(false!==$start && false!==$end){
                    $ret=array("start"=>$start,"end"=>$end);
                }
            }else{
                $this->debug("encrypted event, ignoring");
            }
        }else{
            $this->warning("incorrect number of attributes for <new> tag, ignoring event");
        }
        return $ret;
    }/*}}}*/
    private function processDetail()/*{{{*/
    {
        $ret=false;
        try{
            $tag=$this->xml->getAttributeNo(1);
            $this->xml->read();
            $value=$this->xml->value;
            $ret=array($tag=>$value);
        }catch(Exception $e){
            $this->warning("Failed to process detail");
            $this->warning($e->getMessage());
        }
        return $ret;
    }/*}}}*/
    private function processXml()/* {{{ */
    {
        $ret=false;
        try{
            $this->xml->read();
            $name=$this->xml->name;
            if("event"==$name){
                $cn=$this->xml->attributeCount;
                if($cn==4){
                    for($x=0;$x<3;$x++){
                        if($x>0){
                            $netid.="." . str_replace("0x","",$this->xml->getAttributeNo($x));
                        }else{
                            $netid=str_replace("0x","",$this->xml->getAttributeNo($x));
                        }
                    }
                    $eventid=str_replace("0x","",$this->xml->getAttributeNo(3));
                    // the event tag has an empty text block which we need to skip past
                    $this->xml->read();
                    $this->xml->read();
                    $tname=$this->xml->name;
                    switch($tname){
                    case "new":
                        if(false!==($arr=$this->processNew())){
                            $ret=array("netid"=>$netid,"eventid"=>$eventid);
                            foreach($arr as $key=>$val){
                                $ret[$key]=$val;
                            }
                        }else{
                            $this->debug("failed to process tag '<new>', ignoring event");
                        }
                        break;
                    case "detail":
                        if(false!==($arr=$this->processDetail())){
                            $ret=array("netid"=>$netid,"eventid"=>$eventid);
                            foreach($arr as $key=>$val){
                                $ret[$key]=$val;
                            }
                        }else{
                            $this->debug("failed to process tag '<detail>', ignoring event");
                        }
                        break;
                    }
                }else{
                    $this->debug("event has $cn attributes, ignoring event");
                }
            }else{
                $this->warning("not an event: this is called $name, ignoring event");
            }
        }catch(Exception $e){
            $this->warning("Cannot read xml");
            $this->warning($e->getMessage());
        }
        return $ret;
    }/* }}} */
    private function sqlSubStr($event)/*{{{*/
    {
        $usql="";
        foreach($event as $key=>$val){
            switch($key){
            case "start":
                $key="starttime";
                break;
            case "end":
                $key="endtime";
                break;
            case "netid":
                $key="networkid";
                break;
            default:
                break;
            }
            if(strlen($usql)){
                $usql.=",$key='" . $this->mx->escape($val) . "'";
            }else{
                $usql="$key='" . $this->mx->escape($val) . "'";
            }
        }
        return $usql;
    }/*}}}*/
    private function updateEPG($event)/*{{{*/
    {
        $sql="select * from epg where eventid='" . $event["eventid"] . "'";
        if(false!==($arr=$this->mx->arrayQuery($sql))){
            $usql=$this->sqlSubStr($event);
            $tsql="update epg set $usql where eventid='" . $event["eventid"] . "'";
            $this->mx->query($tsql);
        }else{
            $usql=$this->sqlSubStr($event);
            $tsql="insert into epg set $usql";
            $this->mx->query($tsql);
            $this->numevents++;
        }
    }/*}}}*/
    public function getNumEvents()/*{{{*/
    {
        return $this->numevents;
    }/*}}}*/
    public function epgCapStart()/*{{{*/
    {
        $srv="BBC TWO";
        $nottuned=true;
        if(false===($currrecs=$this->lsRecording(true))){
            if(false!==($srvs=$this->lsServices())){
                if(false!==($cn=$this->ValidArray($srvs))){
                    $srv=$srvs[0];
                }else{
                    $this->warning("No services returned from request for service list");
                }
            }else{
                $this->warning("failed to retrieve service list");
            }
        }else{
            if(false!==($cn=$this->ValidArray($currrecs))){
                if($cn){
                    $this->debug("already tuned, not selecting a channel");
                    $nottuned=false;
                }
            }
        }
        if($nottuned){
            $this->debug("Selecting $srv as EPG capture channel");
            $this->select($srv);
        }
        if(false!==($junk=$this->request("epgcapstart"))){
            $this->epgcapturing=true;
            $this->debug("Starting to capture EPG data");
            $this->sendData("epgdata");
            return true;
        }
        return false;
    }/*}}}*/
    public function epgCapStop()/*{{{*/
    {
        if(false!==($junk=$this->request("epgcapstop"))){
            $this->epgcapturing=false;
            $this->disconnect();
            $this->connect();
            return true;
        }
        return false;
    }/*}}}*/
    public function epgEvent()/*{{{*/
    {
        $ret=false;
        if(false!==($eventstr=$this->rcvEpgEvent())){
            try{
                $this->xml->xml($eventstr);
                $this->warningcn=0;
                if(false!==($event=$this->processXml())){
                    $this->updateEPG($event);
                    $ret=$this->numevents;
                }
            }catch(Exception $e){
                $this->warning("failed to interpret string as valid xml");
                $this->warning($e->getMessage());
                $this->warningcn++;
                if($this->warningcn>9){
                    $this->error("10 straight failed xml interpretations");
                    $this->epgCapStop();
                }
            }
        }
        return $ret;
    }/*}}}*/
}
?>
