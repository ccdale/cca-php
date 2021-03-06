<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * logging.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Saturday 19 December 2009, 07:00:46
 * Last Modified: Sunday 21 July 2013, 12:56:34
 * Version: $Id: logging.class.php 447 2010-07-28 06:41:05Z chris $
 */


class WLogging
{
    private $toconsole=true;
    private $minlevel=0;
    private $debugtoconsole=false;
    private $consoletimestamp=true;
    private $tracelevel=0;
    private $tofile=false;
    private $debugfile=false;
    private $fp;
    private $dfp;

    /* __construct  */
    public function __construct($minlevel=LOG_INFO,$tracelevel=0,$tofile=false,$debugfile=false) // {{{
    {
        $this->minlevel=$minlevel;
        /*
         * set $tracelevel to 0,1 or 2
         * then, DEBUG level messages will contain the calling
         * stack trace
         * tracelevel=0: no stack trace
         * tracelevel=1: caller function/class/file/line number
         * tracelevel=2: full stack trace
         */
        $this->tracelevel=$tracelevel;
        /*
         * $tofile reroutes the messages destined for syslog to the named file
         * nothing will go to syslog
         * if $debugfile is set then debug level messages go to that file
         */
        $this->tofile=$tofile;
        $this->debugfile=$debugfile;
        if(false!==$this->tofile){
            $this->fp=fopen($this->tofile,"a");
            if(false!==$this->debugfile){
                $this->dfp=fopen($this->debugfile,"a");
            }
        }
    } // }}}
    /* __destruct  */
    public function __destruct() // {{{
    {
        // $this->debug("Closing log");
        if(false!=$this->tofile){
            fclose($this->fp);
            if(false!=$this->debugfile){
                fclose($this->dfp);
            }
        }else{
            closelog();
        }
    } // }}}
    private function formatMessage($msg,$level) // {{{
    {
        if(!is_string($msg)){
            $msg=print_r($msg,true);
        }
        if($level==LOG_DEBUG){
            $msg="    " . $msg;
        }
        return $msg;
    } // }}}
    /* pretty print the stack trace */
    private function formatStackTrace($trace)/*{{{*/
    {
        $op="";
        if($this->tracelevel>0){
            // remove the first 2 entries in the array as they 
            // refer to this file and the base.class.php that
            // called it
            $junk=array_shift($trace);
            $junk=array_shift($trace);
            $caller=array_shift($trace);
            if(isset($caller["function"])){
                $op="In: " . $caller["function"];
                if(isset($caller["class"]) && strlen($caller["class"])){
                    $op.=" in class: " . $caller["class"];
                }
                $op.=" Line: " . $caller["line"];
                $op.=" file: " . $caller["file"] . PHP_EOL;
                $cn=count($trace);
                if($cn && $this->tracelevel>1){
                    foreach($trace as $k=>$v){
                        $op.="   func: " . $v["function"] . " class: " . $v["class"] . " line: " . $v["line"] . " file: " . $v["file"] . PHP_EOL;
                    }
                }
            }
        }
        return $op;
    }/*}}}*/
    /* log  */
    public function message($msg="",$level=LOG_INFO) // {{{
    {
        $msg=$this->formatMessage($msg,$level);
        /*
        if(!is_string($msg)){
            $msg=print_r($msg,true);
        }
         */
        if($level<=$this->minlevel){
            if($level==LOG_DEBUG){
                if($this->tracelevel>0){
                    $trace=debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,0);
                    if(false!==$trace){
                        $str=$this->formatStackTrace($trace);
                        $msg.="\n" . $str;
                    }
                }
            }
            if(false!==$this->tofile){
                $tmp=date("D d H:i:s");
                if($level=LOG_DEBUG){
                    fwrite($this->dfp,$tmp . " " . $msg . "\n");
                }else{
                    fwrite($this->fp,$tmp . " " . $msg . "\n");
                }
            }else{
                syslog($level,$msg);
            }
            /*
            $this->console($msg,$level,false);
            if($this->toconsole){
                if(isset($_SERVER["TERM"])){
                    if($level<LOG_DEBUG || $this->debugtoconsole){
                        $tmp=date("D d H:i:s");
                        print $tmp . " " . $msg . "\n";
                    }
                }
            }
             */
        }
    } // }}}
    public function info($msg) // {{{
    {
        $this->message($msg,LOG_INFO);
    } // }}}
    public function debug($msg) // {{{
    {
        $this->message($msg,LOG_DEBUG);
    } // }}}
    public function warn($msg) // {{{
    {
        $this->message($msg,LOG_WARNING);
    } // }}}
    public function warning($msg) // {{{
    {
        $this->message($msg,LOG_WARN);
    } // }}}
    public function error($msg) // {{{
    {
        $this->message($msg,LOG_ERR);
    } // }}}
    public function console($msg="",$level=LOG_INFO,$formatmsg=true) // {{{
    {
        $colouroff=CCA_COff;
        $colouron=$this->getColour($level);
        if($formatmsg){
            $msg=$this->formatMessage($msg,$level);
        }
        /*
        if(!is_string($msg)){
            $msg=print_r($msg,true);
        }
         */
        if($level<=$this->minlevel){
            /*
            if($level==LOG_DEBUG){
                $msg="    " . $msg;
            }
             */
            if($this->toconsole){
                if(isset($_SERVER["TERM"])){
                    if($level<LOG_DEBUG || $this->debugtoconsole){
                        if($this->consoletimestamp){
                            $tmp=date("D d H:i:s");
                        }else{
                            $tmp="";
                        }
                        print $tmp . $colouron . " " . $msg . $colouroff . "\n";
                    }
                }
            }
        }
    } // }}}
    private function getColour($level=LOG_INFO) // {{{
    {
        $colouroff=CCA_COff;
        $colouron=CCA_CWhite;
        switch($level){
        case LOG_EMERG:
            $colouron=CCA_CRed;
            break;
        case LOG_ALERT:
            $colouron=CCA_CRed;
            break;
        case LOG_CRIT:
            $colouron=CCA_CRed;
            break;
        case LOG_ERR:
            $colouron=CCA_CRed;
            break;
        case LOG_WARNING:
            $colouron=CCA_CYellow;
            break;
        case LOG_NOTICE:
            $colouron=CCA_CGreen;
            break;
        case LOG_INFO:
            $colouron=CCA_CWhite;
            break;
        case LOG_DEBUG:
            $colouron=CCA_CGrey;
            break;
        }
        return $colouron;
    } // }}}
    public function colour($msg="",$level=LOG_INFO) // {{{
    {
        $colouroff=CCA_COff;
        $colouron=$this->getColour($level);
        $msg=$this->formatMessage($msg,$level);
        /*
        $colouron=CCA_CWhite;
        switch($level){
        case LOG_EMERG:
            $colouron=CCA_CRed;
            break;
        case LOG_ALERT:
            $colouron=CCA_CRed;
            break;
        case LOG_CRIT:
            $colouron=CCA_CRed;
            break;
        case LOG_ERR:
            $colouron=CCA_CRed;
            break;
        case LOG_WARNING:
            $colouron=CCA_CYellow;
            break;
        case LOG_NOTICE:
            $colouron=CCA_CGreen;
            break;
        case LOG_INFO:
            $colouron=CCA_CWhite;
            break;
        case LOG_DEBUG:
            $colouron=CCA_CGrey;
            break;
        }
         */
        /*
        if(!is_string($msg)){
            $msg=print_r($msg,true);
        }
         */
        if($level<=$this->minlevel){
            /*
            if($level==LOG_DEBUG){
                $msg="    " . $msg;
            }
             */
            syslog($level,$msg);
            $this->console($msg,$level,false);
            /*
            if($this->toconsole){
                if(isset($_SERVER["TERM"])){
                    $tmp=date("D d H:i:s");
                    print $tmp . $colouron . " " . $msg . $colouroff . "\n";
                }
            }
             */
        }
    } // }}}
    public function getMinLevel() // {{{
    {
        return $this->minlevel;
    } // }}}
    public function setMinLevel($level=LOG_INFO) // {{{
    {
        if($level<=LOG_DEBUG && $level>=LOG_EMERG){
            $this->minlevel=$level;
        }
    } // }}}
    public function getToConsole() // {{{
    {
        return $this->toConsole;
    } // }}}
    public function setToConsole($toConsole=true) // {{{
    {
        if(is_bool($toConsole)){
            $this->toConsole=$toConsole;
        }
    }  // }}}
}
?>
