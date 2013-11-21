<?PHP
/**
* mysql.class.php
*
* c. c. allison
* started 29/05/03
* instantiates a class for MySql access
*
* Last Modified: Saturday 17 September 2011, 21:33:59
 *
 * $Id: mysql.class.php 708 2011-09-17 20:34:28Z chris $
*/
if(!defined("MYSQLINSERT")){
    define("MYSQLINSERT",4);
}
if(!defined("MYSQLDELETE")){
    define("MYSQLDELETE",3);
}
if(!defined("MYSQLSELECT")){
    define("MYSQLSELECT",1);
}
if(!defined("MYSQLUPDATE")){
    define("MYSQLUPDATE",2);
}
if(!defined("MYSQLCREATE")){
    define("MYSQLCREATE",5);
}
if(!defined("MYSQLDROP")){
    define("MYSQLDROP",6);
}
class Mysql
{
	// internal variables
	// database
	var $_db;
	// hostname
	var $_host;
	// mysql username
	var $_user;
	// mysql password
	var $_pass;
	
	// link id
	var $_link_id;
	
	// error code to indicate failure
	// -2 - unable to connect to mysql server (initial setting)
	// -1 - unable to open selected database
	//  0 - success, no failure
	var $_error_code=-2;
	
	// mysql query error details
	var $_mysql_errno;
	var $_mysql_error;
	
	// result id of the query
	var $_result_id;
	// number of rows in the result set or the number of affected rows
	// for a delete, insert or update query
	var $_num_rows;
	// active row in the reult set
	var $_row;
	
	// type of query
	// 0 - error in query
	// 1 - select
	// 2 - update
	// 3 - delete
	// 4 - insert
	var $_query_type=0;
	
	var $_insert_id;
	
    var $querylog;
	
	// class constructor
	/**
 * instatiates the class
 *
 * 
 *
 */
 function Mysql($db="", $host="", $user="", $pass="")
	{
		//var $_db=$site["mysql"]["db"];
	// hostname
	//var $_host=$site["mysql"]["host"];
	// mysql username
	//var $_user=$site["mysql"]["user"];
	// mysql password
	//var $_pass=$site["mysql"]["pass"];
        // debug("db",$db);
		if($db)
		{
			$this->_db=$db;
		}else{
            if(defined("MYSQLDB")){
                $this->_db=MYSQLDB;
            }
		}
        // debug("host",$host);
		if($host)
		{
			$this->_host=$host;
		}else{
            if(defined("MYSQLHOST")){
                $this->_host=MYSQLHOST;
            }
		}
        // debug("user",$user);
		if($user)
		{
			$this->_user=$user;
		}else{
            if(defined("MYSQLUSER")){
                $this->_user=MYSQLUSER;
            }
		}
        // debug("pass",$pass);
		if($pass)
		{
			$this->_pass=$pass;
		}else{
            if(defined("MYSQLPASS")){
                $this->_pass=MYSQLPASS;
            }
		}
		$this->_link_id=mysql_connect($this->_host,$this->_user,$this->_pass);
		$this->_mysql_errno=mysql_errno();
		if(!$this->_mysql_errno)
		{
			// we succeeded in linking to the server so increment the error_code
			$this->_error_code++;
                // debug("errcode",$this->_error_code);
			if(mysql_select_db($this->_db,$this->_link_id))
			{
				// we succeeded in opening the database so increment the error_code again
				$this->_error_code++;
                // debug("errcode",$this->_error_code);
			}
		}
        $this->querylog=array();
	}
	
	// public functions
	
	// public function success()
	// returns the error code if the initialisation or query failed
	/**
 * returns the last error-code if it exists
 *
 * 
 *
 * @return boolean 
 */
 function success()
	{
		return $this->_error_code;
	}
	
	// public function error_number()
	// returns the mysql error number if there was a query error
	/**
 * mysql error number
 *
 * 
 *
 * @return int 
 */
 function error_number()
	{
		return $this->_mysql_errno;
	}
	
	// public function error_text()
	// returns the mysql error text if there was a query error
	/**
 * mysql error text
 *
 * 
 *
 * @return string 
 */
 function error_text()
	{
		return $this->_mysql_error;
	}
	
