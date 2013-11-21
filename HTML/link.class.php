<?php
/*
* link.class.php
* Last Modified: Tuesday 22 September 2009, 12:25:14
 *
 * $Id: link.class.php 5 2009-09-23 14:15:01Z chris $
*/
class ALink
{
	var $target;
	var $get;
	var $class;
	var $text;
	var $new;
    var $attr;
    var $atts;
	function ALink($get,$text,$target="",$class="xBody",$new=false,$atts="")
	{
		if(strlen($target))
		{
			$this->setTarget($target);
		}else{
			$this->setTarget($_SERVER["PHP_SELF"]);
		}
		$this->setGet($get);
		$this->setText($text);
		$this->setClass($class);
		$this->setNew($new);
        $this->setAtts($atts);
	}
    function setAtts($atts)
    {
        $this->atts=$atts;
        if(is_array($atts)){
            while(list($key,$val)=each($atts)){
                if(strlen($this->attr)){
                    $this->attr.=" ";
                }
                $this->attr.=$key . "='" . $val . "'";
            }
        }else{
            $this->attr=$atts;
        }
    }
	function setTarget($target)
	{
		$this->target=$target;
	}
	function setGet($get)
	{
		if(is_array($get))
		{
			while(list($k,$v)=each($get))
			{
                if(!$k=="blank"){
                    $kk=urlencode($k);
                    $vv=urlencode($v);
                }else{
                    $kk="blank";
                    $vv=$v;
                }
				if(strlen($this->get))
				{
					$this->get.="&" . $k . "=" . $v;
				}else{
					$this->get="?" . $k . "=" . $v;
				}
			}
		}else{
			$this->get=$get;
		}
	}
	function setText($text)
	{
		$this->text=$text;
	}
	function setClass($class)
	{
		$this->class=$class;
	}
	function setNew($new)
	{
		$this->new=$new;
	}
	function makeLink()
	{
        $name="";
        if(is_array($this->atts) && isset($this->atts["name"])){
            $name="#" . $this->atts["name"];
        }
		$tmp="<a class='" . $this->class . "' ";
		if($this->new)
		{
			$tmp.=" target='_new' ";
		}
		$tmp.="href='" . $this->target;
		$tmp.=$this->get;
        $tmp.=$name . "'";
        if(strlen($this->attr)){
            $tmp.=" " . $this->attr;
        }
		$tmp.=">" . $this->text;
		$tmp.="</a>";
		return $tmp;
	}
}
?>
