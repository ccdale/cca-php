<?PHP
/*
* option_field.class.php
* Last modified: Sat Aug 27 22:11:14 2005
*/
class OptionField
{
	var $oarr;
	function OptionField($v="",$t="",$s=false,$with_auto_submit=false)
	{
		$this->setValue($v);
		$this->setText($t);
		$this->setSelected($s);
        $this->setAutoSubmit($with_auto_submit);
	}
	function setValue($v)
	{
		$this->oarr["value"]=$v;
	}
	function setText($t)
	{
		$this->oarr["text"]=$t;
	}
	function setSelected($s)
	{
		$this->oarr["selected"]=$s;
	}
    function setAutoSubmit($with_auto_submit)
    {
        $this->oarr["autosubmit"]=$with_auto_submit;
    }
	function makeOption()
	{
		$op="<option value='";
		$op.=$this->oarr["value"];
		$op.="'";
		if($this->oarr["selected"])
		{
			$op.=" SELECTED";
		}
        if($this->oarr["autosubmit"])
        {
            $op.=" onClick='submitForm();'";
        }
		$op.=">";
		$op.=$this->oarr["text"];
		$op.="</option>\n";
		return $op;
	}
}
?>