	// public function query($query="")
	// performs the query held in $query
	// returns the query type as an integer if successful
	// or 0 if the query failed
	/**
 * executes the query $query
 *
 * returns the type of query as an integer
 *
 * @return int 
 */
 function query($query="")
	{
		$start = $this->getTime();
		if($query)
		{
            // debug("query",$query);
			$this->_result_id=mysql_query($query,$this->_link_id);
			$this->_mysql_errno=mysql_errno($this->_link_id);
			if(!$this->_mysql_errno)
			{
				if(ereg("^insert", strtolower($query)))
				{
					// twas an insert type query
					$this->_query_type=4;
				}
				elseif(ereg("^select", strtolower($query)) || ereg("^show",strtolower($query)))
				{
					// twas a select or show query type
					$this->_query_type=1;
				}
				elseif(ereg("^delete", strtolower($query)))
				{
					// twas a delete query type
					$this->_query_type=3;
				}
				elseif(ereg("^update", strtolower($query)))
				{
					// twas a update query type
					$this->_query_type=2;
				}
                elseif(ereg("^create",strtolower($query)))
                {
                    $this->_query_type=5;
                }
                elseif(ereg("^drop",strtolower($query)))
                {
                    $this->_query_type=6;
                }

				if($this->_query_type>0)
				{
					if($this->_query_type>1)
					{
						$this->_num_rows=mysql_affected_rows();
					}elseif($this->_query_type<5){
                        if(is_resource($this->_result_id)){
                            $this->_num_rows=mysql_num_rows($this->_result_id);
                        }else{
                            $this->_num_rows=0;
                        }
					}
					if($this->_query_type==4)
					{
						$this->_insert_id=mysql_insert_id();
					}
				}
			}else{
				$this->_mysql_error=mysql_error();
			}
		}
        if($this->error_text()){
            Debug("Query",$query);
            Debug("Error",$this->error_text());
        }
        $this->logquery($query,$start);
		return $this->_query_type;
	}
	
	// public function getRow()
	// returns the next row in the current select mysql result set
	/**
 * returns one row from the db
 *
 * 
 *
 * @return mixed array 
 */
 function getRow($type=MYSQL_BOTH)
	{
		if($this->_result_id)
		{
			$this->_row=mysql_fetch_array($this->_result_id,$type);
		}else{
			$this->_row=false;
		}
		return $this->_row;
	}
	
	// public function num_rows()
	// returns the number of rows in the result or
	// the number of affected rows by the query
	/**
 * the number of rows in the last result set
 *
 * 
 *
 * @return int 
 */
 function numRows()
	{
		return $this->_num_rows;
	}
	
	// private function to format the where clause
	// from the input array $chkfield, unless $chkfeild is a string
	// in which case it is the only field to check and the value is
	// $id
	/**
 * makes a where clause form the supplied variables
 *
 * if chkfield is an array the result will be key=value, key2=value2 and so on, otherwise the result is chkfield=id
 *
 * @return string 
 */
 function WhereClause($chkfield,$id=0)
	{
		$where="";
		if(is_array($chkfield))
		{
			while(list($k,$v)=each($chkfield))
			{
				if(isset($k) && isset($v))
				{
					if(strlen($where))
					{
						$where.=" and " . $k . "=" . $this->sqlFormatField($v);
					}else{
						$where=$k . "=" . $this->sqlFormatField($v);
					}
				}
			}
		}else{
			if($chkfield)
			{
				if($id)
				{
					$where=$chkfield . "=" . $this->sqlFormatField($id);
				}else{
					$where=$chkfield;
				}
			}
		}
		return $where;
	}	
	
	// public function getField($field,$table,$id,$chkfield)
	// returns the field data from the selected field in the table
	/**
 * returns one field from a table in the db
 *
 * 
 *
 * @return string 
 */
 function getField($field,$table,$chkfield,$id=0)
	{
		$ret=0;
		if($field)
		{
			if($table)
			{
				$where=$this->WhereClause($chkfield,$id);
                // $t_field=$this->sqlFormatField($field);
				if(strlen($where))
				{
					$sql="select $field from $table where $where";
					$qt=$this->query($sql);
					if($qt)
					{
						if($temp_arr=$this->getRow(MYSQL_ASSOC))
						{
							$ret=$temp_arr[$field];
						}	
					}	
				}
			}
		}
		return $ret;
	}
	
	// public function getSingleRow($table,$id,$chkfield)
	// returns an array containing the selected single row from the table
	/**
 * returns a single row from a db table
 *
 * 
 *
 * @return array 
 */
 function getSingleRow($table,$chkfield,$id=0)
	{
		$ret=0;
		if($table)
		{
			if($id)
			{
				$where=$this->WhereClause($chkfield,$id);
				if(strlen($where))
				{
					$sql="select * from $table where $where";
					$qt=$this->query($sql);
					if($qt)
					{
						$ret=$this->getRow(MYSQL_ASSOC);
					}
				}
			}
		}
		return $ret;
	}
	
	// private function sqlFormatField($field)
	// returns a string in the correct form for a sql query
	/**
 * formats the variable type for inclusion in a query string
 *
 * 
 *
 * @return string 
 */
 function sqlFormatField($field)
	{
		if(is_string($field))
		{
            $f=addslashes($field);
			return "'" . $f . "'";
		}
		if(is_numeric($field))
		{
			return $field;
		}
		elseif(empty($field))
		{
			return "null";
		}
		else
		{
			return "'" . $field . "'";
		}
	}
	
