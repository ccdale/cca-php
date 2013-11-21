<?php
/** 
 * filesystem functions
 *
 * @file file.php
 *
 * functions to assist with working with files and directories.
 *
 * @package cca-php
 * @author Chris Allison <cca-php@cca.me.uk>
 * Last Modified: Monday 21 October 2013, 11:49:55
 * $Id: file.php 546 2011-06-05 20:23:25Z chris $
 */

/**
 * ensure that the functions in string.php are loaded 
 *
 */
require_once "string.php";

/**
 * setup constants 
 */
define("CCA_FILE_NOTEXIST",0);
define("CCA_FILE_EXIST",1);
define("CCA_FILE_ASARRAY",2);
define("CCA_FILE_ASSTRING",3);

if(!defined("TWENTYFOURHOURS")){
    define("TWENTYFOURHOURS",60*60*24);
}
/** directoryRead {{{1
 *
 * reads a directory returning a sorted array of files. 
 *
 * returns an indexed array of files, links and directories
 * within the directory path $directory or boolean false if
 * $directory is an invalid path.
 * Returned array indexes are:
 * 1. "mixed": sorted array of everything in $directory
 * 2. "unknowntype": sorted array where the type could not be determined
 * 3. "directories": sorted array of sub-directories within $directory
 * 4. "files": sorted array of files within $directory
 * 5. "links": sorted array of soft-links within $directory
 *
 * @access public
 * @param string $directory directory to read
 * @return bool|array array of files and directories within $directory
 */
function directoryRead($directory)
{
    $op=false;
    $type="";
    // debug("directory",$directory);
    $arr=$linkarr=$dirarr=$filearr=$unknownarr=array();
    if(isset($directory) && $directory && is_dir($directory)){
        // debug("directory ok",$directory);
        $d=dir($directory);
        while (false !== ($entry = $d->read())) {
            if(!(($entry==".") || ($entry==".."))){
                $arr[]=$entry;
                if(is_link($directory . "/" . $entry)){
                    $type="link";
                }
                if(is_dir($directory . "/" . $entry)){
                    $type="directory";
                }
                if(is_file($directory . "/" . $entry)){
                    $type="file";
                }
                switch($type){
                case "link":
                    $linkarr[]=$entry;
                    break;
                case "directory":
                    $dirarr[]=$entry;
                    break;
                case "file":
                    $filearr[]=$entry;
                    break;
                default:
                    $unknownarr[]=$entry;
                }
            }
        }
        if(is_array($arr) && count($arr)){
            sort($arr);
        }
        if(is_array($linkarr) && count($linkarr)){
            sort($linkarr);
        }
        if(is_array($dirarr) && count($dirarr)){
            sort($dirarr);
        }
        if(is_array($filearr) && count($filearr)){
            sort($filearr);
        }
        $op["mixed"]=$arr;
        $op["directories"]=$dirarr;
        $op["files"]=$filearr;
        $op["links"]=$linkarr;
        $op["unknowntype"]=$unknownarr;
    }
    return $op;
}
function dread($dir)/*{{{*/
{
    $op=false;
    $arr=$linkarr=$dirarr=$filearr=$unknownarr=$fqfarr=$fqdarr=array();
    if(isset($dir) && is_string($dir) && strlen($dir) && is_dir($dir)){
        $d=dir($dir);
        while (false !== ($entry = $d->read())) {
            if(!(($entry==".") || ($entry==".."))){
                $arr[]=$entry;
                $tentry=$dir . DIRECTORY_SEPARATOR . $entry;
                if(is_link($tentry)){
                    $linkarr[]=$entry;
                }elseif(is_dir($tentry)){
                    $dirarr[]=$entry;
                    $fqdarr[]=$tentry;
                }elseif(is_file($tentry)){
                    $filearr[]=$entry;
                    $fqfarr[]=$tentry;
                }else{
                    $unknownarr[]=$entry;
                }
            }
        }
        $op=array(
            "mixed"=>$arr,
            "directories"=>$dirarr,
            "files"=>$filearr,
            "links"=>$linkarr,
            "unknowntype"=>$unknownarr,
            "fqf"=>$fqfarr,
            "fqd"=>$fqdarr
        );
    }
    return $op;
}/*}}}*/
function frdread($dir)/*{{{*/
{
    $arr=dread($dir);
    $oarr=array();
    if(is_array($arr["fqd"]) && 0!==($cn=count($arr["fqd"]))){
        foreach($arr["fqd"] as $fqd){
            $fqfa=frdread($fqd);
            $oarr=array_merge($oarr,$fqfa);
        }
    }
    if(is_array($arr["fqf"]) && 0!==($cn=count($arr["fqf"]))){
        $oarr=array_merge($oarr,$arr["fqf"]);
    }
    return $oarr;
}/*}}}*/
/** emptyDir {{{1
 * alias for isEmptyDir
 * 
 * @param string $dir 
 * @access public
 * @return bool
 */
