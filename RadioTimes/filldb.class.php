#!/usr/bin/env php
<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * filldb.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Saturday 31 May 2014, 07:15:52
 * Last Modified: Saturday 31 May 2014, 10:06:33
 * Revision: $Id$
 * Version: 0.00
 */

/*
CREATE TABLE `channel` (
`rtid` int(11) NOT NULL,
`freeviewid` int(11) NOT NULL,
`channelname` varchar(50) NOT NULL,
`favourite` tinyint(1) DEFAULT '0',
`getdata` tinyint(1) DEFAULT '0',
`favgroup` tinyint(2) DEFAULT '0',
`sortorder` int(8) DEFAULT '0',
`hasplusone` int(11) DEFAULT '0',
PRIMARY KEY (`rtid`),
KEY `channelname` (`channelname`),
KEY `freeviewid` (`freeviewid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8

CREATE TABLE `schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `channel` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `episode` varchar(255) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `director` int(11) DEFAULT NULL,
  `premiere` tinyint(1) DEFAULT NULL,
  `film` tinyint(1) DEFAULT NULL,
  `isrepeat` tinyint(1) DEFAULT NULL,
  `subtitles` tinyint(1) DEFAULT NULL,
  `widescreen` tinyint(1) DEFAULT NULL,
  `newseries` tinyint(1) DEFAULT NULL,
  `deafsigned` tinyint(1) DEFAULT NULL,
  `blackandWhite` tinyint(1) DEFAULT NULL,
  `star` tinyint(4) DEFAULT NULL,
  `certificate` tinyint(4) DEFAULT NULL,
  `description` text,
  `genre` int(11) DEFAULT NULL,
  `choice` tinyint(1) DEFAULT NULL,
  `starttime` int(11) NOT NULL,
  `endtime` int(11) NOT NULL,
  `duration` int(11) NOT NULL,
  `programid` varchar(128) DEFAULT NULL,
  `seriesid` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `title` (`title`),
  KEY `description` (`description`(255)),
  KEY `channel` (`channel`),
  CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`channel`) REFERENCES `channel` (`rtid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8

CREATE TABLE `actor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actorname` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8

CREATE TABLE `actormap` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actorid` int(11) NOT NULL,
  `scheduleid` int(11) NOT NULL,
  `chr` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `actorid` (`actorid`),
  KEY `scheduleid` (`scheduleid`),
  CONSTRAINT `actormap_ibfk_1` FOREIGN KEY (`actorid`) REFERENCES `actor` (`id`),
  CONSTRAINT `actormap_ibfk_2` FOREIGN KEY (`scheduleid`) REFERENCES `schedule` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
 */

require_once "base.class.php";

class FillDB extends Base
{
    private $mx=false;
    private $rt=false;
    private $fields;

