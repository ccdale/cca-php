<?php

/**
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * filename
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Tuesday 29 September 2009, 13:47:44
 * Last Modified: Tuesday 29 September 2009, 14:29:17
 * Version: $Id$
 */

require_once "file.php";

class Epgdb
{
    private $err=false;
    private $errmsg="";
    private $dbfile="";
    private $db=false;

    public function __construct($dvbstreamerhome,$adapternum=0)
    {
        $this->dbfile=unixPath($dvbstreamerhome) . "epg" . $adapternum . ".db";
        if(false!==checkFile($this->dbfile,CCA_FILE_EXIST)){
            try {
                $this->db=new PDO("sqlite:" . $this->dbfile);
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
    public function getErrorMsg()
    {
        return $this->errmsg;
    }
    public function isError()
    {
        return $this->err;
    }
    public function query($sql="")
    {
        $ret=false;
        if(is_string($sql) && $sql){
            if(is_object($this->db)){
                $res=$this->db->query($sql);
                if(is_object($res)){
                    $ret=$res->fetchAll(PDO::FETCH_ASSOC);
                }
                $res=null;
            }
        }
        return $ret;
    }
    public function createEUID($netid,$tid,$servid,$eventid)
    {
        $hnid=dechex($netid);
        $htid=dechex($tid);
        $hsid=dechex($servid);
        $hevid=dechex($eventid);
        return gmp_strval(gmp_init("0x" . $hnid.$htid.$hsid.$hevid));
    }
}
?>
