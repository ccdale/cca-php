<?php

/** {{{ heading block
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * /home/chris/src/php/cca-php/local.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Sunday 25 January 2009, 12:50:40
 * Version: 0.1
 * Last Modified: Tuesday 22 September 2009, 12:22:45
 *
 * $Id: local.php 5 2009-09-23 14:15:01Z chris $
 */
// }}}

function isPcIdle() // {{{
{
    $ret=false;
    $cmd='DISPLAY=":0.0"; xscreensaver-command -time 2>/dev/null|grep "screen blanked"';
    $tmp=exec($cmd);
    if(strlen($tmp)){
        $ret=true;
    }
    return $ret;
} // }}}
?>
