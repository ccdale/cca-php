<?php

/** {{{ heading block
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * DVB/dvbstreamer.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Wednesday 28 January 2009, 03:58:02
 * Version: 0.3
 * Last Modified: Tuesday 13 July 2010, 10:30:51
 *
 * Changlog:
 * 0.2->0.3
 * added attachment to an already running dvbstreamer daemon
 *
 * 0.1->0.2
 * added 10 seconds sleep after startup to allow dvbstreamer to 
 * settle and read it's config
 *
 * 0.1 initial version
 *
 * $Id: dvbstreamer.class.php 303 2010-07-13 10:33:08Z chris $
 */
// }}}

require_once "Shell/background.class.php";
require_once "Unix/processtable.class.php";
require_once "file.php";

class Dvbstreamer
{
    private $proc=false;
    private $dvbd="";
    private $dvbc="";
    private $user="tvc";
    private $pass="tvc";
    private $host="kaleb";
    private $pt=false;
    private $muxes=array();
    private $streams=array();
    public $alreadyrunning=false;
    public $alreadypid=0;
    private $home="";
    private $pidfile="";
    private $doparsemuxdata=false;

    function __construct($home="",$parsemuxdata=false)
    {
        /*
        $this->dvbd="/usr/bin/dvbstreamer -d " .
            "-u " . $this->user . " " .
            "-p " . $this->pass;
            // "-i " . $this->host . " " . 
        $this->dvbc="/usr/bin/dvbctrl " . 
            "-h " . $this->host . " " . 
            "-u " . $this->user . " " .
            "-p " . $this->pass . " ";
        // check if dvbstreamer is currently running
        $pt=new ProcessTable();
        $xpa=$pt->findProc("dvbstreamer -d -u tvc -p tvc");
        $pt=null;
        if(false!==$xpa){
            $this->alreadypid=$xpa[0]->getPid();
            $this->alreadyrunning=true;
            if(is_string($home) && strlen($home)){
                $this->home=$home;
                $this->pidfile=unixPath($this->home) . ".dvbstreamer/dvbstreamer-0.pid";
            }
        }
        $xpa=null;
         */
        $this->doparsemuxdata=$parsemuxdata;
        if(is_string($home) && strlen($home)){
            $this->home=$home;
            // $this->pidfile=unixPath($this->home) . ".dvbstreamer/dvbstreamer-0.pid";
        }
        $this->dvbc="/usr/bin/dvbctrl " . 
            "-h " . $this->host . " " . 
            "-u " . $this->user . " " .
            "-p " . $this->pass . " ";
        $this->alreadyrunning=true;
    }
    function __destruct()
    {
        $this->stop();
    }
    private function parseMuxData()
    {
        $md=$this->request("lsmuxes");
        if(is_array($md)){
            foreach($md as $mux){
                $mux=trim($mux);
                if(strlen($mux)){
                    $this->muxes[$mux]=array();
                    $this->parseServices($mux);
                    foreach($this->muxes[$mux] as $serv=>$val){
                        $this->parseServInfo($serv,$mux);
                    }
                }
            }
        }
    }
    private function parseServices($mux)
    {
        if(strlen($mux)){
            $sd=$this->request("lsservices " . $mux);
            if(is_array($sd)){
                foreach($sd as $serv){
                    $serv=trim($serv);
                    if(strlen($serv)){
                        $this->muxes[$mux][$serv]=array();
                    }
                }
            }
        }
    }
    private function parseServInfo($serv,$mux)
    {
        if(strlen($serv)){
            $si=$this->request("serviceinfo " . $serv);
            if(is_array($si)){
                foreach($si as $sv){
                    $tmp=explode(":",$sv);
                    for($x=0;$x<count($tmp);$x++){
                        $tmp[$x]=trim($tmp[$x]);
                    }
                    $this->muxes[$mux][$serv][$tmp[0]]=$tmp[1];
                }
            }
        }
    }
    private function getXmlData($outfile)
    {
        if($this->isRunning()){
            $cmd=$this->dvbc . "dumpxmltv >$outfile";
            exec($cmd);
        }
    }
    public function start()
    {
        if($this->alreadyrunning){
            /*
            $this->proc=new BackgroundCommand($this->dvbd);
            // the dvbstreamer daemon writes to it's own pid file
            // once it has forked a child, so set that value
            // as the value currently is the parent 
            // the backgroundcommand class will not start
            // a new process but will take on the existing.
            $this->proc->run(5,$this->pidfile);
             */
            $ret=true;
        }else{
            if(false!==($ret=$this->isRunning())){
                $this->proc=new BackgroundCommand($this->dvbd);
                // start it and wait 10 secs for it to settle down
                $this->proc->run(10);
                $ret=$this->isRunning();
            }
        }
        if($ret){
            $humandata=unixPath($this->home) . ".dvbstreamer/muxdata";
            $data=$humandata . ".dat";
            if($this->doparsemuxdata){
                $this->parseMuxData();
                file_put_contents($humandata,print_r($this->muxes,true));
                file_put_contents($data,serialize($this->muxes));
            }else{
                if(file_exists($data)){
                    $this->muxes=unserialize(file_get_contents($data));
                }else{
                    $this->parseMuxData();
                    file_put_contents($humandata,print_r($this->muxes,true));
                    file_put_contents($data,serialize($this->muxes));
                }
            }
        }
        return $ret;
    }
    public function stop()
    {
        // if we attached to an already running daemon
        // we shouldn't stop it
        if(false===($ret=$this->alreadyrunning)){
            $this->proc->stop();
        }else{
            // fake the return to say that it has stopped
            return true;
        }
        // otherwise, did the daemon we started stop?
        return !$this->isRunning();
    }
    public function isRunning()
    {
        $ret=$this->alreadyrunning;
        if(false!==$this->proc){
            $ret=$this->proc->isRunning();
        }
        return $ret;
    }
    public function getService($mux,$service)
    {
        $ret=false;
        if(is_string($mux) && $mux && is_string($service) && $service){
            $ret=$this->muxes[$mux][$service];
        }
        return $ret;
    }
    public function getMuxForService($service)
    {
        $ret=false;
        if(is_string($service) && strlen($service)){
            foreach($this->muxes as $key=>$mux){
                if(is_array($mux) && isset($mux[$service]) && is_array($mux[$service])){
                    $ret=$key;
                    break;
                }
            }
        }
        return $ret;
    }
    public function dumpmuxes()
    {
        return $this->muxes;
    }
    public function dumpXmlData($outfile="")
    {
        if(!$outfile){
            $outfile="/media/tv/whatson.xml";
        }
        $this->getXmlData($outfile);
    }
    public function request($req="")
    {
        // $op="";
        if(is_string($req) && strlen($req)){
            $cmd=$this->dvbc . $req;
            // print "request: $cmd\n";
            doLog("DVBD request: $cmd",LOG_DEBUG);
            $lastline=exec($cmd,$op,$ret);
            // $tmp="lastline: $lastline: ret: " . print_r($ret,true) . ": op: " . print_r($op,true);
            // doLog("exec result: $tmp");
            // print "ret: $ret\n";
            // print "lastline: $lastline\n";
            // $iop=is_array($op)?implode("\n",$op):$op;
            // print "output: $iop\n";
            // print "output: $ret\n";
        }
        if($ret){
            return false;
        }else{
            return $op;
        }
    }
    public function streamNewService($service,$file)
    {
        $sn="";
        if($this->safeChannel($service)){
            if(false!=($arr=$this->lsRecord())){
                foreach($arr as $key=>$sf){
                    if($sf["type"]=="null"){
                        $sn=$key;
                        $tmp=str_replace("dvd","",$key);
                        $cx=$tmp+0;
                        break;
                    }
                }
            }else{
                $sn="<Primary>";
                $cx=0;
            }
        }
        if($sn==""){
            if(isset($arr) && is_array($arr)){
                $cx=count($arr);
            }else{
                $cx=0;
            }
            if($cx==0){
                $sn="<Primary>";
            }else{
                $cx=1;
                $sn="dvb" . $cx;
                while($this->snExiste($sn)){
                    $cx++;
                    $sn="dvb" . $cx;
                }
            }
        }
        if($sn=="<Primary>"){
            $this->request("select $service");
            $this->request("setsfavsonly on");
            $this->request("setmrl file://$file");
        }else{
            if(false===$this->snExiste($sn)){
                $this->request("addsf $sn file://$file");
            }
            $this->request("setsf $sn " . $service);
            $this->request("setsfavsonly $sn on");
            $this->request("setsfmrl $sn file://$file");
        }
        $this->streams[]=array("sid"=>$cx,"service"=>$service,"file"=>$file,"sn"=>$sn);
        return $cx;
        /*
        $sn="";
        // this will re-index and close up any gaps.
        $this->streams=array_values($this->streams);
        $cx=count($this->streams);
        if($cx==0){
            $this->request("select $service");
            // $this->request("setsfavsonly Primary on");
            $this->request("setmrl file://$file");
        }else{
            // $cmux=$this->getMuxForService($this->streams[0]["service"]);
            // if($cmux==$this->getMuxForService($service)){
            if($this->safeChannel($service)){
                $sn="dvb". $cx;
                while(!$this->isSnFree($sn)){
                    $cx++;
                    $sn="dvb". $cx;
                }
                if(false===$this->snExiste($sn)){
                    $this->request("addsf $sn file://$file");
                }
                $this->request("setsf $sn " . $service);
                // $this->request("setsfavsonly $sn on");
                $this->request("setsfmrl $sn file://$file");
            }
        }
        $this->streams[]=array("sid"=>$cx,"service"=>$service,"file"=>$file,"sn"=>$sn);
        return $cx;
         */
    }
    public function stopStream($sid)
    {
        if(is_array($this->streams) && count($this->streams)){
            foreach($this->streams as $key=>$val){
                if($val["sid"]==$sid){
                    $sid=$key;
                    break;
                }
            }
            if($this->streams[$sid]["sid"]==0 && $this->streams[$sid]["sn"]==""){
                $this->request("setmrl null://");
            }else{
                $sn=$this->streams[$sid]["sn"];
                $this->request("setsfmrl $sn null://");
            }
            unset($this->streams[$sid]);
        }
    }
    public function stopStreamByFilename($fn)
    {
        if(false!==($arr=$this->lsRecord())){
            foreach($arr as $name=>$stream){
                if($stream["filename"]==$fn){
                    if($name=="<Primary>"){
                        $this->request("setmrl null://");
                    }else{
                        $this->request("setsfmrl $name null://");
                        $this->request("rmsf $name");
                    }
                    break;
                }
            }
        }
        if(is_array($this->streams) && count($this->streams)){
            foreach($this->streams as $key=>$stream){
                if($stream["file"]==$fn){
                    unset($this->streams[$key]);
                    $this->streams=array_values($this->streams);
                    break;
                }
            }
        }
    }
    public function lsRecord()
    {
        $ret=false;
        $output=$this->request("lssfs");
        if(is_array($output) && count($output)){
            $ret=array();
            foreach($output as $op){
                $la=explode(":",$op);
                $tmp=explode("(",trim($la[2]));
                $filename=trim($tmp[0]);
                if(strlen($filename)>2){
                    $filename=substr($filename,2);
                }else{
                    $filename="";
                }
                $channel=trim($tmp[1]);
                $channel=substr($channel,0,strlen($channel)-1);
                $ret[trim($la[0])]=array("type"=>trim($la[1]),"filename"=>$filename,"channel"=>$channel);
            }
        }
        return $ret;
    }
    private function snExiste($sn="")
    {
        $ret=false;
        if(is_string($sn) && strlen($sn)){
            $arr=$this->lsRecord();
            if(is_array($arr) && count($arr) && isset($arr[$sn])){
                $ret=$arr;
            }
        }
        return $ret;
    }
    private function isSnFree($sn="")
    {
        $ret=true;
        if(false!==($arr=$this->snExiste($sn))){
            if($arr[$sn]["type"]=="file" || $arr[$sn]["type"]=="udp"){
                $ret=false;
            }
        }
        return $ret;
    }
    private function safeChannel($channel="")
    {
        $ret=false;
        if(false!==($newmux=$this->getMuxForService($channel))){
            $oldmux=$newmux;
            if(false!==($arr=$this->lsRecord())){
                foreach($arr as $key=>$sf){
                    if($sf["type"]!="null"){
                        $oldmux=$this->getMuxForService($sf["channel"]);
                        break;
                    }
                }
                // $keys=array_keys($arr);
                // $oldmux=$this->getMuxForService($arr[$keys[0]]["channel"]);
            }
        }
        if($oldmux==$newmux){
            $ret=true;
        }
        return $ret;
    }
}
?>
