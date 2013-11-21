#!/usr/bin/php
<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Thursday  1 July 2010, 23:20:58
 * Last Modified: Tuesday 23 August 2011, 11:10:40
 *
 * $Id: livetv.php 703 2011-08-23 10:11:09Z chris $
 */
declare(ticks = 1);

require_once "DVB/recording.class.php";
require_once "file.php";
require_once "LOG/logging.class.php";
require_once "DVB/program.class.php";
require_once "DVB/dvblistings.functions.php";
require_once "DVB/series.class.php";
require_once "DVB/previousrecorded.class.php";

date_default_timezone_set('Europe/London');
// send debug messages to console
// $log=new Logging(true,"LTV",0,LOG_DEBUG,true);
// do not send debug messages to console
$log=new Logging(false,"LTV",0,LOG_DEBUG);

$log->message("Live TV Monitor starting");

$uarr=posix_getpwuid(posix_getuid());
$homedir=$uarr["dir"];
$appdir=unixPath($homedir) . ".TVd";
$configfile=unixPath($appdir) . "config.php";
$config=array("homedir"=>unixPath($homedir),"appdir"=>unixPath($appdir));
if(false!==checkFile($configfile,CCA_FILE_EXIST)){
    $log->message("Loading configuration from $configfile",LOG_DEBUG);
    require_once $configfile;
    define("STREAMADDRESS",$config["streamaddress"]);
    define("THEBIN",$config["thebin"]);
    define("XMLTVFILE",$config["xmltvfile"]);
}else{
    $log->message("Unable to find configuration file $configfile. Process will exit.",LOG_WARNING);
    exit;
}
// am i already running
$pid=exec("/usr/bin/pgrep livetv",$op,$ret);
$mypid=posix_getpid();
if(!$ret){
    foreach($op as $pidval){
        $pid=$pidval+0;
        if($pid!=$mypid){
            $r=new Recording(0,$log);
            $r->allCurrentlyRecording(true);
            $p=new Program(0,$log);
            if(false!==($ret=$p->nextRecordId())){
                print "Next recording: " . $p->getData("title") . " on " . $p->channelName() . " at " . date("H:i",$p->getData("start")) . "\n";
            }
            $log->message("Another instance is already running - $pid. closing this one.",LOG_INFO);
            print "Another instance is already running pid: $pid. I'll signal it and close.\n";
            posix_kill($pid,SIGHUP);
            // exec("/usr/bin/pkill -SIGHUP $pid");
            exit;
        }
    }
}

// daemonize the process
$pid=pcntl_fork();
if($pid==-1){
    die("Could not fork\n");
}elseif($pid){
    $log->message("Child pid: $pid. Parent exiting",LOG_DEBUG);
    exit;
}else{
    $log->message("Live TV Monitor started OK.");
}