	// function explodeData($data_arr)
	// explodes the $data_arr variable into the
	// string form "(key1,key2,key3) values (val1,val2,val3)"
	/**
 * explodes an array into key=val, key2=val2 etc
 *
 * 
 *
 * @return string 
 */
 function explodeData($data_arr)
	{
		$keys="";
		$vals="";
		while(list($key,$value)=each($data_arr))
		{
			// check to weed out numeric based keys
			if(!is_numeric($key))
			{
                // $ky=$this->sqlFormatField($key);
                $ky=$key;
				if($keys)
				{
					$keys.="," . $ky;
				}else{
					$keys=$ky;
				}
				$val=$this->sqlFormatField($value);
				if($vals)
				{
					$vals.="," . $val;
				}else{
					$vals=$val;
				}
			}
		}
		return "(" . $keys . ") values (" . $vals . ")";
	}
	
	// public function explodeUpdate($data_arr)
	// explodes the $data_arr variable into the
	// string form "$key=$value,$key2=$value"
	/**
 * explodes data_arr into "(key,key2) values (val,val2)" form
 *
 * 
 *
 * @return string 
 */
 function explodeUpdate($data_arr)
	{
		$ret="";
		while(list($key,$value)=each($data_arr))
		{
			if(!is_numeric($key))
			{
				if($ret)
				{
					$ret.="," . $key . "=" . $this->sqlFormatField($value);
				}else{
					$ret=$key . "=" . $this->sqlFormatField($value);
				}
			}
		}
		return $ret;
	}
	
	// Public function getRecord($table,$id,$chkfield)
	// returns an array containing the requested record
	/**
 * returns one record form the db
 *
 * 
 *
 * @return array 
 */
 function getRecord($table,$chkfield,$id=0,$format=MYSQL_BOTH)
	{
		$ret=0;
		if($table)
		{
			$where=$this->WhereClause($chkfield,$id);
			if(strlen($where))
			{
				$sql="select * from $table where $where";
                // debug("sql",$sql);
				$qt=$this->query($sql);
				if($qt)
				{
					$ret=$this->getRow($format);
				}	
			}
		}
		return $ret;
	}
	
	// Public function setRecord($data_arr,$table,$id,$chkfield)
	// inserts/creates the requested row in the database
	// $data_arr format is
	// $data_arr["field name"]=value
	/**
 * updates the record in the db
 *
 * 
 *
 * @return void 
 */
 function setRecord($data_arr,$table,$id=0,$chkfield="")
	{
		if($id)
		{
			// we already have this record in the db
			// so we are updating it
			$where=$this->WhereClause($chkfield,$id);
			if(strlen($where))
			{
				$sql="update " . $table . " set ";
				$sql.=$this->explodeUpdate($data_arr);
				$sql.=" where $where";
			}
		}else{
			// this record does not yet exist
			// so we are creating it
			$sql="insert into " . $table . " ";
			$sql.=$this->explodeData($data_arr);
		}
        // debug("in setRecord - query",$sql);
		if(strlen($sql))
		{
			$this->query($sql);
		}
		if($this->_mysql_errno)
		{
			return 0;
		}elseif($id)
		{
			return $id;
		}else{
			return $this->_insert_id;
		}
	}
	
