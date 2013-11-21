<?php
/** {{{ heading block
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * lib/processtable.class.php
 *
 * c.c.allison
 *
 * Started: Sunday 18 January 2009, 23:20:44
 * version: 0.01
 * Last Modified: Tuesday 22 September 2009, 12:26:17
 *
 * $Id: processtable.class.php 5 2009-09-23 14:15:01Z chris $
 */
// }}}

require_once "Unix/process.class.php";

Class ProcessTable // {{{
{
    /**
     * processes 
     * 
     * @var array
     * @access private
     */
    private $processes=array();

    function __construct() // {{{
    {
        $this->getProcessTable();
    } // }}}
    private function getProcessTable() // {{{
    {
        $this->processes=array();
        clearstatcache();
        $d=dir("/proc");
        while(false !== ($entry=$d->read())){
            if($entry!="." && $entry!=".."){
                if(is_dir("/proc/" . $entry)){
                    // if $entry is anything other than a number
                    // this will evaluate to 0
                    if(($entry+0)>0){
                        $this->processes[$entry]=new Process($entry);
                    }
                }
            }
        }
        $d->close();
    } // }}}
    private function procArrAsStr($arr,$nul=false) // {{{
    {
        $ret="";
        foreach($arr as $p){
            if(strlen($p->getCmdline())){
                $ret.=$p->getPid() . " " . $p->getCmdline() . "\n";
            }elseif($nul!==false){
                $ret.=$p->getPid() . " " . $p->getCmdline() . "\n";
            }
        }
        return $ret;
    } // }}}
    public function procExists($pid=0) // {{{
    {
        $this->getProcessTable();
        if(isset($this->processes[$pid])){
            return true;
        }else{
            return false;
        }
    } // }}}
    public function procCmd($pid=0) // {{{
    {
        if($this->procExists($pid)){
            return $this->processes[$pid]->getCmdline();
        }else{
            return false;
        }
    } // }}}
    public function getProcesses() // {{{
    {
        return $this->processes;
    } // }}}
    public function getProcsAsStr() // {{{
    {
        return $this->procArrAsStr($this->processes);
    } // }}}
    public function findProc($proc="") // {{{
    {
        $ret=false;
        if(is_string($proc) && strlen($proc)){
            $this->getProcessTable();
            foreach($this->processes as $p){
                if(false!==strpos($p->getCmdline(),$proc)){
                    $ret[]=$p;
                }
            }
        }
        return $ret;
    } // }}}
    public function findProcAsStr($proc) // {{{
    {
        $ret="";
        if(false!==($arr=$this->findProc($proc))){
            $ret=$this->procArrAsStr($arr);
        }
        return $ret;
    } // }}}
    function __destruct() // {{{
    {
    } // }}}
} // }}}


?>
