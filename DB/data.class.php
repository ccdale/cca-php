<?PHP
/*
** data.class.php
** c.c.allison
** started 30/05/03
* Last Modified: Saturday 17 September 2011, 21:55:32
 *
 * $Id: data.class.php 710 2011-09-17 20:55:53Z chris $
*/

require_once "DB/mysql.class.php";

/**
* base data class for the application
*
* 
*
*/
class Data
{
    /**
     * array for the data that this class is for
     *
     * 
     *
     */
    var $arr;
    /**
     * id number in the database
     *
     * 
     *
     */
    var $data_id;
    /**
     * table in the database
     *
     * 
     *
     */
    var $table;
    /**
     * the field in the table that we use to retrieve the data
     *
     * 
     *
     */
    var $chkfield;
    /**
     * the mysql class object
     *
     * 
     *
     */
    var $mx;
    /**
     * shows whether the data in the array is in step with the database
     *
     * not now used
     *
     */
    var $clean=true;
    /**
     * total rows in this table
     *
     */
    var $totalrows;
    /**
     * if this item is part of a mapped set
     */
    var $ismapped;
    /**
     * the map table that this item appears in
     */
    var $maptable;
    /**
     * the field in the maptable that pertains to items of this class
     */
    var $mapfield;
    /**
     * shows whether this item has sub-items via a map table
     */
    var $hassubitems;
    /**
     * shows whether this item is a sub-item via a map table
     */
    var $issubitem;
    /**
     * the field in the map table that holds the  ids of the sub-items
     */
    var $subitemmapfield;
    /**
     * the number of subitems
     */
    var $subitemquantity;
    /**
     * the subitem ids of this item (numeric indexed array)
     */
    var $subarr=array();
    /**
     * number of pages it will take to display all the items in this table
     */
    var $pages;
    /**
     * length of each data page when showing all items in this table
     */
    var $pagelength;
    /**
     * starting point for paged display
     */
    var $startpoint;
    /*
     * field in the table to order results by
     */
    var $orderby;
    var $letter;
    var $byletter;
    private $log=false;
    private $canlog=false;


    // constructor
    /**
     * Class Constructor
     *
     * Start up function for the Data class. Connects to the database and retireves
     * the required row of data if the id variable is set.
     * returns 0 on startup success (i.e. the data is in the database) or -101 if the
     * data is 0, i.e. this is a new whatever and the fields have not been filled in
     * yet.
     *
     * @return int 
     */
    function Data($table_in,$chkfield_in,$data_id=0,$config=false,$log=false)
    {
        $ret=0;
        $this->table=$table_in;
        $this->chkfield=$chkfield_in;
        /*
        if(is_array($config) && isset($config["mysqlhost"])){
            $this->mx=new Mysql($config["mysqldb"],$config["mysqlhost"],$config["mysqluser"],$config["mysqlpass"]);
        }else{
             // * the MYSQLHOST etc must be defined for this to succeed
            $this->mx=new Mysql;
        }
         */
        $this->mx=new Mysql();
        if($data_id)
        {
            $this->data_id=$data_id;
            $this->arr=$this->mx->getSingleRow($this->table,$this->chkfield,$this->data_id);
            $this->data_id=$this->arr[$this->chkfield];
            // unset($this->arr[$this->chkfield];
            $ret=$this->data_id;
        }
        // $this->setPageLength();
        // $this->setStartPoint();
        // $this->setByLetter(false);
        if($log && is_object($log) && get_class($log)=="Logging"){
            $this->log=$log;
            $this->canlog=true;
        }
        return $ret;
    }
    public function __destruct()
    {
        $qarr=$this->mx->getQueryLog();
        $str="Data class: table: " . $this->table . " query log:\n";
        $str.=print_r($qarr,true);
        $this->logg($str,LOG_DEBUG);
    }
    private function logg($msg,$level=LOG_INFO)
    {
        if($this->canlog){
            $this->log->message($msg,$level);
        }
    }
    function deleteMe()
    {
        $ret=false;
        if($this->data_id && $this->chkfield && $this->table){
            $sql="delete from " . $this->table . " where " . $this->chkfield . "=" . $this->data_id;
            $this->mx->query($sql);
            // $tmp=$this->mx->error_number();
            // Debug("mx error number",$tmp);
            if(!$this->mx->error_number()){
                $ret=true;
            }
        }
        return $ret;
    }
    function getTotalRows()
    {
        if(!isset($this->totalrows)){
            $this->queryTotalRows();
        }
        return $this->totalrows;
    }

    /**
     * shows whether the data in the database is in step with the data in the class
     *
     * not now used
     *
     * @return boolean 
     */
    function isClean()
    {
        return $this->clean;
    }

    /**
     * updates the row in the database with the new data
     *
     * or creates it if it doesn't exist
     *
     * @return void 
     */
    function updateDB()
    {
        // debug("in updatedb",1);
        // debug("updatedb: data_id",$this->data_id);
        $this->data_id=$this->mx->setRecord(
            $this->arr,$this->table,$this->data_id,$this->chkfield);
        // debug("updatedb: data_id",$this->data_id);
        $ret=($this->mx->success() ? 0 : 1);
        // debug("updatedb: ret",$ret);
        $this->queryTotalRows();
        return $ret;
    }
    function queryTotalRows()
    {
        $sql = "SELECT count(*) as 'totalrows' FROM " . $this->table . " WHERE 1";
        $tmp=$this->mx->query($sql);
        $tarr=$this->mx->getRow(MYSQL_ASSOC);
        $this->totalrows=$tarr['totalrows'];
    }