function emptyDir($dir="")
{
    return isEmptyDir($dir);
}
/** isEmptyDir {{{1
 * checks if the directory $dir is empty
 * 
 * 
 * @param string $dir 
 * @access public
 * @return bool
 */
function isEmptyDir($dir="")
{
    $ret=false;
    if(is_string($dir) && $dir && file_exists($dir)){
        $dira=directoryRead($dir);
        if(is_array($dira)){
            if(count($dir["mixed"])==0){
                $ret=true;
            }
        }
    }
    return $ret;
}

/** noClobberFileMove {{{1
 * moves $src file to $dest directory without overwriting an existing file with the same name
 * 
 * @param string $src 
 * @param string $dest 
 * @param int $pad 
 * @access public
 * @return bool|string fully qualified filename if successful, or false if not
 */
function noClobberFileMove($src="",$dest="",$pad=3)
{
    $ret=false;
    $cc=0;
    if(is_string($src) && $src && file_exists($src) && is_string($dest) && $dest && file_exists($dest)){
        $farr=pathinfo($src);
        while(file_exists(unixPath($dest) . $farr["basename"])){
            $cc++;
            $tcc=padString($cc . "","0",$pad);
            $farr["basename"]=$farr["filename"] . "_" . $tcc . "." . $farr["extension"];
        }
        $ret=rename($src,unixPath($dest) . $farr["basename"]);
    }
    if($ret){
        return unixPath($dest) . $farr["basename"];
    }else{
        return $ret;
    }
}

/** unixPath {{{1
 * ensures that $path is in the correct unix format for a directory
 *
 * changes backslash "\" path identifiers to forwardslash (windows->unix)
 * adds a trailing backslash if necessary.
 * 
 * @param mixed $path 
 * @access public
 * @return string
 */
function unixPath($path)
{
    $tpath=str_replace(chr(92),'/',$path);
    if(substr($tpath,-1)=="/"){
        return $path;
    }else{
        return $path . "/";
    }
}

/** makeSensibleFilename {{{1
 * makes a sensible unix filename from $fn
 *
 * strips $fn of all characters except for alphnumerics and numbers
 * 
 * @param string $fn 
 * @access public
 * @return string
 */
function makeSensibleFilename($fn="")
{
    return preg_replace("/[^a-zA-Z0-9]/","",$fn);
}

/** noClobberDir {{{1
 * returns a unique directory name for $dir
 *
 * adds an underscore and incrementing digits to $dir
 * until a unique name is formed.  It does not create the directory.
 * returns false on error or a unique pathname for $dir
 * 
 * @param string $dir 
 * @access public
 * @return bool|string
 */
