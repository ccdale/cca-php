<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * cli.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Sunday  6 October 2013, 11:21:55
 * Last Modified: Sunday 20 October 2013, 16:12:21
 * Revision: $Id$
 * Version: 0.00
 */

// colour defines {{{1
define("CCA_CLS","[H[2J");

define("CCA_Black","0;30");
define("CCA_DGray","1;30");
define("CCA_Blue","0;34");
define("CCA_LBlue","1;34");
define("CCA_Green","0;32");
define("CCA_LGreen","1;32");
define("CCA_Cyan","0;36");
define("CCA_LCyan","1;36");
define("CCA_Red","0;31");
define("CCA_LRed","1;31");
define("CCA_Purple","0;35");
define("CCA_LPurple","1;35");
define("CCA_Brown","0;33");
define("CCA_Yellow","1;33");
define("CCA_LGray","0;37");
define("CCA_White","1;37");
// define("CCA_CStart","\[\033");
define("CCA_CStart","\033");
// define("CCA_CEnd","m\]");
define("CCA_CEnd","m");
define("CCA_COff",CCA_CStart . "[0" . CCA_CEnd);

define("CCA_CWhite",CCA_CStart . "[" . CCA_White . CCA_CEnd);
define("CCA_CBlack",CCA_CStart . "[" . CCA_Black . CCA_CEnd);
define("CCA_CCyan",CCA_CStart . "[" . CCA_LCyan . CCA_CEnd);
define("CCA_CDCyan",CCA_CStart . "[" . CCA_Cyan . CCA_CEnd);
define("CCA_CGreen",CCA_CStart . "[" . CCA_LGreen . CCA_CEnd);
define("CCA_CDGreen",CCA_CStart . "[" . CCA_Green . CCA_CEnd);
define("CCA_CRed",CCA_CStart . "[" . CCA_LRed . CCA_CEnd);
define("CCA_CDRed",CCA_CStart . "[" . CCA_Red . CCA_CEnd);
define("CCA_CBlue",CCA_CStart . "[" . CCA_LBlue . CCA_CEnd);
define("CCA_CDBlue",CCA_CStart . "[" . CCA_Blue . CCA_CEnd);
define("CCA_CBrown",CCA_CStart . "[" . CCA_Brown . CCA_CEnd);
define("CCA_CYellow",CCA_CStart . "[" . CCA_Yellow . CCA_CEnd);
define("CCA_CGrey",CCA_CStart . "[" . CCA_LGray . CCA_CEnd);
define("CCA_CDGrey",CCA_CStart . "[" . CCA_DGray . CCA_CEnd);
define("CCA_CPurple",CCA_CStart . "[" . CCA_LPurple . CCA_CEnd);
define("CCA_CDPurple",CCA_CStart . "[" . CCA_Purple . CCA_CEnd);
// end colour defines }}}

require_once "base.class.php";

class CLI extends Base
{
    private $menu=false;
    private $menucmds;
    private $menuop;
    private $menuwidth;