    /**
     * changes the current data part $part to be $data
     *
     * 
     *
     * @return void 
     */
    function setData($part,$data)
    {
        if(is_string($data)){
            $this->arr[$part]=addslashes($data);
        }else{
            $this->arr[$part]=$data;
        }
        return $this->updateDB();
    }
    /**
     * synonym for setData
     *
     * 
     *
     */
    function setPart($part,$data)
    {
        return $this->setData($part,$data);
    }

    /**
     * returns the data part $part
     *
     * 
     *
     * @return mixed 
     */
    function getData($part)
    {
        return trim(stripslashes($this->arr[$part]));
    }
    /**
     * synonym for getData
     *
     * 
     *
     */
    function getPart($part)
    {
        return $this->getData($part);
    }
    /**
     * sets the complete data array to be $arr_in
     *
     * 
     *
     */
    function setAData($arr_in)
    {
        $this->arr=$arr_in;
        return $this->updateDB();
    }
    /**
     * synonym for setAData
     *
     * 
     *
     */
    function setAPart($arr_in)
    {
        return $this->setAData($arr_in);
    }
    /**
     * returns the complete data array
     *
     * 
     *
     * @return mixed array
     */
    function getAData()
    {
        return $this->arr;
    }
    /**
     * synonym for getAData
     *
     * 
     *
     */
    function getAPart()
    {
        return $this->getAData();
    }
    /**
     * unused
     *
     * unused
     *
     * @return mixed 
     */
    function getDataEx($table,$field,$part)
    {
        if($tmp=$this->getData($part))
        {
            return $this->mx->getField($field,$table,$part,$tmp);
        }else{
            return false;
        }
    }

    /**
     * unused
     *
     * unused
     *
     * @return void 
     */
    function setDataEx($table,$field,$part)
    {
        if($tmp=getDataEx($table,$field,$part))
        {
            $this->arr[$field]=$tmp;
        }
    }
    function getTableAll()
    {
        return $this->mx->getAllResults("*",$this->table,1);
    }
    function padString($string,$pad=" ",$length=2,$bLeft=true)
    {
        $tmp=$string;
        while(strlen($tmp)<$length)
        {
            if($bLeft)
            {
                $tmp=$pad . $tmp;
            }else{
                $tmp.=$pad;
            }
        }
        return $tmp;
    }
    function nPadStr($string,$length=2)
    {
        return $this->padString($string,"0",$length);
    }
    function makeExist($al,$fld)
    {
        // debug("makeexist - fld: ",$fld);
        // debug("makeexist - al: ",$al);
        $ret=false;
        $carr=$this->mx->getRecord($this->table,array($fld=>$al));
        // debug("getRecord returned: ",$carr);
        // debug("num rows: ",$this->mx->numRows());
        if(is_array($carr) && $this->mx->numRows()){
            // $this->setAData($carr);
            // debug("positive",1);
            $this->arr=$carr;
            $this->data_id=$this->arr[$this->chkfield];
            $ret=true;
        }else{
            // debug("negative",1);
            $this->setData($fld,$al);
        }
        return $ret;
    }
    function giveAll($paged=true)
    {
        // debug("startpoint",$this->startpoint);
        $ob="";
        $ex="";
        if(strlen($this->orderby)){
            $ob="order by '" . $this->orderby . "' ";
        }
        $ex=$ob . " limit " . $this->startpoint . "," . $this->pagelength;
        if($paged){
            if($this->byletter){
                $tarr=$this->mx->searchStartsWith("*",$this->table,$this->orderby,chr($this->letter),$ex);
                $this->totalrows=$tarr["totalrows"];
                return $tarr;
            }else{
                return $this->mx->getAllResults("*",$this->table,1,0,$ex);
            }
        }else{
            if($this->byletter){
                $tarr=$this->mx->searchStartsWith("*",$this->table,$this->orderby,chr($this->letter),$ob);
                $this->totalrows=$tarr["totalrows"];
                return $tarr;
            }else{
                return $this->mx->getAllResults("*",$this->table,1,0,$ob);
            }
        }
    }
    function setByLetter($byletter=true)
    {
        $this->byletter=$byletter;
    }
    function setLetter($letter)
    {
        $this->letter=$letter;
    }
    function setStartPoint($sp=0)
    {
        $this->startpoint=$sp;
    }
    function setPageLength($pl=DB_ITEMSPERPAGE)
    {
        $this->pagelength=$pl;
        $tr=$this->getTotalRows();
        $this->pages=$tr/$pl;
        if($tr % $pl){
            $this->pages+=1;
        }
    }
    /**
     * sets up mapping to sub items or parent items
     * $mt is the map table
     * $mf is the map field for this item
     * $smf is the sub item map field
     * $issi - is sub item t/f
     * $hassi - has sub items t/f
     */
    function setMapping($mt,$mf,$smf="",$issi=false,$hassi=true)
    {
        $this->maptable=$mt;
        $this->mapfield=$mf;
        $this->subitemmapfield=$smf;
        $this->hassubitems=$hassi;
        $this->issubitem=$issi;
        if($this->hassubitems && $this->maptable && $this->mapfield){
            $sql="select count(*) as subitems from ";
        }
    }
    function setOrderBy($orderby)
    {
        $this->orderby=$orderby;
    }
    function fieldData()
    {
        $tarr=false;
        if($this->table){
            $qtype=$this->mx->query("show columns from " . $this->table);
            $cn=$this->mx->numRows();
            if($cn){
                $tarr=array();
                while($arr=$this->mx->getRow(MYSQL_ASSOC)){
                    $tarr[]=$arr;
                }
                if(count($tarr)==0){
                    $tarr=false;
                }
            }
        }
        return $tarr;
    }
    function fieldNames()
    {
        if(false!==($farr=$this->fieldData())){
            $tarr=array();
            foreach($farr as $valarr){
                $tarr[]=$valarr["Field"];
            }
            return $tarr;
        }
        return false;
    }
}
?>