	// public function setField($data,$field,$table,$id=0,$chk_field="")
	// updates the supplied field in the db
	/**
 * updates the field in the db table
 *
 * 
 *
 * @return void 
 */
 function setField($data,$field,$table,$id,$chk_field)
	{
		if(strlen($field))
		{
			if(strlen($table))
			{
				$where=$this->WhereClause($chk_field,$id);
				$t_data=$this->sqlFormatField($data);
                // $t_field=$this->sqlFormatField($field);
				if(strlen($where))
				{
					$sql="update $table set $field=$t_data where $where";
					$this->query($sql);
				}	
				
			}	
		}
	}
	/**
 * returns a mysql result id for the data requested
 *
 * 
 *
 * @return int 
 */
 function getResultSet($data,$table,$chkfield,$id=0,$ext=0)
	{
		$ret=0;
		if(strlen($data))
		{
			if(strlen($table))
			{
				if($chkfield==1)
				{
					$where=" 1";
				}else{
					$where=$this->WhereClause($chkfield,$id);
				}
				if(strlen($where))
				{
					$sql="select $data from $table where $where";
					if($ext)
					{
						if(is_string($ext))
						{
							$sql.=" " . $ext;
						}
					}
//                     debug("sql",$sql);
					$ret=$this->query($sql);
				}
			}
		}
		return $ret;
	}
    function searchResults($data,$table,$search_field,$search,$ext=0)
    {// {{{
        $tarr=false;
        if(strlen($data) && strlen($table) && strlen($search_field) && strlen($search))
        {
            $where=$search_field;
            $where.=" like '%". $search . "%'";
            $sql="select $data from $table where $where";
            // debug("ext",$ext);
            if($ext)
            {
                $sql.=" " . $ext;
            }
            // debug("sql",$sql);
            $ret=$this->query($sql);
        }
        while($arr=$this->getRow(MYSQL_ASSOC))
        {
            $tarr[]=$arr;
        }
        return $tarr;
    }// }}}
    function searchStartsWith($data,$table,$search_field,$search,$ext=0)
    {// {{{
        $tarr=false;
        if(strlen($data) && strlen($table) && strlen($search_field) && strlen($search))
        {
            $where=$search_field;
            $where.=" like '". $search . "%'";
            $sql="select count(*) as totalrows from $table where $where";
            $ret=$this->query($sql);
            $arr=$this->getRow();
            $tarr["totalrows"]=$arr["totalrows"];
            // debug("arr in search for total rows",$arr);
            $sql="select $data from $table where $where";
            // debug("ext",$ext);
            if($ext)
            {
                $sql.=" " . $ext;
            }
            // debug("sql",$sql);
            $ret=$this->query($sql);
        }
        while($arr=$this->getRow(MYSQL_ASSOC))
        {
            $tarr[]=$arr;
        }
        return $tarr;
    }// }}}

	function getAllResults($data,$table,$chkfield=1,$id=0,$ext=0,$both=MYSQL_BOTH)
	{
		$tarr=false;
		if(strlen($data))
		{
			if(strlen($table))
			{
				if($chkfield==1)
				{
					$where=" 1";
				}else{
					$where=$this->WhereClause($chkfield,$id);
				}
				if(strlen($where))
				{
					$sql="select $data from $table where $where";
					if($ext)
					{
						if(is_string($ext))
						{
							$sql.=" " . $ext;
						}
					}
                    // debug("doing get all results",1);
                    // debug("sql",$sql);
					$ret=$this->query($sql);
				}
			}
		}
		while($arr=$this->getRow($both))
		{
			$tarr[]=$arr;
		}
		return $tarr;
	}
function queryResult($sql="")
{
    $ret=false;
    if($sql && is_string($sql) && strlen($sql)){
        $type=$this->query($sql);
        if($type){
            if($type==MYSQLSELECT && $this->numRows()){
                $ret=array();
                while($row=$this->getRow(MYSQL_ASSOC)){
                    $ret[]=$row;
                }
            }else{
                if($enum=$this->error_number()){
                    $ret=0-$enum;
                }else{
                    if($type==MYSQLINSERT){
                        $ret=$this->_insert_id;
                    }else{
                        $ret=true;
                    }
                }
            }
        }
    }
    return $ret;
}
	function deleteRecord($table,$chkfield,$id)
	{
		if(strlen($table))
		{
			if(strlen($chkfield))
			{
				$where=$this->whereClause($chkfield,$id);
				if(strlen($where))
				{
					$sql="delete from $table where $where";
					$ret=$this->query($sql);
				}
			}
		}
	}
	function listTables()
	{
		return mysql_list_tables($this->_db);
	}
	function listFields($table)
	{
		$op["num_fields"]=0;
		if(strlen($table))
		{
			$fields=mysql_list_fields($this->_db,$table,$this->_link_id);
			$columns = mysql_num_fields($fields);
			for($i=0;$i<$columns;$i++)
			{
				$op[$i]["field_name"]=mysql_field_name($fields, $i);
				$op[$i]["field_type"]=mysql_field_type($fields, $i);
			}
			$op["num_fields"]=$columns;
		}
		return $op;
	}
    function logquery($sql,$start)
    {
        $q=array("sql"=>$sql,'time' => ($this->getTime() - $start)*1000);
        $this->querylog[]=$q;
    }
	function getTime() {
		$time = microtime();
		$time = explode(' ', $time);
		$time = $time[1] + $time[0];
		$start = $time;
		return $start;
	}
	
	public function getReadableTime($time) {
		$ret = $time;
		$formatter = 0;
		$formats = array('ms', 's', 'm');
		if($time >= 1000 && $time < 60000) {
			$formatter = 1;
			$ret = ($time / 1000);
		}
		if($time >= 60000) {
			$formatter = 2;
			$ret = ($time / 1000) / 60;
		}
		$ret = number_format($ret,3,'.','') . ' ' . $formats[$formatter];
		return $ret;
	}
    public function getQueryLog()
    {
        return $this->querylog;
    }
}

?>
