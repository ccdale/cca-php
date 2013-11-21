<?PHP
/**
* input_field.class.php
*
* creates a generic html form input field
*
* Last Modified: Thursday  3 June 2010, 13:26:22
*/
class InputField
{
	/**
 * array to hold details of this field
 *
 * 
 *
 */
 var $da; // array to hold the details
	
	/**
 * constructor for class
 *
 * initialises class variables and creates the class
 *
 * @return void 
 */
 function InputField($type="",$name="",$value="",$ext=false,$label="",$align_right=false)
	{
		$this->setName($name);
		$this->setType($type);
		$this->setvalue($value);
		$this->setExt($ext);
		$this->setlabel($label);
		// $this->setAlign(($align_right ? "right" : "left"));
        if($align_right){
            // $tmp="true";
            $this->setAlign("right");
        }else{
            // $tmp="false";
            $this->setAlign("left");
        }
            // debug("align_right",$tmp);
	}
	function setAlign($align="left")
	{
        // $tmp=$this->getName();
        // debug("field name",$tmp);
        // debug("align",$align);
		$this->da["align"]=$align;
	}
	/**
 * sets the extended opetion for some fields
 *
 * 
 *
 * @return viod 
 */
 function setExt($ext)
	{
		$this->da["ext"]=$ext;
	}
	/**
 * returns the extended option for some types of field
 *
 * 
 *
 * @return bool 
 */
 function getExt()
	{
		switch($this->da["type"])
		{
			case "radio":
			    $ret=($this->da["ext"] ? " checked" : "");
			break;
			case "checkbox":
			    $ret=($this->da["ext"] ? " CHECKED" : "");
			break;
			default:
			    $ret="";
			break;
		}
		return $ret;
	}
	/**
 * sets the name of the field
 *
 * 
 *
 * @return void 
 */
 function setName($name)
	{
		if(strlen($name))
		{
			$this->da["name"]=$name;
		}
	}
	/**
 * returns the name of the field
 *
 * 
 *
 * @return string 
 */
 function getName()
	{
		return $this->da["name"];
	}
	/**
 * sets the type of field
 *
 * 
 *
 * @return void 
 */
 function setType($type)
	{
		$this->da["type"]=(strlen($type) ? $type : "text");
	}
	/**
 * returns the type of field
 *
 * 
 *
 * @return string 
 */
 function getType()
	{
		return $this->da["type"];
	}
	/**
 * sets the value of the field
 *
 * 
 *
 * @return void 
 */
 function setValue($value)
	{
		$tmp=(is_string($value) ? $value : $value . "");
		$this->da["value"]=$tmp;
	}
	/**
 * returns the value of the field
 *
 * 
 *
 * @return string 
 */
 function getValue()
	{
		return $this->da["value"];
	}
	/**
 * sets the size of the field
 *
 * 
 *
 * @return void 
 */
 function setSize($size)
	{
		$this->da["size"]=$size;
	}
	/**
 * returns the field size
 *
 * 
 *
 * @return string 
 */
 function getSize()
	{
		return $this->da["size"];
	}
	/**
 * sets the label for this field
 *
 * 
 *
 * @return void 
 */
 function setLabel($label)
	{
		$this->da["label"]=$label;
	}
	/**
 * returns the label for this field
 *
 * 
 *
 * @return string 
 */
 function getLabel()
	{
		return $this->da["label"];
	}
	/**
 * sets the label position 
 *
 * if $pos=true then label is to the left of the field, else it is to the right
 *
 * @return void 
 */
 function setLabelPos($pos=true)
	{
		$this->da["labelpos"]=$pos;
	}
	/**
 * returns the label position
 *
 * 
 *
 * @return bool 
 */
 function getLabelPos()
	{
        if(!isset($this->da["labelpos"])){
            $this->setLabelPos();
        }
		return $this->da["labelpos"];
	}
	function setRows($rows)
	{
		$this->da["rows"]=$rows;
	}
	function setCols($cols)
	{
		$this->da["cols"]=$cols;
	}
	/**
 * returns a hidden form field
 *
 * 
 *
 * @return string 
 */
 function Hidden($name,$value)
	{
		$this->setType("hidden");
		$this->setName($name);
		$this->setValue($value);
		return $this->getfield();
	}
	/**
 * returns a text input field
 *
 * 
 *
 * @return string 
 */
 function Text($name="",$value="",$size="20",$label="",$labelpos=false)
	{
		$this->setType("text");
		$this->setname($name);
		$this->setValue($value);
		$this->setSize($size);
		$this->setLabel($label);
		$this->setlabelPos($labelpos);
		return $this->getfield();
	}
	function TextArea($name="",$value="",$rows="20",$cols="70",$label="",$labelpos=false)
	{
		$this->setType("textarea");
		$this->setname($name);
		$this->setvalue($value);
		$this->setRows($rows);
		$this->setCols($cols);
		$this->setSize($size);
		$this->setLabel($label);
		$this->setlabelPos($labelpos);
		return $this->getfield();
	}
	/**
 * returns a checkbox field
 *
 * 
 *
 * @return string 
 */
 function Checkbox($name,$label="",$ext=false,$labelpos=true)
	{
		$this->setType("checkbox");
		$this->setExt($ext);
		$this->setname($name);
		$this->setValue("1");
		$this->setLabel($label);
		$this->setlabelPos($labelpos);
		return $this->getfield();
	}
	/**
 * returns a radio button
 *
 * 
 *
 * @return string 
 */
 function Radio($name,$value,$label="",$ext=false,$labelpos=true)
	{
		$this->setType("radio");
        $this->setValue($value);
		$this->setExt($ext);
		$this->setname($name);
        if(!strlen($label)){
            $label=$value;
        }
		$this->setLabel($label);
		return $this->getfield();
	}
	/**
 * returns a password input field
 *
 * 
 *
 * @return string 
 */
 function Password($name,$size="20",$value="",$label="",$labelpos=false)
	{
		$this->settype("password");
		$this->setname($name);
		$this->setsize($size);
		$this->setValue($value);
		$this->setLabel($label);
		$this->setlabelPos($labelpos);
		return $this->getfield();
	}
	/**
 * returns this field
 *
 * 
 *
 * @return string 
 */
 function getField()
	{
        // Debug("input field da",$this->da);
		if(isset($this->da["type"]))
		{
			if($this->da["type"]=="textarea")
			{
				$op="<textarea ";
			}else{
				$op="<input ";
			}
			// $op.="align='" . $this->da["align"] . "' ";
			while(list($key,$val)=each($this->da))
			{
				if($key!="ext" && $key!="label" && $key!="labelpos")
				{
					if($key=="value")
					{
						if($this->da["type"]!="textarea")
						{
							$op.=$key . "='" . $val . "' ";
						}
					}else{
						$op.=$key . "='" . $val . "' ";
					}
				}
			}
			$op.=$this->getExt();
			$op.=">";
			
			if(isset($this->da["label"]))
			{
				if($this->getLabelPos())
				{
					$op.=" " . $this->da["label"] . "\n";
				}else{
					$op=$this->da["label"] . " " . $op . "\n";
				}
			}
			if($this->da["type"]=="textarea")
			{
				$op.=$this->da["value"];
				$op.="</textarea>";
			}
		}
		return $op;
	}
	/**
 * sets field attribute $field to value $data
 *
 * 
 *
 * @return void 
 */
 function setField($field,$data)
	{
		$this->da[$field]=$data;
	}
	/**
 * deletes the field attribute field
 *
 * 
 *
 * @return void 
 */
 function unSetField($field)
	{
		unset($this->da[$field]);
	}
}
?>
