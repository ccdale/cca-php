<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * dvblistings.functions.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Sunday  3 January 2010, 23:45:24
 * Last Modified: Monday  4 July 2011, 09:25:53
 * Version: $Id: dvblistings.functions.php 690 2011-07-04 08:26:28Z chris $
 */

function readMoreData($fp,&$data)
{
    if(!feof($fp)){
        $data.=fread($fp,4096);
        return true;
    }else{
        return false;
    }
}
function updateChannelInfo($chans)
{
    $ret=false;
    if(is_array($chans) && count($chans)){
        $tchans=array();
        $dvb=new DVBCtrl("kaleb","tvc","tvc");
        if(false!==($ret=$dvb->connect())){
            foreach($chans as $eitid=>$chan){
                $tchans[$eitid]=array();
                $info=$dvb->serviceInfo($chan);
                $tchans[$eitid]["name"]=$info["Name"];
                switch($info["Type"]){
                case "Digital TV":
                    $tchans[$eitid]["type"]="tv";
                    break;
                case "Digital Radio":
                    $tchans[$eitid]["type"]="radio";
                    break;
                case "Data":
                    $tchans[$eitid]["type"]="data";
                    break;
                }
                if($info["Conditional Access?"]=="Free to Air"){
                    $tchans[$eitid]["access"]="y";
                }else{
                    $tchans[$eitid]["access"]="n";
                }
                $tchans[$eitid]["mux"]=$info["Multiplex UID"];
            }
            $ret=$tchans;
        }
        $dvb=null;
    }
    return $ret;
}
function doChannelUpdate($log)
{
    if(false!==($chans=parseXML(true,$log))){
        if(false!==($chans=updateChannelInfo($chans))){
            foreach($chans as $key=>$chan){
                $tmp=$chan;
                $tmp["eitid"]=$key;
                $c=new Channel(0,$log);
                $c->channelUpdateInfo($tmp);
            }
        }else{
            $log->message("Error updating channel info from dvbstreamer");
        }
    }else{
        $log->message("Error parsing XML for channel informaiton");
    }
}
function doProgramUpdate($log)
{
    parseXML(false,$log);
}
function doDumpXML($log)
{
    global $inserted,$discarded,$splitprogs,$discardedcounts;
    $log->message("Starting to update listings data");
    $cmd="/usr/bin/dvbctrl -h kaleb -u tvc -p tvc dumpxmltv >" . XMLTVFILE;
    exec($cmd);
    $log->message("Listings dumped.");
    $inserted=$discarded=$splitprogs=0;
    $discardedcounts=array();
}
function parseXML($gettingchannels=false,$log)
{
    global $filesize,$inserted,$discarded,$exit,$splitprogs,$discardedcounts;
    $ret=false;
    $unfoundchunks=0;
    $maxchunks=10;
    $lastinserted=0;
    if(checkFile(XMLTVFILE,CCA_FILE_EXIST)){
        $delimstart="<programme";
        $delimend="</programme>";
        $data="";
        if($gettingchannels){
            $delimstart="<channel";
            $delimend="</channel>";
            $chans=array();
        }else{
            $chans=getChannelDataFromDB();
            $ret=$chans;
        }
        $filesize=filesize(XMLTVFILE);
        if($fp=fopen(XMLTVFILE,"r")){
            if(readMoreData($fp,$data)){
                do{
                    if($exit){
                        break;
                    }
                    $tarr=textBetween($data,$delimstart,$delimend);
                    if($tarr["start"]){
                        if($tarr["end"]){
                            $unfoundchunks=0;
                            if($gettingchannels){
                                $chans=processChannelData($tarr["text"],$chans,$log);
                            }else{
                                processProgramData($tarr["text"],$chans,$log);
                                $tmp=$lastinserted+1000;
                                if($inserted>$tmp){
                                    $lastinserted=$inserted;
                                    $log->message("inserted $inserted, discarded $discarded, split $splitprogs",LOG_DEBUG);
                                }
                            }
                            $data=$tarr["outside"];
                        }else{
                            if(readMoreData($fp,$data)){
                                $unfoundchunks++;
                                if($unfoundchunks>$maxchunks){
                                    $log->message("Unable to find $delimend, read " . strlen($data) . " bytes from $delimstart.");
                                    $log->message("exiting.");
                                    break;
                                }
                            }
                        }
                    }else{
                        $unfoundchunks++;
                        if($unfoundchunks>$maxchunks){
                            $log->message("Unable to find $delimend, read " . strlen($data) . " bytes from $delimstart.");
                            $log->message("exiting.");
                            if($gettingchannels){
                                $ret=$chans;
                            }
                            break;
                        }
                        if(!readMoreData($fp,$data)){
                            if($gettingchannels){
                                $ret=$chans;
                            }
                            $log->message("End of file.");
                            $log->message("inserted $inserted, discarded $discarded, split $splitprogs",LOG_DEBUG);
                            $inserted=$discarded=$splitprogs=0;
                            foreach($discardedcounts as $chanid=>$cn){
                                $c=new Channel($chanid);
                                $log->message("    Discarded $cn for channel " . $c->getName(),LOG_DEBUG);
                            }
                            $discardedcounts=array();
                            break;
                        }
                    }
                }while(1);
            }
            fclose($fp);
        }else{
            $log->message("Error opening " . XMLTVFILE . " for reading.");
        }
    }else{
        $log->message(XMLTVFILE . " does not exist, exiting.");
    }
    return $ret;
}
function getChannelDataFromDB()
{
    $chans=array();
    $c=new Channel();
    $tmp=$c->allChannels();
    $ftmp=$c->allChannels(true);
    foreach($tmp as $chan){
        $chans[$chan["eitid"]]=$chan;
    }
    foreach($ftmp as $chan){
        $chans[$chan["eitid"]]=$chan;
    }
    return $chans;
}
function processChannelData($cdata,$chans,$log)
{
    $tchan=parseChannelData($cdata);
    $chans[$tchan["eitid"]]=$tchan["name"];
    $log->message("Added " . $tchan["name"] . " to channel list");
    return $chans;
}
function canSplitProgramData($parr)
{
    return false;
    /*
    $ret=true;
    $dontdochannel=array(73,39,21,78,75);
    $exacttitle=array("Home Shopping","Breakfast","Super Casino","Close","Teleshopping","Film4 Preview","Closedown","BBC Red Button","..programmes start at","More4 Preview","BBC News","Pages from Ceefax");
    $substrtitle=array("Cricket","This Is ","Programmes start at","Babestation","CITV","Back at","..programmes start at");
    if(!in_array($parr["channel"],$dontdochannel)){
        if(!in_array($parr["title"],$exacttitle)){
            foreach($substrtitle as $sst){
                if($sst==substr($parr["title"],0,strlen($sst))){
                    $ret=false;
                    break;
                }
            }
        }else{
            $ret=false;
        }
    }else{
        $ret=false;
    }
    return $ret;
     */
}
function processProgramData($pdata,$chans,$log=false)
{
    global $inserted,$discarded,$splitprogs;
    if(false!==($parr=parseProgramData($pdata,$chans,$log))){
        $threehours=3*60*60;
        $halfhour=60*30;
        $len=$parr["stop"]-$parr["start"];
        if($len>$threehours && canSplitProgramData($parr)){
        // if($len>=$threehours && $parr["type"]!=="radio" && substr($parr["title"],0,8)!=="This Is " && $parr["title"]!=="Close" && $parr["title"]!=="Teleshopping" && $parr["title"]!=="Film4 Preview" && substr($parr["title"],0,19)!=="Programmes start at" && substr($parr["title"],0,11)!=="Babestation" && substr($parr["title"],0,4)!=="CITV" && substr($parr["title"],0,7)!=="Back at" && $parr["title"]!=="Closedown" && $parr["title"]!=="BBC Red Button" && substr($parr["title"],0,21)!=="..programmes start at" && $parr["title"]!=="More4 Preview" && $parr["title"]!=="BBC News" && $parr["title"]!=="Pages from Ceefax" && $parr["channel"]!=73){
            unset($parr["type"]);
            $start=$parr["start"];
            $parts=intval($len/$halfhour);
            $rem=$len%$halfhour;
            if($rem){
                $parts++;
            }
            for($x=1;$x<=$parts;$x++){
                $tarr=$parr;
                $tarr["description"].=" Program has been split. This is part $x of $parts";
                $tarr["title"].=" Split: $x of $parts.";
                $tarr["start"]=$start;
                $stop=$start+$halfhour;
                if($stop>$parr["stop"]){
                    $stop=$parr["stop"];
                }
                $tarr["stop"]=$stop;
                $start=$stop;
                $p=new Program();
                $p->removeOverlaps($tarr);
                $inserted++;
            }
            $splitprogs++;
        }else{
            unset($parr["type"]);
            $p=new Program();
            $p->removeOverlaps($parr);
            $inserted++;
        }
    }else{
        $discarded++;
    }
}
function parseProgramData($str,$chans,$log=false)
{
    // global $chans;
    global $discardedcounts;
    $parr=false;
    $now=mktime();
    if(is_string($str) && strlen($str)){
        $parr=array();
        $tarr=textBetween($str,'start="','"');
        $str=$tarr["right"];
        $parr["start"]=EITtoTS($tarr["text"]);
        if($parr["start"]>$now){
            $tarr=textBetween($str,'stop="','"');
            $str=$tarr["right"];
            $parr["stop"]=EITtoTS($tarr["text"]);
            $tarr=textBetween($str,'channel="','"');
            $str=$tarr["right"];
            // $parr["channel"]=$chans[$tarr["text"]]["name"];
            $eitid=trim($tarr["text"]);
            if(isset($chans[$eitid]) && $chans[$eitid]["type"]!="data" && $chans[$eitid]["access"]=="y" && $chans[$eitid]["getdata"]=='y'){
                $parr["channel"]=$chans[$eitid]["id"];
                $parr["type"]=$chans[$eitid]["type"];
                $parr["mux"]=$chans[$eitid]["mux"];
                // if($getdata[$tarr["text"]]==1){
                $tarr=textBetween($str,"<title","</title>");
                $str=$tarr["outside"];
                $parr["title"]=substr($tarr["text"],strpos($tarr["text"],">")+1);
                $tarr=textBetween($str,"<desc","</desc>");
                $str=$tarr["outside"];
                $parr["description"]=substr($tarr["text"],strpos($tarr["text"],">")+1);
                $tarr=textBetween($str,"<content","</content>");
                $str=$tarr["outside"];
                $parr["programid"]=substr($tarr["text"],strpos($tarr["text"],">")+1);
                $tarr=textBetween($str,"<series","</series>");
                $str=$tarr["outside"];
                $parr["seriesid"]=substr($tarr["text"],strpos($tarr["text"],">")+1);
                // }else{
                // $parr=false;
                // }
            }else{
                if(isset($chans[$eitid])){
                    if(!isset($discardedcounts[$chans[$eitid]["id"]])){
                        $discardedcounts[$chans[$eitid]["id"]]=0;
                    }
                    $discardedcounts[$chans[$eitid]["id"]]++;
                }
                $parr=false;
            }
        }else{
            $parr=false;
        }
        $tarr=null;
    }
    return $parr;
}
function parseChannelData($str)
{
    $carr=false;
    if(is_string($str) && strlen($str)){
        $tarr=textBetween($str,'id="','"');
        $str=$tarr["right"];
        $carr=array();
        $carr["eitid"]=$tarr["text"];
        $tarr=textBetween($str,"<display-name>","</display-name>");
        $carr["name"]=$tarr["text"];
    }
    return $carr;
}
function EITtoTS($eittimestring="")
{
    $tzoffset=date("Z");
    $tzoffset=intval($tzoffset/3600);
    /*
     * timestring is of the form YYYYMMDDHHmmSS [+|-]HHmm
     */
    $matches=array();
    $ts=0;
    if($eittimestring){
        $pattern='/([0-9]{1,4})([0-9]{1,2})([0-9]{1,2})([0-9]{1,2})([0-9]{1,2})([0-9]{1,2}) ([+|-])([0-9]{1,2})([0-9]{1,2})/';
        preg_match($pattern,$eittimestring,$matches);
        // $tzoffset=intval($matches[8]);
        $ts=mktime($matches[4]+$tzoffset,$matches[5],$matches[6],$matches[2],$matches[3],$matches[1]);

    }
    return $ts;
}
function getFileFromRT($fn="",$log,$alwaysusecache=false)
{
    $fn.="";
    $arr=false;
    $now=time();
    $yesterday=$now-(3600*24);
    $lfn=unixPath(RTCACHE) . $fn . ".dat";
    $rfn=unixPath(RTURL) . $fn . ".dat";
    if(checkFile($lfn,CCA_FILE_EXIST)){
        $fmt=filemtime($lfn);
        if($fmt<$yesterday && !$alwaysusecache){
            $log->message("Getting $rfn from radiotimes.");
            if(false!==($op=file($rfn))){
                $log->message("Data received ok.");
                file_put_contents($lfn,$op);
                $arr=getFile($lfn,CCA_FILE_ASARRAY);
            }else{
                $log->message("Error in receiving data from radiotimes.");
            }
        }else{
            $log->message("$lfn already in cache, not updating listings.");
            // $arr=getFile($lfn,CCA_FILE_ASARRAY);
        }
    }else{
        $log->message("Not in cache. Getting $rfn from radiotimes.");
        if(false!==($op=file($rfn))){
            $log->message("Data received ok.");
            file_put_contents($lfn,$op);
            $arr=getFile($lfn,CCA_FILE_ASARRAY);
        }else{
            $log->message("Error in receiving data from radiotimes.");
        }
    }
    return $arr;
}
function updateRTData($log)
{
    $c=new Channel();
    $rtarr=$c->rtChannels();
    if(is_array($rtarr) && count($rtarr)){
        foreach($rtarr as $rtc){
            $c=new Channel($rtc["id"]);
            $rtid=$c->getData("rtid");
            $name=$c->getData("name");
            $log->message("Asking for data for channel $name");
            if(false!==($data=getFileFromRT($rtid,$log))){
                $log->message("Data retrieved ok. Processing ...");
                processRTData($c,$data,$log);
                $log->message("Processing completed for channel $name");
            }else{
                $log->message("Failed to retrieve data for channel $name");
            }
        }
    }
}
function processRTData($c,$data,$log)
{
    $fp=false;
    $now=mktime();
    if(defined("ERRLOG")){
        $fp=fopen(ERRLOG,"a");
    }else{
        $log->message("processRTData: unable to open an error log file, ERRLOG is not defined");
    }
    $failed=0;
    $total=0;
    $pname=array("title","subtitle","episodetitle","year","junk","cast","junk","film","junk","junk","junk","newseries","junk","junk","starrating","junk","genre","description","choice","date","start","stop","channel","programid","seriesid","record","mux");
    if(is_array($data) && count($data) && is_object($c) && get_class($c)=="Channel" && is_object($log) && get_class($log)=="Logging"){
        $mux=$c->getData("mux");
        $channel=$c->getData("id");
        $cname=$c->getData("name");
        foreach($data as $datline){
            $tmp=explode("~",$datline);
            $cn=count($tmp);
            if($cn>22){
                for($x=0;$x<23;$x++){
                    if($pname[$x]!=="junk"){
                        $parr[$pname[$x]]=trim($tmp[$x]);
                    }
                }
                $parr["mux"]=$mux;
                $parr["channel"]=$channel;
                $parr["start"]=rtDtToTs($parr["date"],$parr["start"]);
                if($parr["start"]>$now){
                    $parr["stop"]=rtDtToTs($parr["date"],$parr["stop"]);
                    $parr["film"]=$parr["film"]=="true"?"y":"n";
                    $parr["newseries"]=$parr["newseries"]=="true"?"y":"n";
                    $parr["choice"]=$parr["choice"]=="true"?"y":"n";
                    $cast=$parr["cast"];
                    unset($parr["cast"]);
                    unset($parr["date"]);
                    $p=new Program(0,$log);
                    if(false!==($res=$p->updateWithRTData($parr))){
                        if(is_array($res) && isset($res["result"])){
                            if(isset($res["overlaps"])){
                                /*
                                 * uh oh, title mismatch
                                 */
                                $err=date("D d H:i") . " Title mismatch\nRTDATA\n";
                                foreach($parr as $key=>$data){
                                    $err.="$key:$data\n";
                                }
                                $err.="cast:$cast\n";
                                $err.="DBDATA\n";
                                foreach($res["overlaps"][0] as $key=>$data){
                                    $err.="$key:$data\n";
                                }
                                $err.=$parr["start"] . ":" . date("D d H:i",$parr["start"]) . "\n";
                                $err.=$parr["stop"] . ":" . date("D d H:i",$parr["stop"]) . "\n";
                                $err.=$res["overlaps"][0]["start"] . ":" . date("D d H:i",$res["overlaps"][0]["start"]) . "\n";
                                $err.=$res["overlaps"][0]["stop"] . ":" . date("D d H:i",$res["overlaps"][0]["stop"]) . "\n";
                                $err.="Channel:$cname\n";
                                $err.="\n";
                                if($fp){
                                    fwrite($fp,$err);
                                }
                                $failed++;
                            }else{
                                rtMakeCast($p,$cast,$log);
                                $total++;
                            }
                        }else{
                            $failed++;
                        }
                    }else{
                        $failed++;
                    }
                }else{
                    $failed++;
                }
                /*
                if(false!==($cnt=$p->removeOverlaps($parr))){
                    $replaced+=$cnt;
                    $total++;
                }
                 */
                $p=null;
            }
        }
        $log->message("$total programs inserted, $failed programs failed.");
    }
    if($fp){
        fclose($fp);
    }
}
function rtMakeCast($p,$cast,$log)
{
    $pid=intval($p->data_id);
    $title=$p->getData("title");
    if($pid && is_string($cast) && strlen($cast)){
        $ctmp=explode("|",$cast);
        if(is_array($ctmp)){
            $cn=count($ctmp);
            if($cn){
                for($x=0;$x<$cn;$x++){
                    $pos=strpos($ctmp[$x],"*");
                    if($pos){
                        $tmp=explode("*",$ctmp[$x]);
                        $name=trim($tmp[1]);
                    }else{
                        $name=trim($ctmp[$x]);
                    }
                    $pos=strpos($name,",");
                    if($pos){
                        $tmp=explode(",",$name);
                        $tn=count($tmp);
                        if($tn){
                            foreach($tmp as $nn){
                                $a=new Actor(trim($nn),$log);
                                if($a->addPid($pid)){
                                    $log->message("rtMakeCast::comma:Tied $nn to $title",LOG_DEBUG);
                                }else{
                                    $log->message("rtMakeCast::comma:Failed to add $nn to $title",LOG_DEBUG);
                                }
                                $a=null;
                            }
                        }
                    }else{
                        $a=new Actor($name,$log);
                        if($a->addPid($pid)){
                            $log->message("rtMakeCast::bar:Tied $name to $title",LOG_DEBUG);
                        }else{
                            $log->message("rtMakeCast::bar:Failed to add $name to $title",LOG_DEBUG);
                        }
                        $a=null;
                    }
                }
            }
        }
    }
}
function rtDateToDateString($dstr)
{
    $months=array("junk","January","February","March","April","May","June","July","August","September","October","November","December");
    $tmp=explode("/",$dstr);
    if(is_array($tmp) && ($cn=count($tmp))==3){
        $month=$months[intval(trim($tmp[1]))];
        $day=trim($tmp[0]);
        $year=trim($tmp[2]);
        return "$day $month $year";
    }
    return false;
}
function rtDtToTs($dt,$tm)
{
    $d=rtDateToDateString($dt);
    // print "$dt\n";
    // print "$d\n";
    // print "$tm\n";
    $tmp=strtotime("$d $tm");
    // print "$tmp\n";
    // print date("D d H:i",$tmp);
    // print "\n";
    return $tmp;
}
function rtProcessProgramToArray($c,$datline)
{
    $parr=false;
    $pname=array("title","subtitle","episodetitle","year","junk","cast","junk","film","junk","junk","junk","newseries","junk","junk","starrating","junk","genre","description","choice","date","start","stop","channel","programid","seriesid","record","mux");
    $tmp=explode("~",$datline);
    $cn=count($tmp);
    if($cn>22){
        $parr=array();
        for($x=0;$x<23;$x++){
            if($pname[$x]!=="junk"){
                $parr[$pname[$x]]=trim($tmp[$x]);
            }
        }
        $parr["mux"]=$c->getData("mux");
        $parr["channel"]=$c->getData("id");
        $parr["start"]=rtDtToTs($parr["date"],$parr["start"]);
        $parr["stop"]=rtDtToTs($parr["date"],$parr["stop"]);
        $parr["film"]=$parr["film"]=="true"?"y":"n";
        $parr["newseries"]=$parr["newseries"]=="true"?"y":"n";
        $parr["choice"]=$parr["choice"]=="true"?"y":"n";
    }
    return $parr;
}
function rtProcessChannel($c,$data,$log)
{
    $carr=false;
    if(is_array($data) && count($data) && is_object($c) && get_class($c)=="Channel" && is_object($log) && get_class($log)=="Logging"){
        $carr=array();
        foreach($data as $datline){
            if(false!==($parr=rtProcessProgramToArray($c,$datline)) && count($parr)){
                $carr[$parr["start"]]=$parr;
            }
        }
    }
    return $carr;
}
function rtProcessData($log)
{
    $progarr=false;
    $c=new Channel();
    $rtarr=$c->rtChannels();
    if(is_array($rtarr) && count($rtarr)){
        foreach($rtarr as $rtc){
            $progarr=array();
            $c=new Channel($rtc["id"]);
            $rtid=$c->getData("rtid");
            $name=$c->getData("name");
            $log->message("Asking for RT data for channel $name");
            if(false!==($data=getFileFromRT($rtid,$log))){
                $log->message("RT Data retrieved ok. Decoding ...");
                if(false!==($pa=rtProcessChannel($c,$data,$log)) && count($pa)){
                    $progarr[$rtc["id"]]=$pa;
                    $log->message("RT Decoding completed for channel $name");
                }else{
                    $log->message("Failed to decode RT data for channel $name");
                }
            }else{
                $log->message("Failed to retrieve RT data for channel $name");
            }
        }
    }
    return $progarr;
}
function parseXML2($gettingchannels=false,$log)
{
    // global $filesize,$inserted,$discarded,$exit;
    $ret=false;
    $channelprograms=false;
    $unfoundchunks=0;
    $maxchunks=10;
    $lastinserted=0;
    if(checkFile(XMLTVFILE,CCA_FILE_EXIST)){
        $delimstart="<programme";
        $delimend="</programme>";
        $data="";
        if($gettingchannels){
            $delimstart="<channel";
            $delimend="</channel>";
            $chans=array();
        }else{
            $chans=getChannelDataFromDB();
            $ret=$chans;
        }
        // $filesize=filesize(XMLTVFILE);
        if($fp=fopen(XMLTVFILE,"r")){
            $channelprograms=array();
            if(readMoreData($fp,$data)){
                do{
                    // if($exit){
                        // break;
                    // }
                    $tarr=textBetween($data,$delimstart,$delimend);
                    if($tarr["start"]){
                        if($tarr["end"]){
                            $unfoundchunks=0;
                            if($gettingchannels){
                                $chans=processChannelData($tarr["text"],$chans,$log);
                            }else{
                                // decodeXMLData($tarr["text"],$chans);
                                if(false!==($parr=parseProgramData($data,$chans)) && count($parr)){
                                    $channelprograms[$parr["channel"]][$parr["start"]]=$parr;
                                }
                                // $tmp=$lastinserted+1000;
                                // if($inserted>$tmp){
                                    // $lastinserted=$inserted;
                                    // $log->message("inserted $inserted, discarded $discarded",LOG_DEBUG);
                                // }
                            }
                            $data=$tarr["outside"];
                        }else{
                            if(readMoreData($fp,$data)){
                                $unfoundchunks++;
                                if($unfoundchunks>$maxchunks){
                                    $log->message("Unable to find $delimend, read " . strlen($data) . " bytes from $delimstart.");
                                    $log->message("exiting.");
                                    break;
                                }
                            }
                        }
                    }else{
                        $unfoundchunks++;
                        if($unfoundchunks>$maxchunks){
                            $log->message("Unable to find $delimend, read " . strlen($data) . " bytes from $delimstart.");
                            $log->message("exiting.");
                            if($gettingchannels){
                                $ret=$chans;
                            }
                            break;
                        }
                        if(!readMoreData($fp,$data)){
                            if($gettingchannels){
                                $ret=$chans;
                            }
                            $log->message("Cannot find $delimstart, End of file. No more data.");
                            break;
                        }
                    }
                }while(1);
            }
            fclose($fp);
        }else{
            $log->message("Error opening " . XMLTVFILE . " for reading.");
        }
    }else{
        $log->message(XMLTVFILE . " does not exist, exiting.");
    }
    // return $ret;
    return $channelprograms;
}
function listingsUpdate2($log=false)
{
    $log->message("Listings update starting.");
    $replaced=0;
    $total=0;
    if(false!==($rtarr=rtProcessData($log)) && count($rtarr)){
        if(false!==($xarr=parseXML2(false,$log)) && count($xarr)){
            foreach($xarr as $cid=>$xc){
                foreach($xc as $stm=>$pg){
                    if(isset($rtarr[$cid]) && isset($rtarr[$cid][$stm])){
                        $rtarr[$cid][$stm]["programid"]=$pg["programid"];
                        $rtarr[$cid][$stm]["seriesid"]=$pg["seriesid"];
                    }else{
                        $rtarr[$cid][$stm]=$pg;
                    }
                }
            }
        }
    }
    $xarr=null;
    $cid=null;
    $xc=null;
    $stm=null;
    $pg=null;
    if(is_array($rtarr) && count($rtarr)){
        foreach($rtarr as $cid=>$rtp){
            foreach($rtp as $stm=>$parr){
                if(isset($parr["cast"])){
                    $cast=$parr["cast"];
                    unset($parr["cast"]);
                }else{
                    $cast=false;
                }
                if(isset($parr["date"])){
                    unset($parr["date"]);
                }
                $p=new Program(0,$log);
                if(false!==($cnt=$p->removeOverlaps($parr))){
                    $replaced+=$cnt;
                    if(0==($replaced%1000) && $replaced){
                        $log->message("replaced $replaced programs.");
                    }
                    $total++;
                    if(0==($total%1000) && $total){
                        $log->message("inserted/updated $total programs.");
                    }
                }
                if(false!==$cast){
                    rtMakeCast($p,$cast,$log);
                }
                $p=null;
            }
        }
    }
    $log->message("Finished updating listings.");
    $log->message("replaced $replaced programs.");
    $log->message("inserted/updated $total programs.");
    $log->message("Listings update completed.");
}
?>
