<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * tools.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Monday 21 October 2013, 11:51:29
 * Last Modified: Monday 21 October 2013, 20:32:21
 * Revision: $Id$
 * Version: 0.00
 */

require_once "base.class.php";

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

class Tools extends Base
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
    }/*}}}*/
    public function dread($dir)/*{{{*/
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
    public function frdread($dir)/*{{{*/
    {
        $arr=$this->dread($dir);
        $oarr=array();
        if(is_array($arr["fqd"]) && 0!==($cn=count($arr["fqd"]))){
            foreach($arr["fqd"] as $fqd){
                $fqfa=$this->frdread($fqd);
                $oarr=array_merge($oarr,$fqfa);
            }
        }
        if(is_array($arr["fqf"]) && 0!==($cn=count($arr["fqf"]))){
            $oarr=array_merge($oarr,$arr["fqf"]);
        }
        return $oarr;
    }/*}}}*/
    /** emptyDir {{{
     * alias for isEmptyDir
     * 
     * @param string $dir 
     * @access public
     * @return bool
     */
    public function emptyDir($dir="")
    {
        return $this->isEmptyDir($dir);
    }/*}}}*/
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
    }/*}}}*/

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
        if(is_string($src) && $src && file_exists($src) && is_string($dest) && $dest && file_exists($dest)){
            $farr=pathinfo($src);
            while(file_exists($this->unixPath($dest) . $farr["basename"])){
                $cc++;
                $tcc=$this->padString($cc . "","0",$pad);
                $farr["basename"]=$farr["filename"] . "_" . $tcc . "." . $farr["extension"];
            }
            $ret=rename($src,$this->unixPath($dest) . $farr["basename"]);
        }
        if($ret){
            return $this->unixPath($dest) . $farr["basename"];
        }else{
            return $ret;
        }
    }/*}}}*/

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
    }/*}}}*/

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
    }/*}}}*/

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
    }/*}}}*/
    public function noClobberFile($file) /*{{{*/
    {
        $ret=false;
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
                $path=$dir . $filename . "-" . $this->padString($iterator) . "." . $pi["extension"];
                $iterator++;
            }
            $ret=$path;
        }
        return $ret;
    }/*}}}*/

    /** goUpDir {{{
     * strips off the last path component of $pathstr
     * 
     * @param string $pathstr 
     * @access public
     * @return string
     */
    public function goUpDir($pathstr="")
    {
        // 
        $op="";
        $pi=pathinfo($pathstr);
        return $this->unixPath($pi["dirname"]);

        $tmp=explode("/",$pi["dirname"]);
        $c=count($tmp);
        print_r($tmp);
        if($c>0){
            $c--;
            for($x=0;$x<$c;$x++){
                $op.=$this->unixPath($tmp[$x]);
            }
        }
        return $op;
    }/*}}}*/

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
    }/*}}}*/

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
    }/*}}}*/

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
    }/*}}}*/

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
    }/*}}}*/
    public function printableFileSizeArray($xfs) /*{{{*/
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
    }/*}}}*/

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
    }/*}}}*/

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
    }/*}}}*/
    public function cleanDir($dir="",$keepext="",$age=0) /*{{{*/
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
    function padString($string,$pad="0",$length=3,$right=false)
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
    }/*}}}*/
    /** stringToArray {{{
     * returns an array with each element being one character of the string $str, the first element being the length.
     *
     * if $str is empty a 1 element array is returned $op[0]==0;
     * 
     * @param string $str 
     * @access public
     * @return array
     */
    function stringToArray($str="")
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
    }/*}}}*/
    /** arrayToString {{{
     * returns the empty string if $arr is not an array or an empty array
     * 
     * @param array $arr 
     * @access public
     * @return string
     */
    function arrayToString($arr)
    {
        if(is_array($arr)){
            return implode($arr,"");
        }else{
            return "";
        }
    }/*}}}*/
    /** splitAtCapitals {{{
     * inserts spaces before any Capital letters in the string $str
     *
     * The 3 words "of","the","is" also have a space inserted before them
     *
     * @param string $str 
     * @access public
     * @return string
     */
    function splitAtCapitals($str="")
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
    }/*}}}*/
    /** insertSpaceBeforeWord {{{
     * inserts a space before the word $word in string $str
     *
     * @param string $word 
     * @param string $str 
     * @access public
     * @return string
     */
    function insertSpaceBeforeWord($word="",$str="")
    {
        $ret=$str;
        if(is_string($word) && strlen($word) && is_string($str) && strlen($str)){
            $ret=str_replace($word," " . $word,$str);
        }
        return $ret;
    }/*}}}*/
    /** checkTrimString {{{
     * trims white space from both ends of $string
     *
     * returns false if $string is not a string or the empty string
     * 
     * @param string $string 
     * @access public
     * @return bool|string
     */
    function checkTrimString($string)
    {
        $ret=false;
        if(is_string($string)){
            if($tmp=trim($string)){
                $ret=$tmp;
            }
        }
        return $ret;
    }/*}}}*/
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
    function textBetween($subject="",$start="",$end="")
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
    }/*}}}*/
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
    function textOutside($subject="",$start="",$end="")
    {
        $arr=$this->textBetween($subject,$start,$end);
        return $arr["outside"];
    }/*}}}*/
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
    function textInside($subject="",$start="",$end="")
    {
        $arr=$this->textBetween($subject,$start,$end);
        return $arr["between"];
    }/*}}}*/
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
    function iniToDefine($fn,$delim="=")
    {
        $defa=false;
        if(false!==($arr=$this->getFile($fn,CCA_FILE_ASARRAY))){
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
    }/*}}}*/
    /** defineToIni {{{
     * writes out an ini file containing the define statements from the php file $deffn
     * 
     * @param string $deffn 
     * @param string $inifn 
     * @param bool $append 
     * @access public
     * @return bool
     */
    function defineToIni($deffn,$inifn,$append=false)
    {
        $ret=false;
        if(false!==($arr=$this->getFile($deffn,CCA_FILE_ASARRAY))){
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
    }/*}}}*/
    public function checkString($str="")/*{{{*/
    {
        if(is_string($str) && ($cn=strlen($str))){
            return $cn;
        }
        return false;
    }/*}}}*/
    public function checkArray($arr)/*{{{*/
    {
        if(is_array($arr) && ($cn=count($arr))){
            return $cn;
        }
        return false;
    }/*}}}*/
    public function ttt($timestamp=0,$withseconds=false) /*{{{*/
    {
        $ts=intval($timestamp);
        if($withseconds){
            return date("H:i:s",$ts);
        }else{
            return date("H:i",$ts);
        }
    } /*}}}*/
    public function ttd($timestamp=0,$withmonth=false,$withyear=false) /*{{{*/
    {
        $ts=intval($timestamp);
        $dt=date("D d",$ts);
        if($withmonth){
            $dt.=" " . date("m",$ts);
        }
        if($withyear){
            $dt.=" " . date("Y",$ts);
        }
        return $dt;
    } /*}}}*/
    public function tdd($timestamp=0,$withseconds=false,$withmonth=false,$withyear=false) /*{{{*/
    {
        return ttd($timestamp,$withmonth,$withyear) . " " . ttt($timestamp,$withseconds);
    } /*}}}*/
}
?>