    public function __construct($logg=false,$menuwidth=40)/*{{{*/
    {
        parent::__construct($logg);
        $this->menuwidth=$menuwidth;
        $this->menuInit();
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    private function switchCase($char)/*{{{*/
    {
        $val=ord($char);
        if($val>=65 && $val<=90){
            $val=$val+32;
        }elseif($val>=97 && $val<=122){
            $val=$val-32;
        }
        return chr($val);
    }/*}}}*/
    private function insertMenuLine($mline)/*{{{*/
    {
        if(strlen($mline)){
            if(strlen($this->menuop)){
                $this->menuop.="\n" . $mline;
            }else{
                $this->menuop=$mline;
            }
        }
    }/*}}}*/
    private function buildMenu()/*{{{*/
    {
        if(false!==($cn=$this->ValidArray($this->menu))){
            $this->menuop="";
            $mline="";
            $mlen=0;
            foreach($this->menu as $str=>$sarr){
                $dlen=strlen($sarr["display"]);
                if($dlen<$this->menuwidth){
                    if(($dlen+$mlen)>$this->menuwidth){
                        $this->insertMenuLine($mline);
                        $mline=$sarr["display"];
                        $mlen=$dlen;
                    }else{
                        if(strlen($mline)){
                            $mline.=", " . $sarr["display"];
                        }else{
                            $mline=$sarr["display"];
                        }
                    }
                }else{
                    $this->insertMenuLine($mline);
                    $mline="";
                    $mlen=0;
                    $this->insertMenuLine($sarr["display"]);
                }
            }
            $this->insertMenuLine($mline);
            $this->menuop.="\n[" . $this->menucmds . "] > ";
            return true;
        }
        return false;
    }/*}}}*/
    public function menuInit()/*{{{*/
    {
        $this->menu=array();
        $this->menuop="";
        $this->menucmds="";
    }/*}}}*/
    public function menuAdd($str)/*{{{*/
    {
        $ret=false;
        if(false!==($len=$this->ValidStr($str))){
            $hotkey=false;
            $pos=0;
            while($pos<$len){
                $tmp=substr($str,$pos,1);
                if(preg_match("/[a-zA-Z]/",$tmp)){
                    if(false===($hpos=strpos($this->menucmds,$tmp))){
                        $hotkey=$tmp;
                        break;
                    }else{
                        $tmp=$this->switchCase($tmp);
                        if(false===($hpos=strpos($this->menucmds,$tmp))){
                            $hotkey=$tmp;
                            break;
                        }
                    }
                }
                $pos++;
            }
            if(false!==$hotkey){
                if($pos>0 && $pos<$len){
                    $tmp=substr($str,0,$pos);
                    $tmp.="(" . $hotkey . ")" . substr($str,$pos+1);
                    $this->menu[$str]=array("hotkey"=>$hotkey,"display"=>$tmp);
                    $this->menucmds.=$hotkey;
                }elseif($pos>0){
                    $tmp=substr($str,0,$pos);
                    $tmp.="(" . $hotkey . ")";
                    $this->menu[$str]=array("hotkey"=>$hotkey,"display"=>$tmp);
                    $this->menucmds.=$hotkey;
                }else{
                    $this->menu[$str]=array("hotkey"=>$hotkey,"display"=>"(" . $hotkey . ")" . substr($str,1));
                    $this->menucmds.=$hotkey;
                }
                $ret=true;
            }
        }
        return $ret;
    }/*}}}*/
    public function menuAddArr($arr,$init=true)/*{{{*/
    {
        $ret=false;
        if($init){
            $this->menuInit();
        }
        if(false!==($cn=$this->ValidArray($arr))){
            $ret=true;
            foreach($arr as $key=>$val){
                $ret=$ret && $this->menuAdd($val);
            }
        }
        return $ret;
    }/*}}}*/
    public function menuDelete($str)/*{{{*/
    {
        if($this->ValidStr($str) && isset($this->menu[$str])){
            unset($this->menu[$str]);
        }
    }/*}}}*/
    /** cliParse {{{1
     * Command line input accepting only certain commands
     * 
     * @access public
     * @return array
     */
    public function cliParse()
    {
        $this->buildMenu();
        $cmds=$this->menucmds;
        $prompt=$this->menuop;
        $cmd="";
        $num=0;
        $input=strtolower($this->cliInput($prompt));
        $mats=preg_match("/([0-9]*)([" . $cmds . "]*)/",$input,$matches);
        if($mats){
            $cmd=$matches[2];
            $num=$matches[1];
        }
        $num=$num+0;
        return array("cmd"=>$cmd,"num"=>$num);
    } /*}}}*/
    /** cliInput {{{
     * command line input with prompt
     * 
     * @access public
     * @return string
     */
    public function cliInput( $prompt="php>" )
    {
        echo $prompt . " ";
        return rtrim( fgets( STDIN ), "\n" );
    } /*}}}*/
    /** colourString {{{1
     * add terminal colour information to string
     *
     * @param string $str
     * @param string $colour
     * @return string
     */
    public function colourString($str,$colour="white")
    {
        $col=parseColourString($colour);
        return $col . $str . CCA_COff;
    } /*}}}*/
    public function parseColourString($colour="white") /*{{{*/
    {
        $col=CCA_CDGrey;
        if(is_string($colour) && strlen($colour)){
            $colour=trim(strtolower($colour));
            if(false!==($pos=strpos($colour,"light"))){
                $light=true;
            }else{
                $light=false;
            }
            if(false!==($pos=strpos($colour,"dark"))){
                $dark=true;
            }else{
                $dark=false;
            }
            $cchar=substr($colour,0,1);
            $breaknext=false;
            while($cchar!==""){
                switch($cchar){
                case "l":
                    $light=true;
                    if($breaknext){
                        $colour=trim(substr($colour,0,strlen($colour)-1));
                    }else{
                        $colour=trim(substr($colour,1));
                    }
                    break;
                case "d":
                    $dark=true;
                    if($breaknext){
                        $colour=trim(substr($colour,0,strlen($colour)-1));
                    }else{
                        $colour=trim(substr($colour,1));
                    }
                    break;
                }
                if($colour!=="red"){
                    $cchar=substr($colour,-1);
                }
                if($breaknext){
                    $cchar="";
                }else{
                    $breaknext=true;
                }
            }
            switch($colour){
            case "white":
                $col=CCA_CWhite;
                if($dark){
                    $col=CCA_LGray;
                }
                break;
            case "black":
                $col=CCA_CBlack;
                break;
            case "cyan":
                $col=CCA_CCyan;
                if($dark){
                    $col=CCA_CDCyan;
                }
                break;
            case "green":
                $col=CCA_CGreen;
                if($dark){
                    $col=CCA_CDGreen;
                }
                break;
            case "red":
                $col=CCA_CRed;
                if($dark){
                    $col=CCA_CDRed;
                }
                break;
            case "blue":
                $col=CCA_CBlue;
                if($dark){
                    $col=CCA_CDBlue;
                }
                break;
            case "brown":
                $col=CCA_CBrown;
                break;
            case "yellow":
                $col=CCA_CYellow;
                break;
            case "grey":
                $col=CCA_CGrey;
                if($dark){
                    $col=CCA_CDGrey;
                }
                break;
            case "gray":
                $col=CCA_CGrey;
                if($dark){
                    $col=CCA_CDGrey;
                }
                break;
            case "purple":
                $col=CCA_CPurple;
                if($dark){
                    $col=CCA_CDPurple;
                }
                break;
            case "magenta":
                $col=CCA_CPurple;
                if($dark){
                    $col=CCA_CDPurple;
                }
                break;
            }
        }
        return $col;
    } /*}}}*/
}
?>
