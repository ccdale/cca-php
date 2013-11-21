<?php

/** {{{ heading block
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * /home/chris/src/php/cca-php/php.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Saturday 24 January 2009, 17:46:59
 * Version: 0.1
 * Last Modified: Monday  9 May 2011, 22:43:56
 *
 * $Id: php.php 545 2011-05-09 21:44:29Z chris $
 */
// }}}


function findSubroutineCalls($str="") // {{{
{
    $ret=false;
    if(is_string($str) && strlen($str)){
        preg_match_all("/([a-zA-Z_][a-zA-Z0-9_-]*)\(/",$str,$matches);
        $ret["calls"]=array_unique($matches[1]);
        sort($ret["calls"]);
    }
    return $ret;
} // }}}
function findFunction($str="") // {{{
{
    $ret=false;
    if(is_string($str) && strlen($str)){
        $arr=textBetween($str,"function ","(");
        // $leftover=textOutside($str,"function ","(");
        $leftover=$arr["outside"];
        // print "leftover: " . substr($leftover,0,20). "\n\n";
        if($arr["text"]!=$str){
            $ret["name"]=$arr["text"];
            $ret["start"]=$arr["start"];
            // $offset=$arr["start"]+$arr["end"];
            // $arr=textBetween(substr($str,$offset),"{","}");
            // print "after start: " . substr(substr($leftover,$arr["start"]),0,20) . "\n\n";
            $arr=textBetween(substr($leftover,$arr["start"]),"{","}");
            // $leftover=textOutside(substr($leftover,$arr["start"]),"{","}");
            $leftover=$arr["outside"];
            $ret["body"]=$arr["text"];
            // $ret["end"]=$offset+$arr["start"]+$arr["end"];
            $ret["end"]=$arr["start"]+$arr["end"];
            $ret["leftover"]=$leftover;
        }
    }
    return $ret;
} // }}}
function findIncludeFiles($str="") // {{{
{
    $ret=false;
    $includes=array("include_once(\"","require_once(\"","include(\"","require(\"");
    if(is_string($str) && strlen($str)){
        $arr["outside"]=$str;
        $c=count($includes);
        for($x=0;$x<$c;$x++){
            $arr=textBetween($arr["outside"],$includes[$x],"\");");
            while(false!==$arr["start"]){
                $ret["includes"][]=$arr["text"];
                $arr=textBetween($arr["outside"],$includes[$x],"\");");
            }
            $arr["outside"]=$arr["text"];
        }
        $ret["leftover"]=$arr["outside"];
    }
    return $ret;
} // }}}
function functionMapFile($file="") // {{{
{
    $ret=array("includes"=>false,"funcs"=>false,"calls"=>false);
    if(checkFile($file,CCA_FILE_EXIST)){
        $pi=pathinfo($file);
        $st=file_get_contents($file);
        $ttmp=findIncludeFiles($st);
        $st=$ttmp["leftover"];
        // $ret["includes"]=$tmp["includes"];
        $c=isset($ttmp["includes"])?count($ttmp["includes"]):0;
        for($x=0;$x<$c;$x++){
            $fn=makeAbsolutePath($pi["dirname"],$ttmp["includes"][$x]);
            $tmp=functionMapFile($fn);
            $ret["includes"][$x]["file"]=$fn;
            $ret["includes"][$x]["calls"]=$tmp["calls"];
            $ret["includes"][$x]["funcs"]=$tmp["funcs"];
            $ret["includes"][$x]["includes"]=$tmp["includes"];
            $ret["funcs"]=array_merge($ret["funcs"],$tmp["funcs"]);
        }
        while(false!==($arr=findFunction($st))){
            $ret["funcs"][$arr["name"]]=array("file"=>$file);
            // $st=substr($st,$arr["end"]);
            $st=$arr["leftover"];
            $tmp=findSubroutineCalls($arr["body"]);
            $ret["funcs"][$arr["name"]]["calls"]=$tmp["calls"];
        }
        $tmp=findSubroutineCalls($st);
        $ret["calls"]=$tmp["calls"];
    }
    return $ret;
} // }}}
?>
