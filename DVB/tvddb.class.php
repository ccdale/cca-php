<?php

/**
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * tvddb.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Thursday  1 October 2009, 12:22:51
 * Last Modified: Thursday  1 October 2009, 12:26:21
 * Version: $Id: tvddb.class.php 26 2009-10-01 11:36:35Z chris $
 */

require_once "file.php";

class TvdDb
{
    private $err=false;
    private $errmsg="";
    private $dbfile="";
    private $db=false;

    public function __construct($tvdhome)
    {
        $this->dbfile=unixPath($tvdhome) . "tvd.db";
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
}
?>
