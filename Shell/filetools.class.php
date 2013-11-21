<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * filetools.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Monday 14 October 2013, 07:57:02
 * Last Modified: Monday 14 October 2013, 08:49:09
 * Revision: $Id$
 * Version: 0.00
 */

require_once "base.class.php";
require_once "Shell/stringtools.class.php";

if(!defined("CCA_FILE_NOTEXIST")){
    define("CCA_FILE_NOTEXIST",0);
}
if(!defined("CCA_FILE_EXIST")){
    define("CCA_FILE_EXIST",1);
}
if(!defined("CCA_FILE_ASARRAY")){
    define("CCA_FILE_ASARRAY",2);
}
if(!defined("CCA_FILE_ASSTRING")){
    define("CCA_FILE_ASSTRING",3);
}
if(!defined("TWENTYFOURHOURS")){
    define("TWENTYFOURHOURS",60*60*24);
}

class FileTools extends Base
{
    public function __construct($logg=false)/*{{{*/
    {
        parent::__construct($logg);
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    /** directoryRead {{{
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
    public function directoryRead($directory)
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
    } /*}}}*/
    /** emptyDir {{{
     * alias for isEmptyDir
     * 
     * @param string $dir 
     * @access public
     * @return bool
     */
    public function emptyDir($dir="")
    {
        return isEmptyDir($dir);
    } /*}}}*/
    /** isEmptyDir {{{
     * checks if the directory $dir is empty
     * 
     * 
     * @param string $dir 
     * @access public
     * @return bool
     */
    public function isEmptyDir($dir="")
    {
        $ret=false;
        if(is_string($dir) && $dir && file_exists($dir)){
            $dira=$this->directoryRead($dir);
            if(is_array($dira)){
                if(count($dir["mixed"])==0){
                    $ret=true;
                }
            }
        }
        return $ret;
    } /*}}}*/
    /** noClobberFileMove {{{
     * moves $src file to $dest directory without overwriting an existing file with the same name
     * 
     * @param string $src 
     * @param string $dest 
     * @param int $pad 
     * @access public
     * @return bool|string fully qualified filename if successful, or false if not
     */
    public function noClobberFileMove($src="",$dest="",$pad=3)
    {
        $ret=false;
        $cc=0;
        $st=new StringTools();
        if(is_string($src) && $src && file_exists($src) && is_string($dest) && $dest && file_exists($dest)){
            $farr=pathinfo($src);
            while(file_exists($this->unixPath($dest) . $farr["basename"])){
                $cc++;
                $tcc=$st->padString($cc . "","0",$pad);
                $farr["basename"]=$farr["filename"] . "_" . $tcc . "." . $farr["extension"];
            }
            $ret=rename($src,$this->unixPath($dest) . $farr["basename"]);
        }
        if($ret){
            return $this->unixPath($dest) . $farr["basename"];
        }else{
            return $ret;
        }
    } /*}}}*/

    /** unixPath {{{
     * ensures that $path is in the correct unix format for a directory
     *
     * changes backslash "\" path identifiers to forwardslash (windows->unix)
     * adds a trailing backslash if necessary.
     * 
     * @param mixed $path 
     * @access public
     * @return string
     */
    public function unixPath($path)
    {
        $tpath=str_replace(chr(92),'/',$path);
        if(substr($tpath,-1)=="/"){
            return $path;
        }else{
            return $path . "/";
        }
    } /*}}}*/

    /** makeSensibleFilename {{{
     * makes a sensible unix filename from $fn
     *
     * strips $fn of all characters except for alphnumerics and numbers
     * 
     * @param string $fn 
     * @access public
     * @return string
     */
    public function makeSensibleFilename($fn="")
    {
        return preg_replace("/[^a-zA-Z0-9]/","",$fn);
    } /*}}}*/

    /** noClobberDir {{{
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
    public function noClobberDir($dir)
    {
        $ret=false;
        if(is_string($dir) && strlen($dir)){
            $path=$dir;
            $pi=pathinfo($dir);
            $c=0;
            while($this->checkFile($path,CCA_FILE_EXIST)){
                $path=$this->unixPath($pi["dirname"]) . $pi["basename"] . "_$c";
                $c++;
            }
            $ret=$path;
        }
        return $ret;
    } /*}}}*/
    /* noClobberFile {{{ */
    public function noClobberFile($file)
    {
        $ret=false;
        $st=new StringTools();
        if(is_string($file) && $file){
            $path=$file;
            $pi=pathinfo($file);
            if($pi["dirname"]){
                $dir=$this->unixPath($pi["dirname"]);
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
            while($this->checkFile($path,CCA_FILE_EXIST)){
                $path=$dir . $filename . "-" . $st->padString($iterator) . "." . $pi["extension"];
                $iterator++;
            }
            $ret=$path;
        }
        return $ret;
    } /*}}}*/

    /** goUpDir {{{
     * strips off the last path component of $pathstr
     * 
     * @param string $pathstr 
     * @access public
     * @return string
     */
    public function goUpDir($pathstr="")
    {
        $op="";
        $pi=pathinfo($pathstr);
        return $this->unixPath($pi["dirname"]);
    } /*}}}*/

    /** makeAbsolutePath {{{
     * 
     * @param string $prefix 
     * @param string $path 
     * @access public
     * @return string
     */
    public function makeAbsolutePath($prefix,$path)
    {
        $start=0;
        $ptmp=explode("/",$path);
        if($ptmp[0]!=""){
            if($ptmp[0]=="." || $ptmp[0]==".."){
                $start=1;
            }
            if($ptmp[0]==".."){
                $ppath=$this->unixPath($this->goUpDir($prefix));
            }else{
                $ppath=$this->unixPath($prefix);
            }
            $c=count($ptmp);
            for($x=$start;$x<$c;$x++){
                $ppath.=$this->unixPath($ptmp[$x]);
            }
            $ret=substr($ppath,0,strlen($ppath)-1);
        }else{
            // already an absolute path
            $ret=$path;
        }
        return $ret;
    } /*}}}*/

    /** realFilesize {{{
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
    public function realFilesize($path)
    {
        $fs=false;
        if(file_exists($path)){
            $cmd="ls -l $path |sed 's/\s\s\{1,\}/ /g'|cut -d' ' -f5";
            $fs=exec($cmd);
        }
        return $fs;
    } /*}}}*/

    /** realFilemtime {{{
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
    public function realFilemtime($path)
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
    } /*}}}*/

    /** printableFilesize {{{
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
    public function printableFilesize($xfs)
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
    } /*}}}*/
    public function printableFileSizeArray($xfs)
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
    } /*}}}*/

    /** checkFile {{{
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
    public function checkFile($fn="",$exists=CCA_FILE_EXIST)
    {
        $ret=false;
        if(is_string($fn) && strlen($fn)){
            $ret=$exists?file_exists($fn):!file_exists($fn);
        }elseif(is_array($fn)){
            $tmp=true;
            $c=count($fn);
            for($x=0;$x<$c;$x++){
                $tmp=$this->checkFile($fn[$x],$exists) && $tmp;
            }
            $ret=$tmp;
        }
        return $ret;
    } /*}}}*/

    /** getFile {{{
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
    public function getFile($fn="",$type=CCA_FILE_ASARRAY)
    {
        $ret=false;
        if($this->checkFile($fn,CCA_FILE_EXIST)){
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
    } /*}}}*/
    public function cleanDir($dir="",$keepext="",$age=0)
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
            $d=$this->directoryRead($dir);
            if(is_array($d["files"]) && count($d["files"])){
                foreach($d["files"] as $file){
                    $fqfn=$this->unixPath($dir) . $file;
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
    } /*}}}*/
}

?>