function logg($msg,$loglevel=LOG_INFO)/*{{{*/
{
    global $log;
    $log->message($msg,$loglevel);
}/*}}}*/
function Debug($key="",$val="")/*{{{*/
{
    logg("Debug: key: $key, val: $val",LOG_DEBUG);
}/*}}}*/
function sig_handler($signo)/*{{{*/
{
    global $signalled;
    $signalled=$signo;
}/*}}}*/
function startDvbstreamer()/*{{{*/
{
    global $config;
    $ll=exec($config["dvbstreamercmd"],$op,$ret);
    // logg("starting dvbstreamer - op array:",LOG_DEBUG);
    // logg($op,LOG_DEBUG);
    // logg("ret: $ret",LOG_DEBUG);
    $ret=intval($ret);
    if($ret){
        logg("LiveTv: startDvbstreamer: dvbstreamer failed to start.",LOG_DEBUG);
        return false;
    }
    sleep($config["dvbstreamersettletime"]);
    logg("LiveTv: startDvbstreamer: dvbstreamer started.",LOG_DEBUG);
    return true;
}/*}}}*/
function stopDvbstreamer()/*{{{*/
{
    global $config;
    $ll=exec($config["dvbkillcmd"],$op,$ret);
    $ret=intval($ret);
    if($ret){
        logg("LiveTv: stopDvbstreamer: failed to stop dvbstreamer",LOG_WARNING);
        return false;
    }
    logg("LiveTv: stopDvbstreamer: dvbstreamer stopped.",LOG_DEBUG);
    return true;
}/*}}}*/
function isDvbstreamerRunning()/*{{{*/
{
    global $config;
    $ll=exec($config["dvbgrepcmd"],$op,$ret);
    $ret=intval($ret);
    if($ret){
        return false;
    }
    return $ll;
}/*}}}*/
function isDvbIdle()/*{{{*/
{
    global $recordings;
    $cn=count($recordings);
    if($cn==0){
        return true;
    }
    return false;
}/*}}}*/
function dvbConnect()/*{{{*/
{
    logg("livetv: dvbConnect: connecting to dvbstreamer",LOG_DEBUG);
    $dvbc=new DVBCtrl();
    if($dvbc->connect()){
        logg("livetv: dvbConnect: connected.",LOG_DEBUG);
        return $dvbc;
    }
    logg("livetv: dvbConnect: failed.",LOG_DEBUG);
    return false;
}/*}}}*/
function dvbCleanUp($force=false)/*{{{*/
{
    global $dvbclean;
    if(!$dvbclean){
        if(false!==($dvbc=dvbConnect())){
            if(false!==($sfs=$dvbc->cleanupServiceFilters(true))){
                if(is_array($sfs) && count($sfs)){
                    foreach($sfs as $filter){
                        logg("livetv: dvbCleanUp: dvb filter removed: $filter",LOG_DEBUG);
                    }
                    $dvbclean=true;
                }else{
                    logg("livetv: dvbCleanUp: nothing to clean up.",LOG_DEBUG);
                    $dvbclean=true;
                }
            }else{
                logg("livetv: dvbCleanUp: Failed to clean up service filters.",LOG_WARNING);
                $dvbclean=false;
            }
        }else{
            logg("livetv: dvbCleanUp: cannot connect to dvbstreamer to clean up",LOG_WARNING);
            $dvbclean=false;
        }
    }
    /*
    if($force){
        stopDvbstreamer();
        sleep(5);
        startDvbstreamer();
        $dvbclean=true;
    }
     */
}/*}}}*/
function serviceDbSynchronise()/*{{{*/
{
    global $config;
    // clearstatcache();
    $ft=filemtime($config["servicedatafile"]);
    $ft+=(60*60*24*7);
    $now=time();
    if($now>=$ft){
        synchroniseServiceDB();
        return 1;
    }else{
        logg("Next DB Sync " . date("D d H:i",$ft),LOG_DEBUG);
        return $ft-$now;
    }
}/*}}}*/
function synchroniseServiceDB()/*{{{*/
{
    global $log,$config;
    logg("Synchronising DB and DVBStreamer",LOG_INFO);
    if(false!==($dvbc=dvbConnect())){
        $arr=$dvbc->request("lsservices");
        $data=array();
        foreach($arr["data"] as $serv){
            $serv=trim($serv);
            $data[$serv]=$dvbc->serviceInfo($serv);
        }
        $fp=fopen($config["servicedatafile"],"w");
        fwrite($fp,serialize($data));
        fclose($fp);
        clearstatcache();
        $cn=count($data);
        if($cn){
            foreach($data as $serv=>$info){
                $c=new Channel(0,$log);
                if($c->setByName($serv."")){
                    $mux=intval($c->getData("mux"));
                    $nmux=intval($info["Multiplex UID"]);
                    if($mux!==$nmux){
                        logg("Channel $serv: Old mux: $mux, New mux: $nmux",LOG_INFO);
                        $c->setData("mux",$nmux);
                    }else{
                        logg("Channel $serv OK",LOG_DEBUG);
                    }
                }else{
                    logg("Channel $serv unknown",LOG_INFO);
                    foreach($info as $key=>$val){
                        $info[$key]=trim($val);
                    }
                    $c->setData("name",$info["Name"]);
                    switch($info["Type"]){
                    case "Digital TV":
                        $type="tv";
                        break;
                    case "Digital Radio":
                        $type="radio";
                        break;
                    default:
                        $type="data";
                        break;
                    }
                    $c->setData("type",$type);
                    $c->setData("mux",$info["Multiplex UID"]);
                    $c->setData("eitid",$info["ID"]);
                    $access=$info["Conditional Access?"]=="Free to Air"?"y":"n";
                    $c->setData("access",$access);
                    $c->setData("favourite","n");
                    logg("Channel $serv inserted into db as id: " . $c->data_id,LOG_DEBUG);
                }
            }
        }
    }
    logg("Synchronisation completed.",LOG_INFO);
}/*}}}*/
function recordNewProgram($pid)/*{{{*/
{
    global $log,$recordings,$nextstreamnumber,$config,$dvbclean;
    $pid=intval($pid);
    if($pid){
        logg("livetv: recordNewProgram: pid: $pid",LOG_DEBUG);
        $cn=count($recordings);
        if(false!==($r=new Recording(0,$log,$config,$nextstreamnumber++,$pid))){
            $recordings[]=$r;
            $dvbclean=false;
            return true;
        }else{
            logg("Failed to record pid: $pid",LOG_WARNING);
            $r->deleteThisRecording();
            $r=null;
        }
        $p=new Program($pid,$log);
        $sr=new Series(0,$log);
        $sr->selectBySeriesId($p->getData("seriesid"));
        if($sr->isInDB()){
            $sr->setNextSeries();
        }
        $p=null;
        $sr=null;
    }
    return false;
}/*}}}*/
function checkStartRecording($outputstatus=false)/*{{{*/
{
    global $log,$recordings,$config;
    /* check and start recording if necessary */
    $now=time();
    $p=new Program(0,$log);
    $tnrs=$p->nextRecordStart($outputstatus);
    $nrs=$tnrs-$now;
    $padding=0;
    $canpad=canPad();
    $stopcurrentchannel=false;
    if(is_bool($canpad) && $canpad){
        $padding=$config["padstart"];
    }elseif(is_bool($canpad)){
        $padding=0;
    }elseif(is_int($canpad)){
        if($canpad==-1){
            $padding=$config["padsamechannel"];
            $stopcurrentchannel=true;
        }else{
            $padding=min($canpad,$config["padstart"]);
        }
    }
    $nrs=$nrs-$padding;
    if($nrs<2){
        logg("checkStartRecording: Padding: $padding",LOG_DEBUG);
        if($p->nextRecordId()){
            if(!isDvbstreamerRunning()){
                startDvbstreamer();
            }
            // if($stopcurrentchannel){
                // stopByChannel($p->getData("channel"));
            // }
            if(isDvbstreamerRunning()){
                if(recordNewProgram($p->getData("id"))){
                    $tnrs=$p->nextRecordStart();
                    $nrs=$tnrs-$now;
                    $nrs=min((5*60),$nrs); // 5 minutes or $nrs
                    $parr=$p->arr;
                    $pr=new PreviousRecorded($p->arr,$log,$config);
                }else{
                    // failed to start to record
                    // try again in 10 seconds
                    $nrs=10;
                }
            }else{
                logg("Failed to start dvbstreamer for new recording.",LOG_WARNING);
                $nrs=10;
            }
            $sr=new Series(0,$log);
            $sr->selectBySeriesId($p->getData("seriesid"));
            if($sr->isInDB()){
                $sr->setNextSeries();
            }
        }
    }
    return $nrs;
}/*}}}*/
function checkStartStreamingChannel()/*{{{*/
{
    global $log,$config;
    $ret=false;
    if(false!==($sst=getFile($config["startstreamingchannel"],CCA_FILE_ASSTRING))){
        $isst=intval($sst);
        $c=new Channel($isst,$log,$config);
        $cname=$c->getData("name");
        logg("Channel streaming file found: $cname",LOG_INFO);
    }
}/*}}}*/
function stopByChannel($channel=0)/*{{{*/
{
    global $recordings,$config;
    if($channel){
        foreach($recordings as $recording){
            if($channel==$recording->getData("channel")){
                $cmd="echo " . $recording->getData("pid") . " >" . $config["stoprecordingthis"];
                exec($cmd);
                break;
            }
        }
    }
}/*}}}*/
function checkStopRecording()/*{{{*/
{
    global $recordings,$log,$config,$twentyfourhours;
    $nre=$twentyfourhours;
    if(checkStopRecordingThis()){
        return 1;
    }else{
        $cnr=count($recordings);
        if($cnr){
            $now=time();
            /* check and stop recording if necessary */
            $canpad=canPad();
            if(is_int($canpad)){
                $padding=min($canpad,$config["padstop"]);
            }else{
                if(is_bool($canpad) && $canpad){
                    $padding=$config["padstop"];
                }else{
                    $padding=0;
                }
            }
            if($padding<0){
                $padding=0;
            }
            logg("checkStopRecording: padding: $padding",LOG_DEBUG);
            $removekey=false;
            foreach($recordings as $key=>$recording){
                if($recording->amRecording()){
                    $tnre=$recording->getData("stop");
                    $tnre=$tnre-$now;
                    $tnre+=$padding;
                    if($tnre<1){
                        $recording->stopRecording();
                        $removekey=$key;
                        $tnre=1;
                    }
                    $nre=min($tnre,$nre);
                }else{
                    // shouldn't get here, but just in case
                    // as this recording has stopped, so remove it
                    $removekey=$key;
                }
            }
            if(false!==$removekey){
                $recordings[$removekey]=null;
                unset($recordings[$removekey]);
            }
            // if that was the last recording we removed
            // clean up the dvb service filters
            $cnr=count($recordings);
            if($cnr==0){
                dvbCleanUp(true);
            }
        }
    }
    return $nre;
}/*}}}*/
function checkStopRecordingThis()/*{{{*/
{
    global $recordings,$config;
    $ret=false;
    if(false!==($srt=getFile($config["stoprecordingthis"],CCA_FILE_ASSTRING))){
        logg("Stop Recording file found");
        $isrt=intval(trim($srt));
        $cnr=count($recordings);
        if($cnr){
            $removekey=false;
            foreach($recordings as $key=>$recording){
                if(0!==intval(($pid=$recording->getData("pid")))){
                    if($pid==$isrt){
                        logg("Found one recording to stop: $pid");
                        if($recording->amRecording()){
                            logg("asking recorder to stop recording: " . $recording->getData("title"));
                            $recording->stopRecording();
                            $removekey=$key;
                            $ret=true;
                        }
                    }
                }
            }
            if(false!==$removekey){
                $recordings[$removekey]=null;
                unset($recordings[$removekey]);
            }
        }
        unlink($config["stoprecordingthis"]);
    }
    return $ret;
}/*}}}*/
function canPad()/*{{{*/
{
    global $recordings,$log;
    $cn=count($recordings);
    $curchans=array();
    if($cn>0){
        foreach($recordings as $recording){
            $cmux=$recording->getCurrentMux();
            $curchans[]=$recording->getData("channel");
        }
        $p=new Program();
        if($p->nextRecordId()){
            $nextchan=$p->getData("channel");
            $nextmux=$p->getData("mux");
            if($nextmux==$cmux){
                // next recording on the same mux, so padding ok.
                $ret=true; // return a blanket ok, if the next bit fails
                if(count($curchans)){
                    foreach($curchans as $chan){
                        if($chan==$nextchan){
                            $ret=-1; // same channel so note this
                        }
                    }
                }
                return $ret;
            }else{
                $nstop=lastStop();
                $nstart=$p->getData("start");
                // return (max($nstop,$nstart)-min($nstart,$nstop));
                if($nstop==$nstart){
                    return 0;
                }else{
                    return $nstart-$nstop;
                }
            }
        }else{
            // nothing else to be recorded??? so padding ok
            return true;
        }
    }else{
        // not recording so padding ok
        return true;
    }
    return false;
}/*}}}*/
function lastStop()/*{{{*/
{
    global $recordings;
    $nre=time();
    $cn=count($recordings);
    if($cn){
        foreach($recordings as $recording){
            $tnre=$recording->getData("stop");
            $nre=max($tnre,$nre);
        }
    }
    return $nre;
}/*}}}*/
function checkFileSizes()/*{{{*/
{
    global $recordings,$log,$nextstreamnumber,$twentyfourhours;
    $rc=$twentyfourhours;
    $cn=count($recordings);
    if($cn){
        foreach($recordings as $recording){
            if($recording->checkFileSize($nextstreamnumber)){
                $nextstreamnumber++;
            }
        }
        // something is being recorded so check again in 5 mins
        $rc=5*60;
    }
    return $rc;
}/*}}}*/
function setAtTime($sleep=0)/*{{{*/
{
    checkAndDeleteAtQ();
    if(is_int($sleep) && $sleep>121){
        $cmd="/usr/bin/at -f /home/chris/.TVd/runmonitor.at ";
        $now=time();
        $snow=$now+$sleep;
        $snow=$snow-120;
        $restarttime=date("H:i",$snow);
        $cmd.=$restarttime;
        $cmd.="  >/dev/null 2>&1";
        exec($cmd,$cmdop,$retvar);
        if($retvar==0){
            logg("Restarting at $restarttime");
            return true;
        }
        logg("Failed to set restart time to $restarttime",LOG_WARNING);
    }
    return false;
}/*}}}*/
function checkAndDeleteAtQ()/*{{{*/
{
    $cmd="/usr/bin/atq";
    exec($cmd,$cmdop,$retvar);
    if($retvar==0){
        if(is_array($cmdop) && count($cmdop)){
            foreach($cmdop as $atq){
                $tmp=explode("\t",$atq,2);
                $cmd="atrm " . trim($tmp[0]);
                exec($cmd);
                logg("Removing restart time at " . $tmp[1],LOG_DEBUG);
            }
        }
    }else{
        $log->message("unable to check atq");
    }
}/*}}}*/
function dvbDate()/*{{{*/
{
    $rec=0;
    $dvb=new DVBCtrl(DVBHOST,DVBUSER,DVBPASS);
    if(false!==($ret=$dvb->connect())){
        if(isDvbIdle()){
            $dvb->request("select BBC ONE");
            sleep(2);
        }
        $rec=$dvb->request("date");
    }
    $dvb=null;
    return $rec;
}/*}}}*/
function checkSetDateFromDVB()/*{{{*/
{
    global $lastdateset;
    // $ret=$lastdateset;
    $now=time();
    // $now+=3600;
    if($now>$lastdateset){
        if(!isDvbstreamerRunning()){
            startDvbstreamer();
        }
        $dtt=dvbDate();
        if(isset($dtt["data"])){
            $cn=count($dtt["data"]);
            if($cn==2){
                $dt=$dtt["data"][0];
                $cmd="echo '$dt' |sed -n '/^UTC/s#UTC Date/Time (YYYY/MM/DD hh:mm:ss) \(.*\)$#\\1 +0000#p'";
                exec($cmd,$op);
                $dt=$op[0];
                $cmd="sudo date -s \"$dt\"";
                logg("Date/time from DVB: $dt");
                exec($cmd,$op);
                // logg("Date set from DVB to: $dt",LOG_DEBUG);
                $lastdateset=$now+3600;
            }
        }
    }
}/*}}}*/
function nextListingsTime($outputstatus=false)/*{{{*/
{
    global $config;
    clearstatcache();
    if(checkFile(XMLTVFILE,CCA_FILE_EXIST)){
        $ft=filemtime(XMLTVFILE);
    }else{
        $ft=0;
    }
    // logg("nextListingsTime: filemtime: " . date("D d/m/y H:i",$ft),LOG_DEBUG);
    // $ft+=TWENTYFOURHOURS;
    $ft+=$config["listingsdumpinterval"];
    // $ft+=(6*60*60); // 6 hours
    if($outputstatus){
        logg("nextListingsTime: " . date("D d/m/y H:i",$ft),LOG_DEBUG);
    }
    $now=time();
    if($now>=$ft){
        updateListings();
        return 1;
    }else{
        return $ft-$now;
    }
}/*}}}*/
function updateListings()/*{{{*/
{
    global $log;
    $dstarted=false;
    if(!isDvbstreamerRunning()){
        startDvbstreamer();
        $dstarted=true;
    }
    $log->message("Downloading new listings data");
    doDumpXML($log);
    if($dstarted){
        stopDvbstreamer();
    }
    $p=new Program(0,$log);
    $p->cleanDB();
    parseXML(false,$log);
    setSeriesRecordings();
}/*}}}*/
function setSeriesRecordings()/*{{{*/
{
    global $log;
    $sr=new Series(0,$log);
    $sarr=$sr->getAllSeriesData();
    if(is_array($sarr) && ($cn=count($sarr))){
        $log->message("starting series update: $cn items to check.");
        foreach($sarr as $series){
            $s=new Series($series["id"],$log);
            $s->setNextSeries();
        }
        $s=null;
    }
    $sr=null;
    $log->message("finished checking series recordings");
}/*}}}*/
function missedRecordings()/*{{{*/
{
    global $log;
    $p=new Program(0,$log);
    $p->missedRecordings();
    $p=null;
}/*}}}*/
function tvDiskFreeSpace()/*{{{*/
{
    global $config;
    $df=disk_free_space($config["thebin"]); // this is in bytes
    logg("Disk free space: " . printableFilesize($df),LOG_INFO);
    $df/=1000000000.0;
    if($df<$config["freediskspacelimit"]){
        logg("Removing non-media files.",LOG_INFO);
        $arr=cleanDir($config["thebin"],$config["filestonotdeletediscriminatedly"],0);
        logCleaned($arr);
        logg("Cleaned " . $config["thebin"] . " of " . printableFilesize($arr["totalbytes"]),LOG_INFO);
        logg("Removing files " . $config["oldfiletime"] . " days old or older.",LOG_INFO);
        $arr=cleanDir($config["thebin"],"",$config["oldfiletime"]);
        logCleaned($arr);
        logg("Cleaned " . $config["thebin"] . " of " . printableFilesize($arr["totalbytes"]),LOG_INFO);
    }
}/*}}}*/
function logCleaned($arr)/*{{{*/
{
    if(is_array($arr) && count($arr)){
        for($x=0;$x<2;$x++){
            if($x==0){
                $a=$arr["cleaned"];
                $txt="Deleted: ";
            }else{
                $a=$arr["failed"];
                $txt="Failed to delete: ";
            }
            if(isset($a) && is_array($a) && count($a)){
                foreach($a as $fa){
                    logg($txt . $fa[0] . " " . printableFilesize($fa[1]),LOG_DEBUG);
                }
            }
        }
    }
}/*}}}*/
function logUpTime()/*{{{*/
{
    global $timestart,$nextstreamnumber,$numrecordingsmade;
    $nrm=$numrecordingsmade+$nextstreamnumber;
    $timenow=time();
    $uptime=secToHMS($timenow-$timestart,true);
    logg("Uptime: $uptime. Recordings: $nrm",LOG_INFO);
}/*}}}*/
$timestart=time();
$lastdateset=1;
$nextlistingstime=1;
$signalled=0;
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP,  "sig_handler");
pcntl_signal(SIGUSR1, "sig_handler");
// pcntl_signal(SIGUSR2, "sig_handler");
$exit=false;
$streams=array();
$recordings=array();
$nextstreamnumber=0;
$numrecordingsmade=0;
$twentyfourhours=24*60*60;
$outputstatus=true;
missedRecordings();
if(isDvbstreamerRunning()){
    stopDvbstreamer();
}
if(startDvbstreamer()){
    do{/*{{{*/
        switch($signalled){
        case 0:
            // do nothing, normal state
            break;
        case SIGTERM:
            // handle shutdown tasks
            $signalled=0;
            $exit=true;
            logg("Term signal received",LOG_DEBUG);
            break;
        case SIGHUP:
            // we want to read the config again
            $signalled=0;
            logg("SIGHUP received",LOG_DEBUG);
            logg("re-reading config",LOG_INFO);
            require $configfile;
            $outputstatus=true;
            break;
        case SIGUSR1:
            // we want to stop a current recording
            // or a new recording has been added
            $signalled=0;
            logg("SIGUSR1 received",LOG_DEBUG);
            break;
        default:
            $signalled=0;
            logg("Unhandled signal: $signalled received",LOG_WARNING);
            break;
        }
        if($exit){
            break;
        }
        if($nextstreamnumber>=1000){
            $numrecordingsmade+=($nextstreamnumber-1);
            $nextstreamnumber=0;
        }
        $nre=checkStopRecording();
        $nrs=checkStartRecording($outputstatus);
        if($nrs<1){
            // If we miss the start this will be negative
            // so ignore that and make it 1
            $nrs=1;
        }
        // checkStopRecordingThis(); // this is now run in checkStopRecording()
        checkSetDateFromDVB();
        $rc=checkFileSizes();
        $sleeptime=min($nrs,$nre,$rc,$twentyfourhours);
        if($sleeptime>0){
            $cn=count($recordings);
            if($cn==0){
                // we are now idle
                logUpTime();
                if($sleeptime>$config["shutdowntime"]){
                    tvDiskFreeSpace();
                    if($sleeptime>$config["nolistingsifsleeplessthan"]){
                        $nlt=nextListingsTime($outputstatus);
                    }else{
                        $nlt=$config["nolistingsifsleeplessthan"];
                    }
                    $sds=serviceDbSynchronise();
                    $sleeptime=min($sleeptime,$nlt,$sds);
                    if($outputstatus){
                        $outputstatus=false;
                    }
                    if($sleeptime>$config["shutdowntime"]){
                        setAtTime($sleeptime);
                        logg("idle for " . secToHMS($sleeptime),LOG_INFO);
                        $exit=true;
                    }else{
                        logg("sleeping for " . secToHMS($sleeptime),LOG_INFO);
                        sleep($sleeptime);
                    }
                    /*
                    if(setAtTime($sleeptime)){
                        logg("am now idle.",LOG_INFO);
                        $exit=true;
                    }else{
                        logg("error setting at time. sleeping for " . secToHMS($sleeptime),LOG_DEBUG);
                        sleep($sleeptime);
                    }
                     */
                }else{
                    logg("sleeping for " . secToHMS($sleeptime),LOG_DEBUG);
                    sleep($sleeptime);
                }
            }else{
                if($outputstatus){
                    $outputstatus=false;
                }
                logg("sleeping for " . secToHMS($sleeptime),LOG_DEBUG);
                sleep($sleeptime);
            }
        }else{
            logg("sleeptime is less than zero ($sleeptime)",LOG_WARNING);
            $exit=true;
        }
    }while(true);/*}}}*/
    if(isDvbstreamerRunning()){
        stopDvbstreamer();
    }
}
logUpTime();
logg("Live TV Monitor stopped");
?>
