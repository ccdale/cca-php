<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * epg.class.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Sunday 15 June 2014, 09:52:57
 * Last Modified: Sunday 15 June 2014, 09:57:32
 * Revision: $Id$
 * Version: 0.00
 */

require_once "DVB2/dvbctrl.class.php";

class EPG extends DVBCtrl
{
    private $epgcapturing=false;

    public function __construct($logg=false,$host="",$user="",$pass="",$adaptor=0,$dvb=false)/*{{{*/
    {
        parent::__construct($logg,$host,$user,$pass,$adaptor,$dvb);
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    private function rcvEpgEvent()/*{{{*/
    {
        $ret=false;
        $msg="";
        $eventstarted=false;
        $incompleteevent=true;
        if($this->fp){
            while($incompleteevent){
                if($eventstarted){
                    $waitfor="</event>";
                }else{
                    $waitfor="<event ";
                }
                $tmp=fgets($this->fp);
                if(false!==($pos=strpos($tmp,$waitfor))){
                    if($eventstarted){
                        $msg.=$tmp;
                        $eventstarted=false;
                        $incompleteevent=false;
                    }else{
                        $msg=$tmp;
                        $eventstarted=true;
                    }
                }else{
                    if($eventstarted){
                        $msg.=$tmp;
                    }
                }
            }
            $ret=$msg;
        }
        return $ret;
    }/*}}}*/
    public function epgCapStart()/*{{{*/
    {
        if(false!==($junk=$this->request("epgcapstart"))){
            $this->epgcapturing=true;
            $this->sendData("epgdata");
            return true;
        }
        return false;
    }/*}}}*/
    public function epgCapStop()/*{{{*/
    {
        if(false!==($junk=$this->request("epgcapstop"))){
            $this->epgcapturing=false;
            $this->disconnect();
            $this->connect();
            return true;
        }
        return false;
    }/*}}}*/
    public function getEpgEvent()/*{{{*/
    {
        return $this->rcvEpgEvent();
    }/*}}}*/
}
?>
