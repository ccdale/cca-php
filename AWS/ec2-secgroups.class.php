<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * ec2-secgroups.class.php
 *
 * Started: Monday 14 April 2014, 03:45:03
 * Last Modified: Tuesday 22 April 2014, 03:27:17
 * Revision: $Id$
 * Version: 0.00
 */

require_once "ec2.class.php";

class EC2SecGroups extends EC2
{
    /** __construct {{{
     *
     * $logg: an instance of the logging class
     * $accesskey: aws access key id
     * $secretkey: aws secret key
     * $region: aws region
     */
    public function __construct($logg=false,$accesskey="",$secretkey="",$region="eu-west-1")
    {
        parent::__construct($logg,$accesskey,$secretkey,$region);
        $this->ckeys["secgroups"]=array("ownerId","groupId","groupName","groupDescription");
        $this->ckeys["ipPermissions"]=array("ipProtocol","fromPort","toPort");
        // this unset is needed to ensure flatten data works correctly.
        if(isset($this->csets)){
            unset($this->csets);
        }
        // $this->csets["secgroups"]=array(
            // array("key"=>"tags","xkey"=>"tagSet","namekey"=>"key","datakey"=>"value")
        // );

    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    /** dsg {{{
     * shorthand for describeSecurityGroups
     */
    public function dsg()
    {
        return $this->describeSecurityGroups();
    }/*}}}*/
    /** describeSecurityGroups {{{
     * describeSecurityGroups
     * list some or all of your security groups
     *
     * see: http://docs.aws.amazon.com/AWSEC2/latest/APIReference/ApiReference-query-DescribeSecurityGroups.html
     * returns: array of security groups
     * $namearr: string or array of strings corresponding to a list of security group names
     * $idarr: string or array of strings corresponding to a list of security group ids
     * $filter: array of key=>val pairs - see the amazon docs for the keys you can use
     */
    public function describeSecurityGroups($namearr=false,$idarr=false,$filter=false)
    {
        $ret=false;
        $this->initParams("DescribeSecurityGroups");
        if(false!==$namearr){
            $this->addNParam("GroupName",$namearr);
        }
        if(false!=$idarr){
            $this->addNParam("GroupId",$idarr);
        }
        $this->addFilterParam($filter);
        if(false!==($this->rawdata=$this->doCurl())){
            $this->decodeRawGroups();
            $ret=$this->data;
        }
        return $ret;
    }/*}}}*/
    private function decodeRawGroups()/*{{{*/
    {
        $ret=false;
        if(isset($this->rawdata["requestId"]) && isset($this->rawdata["securityGroupInfo"])){
            $this->data=array();
            if(isset($this->rawdata["securityGroupInfo"]["item"])){
                foreach($this->rawdata["securityGroupInfo"]["item"] as $secgrp){
                    $this->data[]=$this->flattenSecurityGroup($secgrp);
                }
            }
        }
    }/*}}}*/
    private function flattenSecurityGroup($secgrp)/*{{{*/
    {
        $ret=false;
        if(false!==($tgrp=$this->copyKeys($this->ckeys["secgroups"],$secgrp))){
            if(isset($secgrp["ipPermissions"])){
                if(false!==($cn=$this->ValidArray($secgrp["ipPermissions"]))){
                    if($cn>0){
                        if(isset($secgrp["ipPermissions"]["item"])){
                            $tper=$this->flattenItem($secgrp["ipPermissions"]["item"]);
                            foreach($tper as $per){
                                $ttper=$this->copyKeys($this->ckeys["ipPermissions"],$per);
                                $ttper["groups"]=false;
                                if(isset($per["groups"])){
                                    if(isset($per["groups"]["item"])){
                                        $ttper["groups"]=$this->flattenItem($per["groups"]["item"]);
                                    }
                                }
                                $ttper["ipRanges"]=false;
                                if(isset($per["ipRanges"])){
                                    if(isset($per["ipRanges"]["item"])){
                                        $ttper["ipRanges"]=$this->flattenItem($per["ipRanges"]["item"]);
                                    }
                                }
                                $tgrp["ipPermissions"][]=$ttper;
                            }
                        }
                    }
                }
            }
            $ret=$tgrp;
        }
        return $ret;
    }/*}}}*/
}
?>
