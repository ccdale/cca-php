<?php

/** {{{ heading block
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * /home/chris/src/php/cca-php/App/www.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Saturday 24 January 2009, 17:37:47
 * Version: 0.1
 * Last Modified: Sunday 13 July 2014, 10:17:06
 *
 * $Id: www.php 485 2010-08-19 15:11:31Z chris $
 */
// }}}

/** // {{{ funtion GP($var,$trusted=false)
* returns the contents of the GET or POST variable
* favours POST over GET
*/
function GP($var,$trusted=false)
{
    $op=false;
    if(isset($_GET[$var])){
        $op=$_GET[$var];
    }
	if(isset($_POST[$var]))
	{
		$op=$_POST[$var];
	}
	if(is_string($op))
	{
        $op=trim(urldecode($op));
        if(!$trusted){
            $op=htmlspecialchars($op,ENT_QUOTES);
        }
		// $op=addslashes($op);
	}
	return $op;
} // }}}
/** // {{{ function getDefaultInt($var,$default)
 * returns the contents of the GET or POST variable
 * as an int, or the default value if it isn't set
 */
function getDefaultInt($var,$default)
{
    if(false!==($tmp=GP($var))){
        $tmp=intval($tmp);
        if($tmp>0){
            $op=$tmp;
        }else{
            $op=$default;
        }
    }
} // }}}
/* // {{{ function GPA($namearr)
 * returns an array containing 
 * selected entries from the $_GET and $_POST arrays
 * the selected keys are in $namearr
 */
function GPA($namearr)
{
    $arr=false;
    if(is_array($namearr) && count($namearr))
    {
        reset($namearr);
        while(list(,$v)=each($namearr))
        {
            $arr[$v]=GP($v);
        }
    }else{
        if(is_string($namearr))
        {
            $arr[$namearr]=GP($namearr);
        }
    }
    return $arr;
} // }}}
function GPAll($trusted=false) // {{{
{
    $op=array();
    $arr=array_merge($_GET,$_POST);
    foreach($arr as $key=>$val){
        if(is_string($val)){
            $op[$key]=trim(urldecode($val));
            if(!$trusted){
                $op[$key]=htmlspecialchars($op[$key],ENT_QUOTES);
            }
        }
    }
    return $op;
} // }}}
function GPType($var,$type="string",$trusted=false)
{
    $op=GP($var,$trusted);
    switch($type){
    case "int":
        $op=intval($op);
        break;
    case "string":
        $op="$op";
    }
    return $op;
}

?>