function noClobberDir($dir)
{
    $ret=false;
    if(is_string($dir) && strlen($dir)){
        $path=$dir;
        $pi=pathinfo($dir);
        $c=0;
        while(checkFile($path,CCA_FILE_EXIST)){
            $path=unixPath($pi["dirname"]) . $pi["basename"] . "_$c";
            $c++;
        }
        $ret=$path;
    }
    return $ret;
}
/* noClobberFile {{{1 */
function noClobberFile($file)
{
    $ret=false;
    if(is_string($file) && $file){
        $path=$file;
        $pi=pathinfo($file);
        if($pi["dirname"]){
            $dir=unixPath($pi["dirname"]);
        }else{
            $dir="";
        }
        if(preg_match('/(\w+)-(\d+)$/',$pi["filename"],$matches)){
            $filename=$matches[1];
            $iterator=intval($matches[2]);
        }else{
            $filename=$pi["filename"];
            $iterator=0;
        }
        // $c=0;
        while(checkFile($path,CCA_FILE_EXIST)){
            $path=$dir . $filename . "-" . padString($iterator) . "." . $pi["extension"];
            $iterator++;
        }
        $ret=$path;
    }
    return $ret;
}

/** goUpDir {{{1
 * strips off the last path component of $pathstr
 * 
 * @param string $pathstr 
 * @access public
 * @return string
 */
function goUpDir($pathstr="")
{
    // 
    $op="";
    $pi=pathinfo($pathstr);
    return unixPath($pi["dirname"]);

    $tmp=explode("/",$pi["dirname"]);
    $c=count($tmp);
    print_r($tmp);
    if($c>0){
        $c--;
        for($x=0;$x<$c;$x++){
            $op.=unixPath($tmp[$x]);
        }
    }
    return $op;
}

/** makeAbsolutePath {{{1
 * 
 * @param string $prefix 
 * @param string $path 
 * @access public
 * @return string
 */
function makeAbsolutePath($prefix,$path)
{
    $start=0;
    $ptmp=explode("/",$path);
    if($ptmp[0]!=""){
        if($ptmp[0]=="." || $ptmp[0]==".."){
            $start=1;
        }
        if($ptmp[0]==".."){
            $ppath=unixPath(goUpDir($prefix));
        }else{
            $ppath=unixPath($prefix);
        }
        $c=count($ptmp);
        for($x=$start;$x<$c;$x++){
            $ppath.=unixPath($ptmp[$x]);
        }
        $ret=substr($ppath,0,strlen($ppath)-1);
    }else{
        // already an absolute path
        $ret=$path;
    }
    return $ret;
}

/** realFilesize {{{1
 * returns the unix filesize for filename $path
 * 
 * used where files are greter than the 2gb limit
 * that php can read.  Uses a shell escape to parse
 * the output from ls.
 *
 * @param string $path 
 * @access public
 * @return string
 */
function realFilesize($path)
{
    $fs=false;
    if(file_exists($path)){
        $cmd="ls -l $path |sed 's/\s\s\{1,\}/ /g'|cut -d' ' -f5";
        $fs=exec($cmd);
    }
    return $fs;
}

/** realFilemtime {{{1
 * returns the modification time of file $path
 *
 * used where files are greater than the 2gb limit
 * that php can read. uses a shell escape and parses
 * the output of ls.  returns a unix time stamp.
 * 
 * @param string $path 
 * @access public
 * @return string
 */
function realFilemtime($path)
{
    $fm=false;
    if(file_exists($path)){
        $cmd="ls -l --time-style=long-iso $path |cut -d' ' -f6-7";
        $dfm=exec($cmd);
        // logger("time returned was: $dfm for file: $path");
        $fm=strtotime($dfm);
        // logger("strtotimes answer is $fm");
    }
    return $fm;
}

/** printableFilesize {{{1
 * returns a human readable filesize for the number/string $xfs
 *
 * $xfs can be a number or a string representing a number.  returns
 * a human readable version of that number, limited to 2 decimal 
 * places, with size modifiers (kb,Mb,Gb).
 * 
 * @param int|string $xfs 
 * @access public
 * @return string
 */
function printableFilesize($xfs)
{
    $mod=" bytes";
    if($xfs>1000){
        $mod="kb";
        $xfs=(float)$xfs/1000;
    }
    if($xfs>1000){
        $mod="Mb";
        $xfs=(float)$xfs/1000;
    }
    if($xfs>1000){
        $mod="Gb";
        $xfs=(float)$xfs/1000;
    }
    return number_format((float)$xfs,2) . $mod;
}
function printableFileSizeArray($xfs)
{
    $mod=" bytes";
    if($xfs>1000){
        $mod="kb";
        $xfs=(float)$xfs/1000;
    }
    if($xfs>1000){
        $mod="Mb";
        $xfs=(float)$xfs/1000;
    }
    if($xfs>1000){
        $mod="Gb";
        $xfs=(float)$xfs/1000;
    }
    return array("filesize"=>$xfs,"units"=>substr($mod,0,1));
}

