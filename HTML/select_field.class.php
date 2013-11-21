<?php
/*
* select_field.class.php
* Last Modified: Monday 30 August 2010, 07:33:36
*/
require_once "HTML/option_field.class.php";

class SelectField
{
	var $name;
	var $oarr;
    var $autochange;

	function SelectField($name,$withautochange=false)
	{
		$this->setName($name);
		$this->oarr=array();
        $this->autochange=$withautochange;
	}
	function setName($name)
	{
		$this->name=$name;
	}
	function addOption($v,$t,$s=false,$with_auto_submit=false)
	{
		$opt=new OptionField($v,$t,$s,$with_auto_submit);
		$this->oarr[]=$opt->makeOption();
		unset($opt);
	}
	function makeSelect()
	{
        $op="<select name='$this->name'";
        if($this->autochange){
            $op.=" onchange='selectChanged(this.value);'";
        }
        $op.=">\n";
		$c=count($this->oarr);
		for($i=0;$i<$c;$i++)
		{
			$op.=$this->oarr[$i];
		}
		$op.="</select>\n";
		return $op;
	}
	function letterSelector($pre_sel="0",$auto_submit=false)
	{
	    for($i=65;$i<91;$i++)
	    {
            $s=($pre_sel==chr($i) ? true : false);
			$this->addOption(chr($i),chr($i),$s,$auto_submit);
	    }
	    return $this->makeSelect();
	}
	function numberSelector($pre_sel="A",$auto_submit=false)
	{
	    for($i=0;$i<10;$i++)
	    {
	        $tmp=$i . "";
	        $s=($pre_sel==$tmp ? true : false);
	        $this->addOption($tmp,$tmp,$s,$auto_submit);
	    }
	    return $this->makeSelect();
	}
}
