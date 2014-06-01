<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * radiotimes.class.php
 *
 * Started: Saturday 21 December 2013, 03:11:57
 * Last Modified: Saturday 31 May 2014, 10:00:26
 * Revision: $Id$
 * Version: 0.00
 */
/*
 * see http://www.birtles.org.uk/phpbb3/viewtopic.php?f=5&t=245#p943
 * for details of fields
 *
 * Each channel file (identified by it's ID) called CHANNEL_ID.dat (e.g. 24.dat) contains 14 days of listings for that channel. The fields are separated by a tilda "~", and ended by a new line. The fields that are supplied are :

* Programme Title
* Sub-Title
* Episode
* Year
* Director
* Performers (Cast) - This will be either a string containing the Actors names or be made up of Character name and Actor name pairs which are separated by an asterix "*" and each pair by pipe "|"
e.g. Rocky*Sylvester Stallone|Terminator*Arnold Schwarzenegger.
* Premiere
* Film
* Repear
* Subtitles
* Widescreen
* New series
* Deaf signed
* Black and White
* Film star rating
* Film certificate
* Genre
* Description
* Radio Times Choice - This means that the Radio Times editorial team have marked it as a choice
* Date
* Start Time
* End Time
* Duration (Minutes)
 */

require_once "base.class.php";
require_once "Shell/cache.class.php";

class RadioTimes extends Base
{
    private $cache;
    private $cachetime=86400;
    private $rturl="http://xmltv.radiotimes.com/xmltv/";
    private $fields;

    public function __construct($logg=false)/*{{{*/
    {
        parent::__construct($logg);
        $this->cache=new Cache($logg,"radiotimes");
        $this->fields=array(
            "title",
            "subtitle",
            "episode",
            "year",
            "director",
            "cast",
            "premiere",
            "film",
            "repeat",
            "subtitles",
            "widescreen",
            "newseries",
            "deafsigned",
            "blackandwhite",
            "filmstarrating",
            "filmcertificate",
            "genre",
            "description",
            "choice",
            "date",
            "starttime",
            "endtime",
            "duration"
        );
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    public function getChannelData($cid=0)/*{{{*/
    {
        $programs=false;
        if($cid>0){
            $cfn=$cid . ".psa";
            if(false===($atime=$this->cache->getCacheFileTime($cfn))){
                $atime=0;
            }
            $now=time();
            $then=$atime+$this->cachetime;
            if($then>$now){
                // read data from cache
                $this->info("using cache for RT channel id $cid");
                $programs=$this->cache->getCacheFile($cfn);
            }else{
                // cache is too old, so get new data
                $url=$this->rturl . $cid . ".dat";
                $this->info("Cache is invalid, retrieving $url");
                $data=file($url);
                $this->info("data received");
                $programs=$this->processRTData($data);
                $this->cache->setCacheFile($cfn,$programs);
            }
        }else{
            $this->error("Invalid channel id: $cid");
        }
        return $programs;
    }/*}}}*/
    private function processRTdata($data)/*{{{*/
    {
        $programs=false;
        if(false!==($cn=$this->ValidArray($data))){
            $programs=array();
            foreach($data as $rtline){
                $tmp=explode("~",$rtline);
                if(false!=($cn=$this->ValidArray($tmp))){
                    if($cn==23){
                        $parr=array();
                        for($x=0;$x<$cn;$x++){
                            if($x==5){
                                $parr["cast"]=$this->processCast($tmp[$x]);
                            }else{
                                $parr[$this->fields[$x]]=trim($tmp[$x]);
                            }
                        }
                        if(false!==($dmarr=$this->processRTDate($parr["date"]))){
                            if(false!==($starttime=$this->processRTTime($dmarr,$parr["starttime"]))){
                                $parr["starttime"]=$starttime;
                                $parr["endtime"]=$starttime+($parr["duration"]*60);
                            }
                            // if(false!==($endtime=$this->processRTTime($dmarr,$parr["endtime"]))){
                                // $parr["endtime"]=$endtime;
                            // }
                        }
                        $programs[]=$parr;
                    }
                }
            }
        }
        return $programs;
    }/*}}}*/
    private function processRTTime($dmarr,$tm)/*{{{*/
    {
        $time=false;
        if(false!==($cn=$this->ValidStr($tm))){
            if($cn>4){
                $tmp=explode(":",$tm);
                $hour=intval(trim($tmp[0]));
                $min=intval(trim($tmp[1]));
                $time=mktime($hour,$min,0,$dmarr["month"],$dmarr["day"],$dmarr["year"]);
            }
        }
        return $time;
    }/*}}}*/
    private function processRTDate($dm)/*{{{*/
    {
        $date=false;
        if(false!==($cn=$this->ValidStr($dm))){
            if($cn>9){
                $tmp=explode("/",$dm);
                $date=array();
                $date["day"]=intval(trim($tmp[0]));
                $date["month"]=intval(trim($tmp[1]));
                $date["year"]=intval(trim($tmp[2]));
            }
        }
        return $date;
    }/*}}}*/
    private function processCast($cast)/*{{{*/
    {
        $actors=false;
        if(false!==($cn=$this->ValidStr($cast))){
            if($cn>0){
                $actors=array();
                $tmp=explode("|",$cast);
                if(false!==($cn=$this->ValidArray($tmp))){
                    if($cn>1){
                        foreach($tmp as $chact){
                            $act=explode("*",$chact);
                            if(isset($act[1])){
                                $key=trim($act[0]);
                                if(isset($actors[$key])){
                                    if(!is_array($actors[$key])){
                                        $hold=$actors[$key];
                                        $actors[$key]=array($hold);
                                    }
                                    $actors[$key][]=trim($act[1]);
                                }else{
                                    $actors[trim($act[0])]=trim($act[1]);
                                }
                            }else{
                                $actors[]=trim($act[0]);
                            }
                        }
                    }else{
                        $chact=$tmp[0];
                        $act=explode("*",$chact);
                        if(isset($act[1])){
                            $key=trim($act[0]);
                            if(isset($actors[$key])){
                                if(!is_array($actors[$key])){
                                    $hold=$actors[$key];
                                    $actors[$key]=array($hold);
                                }
                                $actors[$key][]=trim($act[1]);
                            }else{
                                $actors[trim($act[0])]=trim($act[1]);
                            }
                        }else{
                            $actors[]=trim($act[0]);
                        }
                    }
                }
            }
        }
        return $actors;
    }/*}}}*/
}
?>
