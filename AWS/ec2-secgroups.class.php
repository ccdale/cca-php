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
 * Last Modified: Monday 14 April 2014, 03:57:52
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
        $this->ckeys["instances"]=array("instanceId","imageId","privateDnsName","keyName","instanceType","launchTime","kernelId","subnetId","vpcId","privateIpAddress","architecture","rootDeviceType","rootDeviceName","virtualizationType","hypervisor","ebsOptimized");
        $this->csets["instances"]=array(
            array("key"=>"securityGroups","xkey"=>"groupSet","namekey"=>"groupName","datakey"=>"groupId"),
            array("key"=>"tags","xkey"=>"tagSet","namekey"=>"key","datakey"=>"value")
        );

    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    public function describeSecurityGroups()/*{{{*/
    {
        $ret=false;
        $this->initParams("DescribeSecurityGroups");
        if(false!==($this->rawdata=$this->doCurl())){
            $ret=$this->rawdata;
        }
        return $ret;
    }/*}}}*/
}

?>