    public function __construct($logg=false,$mx=false,$rt=false)/*{{{*/
    {
        parent::__construct($logg);
        $this->mx=$mx;
        $this->rt=$rt;
        $this->fields=array(
            array("title"=>"string"),
            array("subtitle"=>"string"),
            array("episode"=>"string"),
            array("year"=>"int"),
            array("director"=>"string"),
            array("cast"=>"array"),
            array("premiere"=>"bool"),
            array("film"=>"bool"),
            array("repeat"=>"bool"),
            array("subtitles"=>"bool"),
            array("widescreen"=>"bool"),
            array("newseries"=>"bool"),
            array("deafsigned"=>"bool"),
            array("blackandwhite"=>"bool"),
            array("filmstarrating"=>"int"),
            array("filmcertificate"=>"string"),
            array("genre"=>"string"),
            array("description"=>"string"),
            array("choice"=>"bool"),
            array("date"=>"string"),
            array("starttime"=>"int"),
            array("endtime"=>"int"),
            array("duration"=>"int")
        );
        $this->fillData();
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    public function fillData()/*{{{*/
    {
        if($this->mx && $this->rt){
            if(false!==($channels=$this->mx->arrayQuery("select * from channel where getdata=1"))){
                if(false!==($cn=$this->ValidArray($channels))){
                    $this->debug("$cn channels to get data for");
                    foreach($channels as $channel){
                        $this->info("Retrieving data for " . $channel["channelname"]);
                        if(false!==($progs=$this->rt->getChannelData($channel["rtid"]))){
                            $this->info("data translated");
                            if(false!==($cn=$this->ValidArray($progs))){
                                $this->debug("$cn programs to insert into db for " . $channel["channelname"]);
                                $this->fillChannelData($channel["rtid"],$progs);
                            }else{
                                $this->warning("no programs to insert into db for " . $channel["channelname"]);
                            }
                        }else{
                            $this->warning("no data received from radiotimes for " . $channel["channelname"]);
                        }
                    }
                }else{
                    $this->warning("No channel data retrieved from db");
                }
            }else{
                $this->error("Error getting channel data from db");
            }
        }else{
            $this->error("invalid class setup");
        }
    }/*}}}*/
    public function fillChannelData($cid=0,$progs=false)/*{{{*/
    {
        $this->debug("Clearing actormap table for channel $cid");
        $rows=$this->mx->deleteQuery("delete from actormap where scheduleid in (select id from schedule where channel=$cid)");
        $this->debug("$rows rows deleted");
        $this->debug("Clearing schedule table for channelid $cid");
        $rows=$this->mx->deleteQuery("delete from schedule where channel=$cid");
        $this->debug("$rows rows deleted");
        foreach($progs as $prog){
            $genreid=$this->getGenreId($prog["genre"]);
            $did=$this->getActorId($prog["director"]);
            $sql="insert into schedule set ";
            $sql.="channel=$cid,";
            $sql.="title='" . $this->mx->escape($prog["title"]) . "',";
            if(false!==($cn=$this->ValidStr($prog["subtitle"])) && $cn>0){
                $sql.="subtitle='" . $this->mx->escape($prog["subtitle"]) . "',";
            }
            if(false!==($cn=$this->ValidStr($prog["episode"])) && $cn>0){
                $sql.="episode='" . $this->mx->escape($prog["episode"]) . "',";
            }
            if(false!==($cn=$this->ValidStr($prog["year"])) && $cn>0){
                $year=intval($prog["year"]);
                if($year>0){
                    $sql.="year=$year,";
                }
            }
            $sql.="director=$did,";
            $tmp=$prog["film"]=="true"?1:0;
            $sql.="film=$tmp,";
            $tmp=$prog["repeat"]=="true"?1:0;
            $sql.="isrepeat=$tmp,";
            $tmp=$prog["subtitles"]=="true"?1:0;
            $sql.="subtitles=$tmp,";
            $tmp=$prog["widescreen"]=="true"?1:0;
            $sql.="widescreen=$tmp,";
            $tmp=$prog["newseries"]=="true"?1:0;
            $sql.="newseries=$tmp,";
            $tmp=$prog["deafsigned"]=="true"?1:0;
            $sql.="deafsigned=$tmp,";
            $tmp=$prog["blackandwhite"]=="true"?1:0;
            $sql.="blackandWhite=$tmp,";
            $tmp=strlen($prog["filmstarrating"])?$prog["filmstarrating"]:0;
            $sql.="star=$tmp,";
            $tmp=strlen($prog["filmcertificate"])?$prog["filmcertificate"]:0;
            $sql.="certificate=$tmp,";
            $sql.="description='" . $this->mx->escape($prog["description"]) . "',";
            $sql.="genre=$genreid,";
            $tmp=$prog["choice"]=="true"?1:0;
            $sql.="choice=$tmp,";
            $sql.="starttime=" . $prog["starttime"] . ",";
            $sql.="endtime=" . $prog["endtime"] . ",";
            $sql.="duration=" . $prog["duration"];
            if(false!==($iid=$this->mx->insertQuery($sql))){
                $this->debug("program inserted ok");
                $sql="";
                $actors="";
                if(false!==($cn=$this->ValidArray($prog["cast"]))){
                    $sql="insert into actormap (actorid,scheduleid,chr) values ";
                    foreach($prog["cast"] as $chr=>$actor){
                        $tchr=$this->mx->escape($chr);
                        if(false===($cn=$this->ValidArray($actor))){
                            $aid=$this->getActorId($actor);
                            if(strlen($actors)){
                                $actors.=",($aid,$iid,'$tchr')";
                            }else{
                                $actors="($aid,$iid,'$tchr')";
                            }
                        }else{
                            foreach($actor as $act){
                                $aid=$this->getActorId($act);
                                if(strlen($actors)){
                                    $actors.=",($aid,$iid,'$tchr')";
                                }else{
                                    $actors="($aid,$iid,'$tchr')";
                                }
                            }
                        }
                    }
                    if(strlen($actors)){
                        $sql.=$actors;
                        if(false!==($junk=$this->mx->insertQuery($sql))){
                            $this->debug("actors inserted ok");
                        }else{
                            $this->warning("failed to insert actors");
                            $this->debug($sql);
                        }
                    }
                }
            }else{
                $this->warning("Failed to insert program " . $prog["title"]);
                $tmp=print_r($prog,true);
                $this->debug($tmp);
            }
        }
    }/*}}}*/
    private function getGenreId($genre="")/*{{{*/
    {
        return $this->getStringId("genre",$genre);
    }/*}}}*/
    private function getActorId($actor="")/*{{{*/
    {
        return $this->getStringId("actor",$actor);
    }/*}}}*/
    private function getStringId($type="actor",$val="")/*{{{*/
    {
        $ret=false;
        $table=$type;
        $field=$type . "name";
        if(false!==$this->mx){
            if(false!==($cn=$this->ValidStr($val))){
                $tval=$this->mx->escape($val);
                $arr=$this->mx->arrayQuery("select id from $table where $field='$tval'");
                if(false!==($cn=$this->ValidArray($arr))){
                    $ret=$arr[0]["id"];
                }else{
                    $ret=$this->mx->insertQuery("insert into $table set $field='$tval'");
                }
            }
        }
        return $ret;
    }/*}}}*/
}
?>
