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
 * Last Modified: Tuesday 15 April 2014, 11:40:34
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
        $this->csets["secgroups"]=array(
            // array("key"=>"tags","xkey"=>"tagSet","namekey"=>"key","datakey"=>"value")
        );

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
     * $filter: array of key=>val pairs
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
            $ret=$this->rawdata;
        }
        return $ret;
    }/*}}}*/
    private function decodeRawGroups()/*{{{*/
    {
    }/*}}}*/
}

?>
