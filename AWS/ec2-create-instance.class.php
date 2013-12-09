<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * EC2-create-instance.class.php
 *
 * Started: Saturday  7 December 2013, 07:13:49
 * Last Modified: Sunday  8 December 2013, 13:05:11
 * Revision: $Id$
 * Version: 0.00
 */

require_once "ec2.class.php";
require_once "ec2-tags.class.php";
require_once "ec2-instances.class.php";

class EC2CreateInstance extends EC2
{
    private $settings=false;
    private $hostnames;
    private $secgrps;
    private $imageid;
    private $keyname;
    private $userdata;
    private $type="t1.micro";
    private $availabilityzone=false;
    private $subnet=false;
    private $xaccesskey;
    private $xsecretkey;
    private $xregion;
    private $shortcircuit;
    /** __construct {{{
     *
     * $logg: an instance of the logging class
     * $accesskey: aws access key id
     * $secretkey: aws secret key
     * $region: aws region
     */
    public function __construct($logg=false,$accesskey="",$secretkey="",$region="eu-west-1",$settings=false,$shortcircuit=false)
    {
        parent::__construct($logg,$accesskey,$secretkey,$region);
        $this->shortcircuit=$shortcircuit;
        $this->xaccesskey=$accesskey;
        $this->xsecretkey=$secretkey;
        $this->xregion=$region;
        $this->ckeys["instances"]=array("instanceId","imageId","privateDnsName","keyName","instanceType","launchTime","kernelId","subnetId","vpcId","privateIpAddress","architecture","rootDeviceType","rootDeviceName","virtualizationType","hypervisor","ebsOptimized");
        $this->csets["instances"]=array(
            array("key"=>"securityGroups","xkey"=>"groupSet","namekey"=>"groupName","datakey"=>"groupId"),
            array("key"=>"tags","xkey"=>"tagSet","namekey"=>"key","datakey"=>"value")
        );
        $this->requiredsettings=array("ImageId","KeyName","InstanceType","Hostnames");
        $this->setSettings($settings);
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    public function setGroups($groups=false)/*{{{*/
    {
        if(is_array($groups)){
            $this->settings["SecurityGroupId"]=$groups;
        }elseif(is_string($groups)){
            $this->settings["SecurityGroupId"]=array($groups);
        }
    }/*}}}*/
    public function setImageid($imageid=false)/*{{{*/
    {
        if(is_string($imageid)){
            $this->settings["ImageId"]=$imageid;
        }
    }/*}}}*/
    public function setKeyname($keyname=false)/*{{{*/
    {
        if(is_string($keyname)){
            $this->settings["KeyName"]=$keyname;
        }
    }/*}}}*/
    public function setUserdata($userdata=false)/*{{{*/
    {
        if(is_string($userdata)){
            $this->settings["UserData"]=$userdata;
        }
    }/*}}}*/
    public function setType($type=false)/*{{{*/
    {
        if(is_string($type)){
            $this->settings["InstanceType"]=$type;
        }
    }/*}}}*/
    public function setAZ($az=false)/*{{{*/
    {
        if(is_string($az)){
            $this->settings["Placement.AvailabilityZone"]=$az;
        }
    }/*}}}*/
    public function setSubnet($subnet=false)/*{{{*/
    {
        if(is_string($subnet)){
            $this->settings["SubnetId"]=$subnet;
        }
    }/*}}}*/
    public function setSettings($settings)/*{{{*/
    {
        if(is_array($settings)){
            $this->settings=$settings;
        }
    }/*}}}*/
    public function runInstance()/*{{{*/
    {
        $ret=false;
        if($this->checkSettings()){
            $this->settings["MinCount"]="1";
            if(is_array($this->settings["Hostnames"])){
                $cn=count($this->settings["Hostnames"]);
            }else{
                $cn=1;
            }
            $this->settings["MaxCount"]="$cn";
            $this->hostnames=$this->settings["Hostnames"];
            unset($this->settings["Hostnames"]);
            $this->initParams("RunInstances");
            foreach($this->settings as $key=>$val){
                if($key=="SecurityGroupId"){
                    $this->addNParam($key,$val);
                }elseif($key=="UserData"){
                    if(file_exists($val)){
                        $str=file_get_contents($val);
                    }else{
                        $str=$val;
                    }
                    $val=base64_encode($str);
                    $this->addParam($key,$val);
                }elseif($key!="Hostnames"){
                    $this->addParam($key,$val);
                }
            }
            if($this->shortcircuit){
                print "Shortcircuiting\n";
                $this->rawdata=unserialize(file_get_contents("dump.psa"));
                if($this->decodeNewRawInstances()){
                    if(is_array($this->hostnames)){
                        $cn=count($this->hostnames);
                        $x=0;
                        foreach($this->data as $instanceid=>$instance){
                            if(isset($this->hostnames[$x])){
                                if(false!==($junk=$this->tagInstance($instanceid,$this->hostnames[$x]))){
                                    print "Instance: $instanceid tagged as " . $this->hostnames[$x] . "\n";
                                }else{
                                    print "Failed to tag $instanceid as " . $this->hostnames[$x] . "\n";
                                }
                            }else{
                                print "Cannot tag $instanceid as I have run out of hostnames\n";
                            }
                            $x++;
                        }
                    }else{
                        foreach($this->data as $instanceid=>$instance){
                            if(false!==($junk=$this->tagInstance($instanceid,$this->hostnames))){
                                print "Instance: $instanceid tagged as " . $this->hostnames[$x] . "\n";
                            }else{
                                print "Failed to tag $instanceid as " . $this->hostnames[$x] . "\n";
                            }
                        }
                    }
                    $ret=$this->data;
                }
            }else{
                $iids=array();
                if(false!==($this->rawdata=$this->doCurl())){
                    file_put_contents("dump.psa",serialize($this->rawdata));
                    // print_r($this->rawdata);
                    // $ret=$this->decodeRawData();
                    if($this->decodeNewRawInstances()){
                        if(is_array($this->hostnames)){
                            $cn=count($this->hostnames);
                            $x=0;
                            foreach($this->data as $instanceid=>$instance){
                                $iids[]=$instanceid;
                                if(isset($this->hostnames[$x])){
                                    if(false!==($junk=$this->tagInstance($instanceid,$this->hostnames[$x]))){
                                        print "Instance: $instanceid tagged as " . $this->hostnames[$x] . "\n";
                                    }else{
                                        print "Failed to tag $instanceid as " . $this->hostnames[$x] . "\n";
                                    }
                                }else{
                                    print "Cannot tag $instanceid as I have run out of hostnames\n";
                                }
                                $x++;
                            }
                        }else{
                            foreach($this->data as $instanceid=>$instance){
                                $iids[]=$instanceid;
                                if(false!==($junk=$this->tagInstance($instanceid,$this->hostnames))){
                                    print "Instance: $instanceid tagged as " . $this->hostnames[$x] . "\n";
                                }else{
                                    print "Failed to tag $instanceid as " . $this->hostnames[$x] . "\n";
                                }
                            }
                        }
                        // print "Sleeping for 30 seconds to allow AWS to catch up\n";
                        // sleep(30);
                        // $di=new EC2Instances(false,$this->xaccesskey,$this->xsecretkey,$this->xregion);
                        // $tmp=$di->di();
                        // $this->data=array();
                        // foreach($iids as $iid){
                            // $this->data[$iid]=$tmp[$iid];
                        // }
                        $ret=$this->data;
                    }
                }
            }
        }
        return $ret;
    }/*}}}*/
    private function tagInstance($instanceid,$hostname)/*{{{*/
    {
        if(is_string($instanceid) && strlen($instanceid)){
            $tag=new EC2Tags(false,$this->xaccesskey,$this->xsecretkey,$this->xregion);
            if(false!==($ret=$tag->ct($instanceid,array("Name"=>$hostname)))){
                return true;
            }
        }
        return false;
    }/*}}}*/
    private function checkSettings()/*{{{*/
    {
        $ret=true;
        foreach($this->requiredsettings as $settingkey){
            if(!isset($this->settings[$settingkey])){
                print "$settingkey is not found\n";
                $ret=false;
            }
        }
        return $ret;
    }/*}}}*/
    private function decodeRawData()/*{{{*/
    {
    }/*}}}*/
    private function decodeNewRawInstances() /*{{{*/
    {
        $ret=false;
        $tarr=array_keys($this->rawdata);
        // print "raw data keys:\n";
        // print_r($tarr);
        // print "\n";
        // if(isset($this->rawdata["requestId"])){
            // print "requestId is set\n";
        // }else{
            // print "requestId is not set\n";
        // }
        // if(isset($this->rawdata["instancesSet"])){
            // print "InstancesSet is set\n";
        // }else{
            // print "InstancesSet is not set\n";
        // }
        if(isset($this->rawdata["requestId"]) && isset($this->rawdata["instancesSet"])){
            $this->data=array();
            // print "Checking for item\n";
            if(isset($this->rawdata["instancesSet"]["item"])){
                // print "item is there\n";
                // print "for each item\n";
                foreach($this->rawdata["instancesSet"]["item"] as $iset){
                    // print "flattening instance\n";
                    $tinst=$this->flattenInstance($iset);
                    // print_r($tinst);
                    // print "\n";
                    $this->data[$tinst["instanceId"]]=$tinst;
                }
            }
            $ret=true;
        }
        return $ret;
    } /*}}}*/
}
?>
