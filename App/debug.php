<?php

/** {{{ heading block
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * /home/chris/src/php/cca-php/App/debug.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Saturday 24 January 2009, 17:39:15
 * Version: 0.1
 * Last Modified: Monday  1 February 2010, 17:40:35
 *
 * $Id: debug.php 129 2010-02-01 17:40:56Z chris $
 */
// }}}

function Debug($label,$var=false) // {{{
{
	global $dbg;
    if(isset($argc)){
        $ending="\n";
    }else{
        $ending="<br>\n";
    }
    if($var===false){
        $var=$label;
        $label="";
    }
    $tmp=print_r($var,true);
    if($ending=="<br>"){
        $tmp=nl2br($tmp);
    }
	$dbg.=$label . ": " . $tmp . $ending;
} // }}}

?>
