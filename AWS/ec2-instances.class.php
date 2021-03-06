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
 * Last Modified: Tuesday 22 April 2014, 04:13:04
 * Revision: $Id$
 * Version: 0.00
 */

require_once "ec2.class.php";

class EC2Instances extends EC2
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
    /** di {{{
     * shorthand for describeInstances
     */
    public function di()
    {
        return $this->describeInstances();
    }/*}}}*/
    /** describeInstances {{{
     * describeInstances
     * list all your instances
     *
     * see: http://docs.aws.amazon.com/AWSEC2/latest/APIReference/ApiReference-query-DescribeInstances.html
     * returns: array of instances
     *
     * $instanceid: string or array of strings
     * $filter: array of key=>val pairs
     */
    public function describeInstances($instanceid=false,$filter=false)
    {
        $ret=false;
        $this->initParams("DescribeInstances");
        if(false!==$instanceid){
            $this->addNParam("InstanceId",$instanceid);
        }
        $this->addFilterParam($filter);
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
                    if(isset($tinst["instanceId"])){
                        $this->data[$tinst["instanceId"]]=$tinst;
                    }
                }
            }
            $ret=true;
        }
        return $ret;
    } /*}}}*/
    private function flattenInstance($iarr) /*{{{*/
    {
        $ret=false;
        if(false!==($tinst=$this->flattenData($iarr,"instances"))){
            if(isset($iarr["instanceState"])){
                $tinst["state"]=$iarr["instanceState"]["name"];
                $tinst["statecode"]=$iarr["instanceState"]["code"];
            }else{
                $tinst["state"]="unknown";
                $tinst["statecode"]=-1;
            }
            if(isset($iarr["placement"])){
                $tinst["availabilityZone"]=$iarr["placement"]["availabilityZone"];
            }else{
                $tinst["availabilityZone"]="unknown";
            }
            if(isset($tinst["tags"]["Name"])){
                $tinst["Name"]=$tinst["tags"]["Name"];
            }else{
                if(isset($tinst["instanceId"])){
                    $tinst["Name"]=$tinst["instanceId"];
                }else{
                    $tinst["Name"]="unknown";
                }
            }
            $ret=$tinst;
        }
        return $ret;
    } /*}}}*/
}
?>
