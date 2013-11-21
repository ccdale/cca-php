<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Friday 17 June 2011, 12:51:58
 * Last Modified: Friday 17 June 2011, 15:16:04
 * Version: $Id: lame.class.php 661 2011-06-17 14:16:26Z chris $
 */

class Lame extends Extern
{
    private $outputfile="";
    private $audiofile="";
    private $config=false;
    private $title="";
    private $comment="";
    private $author="";
    private $album="";
    private $genre="";
    private $year="";
    private $tracknum="";
    private $quality="2";
    private $bitrate="128";
    private $mp2input=true;

    public function __construct($log,$config,$audiofile,$outputfile)
    {
        parent::__construct("",$log,array("lame"=>false));
        $this->config=$config;
        $this->setAudiofile($audiofile);
        $this->setOutputfile($outputfile);
        $this->setBitrate("128");
        $this->setQuality("2");
        $this->setMp2Input();
        if($this->progs["lame"]!==false){
            $this->logg("__construct(): Lame binary found ok",LOG_DEBUG);
            return true;
        }
        $this->logg("__construct(): Lame binary not found",LOG_WARNING);
        return false;
    }
    public function __destruct()
    {
        $this->logg("__destruct(): Lame",LOG_DEBUG);
        parent::__destruct();
    }
    public function getAudiofile()
    {
        return $this->audiofile;
    }
    public function setAudiofile($audiofile="")
    {
        if($this->checkString($audiofile)){
            $this->audiofile=$audiofile;
        }
    }
    public function getOutputfile()
    {
        return $this->outputfile;
    }
    public function setOutputfile($outputfile="")
    {
        if($this->checkString($outputfile)){
            $this->outputfile=$outputfile;
        }
    }
    public function getTitle()
    {
        return $this->title;
    }
    public function setTitle($title="")
    {
        if($this->checkString($title)){
            $this->title=$this->sanitiseString($title);
            $this->logg("Title set to $title",LOG_DEBUG);
        }
    }
    public function getComment()
    {
        return $this->comment;
    }
    public function setComment($comment="")
    {
        if($this->checkString($comment)){
            $this->comment=$this->sanitiseString($comment);
            $this->logg("Comment set to $comment",LOG_DEBUG);
        }
    }
    public function getAuthor()
    {
        return $this->author;
    }
    public function setAuthor($author="")
    {
        if($this->checkString($author)){
            $this->author=$this->sanitiseString($author);
            $this->logg("Author set to $author",LOG_DEBUG);
        }
    }
    public function getAlbum()
    {
        return $this->album;
    }
    public function setAlbum($album="")
    {
        if($this->checkString($album)){
            $this->album=$this->sanitiseString($album);
            $this->logg("Album set to $album",LOG_DEBUG);
        }
    }
    public function getTrackNumber()
    {
        return $this->trackNumber;
    }
    public function setTrackNumber($trackNumber="")
    {
        if($this->checkString($trackNumber)){
            $this->trackNumber=$this->sanitiseString($trackNumber);
            $this->logg("TrackNumber set to $trackNumber",LOG_DEBUG);
        }
    }
    public function getGenre()
    {
        return $this->genre;
    }
    public function setGenre($genre="")
    {
        if($this->checkString($genre)){
            $this->genre=$this->sanitiseString($genre);
            $this->logg("Genre set to $genre",LOG_DEBUG);
        }
    }
    public function getYear()
    {
        return $this->year;
    }
    public function setYear($year="")
    {
        if($this->checkString($year)){
            $this->year=$this->sanitiseString($year);
            $this->logg("Year set to $year",LOG_DEBUG);
        }
    }
    public function getQuality()
    {
        return $this->quality;
    }
    public function setQuality($quality="")
    {
        if($this->checkString($quality)){
            $this->quality=$this->sanitiseString($quality);
            $this->logg("Quality set to $quality",LOG_DEBUG);
        }
    }
    public function getBitrate()
    {
        return $this->bitrate;
    }
    public function setBitrate($bitrate="")
    {
        if($this->checkString($bitrate)){
            $this->bitrate=$this->sanitiseString($bitrate);
            $this->logg("Bitrate set to $bitrate",LOG_DEBUG);
        }
    }
    public function getMp2Input()
    {
        return $this->mp2input;
    }
    public function setMp2Input()
    {
        $this->mp2input=true;
    }
    public function unsetMp2Input()
    {
        $this->mp2input=false;
    }
    public function doLame()
    {
        $opts=$this->getOpts();
        $cmd=$this->progs["lame"] . " $opts " . $this->audiofile . " " . $this->outputfile;
        return $this->Run($cmd);
    }
    private function getOpts()
    {
        $opts="-S -b " . $this->bitrate . " -q " . $this->quality;
        if($this->mp2input){
            if($this->checkString($opts)){
                $opts.=" --mp2input";
            }else{
                $opts="--mp2input";
            }
        }
        if($this->checkString($this->title)){
            if($this->checkString($opts)){
                $opts.=" --tt \"" . $this->title . "\"";
            }else{
                $opts="--tt \"" . $this->title . "\"";
            }
        }
        if($this->checkString($this->author)){
            if($this->checkString($opts)){
                $opts.=" --ta \"" . $this->author . "\"";
            }else{
                $opts="--ta \"" . $this->author . "\"";
            }
        }
        if($this->checkString($this->album)){
            if($this->checkString($opts)){
                $opts.=" --tl \"" . $this->album . "\"";
            }else{
                $opts="--tl \"" . $this->album . "\"";
            }
        }
        if($this->checkString($this->year)){
            if($this->checkString($opts)){
                $opts.=" --ty \"" . $this->year . "\"";
            }else{
                $opts="--ty \"" . $this->year . "\"";
            }
        }
        if($this->checkString($this->comment)){
            if($this->checkString($opts)){
                $opts.=" --tc \"" . $this->comment . "\"";
            }else{
                $opts="--tc \"" . $this->comment . "\"";
            }
        }
        if($this->checkString($this->tracknum)){
            if($this->checkString($opts)){
                $opts.=" --tn \"" . $this->tracknum . "\"";
            }else{
                $opts="--tn \"" . $this->tracknum . "\"";
            }
        }
        if($this->checkString($this->genre)){
            if($this->checkString($opts)){
                $opts.=" --tg \"" . $this->genre . "\"";
            }else{
                $opts="--tg \"" . $this->genre . "\"";
            }
        }
        return $opts;
    }
    private function sanitiseString($str)
    {
        return str_replace('"',"'",$str);
    }
}
?>
