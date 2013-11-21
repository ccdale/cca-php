<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Sunday 12 June 2011, 15:57:36
 * Last Modified: Tuesday 14 June 2011, 05:28:56
 * Version: $Id: convertts.class.php 649 2011-06-15 10:59:45Z chris $
 */

require_once "Shell/extern.class.php";
require_once "DB/mysql.class.php";
require_once "DVB/tsfile.class.php";

class ConvertTs extends Extern
{
    private $config=false;
    private $tsfile=false;
    private $files=array();
    private $mx=false;

    public function __construct($id=0,$log=false,$config=false)
    {
        $this->config=$config;
        parent::__construct("",$log,array("projectx"=>false,"mplex"=>false,"spumux"=>false,"pxsup2dast"=>false,"ffprobe"=>false));
        $id=intval($id);
        // $id is the id of a progrec entry which maps to one or more tsfiles
        if($id){
            $this->tsfile=new TSFILE($id,$this->log,$this->config);
        }
    }
    public function __destruct()
    {
        $this->logg("_destruct()",LOG_DEBUG);
    }
    private function editWithPx()
    {
        $cmd=$this->progs["projectx"] . " -gui " . $this->tsfile->getFqfn();
    }
}
?>