/** checkFile {{{1
 * checks for the existance or non-existance of file(s).
 *
 * checks if file $fn exists or doesn't exist depending on
 * the $exists parameter (CCA_FILE_EXIST || CCA_FILE_NOTEXIST).
 * If file $fn is an array, the operation is performed on each
 * member of the array, with the results anded together.
 * 
 * @param array|string $fn 
 * @param int $exists 
 * @access public
 * @return bool
 */
function checkFile($fn="",$exists=CCA_FILE_EXIST)
{
    $ret=false;
    if(is_string($fn) && strlen($fn)){
        $ret=$exists?file_exists($fn):!file_exists($fn);
    }elseif(is_array($fn)){
        $tmp=true;
        $c=count($fn);
        for($x=0;$x<$c;$x++){
            $tmp=checkFile($fn[$x],$exists) && $tmp;
        }
        $ret=$tmp;
    }
    return $ret;
}

/** getFile {{{1
 * reads the contents of file $fn into an array or string
 *
 * returns false if the file does not exist, else returns the
 * file contents as a string or array depending on the $type parameter
 * (CCA_FILE_ASSTRING || CCA_FILE_ASARRAY)
 * 
 * @param string $fn 
 * @param int $type 
 * @access public
 * @return bool|array|string
 */
function getFile($fn="",$type=CCA_FILE_ASARRAY)
{
    $ret=false;
    if(checkFile($fn,CCA_FILE_EXIST)){
        if($type==CCA_FILE_ASSTRING){
            $ret=file_get_contents($fn);
        }else{
            $tmp=file($fn);
            // strip off white space and the new line from each element
            $c=count($tmp);
            for($x=0;$x<$c;$x++){
                $ret[$x]=trim($tmp[$x]);
            }
        }
    }
    return $ret;
}
function cleanDir($dir="",$keepext="",$age=0)
{
    if(!is_array($keepext)){
        $ke=array($keepext);
    }else{
        $ke=$keepext;
    }
    $cn=count($ke);
    $now=mktime();
    $oldfiletime=$now-($age*TWENTYFOURHOURS);
    $totalfs=0.0;
    $fs=0.0;
    $clean=array();
    $cleaned=array();
    $failed=array();
    if(is_string($dir) && strlen($dir)){
        $d=directoryRead($dir);
        if(is_array($d["files"]) && count($d["files"])){
            foreach($d["files"] as $file){
                $fqfn=unixPath($dir) . $file;
                if(file_exists($fqfn)){
                    $pi=pathinfo($fqfn);
                    if(isset($pi["extension"])){
                        $skip=false;
                        if($cn){
                            foreach($ke as $kext){
                                if($pi["extension"]==$kext){
                                    $skip=true;
                                    break;
                                }
                            }
                        }
                        if(!$skip){
                            if($age){
                                $ft=filemtime($fqfn);
                                if($ft<$oldfiletime){
                                    $clean[]=$fqfn;
                                }
                            }else{
                                $clean[]=$fqfn;
                            }
                        }
                    }else{
                        if($age){
                            $ft=filemtime($fqfn);
                            if($ft<$oldfiletime){
                                $clean[]=$fqfn;
                            }
                        }
                    }
                }
            }
        }
    }
    $cn=count($clean);
    if($cn){
        foreach($clean as $fqfn){
            $fs=(float)filesize($fqfn);
            if(unlink($fqfn)){
                $cleaned[]=array($fqfn,$fs);
                $totalfs+=$fs;
            }else{
                $failed[]=array($fqfn,$fs);
            }
        }
    }
    return array("cleaned"=>$cleaned,"failed"=>$failed,"totalbytes"=>$totalfs);
}
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
?>
