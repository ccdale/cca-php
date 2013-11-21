<?php

/** {{{ heading block
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * src/php/cca-php-0.2/Unix/passwd.class.php
 *
 * C.C.Allison
 * daemon@cca.me.uk
 *
 * Started: Saturday 31 January 2009, 11:35:15
 * Version: 
 * Last Modified: Tuesday 22 September 2009, 12:25:53
 *
 * $Id: passwd.class.php 5 2009-09-23 14:15:01Z chris $
 */
// }}}

require_once "file.php";

class Passwd
{
    const PFILE="/etc/passwd";
    const UNAME=0;
    const PASSWD=1;
    const UID=2;
    const GID=3;
    const FNAME=4;
    const HOME=5;
    const SHELL=6;

    private $users=array();
    private $uids=array();

    public function __construct() // {{{
    {
        $this->getUsers();
    } // }}}
    public function findUser($user="") // {{{
    {
        $ret=false;
        if(is_string($user) && strlen($user)){
            $tmp=$this->users[$user];
            if(is_array($tmp) && count($tmp)>self::SHELL){
                $ret=$tmp;
            }
        }
        return $ret;
    } // }}}
    public function findUid($user="") // {{{
    {
        $ret=false;
        if(is_string($user) && strlen($user)){
            $tmp=$this->uids[$user];
            if(is_array($tmp) && count($tmp)>self::SHELL){
                $ret=$tmp;
            }
        }
        return $ret;
    } // }}}
    private function getUsers() // {{{
    {
        if(false!==($tmp=getFile(self::PFILE,CCA_FILE_ASARRAY))){
            if(is_array($tmp)){
                $cx=count($tmp);
                foreach($tmp as $userline){
                    $ua=explode(":",$userline);
                    $user=$this->assocArray($ua);
                    if(false!==$user){
                        $this->users[$user["uname"]]=$user;
                        $this->uids[$user["uid"]]=$user;
                    }
                }
            }
        }
    } // }}}
    private function assocArray($pa) // {{{
    {
        $ret=false;
        $keys=array("uname","passwd","uid","gid","fname","home","shell");
        if(is_array($pa) && count($pa)>self::SHELL){
            $ret=array();
            foreach($keys as $index=>$key){
                $ret[$key]=$pa[$index];
            }
        }
        return $ret;
    } // }}}
}
/*
    * test 
 */
/*
$p=new Passwd();
print_r($p->findUser("chris"));
print_r($p->findUid("1000"));
 */
?>
