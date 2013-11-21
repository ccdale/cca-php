<?php

/**
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * dvbctrl.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Friday 25 September 2009, 11:37:43
 * Last Modified: Friday 26 August 2011, 02:28:39
 *
 * Version: $Id: dvbctrl.class.php 710 2011-09-17 20:55:53Z chris $
 */

define("DVBSPLIT_OK",0);
define("DVBSPLIT_NEW_NOT_GROWING",1);
define("DVBSPLIT_START_NEW_SERVICE_FAILED",2);
define("DVBSPLIT_ERROR_GETTING_SERVICE",3);

/* DVBCtrl */
class DVBCtrl
{
    private $connecttimeout=10;
    private $fp;
    private $connected=false;
    private $authenticated=false;
    private $data=array();
    private $rcache=false;
    private $rcachetime=false;
    private $favsonly=false;


    public function __construct($host="",$user="",$pass="",$adaptor=0)/*{{{*/
    {
        if(!$host){
            if(defined("DVBHOST")){
                $host=DVBHOST;
            }
        }
        if(!$user){
            if(defined("DVBUSER")){
                $user=DVBUSER;
            }
        }
        if(!$pass){
            if(defined("DVBPASS")){
                $pass=DVBPASS;
            }
        }
        $this->setData("host",$host);
        $this->setData("user",$user);
        $this->setData("pass",$pass);
        $this->setData("port",54197+$adaptor);
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        $this->disconnect();
    }/*}}}*/
    public function getStatus()/*{{{*/
    {
        return array("connected"=>$this->connected,"authenticated"=>$this->authenticated);
    }/*}}}*/
    public function setData($data,$value)/*{{{*/
    {
        $ret=false;
        if(is_string($data) && strlen($data)){
            $this->data[$data]=$value;
            $ret=true;
        }
        return $ret;
    }/*}}}*/
    public function getData($data)/*{{{*/
    {
        $ret=false;
        if(is_string($data) && $data){
            if(isset($this->data[$data])){
                $ret=$this->data[$data];
            }else{
                $this->setData("errno",1);
                $this->setData("errmsg","Data key does not exist: $data");
            }
        }else{
            $this->setData("errno",1);
            $this->setData("errmsg","Data key does not exist: $data");
        }
        return $ret;
    }/*}}}*/
    public function connect()/*{{{*/
    {
        $ret=false;
        try{
            $this->fp=fsockopen($this->getData("host"),$this->getData("port"),$errno,$errmsg,$this->connecttimeout);
        }catch(Exception $e){
            $this->fp=false;
        }
        if(!$this->fp){
            /* an error occured in connecting */
            $this->setData("errno",$errno);
            $this->setData("errmsg",$errmsg);
        }else{
            $res=$this->rcvData();
            if(false!==$res){
                $this->connected=true;
                if($this->auth()){
                    $this->authenticated=true;
                    $ret=true;
                }
            }
        }
        return $ret;
    }/*}}}*/
    public function disconnect()/*{{{*/
    {
        if($this->connected){
            $this->sendData("logout");
            $this->connected=false;
        }
        if($this->fp){
            fclose($this->fp);
            $this->fp=0;
        }
    }/*}}}*/
    public function auth()/*{{{*/
    {
        if(false!==($this->request("auth",array($this->getData("user"),$this->getData("pass")),false))){
            return true;
        }
        return false;
        /*
        $ret=false;
        if($this->connected){
            $msg="auth ";
            $msg.=$this->getData("user") . " ";
            $msg.=$this->getData("pass");
            $this->sendData($msg);
            $reply=$this->rcvData();
            if($this->checkReply($reply)){
                $ret=true;
            }
        }
        return $ret;
         */
    }/*}}}*/
    public function serviceInfo($service)/*{{{*/
    {
        $ret=false;
        if(is_string($service) && $service){
            if(false!=($reply=$this->request("serviceinfo",$service))){
                $info=array();
                foreach($reply["data"] as $dataline){
                    $la=explode(":",$dataline);
                    $info[trim($la[0])]=trim($la[1]);
                }
                $ret=$info;
            }
        }
        return $ret;
    }/*}}}*/
    public function select($service,$filternumber=0,$nowait=false)/*{{{*/
    {
        if($filternumber==0){
            $this->request("select",$service);
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
    }/*}}}*/
    public function waitForSignalLock()/*{{{*/
    {
        $waiting=19;
        while($waiting>0){
            if(false!=($arr=$this->request("festatus"))){
                // logg("waitForSignalLock: resultcode: {$arr['resultcode']}",LOG_DEBUG);
                // logg("waitForSignalLock: status: {$arr['status']}",LOG_DEBUG);
                if(isset($arr["data"][0])){
                    logg("waitForSignalLock: " . trim($arr['data'][0]) . " waiting: $waiting",LOG_DEBUG);
                    if("Sync"==($tmp=substr(trim($arr["data"][0]),-4))){
                        // print "wait time for lock: " . 19-$waiting . "\n";
                        $waiting=0;
                    }
                }else{
                    logg("waitForSignalLock: request returned array",LOG_DEBUG);
                    logg(print_r($arr,true),LOG_DEBUG);
                }
            }else{
                logg("false returned from request for festatus: $waiting",LOG_DEBUG);
            }
            $waiting--;
            sleep(1);
        }
    }/*}}}*/
    public function setmrl($file,$filternumber=0)/*{{{*/
    {
        if($filternumber==0){
            $this->request("setmrl","file://" . $file);
        }else{
            $filter="dvb" . $filternumber;
            $this->request("setsfmrl",array($filter,"file://" . $file));
        }
    }/*}}}*/
    public function setUdpMrl($port,$filternumber=0)/*{{{*/
    {
        if($filternumber==0){
            $this->request("setmrl",STREAMADDRESS . $port);
        }else{
            $filter="dvb" . $filternumber;
            $this->request("setsfmrl",array($filter,STREAMADDRESS . $port));
        }
    }/*}}}*/
    private function rcvData()/*{{{*/
    {
        $ident="DVBStreamer/";
        $ret=false;
        $rcode="";
        $rstatus="";
        $msg=array();
        if($this->fp){
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
        }
        if(is_string($rcode) && strlen($rcode)){
            $ret=array("resultcode"=>intval($rcode),"status"=>$rstatus,"data"=>$msg);
        }elseif(is_int($rcode)){
            $ret=array("resultcode"=>$rcode,"status"=>$rstatus,"data"=>$msg);
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
        if(false!==$reply && is_array($reply) && isset($reply["resultcode"])){
            if($reply["resultcode"]==0){
                $ret=true;
            }
        }
        return $ret;
    }/*}}}*/
    public function request($cmd="",$argarr="",$auth=true)/*{{{*/
    {
        $ret=false;
        $preauth=true;
        if(is_string($cmd) && $cmd){
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
                $this->sendData($tmp);
                $reply=$this->rcvData();
                // logg("request: $tmp",LOG_DEBUG);
                // logg("request: resultcode: {$reply["resultcode"]}",LOG_DEBUG);
                // logg("request: status: {$reply["status"]}",LOG_DEBUG);
                if(false!==($ret=$this->checkReply($reply))){
                    $ret=$reply;
                    logg("request: $tmp: OK",LOG_DEBUG);
                }else{
                    logg("request: $tmp: " . $reply["resultcode"],LOG_DEBUG);
                }
            }
        }
        return $ret;
    }/*}}}*/
    public function lsRecording($usecache=true)/*{{{*/
    {
        $ret=false;
        if($this->rcache!==false && $usecache){
            return $this->rcache;
        }
        if(false!==($reply=$this->request("lssfs"))){
            $ln=count($reply["data"]);
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
    }/*}}}*/
    public function safeToRecordService($service)/*{{{*/
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
    }/*}}}*/
    private function filterExists($filter="")/*{{{*/
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
    }/*}}}*/
    private function isFilterFree($filter)/*{{{*/
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
    }/*}}}*/
    private function findFreeFilter()/*{{{*/
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
    }/*}}}*/
    public function filterNumberForFile($file="")/*{{{*/
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
    }/*}}}*/
    public function serviceForFile($file="")/*{{{*/
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
    }/*}}}*/
    public function recordNewService($service,$file)/*{{{*/
    {
        $ret=false;
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
                // }else{
                    // print "cant find service for file: $file\n";
                }
            }
        // }else{
            // print "not safe for $service with file: $file\n";
        }
        // print "pausing for 20 seconds\n";
        // sleep(20);
        return $ret;
    }/*}}}*/
    public function stopRecording($file="")/*{{{*/
    {
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
                    }
                }
            }
        }
        return $ret;
    }/*}}}*/
    public function stopByFilterNumber($filternumber=false)/*{{{*/
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
    }/*}}}*/
    public function split($file="",$newfilename="")/*{{{*/
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
    }/*}}}*/
    public function streamNewService($service,$port)/*{{{*/
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
                        logg("dvbctrl: streamNewService: service for file localhost:$port - $srv",LOG_DEBUG);
                        if($srv==$service){
                            return $freefilternumber;
                        }else{
                            logg("dvbctrl: streamNewService: $srv does not equal $service.",LOG_DEBUG);
                        }
                    }else{
                        logg("dvbctrl: streamNewService: failed to get service for file localhost:$port",LOG_DEBUG);
                    }
                }else{
                    logg("dvbctrl: streamNewService: no free filter.",LOG_DEBUG);
                }
            }else{
                logg("dvbctrl: streamNewService: not safe to record service",LOG_DEBUG);
            }
        }else{
            logg("dvbctrl: streamNewService: $service is not a string or is an empty string.",LOG_DEBUG);
        }
        return false;
    }/*}}}*/
    public function stopFilter($filter)/*{{{*/
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
    }/*}}}*/
    private function removeFilter($filter)/*{{{*/
    {
        if($filter!="<Primary>"){
            if(false!==($reply=$this->request("rmsf",$filter))){
                return true;
            }
        }else{
            return true;
        }
        return false;
    }/*}}}*/
    public function cleanupServiceFilters($force=false)/*{{{*/
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
                        // if($this->isFilterFree($filter)){
                            if(false!==$this->removeFilter($filter)){
                                if(is_bool($ret)){
                                    $ret=array();
                                }
                                $ret[]=$filter;
                            }
                        // }
                    }else{
                        $ret=true;
                    }
                    $counter++;
                }
            }
        }
        return $ret;
    }/*}}}*/
    public function setFavsonlyOn()/*{{{*/
    {
        $this->favsonly=true;
    }/*}}}*/
    public function setFavsonlyOff()/*{{{*/
    {
        $this->favsonly=false;
    }/*}}}*/
    private function favsOnly($filternumber)/*{{{*/
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
    }/*}}}*/
}
?>
