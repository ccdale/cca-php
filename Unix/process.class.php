<?php
/** {{{ heading block
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * lib/process.class.php
 *
 * c.c.allison
 *
 * Started: Sunday 18 January 2009, 22:43:44
 * version: 0.01
 * Last Modified: Sunday  1 June 2014, 09:21:19
 *
 * $Id: process.class.php 32 2009-10-02 21:28:41Z chris $
 */
// }}}

Class Process // {{{
{
    private $pid=0;
    private $cmdline="";
    private $starttime;

    function __construct($pid=0) // {{{
    {
        $this->setPid($pid);
        if($this->exists()){
            $this->getthisCmdline();
            $this->starttime=time();
            return true;
        }else{
            return false;
        }
    } // }}}
    function __destruct() // {{{
    {
    } // }}}
    private function startTime() // {{{
    {
        $this->starttime=time();
    } // }}}
    public function getStartTime() // {{{
    {
        return $this->starttime;
    } // }}}
    public function exists() // {{{
    {
        clearstatcache();
        if(is_dir("/proc/" . $this->pid)){
            return true;
        }else{
            return false;
        }
    } // }}}
    private function getthisCmdline() // {{{
    {
        if($this->exists()){
            $this->cmdline=file_get_contents("/proc/" . $this->pid . "/cmdline");
        }
    } // }}}
    public function getpid() // {{{
    {
        return $this->pid;
    } // }}}
    public function setpid($Pid=0) // {{{
    {
        if(is_numeric($Pid)){
            $this->pid=$Pid;
            $this->getthisCmdline();
        }
    } // }}} 
    public function getCmdline() // {{{
    {
        return trim(str_replace("\0"," ",$this->cmdline));
    } // }}}
    public function killMe() // {{{
    {
        $ret=false;
        if($this->pid){
            $ret=posix_kill($this->pid,SIGTERM);
        }
        return $ret;
    } // }}}
    public function forceKill() // {{{
    {
        if($this->pid){
            posix_kill($this->pid,SIGKILL);
        }
    } // }}}
} // }}}

?>
