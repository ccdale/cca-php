<?php

/**
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * adapterdb.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Tuesday 29 September 2009, 12:50:41
 * Last Modified: Thursday  1 October 2009, 13:35:42
 * Version: $Id$
 */

require_once "file.php";

class AdapterDb
{
    private $err=false;
    private $errmsg="";
    private $dbfile="";
    private $db=null;
    private $sid=false;
    private $tv=false;
    private $radio=false;

    public function __construct($dvbstreamerhome,$adapternum=0)
    {
        $this->dbfile=unixPath($dvbstreamerhome) . "adapter" . $adapternum . ".db";
        if(false!==checkFile($this->dbfile,CCA_FILE_EXIST)){
            try {
                $this->db=new PDO("sqlite:" . $this->dbfile);
                $this->getSid();
                $this->getTv();
                $this->getRadio();
            }catch (PDOException $e){
                $this->err=true;
                $this->errmsg='Connection failed: ' . $e->getMessage();
            }
        }else{
            $this->err=true;
            $this->errmsg="File not found: " . $this->dbfile;
        }
    }
    public function __destruct()
    {
        $this->db=null;
    }
    /* getErrorMsg  */
    public function getErrorMsg()
    {
        return $this->errmsg;
    }
    /* getErrorNum  */
    public function isError()
    {
        return $this->err;
    }
    /* getServiceIds  */
    public function getServiceIds()
    {
        return $this->sid;
    }
    public function getRadioServices()
    {
        return $this->radio;
    }
    public function getTvServices()
    {
        return $this->tv;
    }
    private function getSid()
    {
        if(is_object($this->db)){
            $res=$this->db->query("select id,name from Services");
            if(is_object($res)){
                $arr=$res->fetchAll(PDO::FETCH_ASSOC);
                if(is_array($arr)){
                    $sid=array();
                    foreach($arr as $row){
                        $sid[$row["name"]]=$row["id"];
                    }
                    $this->sid=$sid;
                    unset($sid);
                    unset($arr);
                }
            }
            $res=null;
        }
    }
    private function getTv()
    {
        if(is_object($this->db)){
            $res=$this->db->query("select id,name from Services where type=0 and ca=0");
            if(is_object($res)){
                $arr=$res->fetchAll(PDO::FETCH_ASSOC);
                if(is_array($arr)){
                    $sid=array();
                    foreach($arr as $row){
                        $sid[$row["name"]]=$row["id"];
                    }
                    $this->tv=$sid;
                    unset($sid);
                    unset($arr);
                }
            }
            $res=null;
        }
    }
    private function getRadio()
    {
        if(is_object($this->db)){
            $res=$this->db->query("select id,name from Services where type=1 and ca=0");
            if(is_object($res)){
                $arr=$res->fetchAll(PDO::FETCH_ASSOC);
                if(is_array($arr)){
                    $sid=array();
                    foreach($arr as $row){
                        $sid[$row["name"]]=$row["id"];
                    }
                    $this->radio=$sid;
                    unset($sid);
                    unset($arr);
                }
            }
            $res=null;
        }
    }
}
?>
