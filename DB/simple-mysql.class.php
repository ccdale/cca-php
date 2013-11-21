<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * Started: Monday 23 July 2012, 13:41:11
 * Last Modified: Friday 11 October 2013, 13:10:24
 * Version: $Id: mysql.class.php 6369 2012-07-27 08:20:55Z pfcallison $
 */

require_once "base.class.php";

/** MySql Class
 * simple class to connect and do db stuff
 * with mysql
 */
class MySql extends Base
{
    private $conn;
    private $rs;
    private $dbhost;
    private $dbdb;
    private $dbuser;
    private $dbpass;
    private $canconnect=false;
    private $connected=false;
    private $selected=true;

    public function __construct($logg=false,$host="",$user="",$pass="",$db="",$force=false) /*{{{*/
    {
        parent::__construct($logg);
        if($this->ValidStr($host)){
            $this->dbhost=$host;
        }elseif(defined("MYSQLHOST")){
            $this->dbhost=MYSQLHOST;
        }else{
            $this->dbhost="localhost";
        }
        if($this->ValidStr($user)){
            $this->dbuser=$user;
        }elseif(defined("MYSQLUSER")){
            $this->dbuser=MYSQLUSER;
        }else{
            $this->dbuser="";
        }
        if($this->ValidStr($pass)){
            $this->dbpass=$pass;
        }elseif(defined("MYSQLPASS")){
            $this->dbpass=MYSQLPASS;
        }else{
            $this->dbpass="";
        }
        if($this->ValidStr($db)){
            $this->dbdb=$db;
        }elseif(defined("MYSQLDB")){
            $this->dbdb=MYSQLDB;
        }else{
            $this->dbdb="";
        }
        if($this->ValidStr($this->dbhost) && $this->ValidStr($this->dbuser) && $this->ValidStr($this->dbpass) && $this->ValidStr($this->dbdb)){
            $this->canconnect=true;
        }elseif($this->force){
            $this->canconnect=true;
        }
        try {
            $this->conn=mysql_connect($this->dbhost,$this->dbuser,$this->dbpass);
            $this->connected=true;
            $this->debug("Connected to db host ok: " . $this->dbhost);
        }catch (Exception $e){
            $this->error('Caught exception when connecting to db (' . $this->dbhost . '): ' .  $e->getMessage());
            $this->connected=false;
        }
        try {
            mysql_select_db($this->dbdb);
            $this->selected=true;
            $this->debug("DB selected ok: " . $this->dbdb);
        }catch (Exception $e){
            $this->error('Caught exception in selecting db (' . $this->dbdb . '): ' .  $e->getMessage());
        }
    } // }}}
    public function __destruct() /*{{{*/
    {
        $this->closedb();
        parent::__destruct();
    } // }}}
    public function closedb() // {{{
    {
        if($this->conn){
            try{
                @mysql_close($this->conn);
            }catch(Exception $e){
                $this->debug($e->getMessage());
            }
        }
    } // }}}
    public function amOK() // {{{
    {
        if($this->connected && $this->selected){
            return true;
        }
        return false;
    } // }}}
    public function query($sql="") // {{{
    {
        $this->rs=null;
        if($this->amOK() && $this->ValidStr($sql)){
            $this->debug("Query: $sql");
            $this->rs=mysql_query($sql);
            if(false===$this->rs){
                $this->error("Query error: " . mysql_errno() . ": " . mysql_error());
            }
        }else{
            $this->warning("mysql class not ok, or sql not a valid str");
            $tmp=print_r($sql,true);
            $this->warning("print_r(\$sql): $tmp");
        }
        return $this->rs;
    } // }}}
    public function insertQuery($sql="") // {{{
    {
        /*
         * returns insert id or false for insert queries
         */
        $ret=$this->query($sql);
        if($ret){
            $ret=mysql_insert_id();
        }else{
            $str=mysql_error($this->conn);
            $this->debug($str);
        }
        return $ret;
    } // }}}
    public function arrayQuery($sql="") // {{{
    {
        $ret=false;
        $this->query($sql);
        if($this->rs){
            $cn=mysql_num_rows($this->rs);
            $this->debug("$cn rows returned");
            if($cn>0){
                $ret=array();
                while(false!=($arr=mysql_fetch_assoc($this->rs))){
                    $ret[]=$arr;
                }
            }
        }
        return $ret;
    } // }}}
}
?>
