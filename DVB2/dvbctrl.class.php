<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * dvbctrl.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Tuesday  3 June 2014, 06:13:35
 * Last Modified: Sunday 15 June 2014, 09:57:39
 * Revision: $Id$
 * Version: 0.00
 */

require_once "base.class.php";

class DVBCtrl extends Base
{
    private $host;
    private $port;
    private $user;
    private $pass;
    private $adaptor;
    private $connecttimeout=10;
    private $fp=false;
    private $connected=false;
    private $authenticated=false;
    private $DVB;
    private $dvbismine=false;
    private $canconnect=false;
    private $rcache=false;
    private $rcachetime=0;
    private $favsonly=false;

    public function __construct($logg=false,$host="",$user="",$pass="",$adaptor=0,$dvb=false)/*{{{*/
    {
        parent::__construct($logg);
        $this->host=$host;
        $this->user=$user;
        $this->pass=$pass;
        $this->adaptor=$adaptor;
        $this->port=54197 + $this->adaptor;
        $this->debug("host:" . $this->host . ", user: " . $this->user . ", pass: " . $this->pass . ", port: " . $this->port);
        $this->DVB=$dvb;
        if(false===$this->DVB){
            $this->DVB=new DvbStreamer($logg,$this->adaptor,$this->user,$this->pass); 
            $this->dvbismine=true;
        }
        if($this->DVB && is_object($this->DVB) && get_class($this->DVB)==="DvbStreamer"){
            if($this->DVB->isRunning()){
                $this->canconnect=true;
            }
        }
        if($this->canconnect){
            $this->connect();
            if($this->connected && $this->authenticated){
                $this->debug("Connected and authenticated ok");
            }
        }else{
            $this->error("Cannot find or start a DVBStreamer object");
        }
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        $this->debug("disconnecting");
        $this->disconnect();
        if($this->dvbismine){
            $this->debug("Stopping my copy of dvbstreamer");
            $this->DVB=null;
        }
        parent::__destruct();
    }/*}}}*/
    private function disconnect()/*{{{*/
    {
        if($this->connected){
            $this->sendData("logout");
            $this->connected=false;
        }
        if($this->fp){
            fclose($this->fp);
            $this->fp=false;
        }
    }/*}}}*/
    private function connect()/*{{{*/
    {
        $ret=false;
        try{
            $this->fp=fsockopen($this->host,$this->port,$errno,$errmsg,$this->connecttimeout);
        }catch(Exception $e){
            $this->fp=false;
            $this->error("Failed to connect to dvbstreamer adapter " . $this->adapter);
            $this->error("fsockopen said: $errno: $errmsg");
        }
        if($this->fp){
            // $res=$this->rcvData();
            if(false!==($res=$this->rcvData()) && false!==($cn=$this->ValidArray($res))){
                $this->connected=true;
                $this->debug("connected ok");
                if(false!==($junk=$this->authenticate())){
                    $this->authenticated=true;
                    $this->debug("authenticated ok");
                    $ret=true;
                }
            }
        }
        return $ret;
    }/*}}}*/
    private function authenticate()/*{{{*/
    {
        if(false!==($junk=$this->request("auth",array($this->user,$this->pass),false))){
            return true;
        }
        $this->error("failed to authenticate");
        return false;
    }/*}}}*/
    private function rcvData()/*{{{*/
    {
        $ident="DVBStreamer/";
        $ret=false;
        $rcode="";
        $rstatus="";
        $msg=array();
        if($this->fp){
            // $this->debug("starting receive data loop");
            while(!feof($this->fp)){
                $tmp=fgets($this->fp);
                // print $tmp;
                if(substr($tmp,0,strlen($ident))==$ident){
                    $ta=explode("/",$tmp);
                    $ra=explode(" ",trim($ta[2]));
                    $rcode=$ra[0];
                    $rstatus=$ra[1];
                    break;
                }else{
                    $msg[]=$tmp;
                }
            }
            // $this->debug("loop completed");
        }else{
            $this->error("rcvData: fp is not a pointer");
        }
        if(is_string($rcode) && strlen($rcode)){
            $ret=array("resultcode"=>intval($rcode),"status"=>$rstatus,"data"=>$msg);
            // $this->debug("rcode is a string: $rcode");
            // $this->debug("status: $rstatus");
            // $tmp=print_r($msg,true);
            // $this->debug("data: $tmp");
        }elseif(is_int($rcode)){
            $ret=array("resultcode"=>$rcode,"status"=>$rstatus,"data"=>$msg);
            // $this->debug("rcode is an int: $rcode");
        // }else{
            // $this->debug("status: $rstatus");
            // $this->debug("resultcode: $rcode");
            // $tmp=print_r($msg,true);
            // $this->debug("data: $tmp");
        }
        return $ret;
    }/*}}}*/
    private function sendData($data)/*{{{*/
    {
        if($this->connected){
            // print "Sending: $data\n";
            $len=strlen($data);
            if($len){
                if(substr($data,$len-1)!=="\n"){
                    fwrite($this->fp,$data . "\n");
                }else{
                    fwrite($this->fp,$data);
                }
            }
        }
    }/*}}}*/
    private function checkReply($reply)/*{{{*/
    {
        $ret=false;
        $tmp=print_r($reply,true);
        if(false!==$reply){
            if(false!==($cn=$this->ValidArray($reply))){
                if(isset($reply["resultcode"])){
                    if(isset($reply["status"])){
                        if($reply["resultcode"]==0){
                            // if($reply["status"]=="OK"){
                                $ret=true;
                            // }else{
                                // $this->warning("checkReply: bad status: $tmp");
                            // }
                        }else{
                            $this->warning("checkReply: bad resultcode: $tmp");
                        }
                    }else{
                        $this->warning("checkReply: status not set: $tmp");
                    }
                }else{
                    $this->warning("checkReply: resultcode not set: $tmp");
                }
            }else{
                $this->warning("checkReply: not a valid array: $tmp");
            }
        }else{
            $this->warning("checkReply: reply is false: $tmp");
        }
        // if(false!==$reply && is_array($reply) && isset($reply["resultcode"])){
            // if($reply["resultcode"]==0){
                // $ret=true;
            // }
        // }
        return $ret;
    }/*}}}*/
    private function favsOnly($filternumber) /*{{{*/
    {
        if($filternumber==0){
            $filter="<Primary>";
        }else{
            $filter="dvb" . $filternumber;
        }
        if($this->favsonly){
            $on="on";
        }else{
            $on="off";
        }
        $this->request("setsfavsonly","$filter $on");
    } /*}}}*/
    private function filterExists($filter="") /*{{{*/
    {
        $ret=false;
        if(is_string($filter) && $filter){
            if(false!==($srec=$this->lsRecording())){
                if(0!==($cn=count($srec))){
                    foreach($srec as $key=>$cr){
                        if($key==$filter){
                            $ret=true;
                            break;
                        }
                    }
                }
            }
        }
        return $ret;
    } /*}}}*/
    private function isFilterFree($filter) /*{{{*/
    {
        $ret=false;
        if(is_string($filter) && $filter){
            if(false!==($srec=$this->lsRecording())){
                if(0!==($cn=count($srec))){
                    foreach($srec as $key=>$cr){
                        if($key==$filter){
                            if($cr["type"]=="null"){
                                $ret=true;
                            }
                        }
                    }
                }
            }
        }
        return $ret;
    } /*}}}*/
    private function findFreeFilter() /*{{{*/
    {
        $ret=-1;
        if(false!==($srec=$this->lsRecording())){
            $counter=0;
            if(0!==($cn=count($srec))){
                foreach($srec as $key=>$cr){
                    if($cr["type"]=="null"){
                        break;
                    }else{
                        $counter++;
                    }
                }
            }
            $ret=$counter;
        }
        return $ret;
    } /*}}}*/
    private function removeFilter($filter) /*{{{*/
    {
        if($filter!="<Primary>"){
            if(false!==($reply=$this->request("rmsf",$filter))){
                return true;
            }
        }else{
            return true;
        }
        return false;
    } /*}}}*/
    private function waitForSignalLock() /*{{{*/
    {
        $waiting=19;
        while($waiting>0){
            if(false!=($arr=$this->request("festatus"))){
                // $this->debug("waitForSignalLock: resultcode: {$arr['resultcode']}");
                // $this->debug("waitForSignalLock: status: {$arr['status']}");
                if(isset($arr["data"][0])){
                    $this->debug("waitForSignalLock: " . trim($arr['data'][0]) . " waiting: $waiting");
                    if("Sync  ]"==($tmp=substr(trim($arr["data"][0]),-7))){
                        // print "wait time for lock: " . 19-$waiting . "\n";
                        $waiting=0;
                    }
                }else{
                    $this->debug("waitForSignalLock: request returned array");
                    $this->debug(print_r($arr,true));
                }
            }else{
                $this->debug("false returned from request for festatus: $waiting");
            }
            $waiting--;
            sleep(1);
        }
    } /*}}}*/
    private function cleanupServiceFilters($force=false) /*{{{*/
    {
        $ret=false;
        if(false!==($sfarr=$this->lsRecording(false))){
            $counter=0;
            if(0!==($cn=count($sfarr))){
                foreach($sfarr as $filter=>$service){
                    if($force){
                        $this->stopFilter($filter);
                    }
                    if($counter!==0){
                        if($this->isFilterFree($filter)){
                            if(false!==$this->removeFilter($filter)){
                                if(is_bool($ret)){
                                    $ret=array();
                                }
                                $ret[]=$filter;
                            }
                        }
                    }else{
                        $ret=true;
                    }
                    $counter++;
                }
            }
        }
        return $ret;
    } /*}}}*/
    private function getsf($filtername)/*{{{*/
    {
        $ret=false;
        if(false!==($cn=$this->ValidString($filtername))){
            if(false!==($reply=$this->request("getsf",$filtername))){
                // we are only expecting one line of data
                if(false!==($cn=$this->ValidString($reply["data"][0]))){
                    $tmp=explode(":",$reply["data"][0]);
                    $service=trim($tmp[1]);
                    if(false!==($cn=$this->ValidString($service))){
                        $ret=str_replace('"','',$service);
                    }else{
                        $this->warning("getsf: bad data for service: $filtername: " . print_r($reply,true));
                    }
                }else{
                    $this->warning("getsf: no data returned from dvbstreamer for filter: $filtername");
                }
            }else{
                $this->warning("getsf: no reply from dvbstreamer for filter: $filtername");
            }
        }else{
            $this->warning("getsf: not a valid string: $filtername");
        }
        return $ret;
    }/*}}}*/
    private function getsfmrl($filtername)/*{{{*/
    {
        $ret=false;
        if(false!==($cn=$this->ValidString($filtername))){
            if(false!=($reply=$this->request("getsfmrl",$filtername))){
                // we are only expecting one line of data
                if(false!==($cn=$this->ValidString($reply["data"][0]))){
                    $mrl=trim($reply["data"][0]);
                    $ret=$mrl;
                }else{
                    $this->warning("getsfmrl: no data returned from dvbstreamer for filter: $filtername");
                }
            }else{
                $this->warning("getsfmrl: no reply from dvbstreamer for filter: $filtername");
            }
        }else{
            $this->warning("getsfmrl: not a valid string: $filtername");
        }
        return $ret;
    }/*}}}*/
    private function decodeMrl($mrl)/*{{{*/
    {
        $ret=false;
        if(false!==($cn=$this->ValidString($mrl))){
            if($cn>4){
                $tmp=explode(":",$mrl);
                $cn=count($tmp);
                if(2==$cn){
                    $type=trim($tmp[0]);
                    if($type=="null"){
                        $fn="";
                    }else{
                        $fn=str_replace("//","",$tmp[1]);
                    }
                    $ret=array("type"=>$type,"filename"=>$fn);
                }else{
                    $this->warning("decodeMrl: $cn parts found in mrl string, was expecting 2: $mrl");
                }
            }else{
                $this->warning("decodeMrl: string is not long enough to be an mrl: $mrl");
            }
        }else{
            $this->warning("decodeMrl: not a valid string: $mrl");
        }
        return $ret;
    }/*}}}*/
    public function request($cmd="",$argarr="",$auth=true)/*{{{*/
    {
        $ret=false;
        $preauth=true;
        if(false!==($cn=$this->ValidString($cmd))){
            $tmp=$cmd;
            if($this->connected){
                if($auth){
                    $preauth=$this->authenticated;
                }
                if($preauth){
                    if(is_string($argarr) && $argarr){
                        $tmp.=" " . $argarr;
                    }elseif(is_array($argarr) && (0!==($an=count($argarr)))){
                        foreach($argarr as $arg){
                            $tmp.=" " . $arg;
                        }
                    }
                }
                // $this->debug("request: $tmp");
                $this->sendData($tmp);
                $reply=$this->rcvData();
                if(false!==($ret=$this->checkReply($reply))){
                    $ret=$reply;
                    // $this->debug("request: $tmp: OK");
                }else{
                    $this->debug("request: $tmp: " . $reply["resultcode"]);
                    $tmp=print_r($reply,true);
                    $this->debug($tmp);
                }
            }else{
                $this->warning("request: not connected");
            }
        }else{
            $this->error("request: cmd is not a valid string: $cmd: len: $cn");
        }
        return $ret;
    }/*}}}*/
    public function lsServices()/*{{{*/
    {
        $ret=false;
        if(false!==($reply=$this->request("lslcn"))){
            $info=array();
            foreach($reply["data"] as $dataline){
                $la=explode(":",$dataline);
                $info[str_replace('"','',trim($la[0]))]=str_replace('"','',trim($la[1]));
            }
            $ret=$info;
        }
        return $ret;
    }/*}}}*/
    public function serviceInfo($service)  /*{{{*/
    {
        $ret=false;
        if(is_string($service) && $service){
            if(false!=($reply=$this->request("serviceinfo","'$service'"))){
                $info=array();
                foreach($reply["data"] as $dataline){
                    $la=explode(":",$dataline);
                    $info[trim($la[0])]=trim($la[1]);
                }
                $ret=$info;
            }
        }
        return $ret;
    } /*}}}*/
    public function select($service,$filternumber=0,$nowait=false) /*{{{*/
    {
        if($filternumber==0){
            $this->request("select","'$service'");
        }else{
            $filter="dvb" . $filternumber;
            if(!$this->filterExists($filter)){
                $this->request("addsf",array($filter,"null://"));
                /* update recording cache */
                $this->lsRecording(false);
            }
            $this->request("setsf",array($filter,$service));
        }
        if(!$nowait){
            // wait for a bit for dvbstreamer to retune
            $this->waitForSignalLock();
            // wait a bit longer for dvbstreamer to update it's tables
            // (the output of lsdvb takes a bit of time to show
            // it has changed channel)
            sleep(2);
        }
    } /*}}}*/
    public function setmrl($file,$filternumber=0) /*{{{*/
    {
        if($filternumber==0){
            $this->request("setmrl","file://" . $file);
        }else{
            $filter="dvb" . $filternumber;
            $this->request("setsfmrl",array($filter,"file://" . $file));
        }
    } /*}}}*/
    public function setUdpMrl($port,$filternumber=0) /*{{{*/
    {
        if($filternumber==0){
            $this->request("setmrl",STREAMADDRESS . $port);
        }else{
            $filter="dvb" . $filternumber;
            $this->request("setsfmrl",array($filter,STREAMADDRESS . $port));
        }
    } /*}}}*/
    public function xlsRecording($usecache=true) /*{{{*/
    {
        $ret=false;
        if($this->rcache!==false && $usecache){
            return $this->rcache;
        }
        if(false!==($reply=$this->request("lssfs"))){
            $ln=count($reply["data"]);
            $tmp=print_r($reply,true);
            $this->debug("lsrecording");
            $this->debug($tmp);
            if($ln){
                $ret=array();
                foreach($reply["data"] as $dataline){
                    $la=explode(":",$dataline,3);
                    $filter=trim($la[0]);
                    $type=trim($la[1]);
                    $tmp=explode("(",trim($la[2]));
                    $filename=trim($tmp[0]);
                    if(strlen($filename)>2){
                        $filename=substr($filename,2);
                    }else{
                        $filename="";
                    }
                    $channel=trim($tmp[1]);
                    $channel=substr($channel,0,strlen($channel)-1);
                    $ret[$filter]=array("type"=>$type,"filename"=>$filename,"channel"=>$channel);
                    if(false!==($info=$this->serviceinfo($ret[$filter]["channel"]))){
                        $ret[$filter]["mux"]=$info["Multiplex UID"];
                    }
                }
                $this->rcache=$ret;
                $this->rcachetime=time();
            }
        }
        return $ret;
    } /*}}}*/
    public function lsRecording($usecache=true)/*{{{*/
    {
        $ret=false;
        if($this->rcache!==false && $usecache){
            return $this->rcache;
        }
        if(false!==($reply=$this->request("lssfs"))){
            if(false!==($cn=$this->ValidArray($reply["data"]))){
                if($cn){
                    $op=array();
                    for($filter=0;$filter<$cn;$filter++){
                        $filtername=$reply["data"][$filter];
                        if(false!==($service=$this->getsf($filtername))){
                            if(false!==($mrl=$this->getsfmrl($filtername))){
                                if(false!==($top=$this->decodeMrl($mrl))){
                                    $op[$filter]=array("type"=>$top["type"],"filename"=>$top["filename"],"channel"=>$service);
                                    if(false!==($info=$this->serviceInfo($service))){
                                        $op[$filter]["mux"]=$info["Multiplex UID"];
                                    }else{
                                        $this->warning("lsRecording: failed to retrieve service info data for service: $service");
                                    }
                                }else{
                                    $this->warning("lsRecording: failed to decode mrl: $mrl");
                                }
                            }else{
                                $this->warning("lsRecording: no mrl returned for filter: $filtername");
                            }
                        }else{
                            $this->warning("lsRecording: no service returned for filter: $filtername");
                        }
                    }
                    $ret=$op;
                    $this->rcache=$op;
                    $this->rcachetime=time();
                }else{
                    $this->warning("lsRecording: data returned but no lines from dvbstreamer");
                }
            }else{
                $this->warning("lsRecording: no data from dvbstreamer");
            }
        }else{
            $this->warning("lsRecording: no reply from dvbstreamer");
        }
        return $ret;
    }/*}}}*/
    public function safeToRecordService($service) /*{{{*/
    {
        $ret=false;
        if(is_string($service) && $service){
            if(false!==($sinfo=$this->serviceInfo($service))){
                $oldmux=$newmux=$sinfo["Multiplex UID"];
                if(false!==($currentrecording=$this->lsRecording())){
                    if(0!==($cn=count($currentrecording))){
                        foreach($currentrecording as $cr){
                            if($cr["type"]=="file" || $cr["type"]=="udp"){
                                $oldmux=$cr["mux"];
                                break;
                            }
                        }
                    }
                }
                if($oldmux==$newmux){
                    $ret=true;
                }
            }
        }
        return $ret;
    } /*}}}*/
    public function filterNumberForFile($file="") /*{{{*/
    {
        if(is_string($file) && $file){
            if(false!==($crec=$this->lsRecording(false))){
                $counter=0;
                if(0!==($cn=count($crec))){
                    foreach($crec as $key=>$cr){
                        if($cr["filename"]==$file){
                            return $counter;
                        }
                        $counter++;
                    }
                }
            }
        }
        return -1;
    } /*}}}*/
    public function serviceForFile($file="") /*{{{*/
    {
        if(is_string($file) && $file){
            if(false!==($crec=$this->lsRecording())){
                if(0!==($cn=count($crec))){
                    foreach($crec as $key=>$cr){
                        if($cr["filename"]==$file){
                            return $cr["channel"];
                        }
                    }
                }
            }
        }
        return false;
    } /*}}}*/
    public function recordNewService($service,$file) /*{{{*/
    {
        $ret=false;
        $freefilternumber=0;
        if((false!==$this->safeToRecordService($service)) && is_string($file) && $file){
            if(-1!==($freefilternumber=$this->findFreeFilter())){
                $this->select($service,$freefilternumber);
                $this->favsOnly($freefilternumber);
                // sleep(5);
                $this->setmrl($file,$freefilternumber);
                // sleep(5);
                $junk=$this->lsRecording(false);
                if(false!==($srv=$this->serviceForFile($file))){
                    if($srv==$service){
                        $ret=true;
                    // }else{
                        // print "service $srv does not equal $service\n";
                    }
                }else{
                    $this->warning("cant find service for file: $file");
                }
            }
        }else{
            $this->warning("not safe for $service with file: $file");
        }
        // print "pausing for 20 seconds\n";
        // sleep(20);
        return $ret;
    } /*}}}*/
    public function stopRecording($file="") /*{{{*/
    {
        // $this->request("setmrl","null://");
        // return true;

        $ret=false;
        if(-1!==($filternumber=$this->filterNumberForFile($file))){
            if($filternumber==0){
                $this->request("setmrl","null://");
            }else{
                $this->request("setsfmrl",array("dvb" . $filternumber,"null://"));
            }
            if(false!==($crec=$this->lsRecording(false))){
                if(0!==($cn=count($crec))){
                    $found=false;
                    foreach($crec as $key=>$cr){
                        if($cr["filename"]==$file){
                            $found=true;
                        }
                    }
                    if(!$found){
                        /*
                        if($filternumber){
                            $this->request("rmsf","dvb" . $filternumber);
                        }
                         */
                        $ret=true;
                    }else{
                        $this->warning("Failed to stop recording into $file");
                    }
                }
            }
        }
        return $ret;
    } /*}}}*/
    public function stopByFilterNumber($filternumber=false) /*{{{*/
    {
        $ret=false;
        if($filternumber!==false){
            if($filternumber==0){
                $this->request("setmrl","null://");
                $key="<Primary>";
            }else{
                $this->request("setsfmrl",array("dvb" . $filternumber,"null://"));
                $key="dvb$filternumber";
            }
            if(false!==($crec=$this->lsRecording(false))){
                if(0!==($cn=count($crec))){
                    $found=false;
                    foreach($crec as $kk=>$cr){
                        if($kk==$key){
                            if($cr["type"]=="null"){
                                $found=false;
                            }else{
                                $found=true;
                            }
                        }
                    }
                    if(!$found){
                        /*
                        if($filternumber){
                            $this->request("rmsf","dvb" . $filternumber);
                        }
                         */
                        $ret=true;
                    }
                }
            }
        }
        return $ret;
    } /*}}}*/
    public function split($file="",$newfilename="") /*{{{*/
    {
        if(false!==($service=$this->serviceForFile($file))){
            if(is_string($newfilename) && $newfilename && !file_exists($newfilename)){
                if(false!==$this->recordNewService($service,$newfilename)){
                    // wait for file to grow to at least 5MB
                    $fs=0;
                    $iterations=0;
                    while($fs<10000){
                        sleep(2);
                        $fs=filesize($newfilename);
                        $iterations++;
                        if($iterations>4){
                            // we have waited 10 seconds for the file to grow
                            // bail out
                            $this->stopRecording($newfilename);
                            return DVBSPLIT_NEW_NOT_GROWING;
                        }
                    }
                    $this->stopRecording($file);
                    return DVBSPLIT_OK;
                }else{
                    return DVBSPLIT_START_NEW_SERVICE_FAILED;
                }
            }
        }
        return DVBSPLIT_ERROR_GETTING_SERVICE;
    } /*}}}*/
    public function streamNewService($service,$port) /*{{{*/
    {
        if(is_string($service) && $service){
            if(false!==$this->safeToRecordService($service)){
                if(-1!==($freefilternumber=$this->findFreeFilter())){
                    // $this->select($service,$freefilternumber,true);
                    $this->select($service,$freefilternumber);
                    $this->setUdpMrl($port,$freefilternumber);
                    // sleep(5); // wait a bit for dvbstreamer to retune
                    $junk=$this->lsRecording(false);
                    if(false!==($srv=$this->serviceForFile("localhost:$port"))){
                        $this->debug("dvbctrl: streamNewService: service for file localhost:$port - $srv");
                        if($srv==$service){
                            return $freefilternumber;
                        }else{
                            $this->debug("dvbctrl: streamNewService: $srv does not equal $service.");
                        }
                    }else{
                        $this->debug("dvbctrl: streamNewService: failed to get service for file localhost:$port");
                    }
                }else{
                    $this->debug("dvbctrl: streamNewService: no free filter.");
                }
            }else{
                $this->debug("dvbctrl: streamNewService: not safe to record service");
            }
        }else{
            $this->debug("dvbctrl: streamNewService: $service is not a string or is an empty string.");
        }
        return false;
    } /*}}}*/
    public function stopFilter($filter) /*{{{*/
    {
        if($filter=="<Primary>"){
            $req="setmrl";
            $opt="null://";
        }else{
            $req="setsfmrl";
            $opt=array($filter,"null://");
        }
        if(false!==($reply=$this->request($req,$opt))){
            return true;
        }
        return false;
    } /*}}}*/
    public function setFavsonlyOn() /*{{{*/
    {
        $this->favsonly=true;
    } /*}}}*/
    public function setFavsonlyOff() /*{{{*/
    {
        $this->favsonly=false;
    } /*}}}*/
}
?>
