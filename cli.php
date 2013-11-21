<?php
/** {{{1
 * Command Line Interpreter Helper functions
 *
 * @author C.C.Allison
 * @package cca-php
 *
 * Started: Thursday  5 February 2009, 05:58:42
 * Last Modified: Sunday  6 October 2013, 11:31:17
 * $Id: cli.php 301 2010-07-07 13:28:59Z chris $
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

/** parseColourString
 * determine required colour from colour name
 *
 * $colour="[[l|light|d|dark]][ ]<[white|black|cyan|green|red|blue|brown|yellow|grey|gray|purple|magenta]>[l|d]"
 * @param string $colour
 * @return string
 */
function parseColourString($colour="white") /*{{{*/
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
/** colourString {{{1
 * add terminal colour information to string
 *
 * @param string $str
 * @param string $colour
 * @return string
 */
function colourString($str,$colour="white")
{
    $col=parseColourString($colour);
    return $col . $str . CCA_COff;
}
/** cliInput {{{1
 * command line input with prompt
 * 
 * @param string $prompt 
 * @access public
 * @return string
 */
function cliInput( $prompt="php>" )
{
    echo $prompt . " ";
    return rtrim( fgets( STDIN ), "\n" );
}
/** cliParse {{{1
 * Command line input accepting only certain commands
 * 
 * @param string $prompt 
 * @param string $cmds 
 * @access public
 * @return array
 */
function cliParse($prompt,$cmds)
{
    $cmd="";
    $num=0;
    $input=strtolower(cliInput($prompt));
    $mats=preg_match("/([0-9]*)([" . $cmds . "]*)/",$input,$matches);
    if($mats){
        $cmd=$matches[2];
        $num=$matches[1];
    }
    $num=$num+0;
    return array("cmd"=>$cmd,"num"=>$num);
}
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
?>
