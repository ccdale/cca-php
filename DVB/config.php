<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Wednesday 14 July 2010, 07:06:41
 * Last Modified: Thursday  1 September 2011, 08:56:48
 *
 * $Id: config.php 707 2011-09-01 06:57:17Z chris $
 */

if(!defined("MYSQLHOST")){
    define("MYSQLHOST","kaleb");
}
if(!defined("MYSQLDB")){
    define("MYSQLDB","tvd");
}
if(!defined("MYSQLUSER")){
    define("MYSQLUSER","tvu");
}
if(!defined("MYSQLPASS")){
    define("MYSQLPASS","tvu");
}
if(!defined("DVBHOST")){
    define("DVBHOST","kaleb");
}
if(!defined("DVBUSER")){
    define("DVBUSER","tvc");
}
if(!defined("DVBPASS")){
    define("DVBPASS","tvc");
}

$config["mysqlhost"]="kaleb";
$config["mysqldb"]="tvd";
$config["mysqluser"]="tvu";
$config["mysqlpass"]="tvu";

$config["dvbhost"]="kaleb";
$config["dvbuser"]="tvc";
$config["dvbpass"]="tvc";

$config["maxstreams"]=2;
$config["streambase"]=17294;
$config["streamaddress"]="udp://localhost:";
$config["mencoder"]="/usr/bin/mencoder";
// $config["mencopts"]="-mc 0 -noskip -forceidx -lavdopts er=4 -vc ffmpeg12 -of mpeg -oac copy -ovc copy -o ";
$config["mencopts"]="-forceidx -lavdopts er=4 -vc ffmpeg12 -of mpeg -oac copy -ovc copy -o ";
$config["mplayer"]="/usr/bin/mplayer";
$config["mpopts"]="-dumpaudio -dumpfile ";

$config["usevlc"]=true;
$config["vlc"]="/usr/bin/cvlc";
$config["vlcstreamaddress"]="udp://@:";
$config["vlcopts"]="--sout file/ps:";

$hn=gethostname();
if($hn=="stone"){
    $config["thebin"]="/kalebsrv/thebin/";
    $config["mybin"]="/druidsrv/thebin/";
    $config["mywork"]="/druidsrv/work/";
    $config["tvdir"]="/kalebsrv/tv/";
    $config["scdir"]="/kalebsrv/tv/";
    $config["tsdir"]="/home/chris/ts/";
    $config["fqreplace"]=array("showcentre","kalebsrv");
    $config["radiodir"]="/druidsrv/radio";
}elseif($hn=="druid"){
    $config["thebin"]="/kalebshowcentre/thebin/";
    $config["mybin"]="/srv/thebin/";
    $config["mywork"]="/srv/work/";
    $config["tvdir"]="/kalebshowcentre/tv/";
    $config["scdir"]="/kalebshowcentre/tv/";
    $config["tsdir"]="/home/chris/ts/";
    $config["fqreplace"]=array("showcentre","kalebshowcentre");
    $config["radiodir"]="/srv/radio";
}else{
    $config["thebin"]="/showcentre/thebin/";
    $config["mybin"]="/showcentre/thebin/";
    $config["mywork"]="/showcentre/work/";
    $config["tvdir"]="/showcentre/tv/";
    $config["scdir"]="/showcentre/tv/";
    $config["tsdir"]="/home/chris/ts/";
    $config["fqreplace"]=false;
}

// $config["scserveraddress"]="192.168.101.2";
$config["scserveraddress"]="192.168.0.2";
// $config["scserverport"]=8000;
$config["scserverport"]=32003;
$config["scserverprotocol"]="http://";
$config["scvodport"]=32003;
// $config["scvodport"]=8000;
$config["showcentre"]=$config["scserverprotocol"] . $config["scserveraddress"] . ":" . $config["scserverport"];
$config["vod"]=$config["scserverprotocol"] . $config["scserveraddress"] . ":" . $config["scvodport"];

$config["stoprecordingthis"]=$config["appdir"] . "stoprecordingthis";
$config["startstreamingchannel"]=$config["appdir"] . "startstreamingchannel";

$config["dvbstreamercmd"]="/usr/bin/dvbstreamer -d -L /home/chris/.TVd/dvbstreamer.log -u tvc -p tvc";
$config["dvbgrepcmd"]="/usr/bin/pgrep dvbstreamer";
$config["dvbkillcmd"]="/usr/bin/pkill dvbstreamer";
$config["dvbstreamersettletime"]=5;
$config["dvbstreamertuningtime"]=5;

$config["splitoverlaptime"]=20;

$config["padstart"]=120;
$config["padstop"]=15*60; // 15 minutes
// $config["padstop"]=100; // testing
$config["padsamechannel"]=20; // 20 seconds at each end, should be sufficient

$config["maxfilesize"]=1980000000; // 19.8 GB
// $config["maxfilesize"]=100000000; // 100MB for testing purposes
$config["maxproglengthbeforesplit"]=60*60;
$config["maxproglengthoveride"]=5*60;
$config["listingsdumpinterval"]=24*60*60;
$config["nolistingsifsleeplessthan"]=60*60; // 1 hour

$config["shutdowntime"]=300; // 5 minutes
$config["mencoderfailtime"]=10; // 10 seconds

$config["xmltvfile"]="/home/chris/.TVd/whatson.xml";

$config["servicedatafile"]="/home/chris/.TVd/dvbc.services.serialized";

$config["freediskspacelimit"]=10; // in GBytes.
$config["filestonotdeletediscriminatedly"]=array("mpg","mp2","output","error");
$config["oldfiletime"]=2;

$config["noprevious"]=array('/^BBC News/','/^BBC London News/');

$config["usestreaming"]=true;

$config["mintsfilesize"]=5000000;
$config["remuxdir"]="/showcentre/ts/remux/";
?>
