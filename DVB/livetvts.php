#!/usr/bin/php
<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Friday 20 May 2011, 10:58:24
 * Last Modified: Monday 22 August 2011, 13:29:47
 * Version: $Id: livetvts.php 710 2011-09-17 20:55:53Z chris $
 */

declare(ticks = 1);

require_once "file.php";
require_once "video.php";
require_once "LOG/logging.class.php";
require_once "DVB/tsfile.class.php";
require_once "DVB/previousrecorded.class.php";
require_once "DVB/series.class.php";

date_default_timezone_set('Europe/London');
// send debug messages to console
// $log=new Logging(true,"LTS",0,LOG_DEBUG,true);
// do not send debug messages to console
$log=new Logging(false,"LTS",0,LOG_DEBUG);

$log->message("Live TS Monitor starting");

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
        $pid=intval($pidval);
        if($pid!=$mypid){
            /*
            $r=new Recording(0,$log);
            $r->allCurrentlyRecording(true);
            $p=new Program(0,$log);
            if(false!==($ret=$p->nextRecordId())){
                print "Next recording: " . $p->getData("title") . " on " . $p->channelName() . " at " . date("H:i",$p->getData("start")) . "\n";
            }
             */
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
    // ok we are the child.
    // things to do now:
    // close stdin,stdout and stderr
    // chdir / (so that disks can be unmounted later)
    // fork again
    fclose(STDIN);
    fclose(STDOUT);
    fclose(STDERR);
    chdir("/");
    $pid=pcntl_fork();
    if($pid==-1){
        die("failed to fork a second time\n");
    }elseif($pid){
        $log->message("2nd fork: child: $pid. This child will now exit",LOG_DEBUG);
        exit;
    }else{
        $log->message("Live TS Monitor started OK.");
    }
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
    logg("signal received: $signo",LOG_DEBUG);
    $signalled=$signo;
}/*}}}*/
function startDvbstreamer()/*{{{*/
{
    global $config;
    if(false===isDvbstreamerRunning()){
        $ll=exec($config["dvbstreamercmd"],$op,$ret);
        // logg("starting dvbstreamer - op array:",LOG_DEBUG);
        // logg($op,LOG_DEBUG);
        // logg("ret: $ret",LOG_DEBUG);
        $ret=intval($ret);
        if($ret){
            logg("startDvbstreamer: dvbstreamer failed to start.",LOG_DEBUG);
            return false;
        }
        sleep($config["dvbstreamersettletime"]);
        logg("startDvbstreamer: dvbstreamer started.",LOG_DEBUG);
    }else{
        logg("startDvbstreamer: DVBstreamer is already running.",LOG_DEBUG);
    }
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
function dvbCleanUp()/*{{{*/
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
}/*}}}*/
function recordNewProgram($pid)/*{{{*/
{
    global $log,$recordings,$config,$numrecordingsmade;
    $pid=intval($pid);
    if($pid){
        // $cn=count($recordings);
        // $recordings[]=new TSFile(0,$log,$config,$pid);
        $r=new TSFile(0,$log,$config,$pid);
        if(false===$r->isRecording(true)){
            logg("failed to start recording " . $r->getProgramTitle(),LOG_WARNING);
            $r->deleteRecordAndFile();
            unset($r);
        }else{
            logg("Recording started OK for " . $r->getProgramTitle(),LOG_INFO);
            $ret=$r->updateProgRec();
            if(false==($ret=$r->updateProgRec())){
                logg("error inserting progrec record for " . $r->getProgramTitle(),LOG_DEBUG);
                logg("mysql error: " . $r->mx->error_text,LOG_DEBUG);
            }
            // $r->mx->query("insert into progrec select * from program where id=$pid");
            $numrecordingsmade++;
            $recordings[]=$r;
            $r=null;
            return true;
        }
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
    $type=$p->nextRecordType();
    $nrs=$tnrs-$now;
    $padding=0;
    if($type=="tv"){
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
                    // $pr=new PreviousRecorded($p->arr,$log,$config);
                }else{
                    // failed to start to record
                    // try again in 10 seconds
                    logg("failed to start new recording, trying agin in 10 seconds",LOG_WARNING);
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
function checkStopRecording()/*{{{*/
{
    global $recordings,$log,$config,$twentyfourhours,$processq;
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
                if($padding=-1){
                    $padding=0;
                }
                $padding=min($canpad,$config["padstop"]);
            }else{
                if(is_bool($canpad) && $canpad){
                    $padding=$config["padstop"];
                }else{
                    $padding=0;
                }
            }
            logg("checkStopRecording: padding: $padding",LOG_DEBUG);
            if($cnr==1){
                logg("There is currently $cnr recording going on.",LOG_DEBUG);
            }else{
                logg("There are currently $cnr recordings going on.",LOG_DEBUG);
            }
            $removekey=false;
            foreach($recordings as $key=>$recording){
                if($recording->isRecording()){
                    $tnre=$recording->getStopTime();
                    $type=$recording->getChannelType();
                    if($type=="radio"){
                        $padding=0;
                    }
                    $xtnre=$tnre+$padding;
                    logg("Stopping at " . date("H:i",$xtnre) . " (" . date("H:i",$tnre) . ") " . $recording->getProgramTitle(),LOG_DEBUG);
                    $tnre=$tnre-$now;
                    $tnre+=$padding;
                    if($tnre<1){
                        if(false!=$recording->stopRecordingFile()){
                            logg("Stopped recording " . $recording->getProgramTitle(),LOG_INFO);
                        }else{
                            logg("Failed to stop recording " . $recording->getProgramTitle(),LOG_WARNING);
                        }
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
                $processq[]=$recordings[$removekey];
                $recordings[$removekey]=null;
                unset($recordings[$removekey]);
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
                        if($recording->isRecording()){
                            logg("asking recorder to stop recording: " . $recording->getProgramTitle());
                            $recording->stopRecordingFile();
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
            $curchans[]=$recording->getChannelId();
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
                return (max($nstop,$nstart)-min($nstart,$nstop));
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
            $tnre=$recording->getStopTime();
            $nre=max($tnre,$nre);
        }
    }
    return $nre;
}/*}}}*/
function missedRecordings()/*{{{*/
{
    global $log;
    $p=new Program(0,$log);
    $p->missedRecordings();
    $p=null;
}/*}}}*/
function checkFileSizes()/*{{{*/
{
    global $recordings,$log,$config,$twentyfourhours;
    $rc=$twentyfourhours;
    $cn=count($recordings);
    if($cn){
        $deletekeys=array();
        $splitkeys=array();
        foreach($recordings as $key=>$recording){
            if($recording->isRecording(true)){
                $lfs=$recording->getLastFilesize();
                $fn=basename($recording->getFqfn());
                $fnum=$recording->getData("filenumber");
                logg("$fnum $fn  " . printableFilesize($lfs),LOG_INFO);
                /*
                 * turn off file splitting
                 * lets get one big ts file
                 * so that we can use vlc to stream
                 * it live if necessary.  mplex can always
                 * keep it within the 2GB limit later
                if($lfs>$config["maxfilesize"]){
                    $splitkeys[]=$key;
                }
                 */
            }else{
                logg("deleting failed recording: " . $recording->getFqfn(),LOG_DEBUG);
                $deletekeys[]=$key;
            }
        }
        if(count($deletekeys)){
            foreach($deletekeys as $dkey){
                $recordings[$dkey]->deleteRecordAndFile();
                $recordings[$dkey]=null;
                unset($recordings[$dkey]);
            }
            $cn=count($recordings);
        }
        if(count($splitkeys)){
            foreach($splitkeys as $skey){
                $pid=$recordings[$skey]->getData("pid");
                $fnum=intval($recordings[$skey]->getData("filenumber"));
                $fnum++;
                $r=new TSFILE(0,$log,$config,$pid,$fnum);
                if($r->isRecording(false)){
                    logg("Split recording of " . $recordings[$skey]->getProgramTitle());
                    $recordings[$skey]->stopRecordingFile();
                    $recordings[$skey]=null;
                    unset($recordings[$skey]);
                    $recordings[]=$r;
                    unset($r);
                }
            }
            $cn=count($recordings);
        }
        if($cn){
            // something is being recorded so check again in 5 mins
            $rc=5*60;
        }
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
function logUpTime()/*{{{*/
{
    global $timestart,$numrecordingsmade;
    $nrm=$numrecordingsmade;
    $timenow=time();
    $uptime=secToHMS($timenow-$timestart,true);
    logg("Uptime: $uptime. Recordings: $nrm",LOG_INFO);
}/*}}}*/
function checkOrphans()/*{{{*/
{
    global $log,$config,$recordings;
    logg("Checking for orphaned recordings",LOG_INFO);
    $ret=false;
    $t=new TSFILE(0,$log,$config);
    if(false!==($oarr=$t->orphanRecordings())){
        $t=null;
        // orpaned recordings found
        if(is_array($oarr) && count($oarr)){
            foreach($oarr as $orphan){
                $recordings[]=new TSFILE($orphan["id"],$log,$config);
            }
            $numrecordingsmade=count($recordings);
            if($numrecordingsmade>0){
                foreach($recordings as $recording){
                    logg("added orphaned recording for " . $recording->getProgramTitle(),LOG_DEBUG);
                    if(false===$recording->orphanDBCheck()){
                        logg("failed to make DB consistant for " . $recording->getProgramTitle(),LOG_DEBUG);
                    }else{
                        logg("Success making DB consistant for " . $recording->getProgramTitle(),LOG_DEBUG);
                    }
                    if(isDvbstreamerRunning()){
                        $dvbc=new DVBCtrl();
                        if($dvbc->connect()){
                            $ra=$dvbc->lsRecording(false);
                            $fn=$dvbc->filterNumberForFile($recording->getFqfn());
                            if($fn==-1){
                                $c=new Channel($recording->getChannelId(),$log);
                                $dvbc->setFavsonlyOn();
                                if(false!==($dvbc->recordNewService($c->getName(),$recording->getFqfn()))){
                                    logg("Started orphaned recording for " . $recording->getProgramTitle(),LOG_DEBUG);
                                    $recording->setAmRecording();
                                    $ret=true;
                                }else{
                                    logg("Failed to start orphaned recording for " . $recording->getProgramTitle(),LOG_DEBUG);
                                }
                                $dvbc=null;
                            }else{
                                logg("Taken control of orphaned recording for " . $recording->getProgramTitle(),LOG_DEBUG);
                                $recording->setAmRecording();
                                $ret=true;
                            }
                        }else{
                            logg("Orphaned check: Failed to connect to dvbstreamer.",LOG_DEBUG);
                        }
                    }else{
                        if(startDvbstreamer()){
                            $c=new Channel($recording->getChannelId(),$log);
                            $dvbc=new DVBCtrl();
                            if($dvbc->connect()){
                                logg("selecting channel " . $c->getName() . " for orphaned recording of " . $recording->getProgramTitle() . " to file " . $recording->getFqfn(),LOG_DEBUG);
                                $dvbc->select($c->getName());
                                $dvbc->setFavsonlyOn();
                                if(false!==($dvbc->recordNewService($c->getName(),$recording->getFqfn()))){
                                    $recording->setAmRecording();
                                    logg("Started orphaned recording for " . $recording->getProgramTitle(),LOG_DEBUG);
                                    $ret=true;
                                }else{
                                    logg("Failed to start orphaned recording for " . $recording->getProgramTitle(),LOG_DEBUG);
                                }
                            }
                            $dvbc=null;
                        }else{
                            logg("Orphaned check: Failed to start dvbstreamer",LOG_DEBUG);
                        }
                    }
                }
            }
        }
    }
    return $ret;
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
function  tsProcessQ()/*{{{*/
{
    global $config,$processq;
}/*}}}*/
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP,  "sig_handler");
pcntl_signal(SIGUSR1, "sig_handler");
// pcntl_signal(SIGUSR2, "sig_handler");
$timestart=time();
$dvbclean=false;
$signalled=0;
$recordings=array();
$processq=array();
$numrecordingsmade=0;
$twentyfourhours=24*60*60;
$exit=false;
$outputstatus=true;

$orphan=checkOrphans();
if(isDvbstreamerRunning() && !$orphan){
    /*
    // if dvbstreamer is still running
    // from a previous run of livetv
    // lets check what state it is in.
    $t=new TSFILE(0,$log,$config);
    if(false!==($oarr=$t->orphanRecordings())){
        // orpaned recordings found
        if(is_array($oarr) && count($oarr)){
            foreach($oarr as $orphan){
                $recordings[]=new TSFILE($orphan["id"],$log,$config);
            }
            $numrecordingsmade=count($recordings);
            if($numrecordingsmade>0){
                foreach($recordings as $recording){
                    logg("added orphaned recording for " . $recording->getProgramTitle());
                    if(false===$recording->orphanDBCheck()){
                        logg("failed to make DB consistant for " . $recording->getProgramTitle());
                    }else{
                        logg("Success making DB consistant for " . $recording->getProgramTitle());
                    }
                }
            }
        }
    }else{
     */
        logg("No orpaned recordings found, why is dvbstreamer running? will stop it",LOG_DEBUG);
        $dvbc=new DVBCtrl();
        if($dvbc->connect()){
            $rec=$dvbc->lsRecording(false);
            if(is_array($rec) && count($rec)){
                logg("dvbstreamer is currently doing:",LOG_DEBUG);
                foreach($rec as $r){
                    logg("type: " . $r["type"] . " channel: " . $r["channel"] . " filename: " . $r["filename"],LOG_DEBUG);
                }
            }
        }
        logg("Stopping dvbstreamer.",LOG_DEBUG);
        stopDvbstreamer();
        $recordings=array();
        $numrecordingsmade=0;
        /*
    }
         */
}
missedRecordings();
if(startDvbstreamer()){
    do{
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
            $cn=count($recordings);
            if($cn){
                foreach($recordings as $r){
                    $r->stopRecordingFile();
                }
            }
            break;
        }
        /*
        if($nextstreamnumber>=1000){
            $numrecordingsmade+=($nextstreamnumber-1);
            $nextstreamnumber=0;
        }
         */
        $nre=checkStopRecording();
        $nrs=checkStartRecording($outputstatus);
        if($nrs<1){
            // If we miss the start this will be negative
            // so ignore that and make it 1
            $nrs=1;
        }
        // checkStopRecordingThis(); // this is now run in checkStopRecording()
        // checkSetDateFromDVB();
        $rc=checkFileSizes();
        $sleeptime=min($nrs,$nre,$rc,$twentyfourhours);
        if($sleeptime>0){
            $cn=count($recordings);
            if($cn==0){
                // we are now idle
                logUpTime();
                if($sleeptime>$config["shutdowntime"]){
                    tvDiskFreeSpace();
                    setAtTime($sleeptime);
                    logg("idle for " . secToHMS($sleeptime),LOG_INFO);
                    $exit=true;
                    /*
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
                    */
                    /*
                    if($sleeptime>$config["shutdowntime"]){
                        setAtTime($sleeptime);
                        logg("idle for " . secToHMS($sleeptime),LOG_INFO);
                        $exit=true;
                    }else{
                        logg("sleeping for " . secToHMS($sleeptime),LOG_DEBUG);
                        sleep($sleeptime);
                    }
                     */
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
    }while(true);
    if(isDvbstreamerRunning()){
        stopDvbstreamer();
    }
}
logUpTime();
logg("Live TV Monitor stopped");
?>
