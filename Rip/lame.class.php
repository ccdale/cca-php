<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Monday 11 April 2011, 17:33:01
 * Last Modified: Friday 22 April 2011, 05:52:17
 * Version: $Id: lame.class.php 538 2011-04-22 04:56:19Z chris $
 */

class Lame
{
    private $lame="/usr/bin/lame --silent --preset fast standard ";
    private $artist="";
    private $title="";
    private $album="";
    private $comment="";
    private $year=0;
    private $genre=0;
    private $disknumber=0;
    private $tracknumber=0;
    private $numtracks=0;
    private $tag="";
    private $wavfile="";
    private $mp3file="";
    private $bg=false;

    public function __construct($wavfile="",$mp3file="",$title="",$artist="",$album="",$disknumber=0,$tracknumber=0,$numtracks=0,$genre=101)
    {
        if(is_string($wavfile) && strlen($wavfile)){
            $this->wavfile=trim($wavfile);
        }
        if(is_string($mp3file) && strlen($mp3file)){
            $this->mp3file=trim($mp3file);
        }
        if(is_string($title) && strlen($title)){
            $this->title=trim($title);
        }
        if(is_string($artist) && strlen($artist)){
            $this->artist=trim($artist);
        }
        if(is_string($album) && strlen($album)){
            $this->album=trim($album);
        }
        $disknumber=intval($disknumber);
        if($disknumber){
            $this->disknumber=$disknumber;
        }
        $tracknumber=intval($tracknumber);
        if($tracknumber){
            $this->tracknumber=$tracknumber;
        }
        $numtracks=intval($numtracks);
        if($numtracks){
            $this->numtracks=$numtracks;
        }
        if(is_string($genre) && strlen($genre)){
            $this->genre=$genre;
        }else{
            $genre=intval($genre);
            if($genre){
                $this->genre=$genre;
            }
        }
    }
    public function __destruct()
    {
        if($this->isRunning()){
            $this->bg->stop();
        }
        $this->bg=null;
    }
    public function getArtist()
    {
        return $this->artist;
    }
    public function setArtist($artist="")
    {
        if(is_string($artist)){
            $this->artist=$artist;
        }
    } 
    public function getTitle()
    {
        return $this->title;
    }
    public function setTitle($title="")
    {
        if(is_string($title)){
            $this->title=$title;
        }
    }
    public function getAlbum()
    {
        return $this->album;
    }
    public function setAlbum($album="")
    {
        if(is_string($album)){
            $this->album=$album;
        }
    } 
    public function getDiskNumber()
    {
        return $this->diskNumber;
    }
    public function setDiskNumber($diskNumber)
    {
        $this->diskNumber=intval($diskNumber);
    } 
    public function getTrackNumber()
    {
        return $this->trackNumber;
    }
    public function setTrackNumber($trackNumber)
    {
        $this->trackNumber=intval($trackNumber);
    } 
    public function getNumTracks()
    {
        return $this->numTracks;
    }
    public function setNumTracks($numTracks)
    {
        $this->numTracks=intval($numTracks);
    } 
    public function getYear()
    {
        return $this->year;
    }
    public function setYear($year)
    {
        $this->year=intval($year);
    }
    public function getComment()
    {
        return $this->comment;
    }
    public function setComment($comment="")
    {
        if(is_string($comment)){
            $this->comment=$comment;
        }
    }
    public function getWavFile()
    {
        return $this->wavFile;
    }
    public function setWavFile($wavFile="")
    {
        if(is_string($wavFile)){
            $this->wavFile=$wavFile;
        }
    } 
    public function getMp3File()
    {
        return $this->mp3File;
    }
    public function setMp3File($mp3File="")
    {
        if(is_string($mp3File)){
            $this->mp3File=$mp3File;
        }
    } 
    private function addToTag($switch="",$str="")
    {
        if(is_string($str) && strlen($str)){
            if(is_string($switch) && strlen($switch)){
                if(strlen($this->tag)){
                    $this->tag.=" " . $switch . " '" . $str . "'";
                }else{
                    $this->tag=$switch . " '" . $str . "'";
                }
            }else{
                if(strlen($this->tag)){
                    $this->tag.=" " . $str;
                }else{
                    $this->tag=$str;
                }
            }
        }
    }
    private function buildTag()
    {
        $this->tag="";
        if(strlen($this->title)){
            $this->addToTag("--tt ",$this->title);
        }
        if(strlen($this->artist)){
            $this->addToTag("--ta ",$this->artist);
        }
        if(strlen($this->album)){
            $this->addToTag("--tl ",$this->album);
        }
        if(strlen($this->comment)){
            $this->addToTag("--tc ",$this->comment);
        }
        if($this->tracknumber){
            if($this->numtracks){
                $this->addToTag("--tn ",$this->tracknumber . "/" . $this->numtracks);
            }else{
                $this->addToTag("--tn ",$this->tracknumber);
            }
        }
        if(is_string($this->genre) && strlen($this->genre)){
            $this->addToTag("","--tg " . $this->genre);
        }else{
            if($this->genre){
                $this->addToTag("","--tg " . $this->genre);
            }
        }
    }
    public function isRunning()
    {
        if(false!==$this->bg){
            $tmp=$this->bg->isRunning();
            if(false===$tmp){
                $this->bg=false;
            }
            return $tmp;
        }
        return false;
    }
    public function encode()
    {
        if(false===$this->isRunning()){
            $this->buildTag();
            $cmdline=$this->lame . " " . $this->tag . " " . $this->wavfile . " " . $this->mp3file;
            // echo $cmdline . "\n";
            $this->bg=new BackgroundCommand($cmdline,10,"phpencoder");
            $this->bg->run(2); // wait 2 seconds for encoder startup
        }
        return $this->isRunning();
    }
}
?>
