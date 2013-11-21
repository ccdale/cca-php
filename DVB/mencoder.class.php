<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Monday 22 August 2011, 13:03:46
 * Last Modified: Monday 22 August 2011, 13:29:29
 * Version: $Id$
 */

require_once "Shell/extern.class.php";
require_once "DVB/ffprobe.class.php";
require_once "file.php";

class Mencoder extends Extern
{
    public function __construct($log=false)
    {
        parent::__construct("",$log,array("mencoder"=>false));
        if($this->progs["mencoder"]!==false){
            $this->logg("__construct(): Mencoder binary found ok",LOG_DEBUG);
            return true;
        }
        $this->logg("__construct(): Mencoder binary not found",LOG_WARNING);
    }
    public function __destruct()
    {
    }
}
?>
