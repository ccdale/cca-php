<?php
/*
 * tag.class.php
 *
 * Last Modified: Monday 21 June 2010, 10:13:39
 *
 */
/*
 * creates a html tag easily
 */
class Tag
{
    var $attr;
    var $content;
    var $name;
    var $newline;
    var $autoclass;
    var $noclose;
    function Tag($name,$content="",$attr="",$newline=true,$autoclass=false,$noclose=false)
    {// {{{
        $this->setName($name);
        $this->setContent($content);
        $this->setAtts($attr);
        $this->setNewLine($newline);
        $this->setautoclass($autoclass);
        $this->noclose=$noclose;
    }// }}}
    function setAutoClass($autoclass=true)
    {// {{{
        $this->autoclass=$autoclass;
    }// }}}
    function setNewLine($newline=true)
    {// {{{
        $this->newline=$newline;
    }// }}}
    function setContent($content)
    {// {{{
        $this->content=$content;
    }// }}}
    function setName($name)
    {// {{{
        $this->name=$name;
    }// }}}
    function setAtts($atts)
    {// {{{
        if(is_array($atts)){// {{{
            if(count($atts)){// {{{
                $this->attr=$atts;
            }// }}}
        }else{
            $this->attr=$atts;
        }// }}}
        // if($this->autoclass){// {{{
            // $this->attr=array("class"=>"x" . $this->name);
        // }// }}}
    }// }}}
    function makeAtts()
    {// {{{
        $ret="";
        if(is_array($this->attr)){
            reset($this->attr);
            // debug("definately an array",$this->attr);
            while(list($key,$val)=each($this->attr)){
                if(strlen($ret)){
                    $ret.=" " . $key . "='" . $val . "'";
                }else{
                    $ret=$key . "='" . $val . "'";
                }
            }
        }else{
            if(strlen($this->attr)){
                $ret=$this->attr;
            }
        }
        if(strlen($ret)){
            $ret=" " . $ret . " ";
        }
        return $ret;
    }// }}}
    function makeTag()
    {// {{{
        $op="<" . $this->name;
        $op.=$this->makeAtts();
        $op.=">";
        if($this->noclose){// {{{
            if($this->newline){// {{{
                $op.="\n";
            }// }}}
            return $op;
        }// }}}
        $op.=$this->content;
        $op.="</" . $this->name . ">";
        if($this->newline){// {{{
            $op.="\n";
        }// }}}
        return $op;
    }// }}}
}
?>
