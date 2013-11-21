<?PHP
/*
* form.class.php
*
* 
*
* Last Modified: Friday 20 August 2010, 11:11:15
* Version: $Id: form.class.php 492 2010-08-20 10:11:41Z chris $
*/
require_once "HTML/class.table.php";
require_once "HTML/input_field.class.php";
class Form
{
    var $da;
    var $labelCell;
    var $inputCell;

    var $title;

    var $withjavascript;
    var $withcolour;
    var $formname;

    function Form($action="",$method="POST",$numcols=2,$title="",$withcolour=false,$withjscript=false,$enctype=false,$name="")
	{
		if(strlen($action))
		{
			$this->setAction($action);
		}else{
			$this->setAction($_SERVER["PHP_SELF"]);
		}
        if(defined("DEBUG_SESSION")){
            if(DEBUG_SESSION)
            {
                $this->setAction($this->da["action"] . "?DBGSESSID=0@clienthost:7869");
            }
        }
		$this->setMethod($method);
		$this->setNumCols($numcols);
		$this->setTitle($title);
		$this->labelCell=array("class"=>"formright");
		$this->inputCell=array("class"=>"formleft");
        $this->withjavascript=$withjscript;
        $this->withcolour=$withcolour;
        $this->setEnctype($enctype);
        $this->setName($name);
	}
    function setName($name="")
    {
        if(is_string($name) && $name){
            $this->formname=$name;
        }else{
            $this->formname="thisForm";
        }
    }
    function setEnctype($enctype)
    {
        $this->da["enctype"]=$enctype;
    }
    function getEnctype()
    {
        $op="";
        if($this->da["enctype"]){
            $op=" enctype='";
            $op.=$this->da["enctype"];
            $op.="'";
        }
        return $op;
    }
	function setTitle($title)
	{
		if(strlen($title))
		{
			$this->title=$title;
			$row=$this->da["table"]->AddRow();
            if($this->withcolour){
                $this->da["table"]->SetFancyRowStyle($row,array("bgcolor"=>HEADCOLOUR));
            }
			$this->da["table"]->SetCellAttributes($row,1,
				array("colspan"=>$this->da["numcols"],"class"=>"formtitle"));
			$this->da["table"]->SetCellContent($row,1,$this->title);
		}
	}
    function setAction($action)
	{
		$this->da["action"]=(strlen($action) ? $action : $_SERVER["PHP_SELF"]);
	}
	function getAction()
	{
		return "Action='" . $this->da["action"] . "'";
	}
	function setMethod($method)
	{
		$this->da["method"]=(strlen($method) ? $method : "POST");
	}
	function getMethod()
	{
		return "Method='" . $this->da["method"] . "'";
	}
	function getNumCols()
	{
		return $this->da["numcols"];
	}
	function setNumCols($numcols)
	{
		$this->da["numcols"]=$numcols;
		$this->da["table"]=new Table();
	}
	function addTableRow()
	{
		$row=$this->da["table"]->AddRow();
		$this->da["table"]->SetCellAttributes($row,1,$this->labelCell);
		$this->da["table"]->SetCellAttributes($row,2,$this->inputCell);
		return $row;
	}
	function addRow($label,$type="text",$name="",$value="",$size="",$ext=false,$right_align=false)
	{
		$row=$this->addTableRow();
		$this->da["table"]->SetCellContent($row,1,$label);
		if($right_align)
		{
			$this->da["table"]->SetCellAttribute($row,2,"class","formright");
		}
		if($type!="direct")
		{
			$ip=new InputField($type,$name,$value,$ext,"",$right_align);
			if(strlen($size))
			{
				$ip->setSize($size);
			}
            if($type=="textarea" && strlen($size))
            {
                $ip->da["cols"]=$size;
                $ip->da["rows"]="20";
            }
			$tmp=$ip->getField();
		}else{
			$tmp=$name;
		}
		
		$this->da["table"]->SetCellContent($row,2,$tmp);
	}
	function addHidden($hid)
	{
		if(isset($this->da["hidden"]) && strlen($this->da["hidden"]))
		{
			$this->da["hidden"].=$hid;
		}else{
			$this->da["hidden"]=$hid;
		}
	}
	function addHid($name,$value)
	{
		$ip=new InputField();
		$this->addHidden($ip->Hidden($name,$value));
	}
	function addHidA($hid_arr="")
	{
		if(is_array($hid_arr))
		{
			reset($hid_arr);
			while(list($k,$v)=each($hid_arr))
			{
				$this->addHid($k,$v);
			}
		}
	}
	function makeForm()
	{
		$op="<form name='" . $this->formname . "' ";
        if($this->da["enctype"]){
            $op.=$this->getEnctype() . " ";
        }
		$op.=$this->getAction() . " ";
		$op.=$this->getMethod();
        if($this->withjavascript){
            $op.=" onfocus='rcvFocus(event.srcElement);' onclick='rcvFocus(event.srcElement);'";
        }
		$op.=">\n";
        if(isset($this->da["hidden"])){
            $op.=$this->da["hidden"];
        }
        if($this->withcolour){
            if(!defined("ROW1COLOUR")){
                define("ROW1COLOUR","#ccc");
            }
            if(!defined("ROW2COLOUR")){
                define("ROW2COLOUR","#ddd");
            }
            if(strlen($this->title))
            {
                $this->da["table"]->Set2RowColors(ROW1COLOUR,ROW2COLOUR,2);
            }else{
                $this->da["table"]->Set2RowColors(ROW1COLOUR,ROW2COLOUR,1);
            }
        }
		$op.=$this->da["table"]->CompileTable();
		$op.="</form>\n";
		return $op;
	}
    function arrayToForm($arr,$withsubmit=true,$submitvalue="Save",$submitname="submit")
    {
        if(is_array($arr)){
            if($arrcount=count($arr)){
                reset($arr);
                while(list($arrkey,$arrval)=each($arr) ){
                    $this->addRow($arrkey,"text",$arrkey,$arrval);
                }
                if($withsubmit){
                    $this->addRow("","submit",$submitname,$submitvalue);
                }
            }
        }
    }
    function fileUploadForm($maxfilesize=1000000000,$label="Choose a file to upload",$button="Upload File",$withname=false,$withdesc=false)
    {
        $this->setEnctype("multipart/form-data");
        $this->addHid("MAX_FILE_SIZE",$maxfilesize);
        if(is_string($withname)){
            $this->addRow($withname,"text","filetitle");
        }
        if(is_string($withdesc)){
            $this->addRow($withdesc,"text","filedesc","",50);
        }
        $this->addRow($label,"file","uploadedfile");
        $this->addRow("","submit","submit",$button);
        return $this->makeForm();
    }
}
