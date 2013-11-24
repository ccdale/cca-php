<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * ec2-instances.class.php
 *
 * Started: Sunday 24 November 2013, 12:23:29
 * Last Modified: Sunday 24 November 2013, 12:44:01
 * Revision: $Id$
 * Version: 0.00
 */

require_once "ec2.class.php";

class EC2Instances extends EC2
{
    public function __construct($logg=false,$accesskey="",$secretkey="",$region="eu-west-1")/*{{{*/
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
    public function di()/*{{{*/ /*{{{*/
    {
        return $this->describeInstances();
    }/*}}}*/ /*}}}*/
    public function describeInstances() /*{{{*/
    {
        $ret=false;
        $this->initParams();
        $this->params["Action"]="DescribeInstances";
        if(false!==($this->rawdata=$this->doCurl())){
            if($this->decodeRawInstances()){
                $ret=$this->data;
            }
        }
        return $ret;
    } /*}}}*/
    private function decodeRawInstances() /*{{{*/
    {
        $ret=false;
        if(isset($this->rawdata["requestId"]) && isset($this->rawdata["reservationSet"])){
            $this->data=array();
            if(isset($this->rawdata["reservationSet"]["item"])){
                foreach($this->rawdata["reservationSet"]["item"] as $iset){
                    $tinst=$this->flattenInstance($iset["instancesSet"]["item"]);
                    $this->data[$tinst["instanceId"]]=$tinst;
                }
            }
            $ret=true;
        }
        return $ret;
    } /*}}}*/
}
?>
