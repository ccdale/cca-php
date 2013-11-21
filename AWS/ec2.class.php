<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * ec2.class.php
 *
 * Started: Thursday 21 November 2013, 12:30:52
 * Last Modified: Thursday 21 November 2013, 15:38:32
 * Version: $Id$
 */

class EC2 extends Base
{
    private $params;
    private $gstr;
    private $qstr;
    private $sig;
    private $url;
    private $accesskey;
    private $secretkey;
    private $region;
    private $rawinstances;
    private $instances;

    public function __construct($logg=null,$accesskey="",$secretkey="",$region="eu-west-1")
    {
        parent::__construct($logg);
        $this->setAccessKey($accesskey);
        $this->setSecretKey($secretkey);
        $this->setRegion($region);
    }
    public function __destruct()
    {
    }
    public function setAccessKey($accessKey="")
    {
        if(is_string($accessKey)){
            $this->accesskey=$accessKey;
        }else{
            var_dump($accessKey);
        }
    }
    public function setSecretKey($secretKey="")
    {
        if(is_string($secretKey)){
            $this->secretkey=$secretKey;
        }
    }
    public function getRegion()
    {
        return $this->region;
    }
    public function setRegion($region="")
    {
        if(is_string($region)){
            $this->region=$region;
        }
    } 
    public function describeInstances()
    {
        $ret=false;
        $this->initParams();
        $this->params["Action"]="DescribeInstances";
        $this->rawinstances=$this->doCurl();
        if($this->decodeRawInstances()){
            $ret=$this->instances;
        }
        return $ret;
    }
    public function getRawData()
    {
        return $this->rawinstances;
    }
    private function initParams()
    {
        $this->params=array(
            "AWSAccessKeyId"=>$this->accesskey,
            "SignatureMethod"=>"HmacSHA256",
            "SignatureVersion"=>"2",
            "Version"=>"2013-10-15"
        );
        $this->params["Timestamp"]=gmdate("Y-m-d\TH:i:s\Z");
    }
    private function buildGetString()
    {
        uksort($this->params,"strcmp");
        $this->gstr="";
        foreach($this->params as $key=>$val){
            $tmp=rawurlencode($key) . "=" . rawurlencode($val);
            if(strlen($this->gstr)){
                $this->gstr.="&" . $tmp;
            }else{
                $this->gstr=$tmp;
            }
        }
        $this->gstr=str_replace("%7E","~",$this->gstr);
    }
    private function doCurl()
    {
        $this->buildGetString();
        $this->qstr="GET\nec2." . $this->region . ".amazonaws.com\n/\n" . $this->gstr;

        $this->sig=urlencode(base64_encode(hash_hmac("sha256",$this->qstr,$this->secretkey,true)));
        $this->url="https://ec2." . $this->region . ".amazonaws.com/?" . $this->gstr . "&Signature=" . $this->sig;
        $ch=curl_init();
        curl_setopt($ch,CURLOPT_URL,$this->url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        $res=curl_exec($ch);
        /*
         * the result is xml
         * the json dance will convert that into a php array
         */
        $arr=json_decode(json_encode(simplexml_load_string($res)),true);
        return $arr;
    }
    private function decodeRawInstances()
    {
        $ret=false;
        if(isset($this->rawinstances["requestId"]) && isset($this->rawinstances["reservationSet"])){
            $this->instances=array();
            foreach($this->rawinstances["reservationSet"]["item"] as $iset){
                $tinst=$this->flattenInstance($iset["instancesSet"]["item"]);
                $this->instances[$tinst["instanceId"]]=$tinst;
            }
            $ret=true;
        }
        return $ret;
    }
    private function flattenInstance($iarr)
    {
        $ckeys=array("instanceId","imageId","privateDnsName","keyName","instanceType","launchTime","kernelId","subnetId","vpcId","privateIpAddress","architecture","rootDeviceType","rootDeviceName","virtualizationType","hypervisor","ebsOptimized");
        $tinst=$this->copyKeys($ckeys,$iarr);
        if(isset($iarr["groupSet"])){
            if(isset($iarr["groupSet"]["item"])){
                $tinst["securityGroups"]=$this->flattenSet($iarr["groupSet"]["item"],"groupName","groupId");
            }
        }
        if(isset($iarr["tagSet"])){
            if(isset($iarr["tagSet"]["item"])){
                $tinst["tags"]=$this->flattenSet($iarr["tagSet"]["item"],"key","value");
            }
        }
        $tinst["state"]=$iarr["instanceState"]["name"];
        $tinst["statecode"]=$iarr["instanceState"]["code"];
        $tinst["availabilityZone"]=$iarr["placement"]["availabilityZone"];
        if(isset($tinst["tags"]["Name"])){
            $tinst["Name"]=$tinst["tags"]["Name"];
        }else{
            $tinst["Name"]=$tinst["instanceId"];
        }
        return $tinst;
    }
    private function copyKeys($keys,$arr)
    {
        $oarr=array();
        foreach($keys as $key){
            if(isset($arr[$key])){
                $oarr[$key]=$arr[$key];
            }
        }
        return $oarr;
    }
    private function flattenSet($arr,$namekey,$datakey)
    {
        $oarr=array();
        if(is_array($arr)){
            $cn=count($arr);
            if($cn>0){
                if(!isset($arr[0])){
                    $arr=array(0=>$arr);
                }
                foreach($arr as $set){
                    if(isset($set[$namekey]) && isset($set[$datakey])){
                        $oarr[$set[$namekey]]=$set[$datakey];
                    }
                }
            }
        }
        return $oarr;
    }
}
?>
