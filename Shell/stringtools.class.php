<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * stringtools.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Monday 14 October 2013, 08:18:37
 * Last Modified: Monday 14 October 2013, 08:32:57
 * Revision: $Id$
 * Version: 0.00
 */

require_once "base.class.php";

class StringTools extends Base
{
    public function __construct($logg=false)/*{{{*/
    {
        parent::__construct($logg);
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    /** padString {{{
     * returns a string padded out with character $pad
     * 
     * character|string $pad is added to the left or right of 
     * $string it is >=$length
     * if $length is not given it defaults to 3
     * if $pad is not given it defaults to "0"
     * if $right is true then $string is padded on the right
     * else the left.
     * if $pad is not a string, or an empty string the the 
     * original $string is returned.
     *
     * @param string $string 
     * @param string $pad 
     * @param int $length 
     * @param bool $right 
     * @access public
     * @return string
     */
    public function padString($string,$pad="0",$length=3,$right=false)
    {
        $tmp=$string;
        if(is_string($pad) && strlen($pad)){
            while(strlen($tmp)<$length)
            {
                if($right)
                {
                    $tmp.=$pad;
                }else{
                    $tmp=$pad . $tmp;
                }
            }
        }
        return $tmp;
    } /*}}}*/
    /** stringToArray {{{
     * returns an array with each element being one character of the string $str, the first element being the length.
     *
     * if $str is empty a 1 element array is returned $op[0]==0;
     * 
     * @param string $str 
     * @access public
     * @return array
     */
    public function stringToArray($str="")
    {
        $op=array();
        $c=0;
        if(is_string($str)){
            for($i=0,$c=strlen($str);$i<$c;$i++)
            {
                $op[$i+1]=substr($str,$i,1);
            }
        }
        $op[0]=$c;
        return $op;
    } /*}}}*/
    /** arrayToString {{{
     * returns the empty string if $arr is not an array or an empty array
     * 
     * @param array $arr 
     * @access public
     * @return string
     */
    public function arrayToString($arr)
    {
        if(is_array($arr)){
            return implode($arr,"");
        }else{
            return "";
        }
    } /*}}}*/
    /** splitAtCapitals {{{
     * inserts spaces before any Capital letters in the string $str
     *
     * The 3 words "of","the","is" also have a space inserted before them
     *
     * @param string $str 
     * @access public
     * @return string
     */
    public function splitAtCapitals($str="")
    {
        $op="";
        $words=array("of","the","is");
        reset($words);
        while(list(,$v)=each($words)){
            $str=$this->insertSpaceBeforeWord($v,$str);
        }
        $zplus=ord("Z")+1;
        $lastsplit=0;
        if(is_string($str) && ($c=strlen($str))){
            for($x=0;$x<$c;$x++){
                $tmp=substr($str,$x,1);
                if($tmp!=" "){
                    $otmp=ord($tmp);
                    if($otmp<$zplus){
                        if(strlen($op)){
                            if($lastsplit<($x-1)){
                                $op.=" " . $tmp;
                                $lastsplit=$x;
                            }else{
                                $op.=$tmp;
                                $lastsplit=$x;
                            }
                        }else{
                            $op=$tmp;
                        }
                    }else{
                        $op.=$tmp;
                    }
                }else{
                    $op.=$tmp;
                }
            }
        }
        return $op;
    } /*}}}*/
    /** insertSpaceBeforeWord {{{
     * inserts a space before the word $word in string $str
     *
     * @param string $word 
     * @param string $str 
     * @access public
     * @return string
     */
    public function insertSpaceBeforeWord($word="",$str="")
    {
        $ret=$str;
        if(is_string($word) && strlen($word) && is_string($str) && strlen($str)){
            $ret=str_replace($word," " . $word,$str);
        }
        return $ret;
    } /*}}}*/
    /** checkTrimString {{{
     * trims white space from both ends of $string
     *
     * returns false if $string is not a string or the empty string
     * 
     * @param string $string 
     * @access public
     * @return bool|string
     */
    public function checkTrimString($string)
    {
        $ret=false;
        if(is_string($string)){
            if($tmp=trim($string)){
                $ret=$tmp;
            }
        }
        return $ret;
    } /*}}}*/
    /** textBetween {{{
     * extract the contents of $subject between two arbitrary sub-strings
     *
     * input: $subject is the string to search
     *        $start is the starting sub-string to search for
     *        $end is the ending sub-string to search for
     * it handles nested delimeters.
     * output: array["start"] - the starting position in $subject of sub-string $start
     *               "end" - ending position in $subject of sub-string $end
     *               "text" - the contents of $subject between $start and $end or $subject if $start is not found
     *               "between" - the contents of $subject between $start and $end or the empty string if $start is not found.
     *               "left" - the left part of $subject before $start
     *               "right" - the right part of $subject after $end
     *               "outside" - concatenated contents of "left" and "right"
     * 
     * @param string $subject 
     * @param string $start 
     * @param string $end 
     * @access public
     * @return array
     */
    public function textBetween($subject="",$start="",$end="")
    {
        $ret=array("start"=>false,"end"=>false,"text"=>$subject,"between"=>"","left"=>"","right"=>"","outside"=>"");
        if(is_string($ret["text"]) && is_string($start)){
            $subl=strlen($ret["text"]);
            $startl=strlen($start);
            if($subl && $startl){
                /* find the beginning delimiter */
                $ret["start"]=strpos($ret["text"],$start);
                if($ret["start"]!==false){
                    /* starting delimiter found */
                    $offset=$ret["start"]+$startl;
                    $ret["text"]=substr($ret["text"],$offset);
                    if(is_string($end)){
                        $endl=strlen($end);
                        if($endl){
                            $ret["end"]=strpos($ret["text"],$end);
                            if($ret["end"]!==false){
                                $checknested=true;
                                $tmppos=0;
                                while($checknested){
                                /* check for nested delimiters 
                                    search between the start point
                                and the currently found end point */
                                    $ttp=strpos(substr($ret["text"],$tmppos,$ret["end"]-$tmppos),$start);
                                    if($ttp!==false){
                                        $tmppos+=$ttp;
                                    /* we have nested delimiters 
                                        move the end point onto the next
                                    delimiter */
                                        $ret["end"]=strpos($ret["text"],$end,$ret["end"]+1);
                                        if($ret["end"]!==false){
                                            $tmppos++;
                                        }else{
                                            $checknested=false;
                                        }
                                    }else{
                                        /* there are no nested delimiters */
                                        $checknested=false;
                                        if($ret["end"]!==false){
                                            $ret["text"]=substr($ret["text"],0,$ret["end"]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if($ret["text"]!=$subject){
            $ret["between"]=$ret["text"];
        }
        if($ret["start"]!==false){
            if($ret["start"]>0){
                $ret["outside"]=substr($subject,0,$ret["start"]);
                $ret["left"]=$ret["outside"];
            }
            if($ret["end"]!==false && $ret["end"]>0){
                $ret["right"]=substr($subject,$ret["start"]+$ret["end"]+$startl+$endl);
                $ret["outside"].=$ret["right"];
            }
        }else{
            $ret["outside"]=$subject;
        }
        return $ret;
    } /*}}}*/
    /** textOutside {{{
     * removes the portion of $subject between the delimeters $start and $end including the delimeters
     *
     * if $end is not found returns $subject upto $start.
     * if $start is not found returns $subject
     * 
     * @param string $subject 
     * @param string $start 
     * @param string $end 
     * @access public
     * @return string
     */
    public function textOutside($subject="",$start="",$end="")
    {
        $arr=$this->textBetween($subject,$start,$end);
        return $arr["outside"];
    } /*}}}*/
    /** textInside {{{
     * returns the sub-string between the delimeters $start and $end within $subject
     * 
     * returns an empty string if $start is not found
     * returns upto the end of $subject if $end is not found
     *
     * @param string $subject 
     * @param string $start 
     * @param string $end 
     * @access public
     * @return string
     */
    public function textInside($subject="",$start="",$end="")
    {
        $arr=$this->textBetween($subject,$start,$end);
        return $arr["between"];
    } /*}}}*/
    /** iniToDefine {{{
     * creates a set of php define statements from an ini file
     * 
     * checks to see if the name is already defined, if not it issues a define statement
     * returns an array of defines issued.
     *
     * @param string $fn 
     * @param string $delim
     * @access public
     * @return array
     */
    public function iniToDefine($fn,$delim="=")
    {
        $defa=false;
        $ft=new FileTools();
        if(false!==($arr=$ft->getFile($fn,CCA_FILE_ASARRAY))){
            $defa=array();
            foreach($arr as $line){
                if($line=$this->checkTrimString($line) && (substr($line,0,1)!="#") && (false!==($pos=strpos($line,$delim)))){
                    $tmp=explode($delim,$line,2);
                    if($defn=$this->checkTrimString($tmp[0])){
                        $defv=trim($tmp[1]);
                        if(!defined($defn)){
                            define($defn,$defv);
                            $defa[]=array("defn"=>$defn,"defv"=>$defv);
                        }
                    }
                }
            }
        }
        return $defa;
    } /*}}}*/
    /** defineToIni {{{
     * writes out an ini file containing the define statements from the php file $deffn
     * 
     * @param string $deffn 
     * @param string $inifn 
     * @param bool $append 
     * @access public
     * @return bool
     */
    public function defineToIni($deffn,$inifn,$append=false)
    {
        $ret=false;
        $ft=new FileTools();
        if(false!==($arr=$ft->getFile($deffn,CCA_FILE_ASARRAY))){
            $inia=array();
            foreach($arr as $line){
                if(strlen($line) && (false!==($pos=stripos($line,"define")))){
                    $ta=$this->textBetween($line,"(",")");
                    if(false!==$ta["start"] && $ta["text"]!=$line){
                        $tmp=explode(",",$ta["text"]);
                        $defn=str_replace('"','',$tmp[0]);
                        $defv=str_replace('"','',$tmp[1]);
                        $inia[]=$defn . " = " . $defv;
                    }
                }
            }
            if(count($inia)){
                $tmp="";
                foreach($inia as $ini){
                    $tmp.=$ini . "\n";
                }
                $mode="w";
                if($append){
                    $mode="a";
                }
                if(false!==($fp=fopen($inifn,$mode))){
                    fwrite($fp,$tmp);
                    fclose($fp);
                    $ret=true;
                }
            }
        }
        return $ret;
    } /*}}}*/
}

?>
