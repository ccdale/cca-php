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
 * Last Modified: Saturday 23 November 2013, 11:21:39
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
    private $rawdata;
    private $instances;

    public function __construct($logg=null,$accesskey="",$secretkey="",$region="eu-west-1") /*{{{*/
    {
        parent::__construct($logg);
        $this->setAccessKey($accesskey);
        $this->setSecretKey($secretkey);
        $this->setRegion($region);
    } /*}}}*/
    public function __destruct() /*{{{*/
    {
    } /*}}}*/
    public function setAccessKey($accessKey="") /*{{{*/
    {
        if(is_string($accessKey)){
            $this->accesskey=$accessKey;
        }else{
            var_dump($accessKey);
        }
    } /*}}}*/
    public function setSecretKey($secretKey="") /*{{{*/
    {
        if(is_string($secretKey)){
            $this->secretkey=$secretKey;
        }
    } /*}}}*/
    public function getRegion() /*{{{*/
    {
        return $this->region;
    } /*}}}*/
    public function setRegion($region="") /*{{{*/
    {
        if(is_string($region)){
            $this->region=$region;
        }
    } /*}}}*/
    public function di()/*{{{*/ /*{{{*/
    {
        return $this->describeInstances();
    }/*}}}*/ /*}}}*/
    public function describeInstances() /*{{{*/
    {
        $ret=false;
        $this->initParams();
        $this->params["Action"]="DescribeInstances";
        $this->rawdata=$this->doCurl();
        if($this->decodeRawInstances()){
            $ret=$this->instances;
        }
        return $ret;
    } /*}}}*/
    /**
     * describeAMIs
     * list your ami images
     *
     * see: http://docs.aws.amazon.com/AWSEC2/latest/APIReference/ApiReference-query-DescribeImages.html
     * returns: array of ami images
     *
     * $executableby: string or array of strings
     * $imageid: string or array of strings
     * $owner: string or array of strings
     * $filter: array of key=>val pairs
     */
    public function describeAMIs($executableby=false,$imageid=false,$owner=false,$filter=false)/*{{{*/
    {
        $ret=false;
        $this->initParams();
        $this->addParam("Action","DescribeImages");
        $this->addParam("ExecutableBy",$executableby);
        $this->addParam("ImageId",$imageid);
        $this->addParam("Owner",$owner);
        $this->addFilterParam("Filter",$filter);
    }/*}}}*/
    public function getRawData() /*{{{*/
    {
        return $this->rawdata;
    } /*}}}*/
    private function initParams() /*{{{*/
    {
        $this->params=array(
            "AWSAccessKeyId"=>$this->accesskey,
            "SignatureMethod"=>"HmacSHA256",
            "SignatureVersion"=>"2",
            "Version"=>"2013-10-15"
        );
        $this->params["Timestamp"]=gmdate("Y-m-d\TH:i:s\Z");
    } /*}}}*/
    /**
     * addParam
     * adds a string or array of strings to the parameters array
     *
     * returns: nothing
     * $key: string
     * $var: false, string or array of strings
     */
    private function addParam($key,$var)/*{{{*/
    {
        $iter=1;
        if(false!==$var){
            if(false!==($cn=$this->ValidArray($var))){
                foreach($var as $item){
                    $name=$key . "." . $iter;
                    $this->params[$name]=$item;
                    $iter++;
                }
            }elseif(false!==($cn=$this->ValidStr($var))){
                $name=$key . "." . $iter;
                $this->params[$name]=$var;
            }
        }
    }/*}}}*/
    /**
     * addFilterParam
     * adds filter parameters
     *
     * returns: nothing
     *
     * $filter: array("filtername"=>"filterval","filtername"=>array("filterval","filterval"))
     *
     */
    private function addFilterParam($filter)/*{{{*/
    {
        if(false!==($cn=$this->ValidArray($filter))){
            $iter=1;
            foreach($filter as $key=>$val){
                if(false!==($cn=$this->ValidArray($val))){
                    $miter=1;
                    foreach($val as $item){
                        $name="Filter" . "." . $iter . "." . $key . "." . $miter;
                        $this->addParam($name,$item);
                        $iter++;
                        $miter++;
                    }
                }else{
                    $name="Filter" . ". " . $iter . "." . $key;
                    $this->addParam($name,$val);
                    $iter++;
                }
            }
        }
    }/*}}}*/
    private function buildGetString() /*{{{*/
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
    } /*}}}*/
    private function signRequest()/*{{{*/
    {
        $this->buildGetString();
        $this->qstr="GET\nec2." . $this->region . ".amazonaws.com\n/\n" . $this->gstr;
        $this->sig=urlencode(base64_encode(hash_hmac("sha256",$this->qstr,$this->secretkey,true)));
        $this->url="https://ec2." . $this->region . ".amazonaws.com/?" . $this->gstr . "&Signature=" . $this->sig;
    }/*}}}*/
    private function doCurl() /*{{{*/
    {
        $this->signRequest();
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
    } /*}}}*/
    private function decodeRawInstances() /*{{{*/
    {
        $ret=false;
        if(isset($this->rawdata["requestId"]) && isset($this->rawdata["reservationSet"])){
            $this->instances=array();
            foreach($this->rawdata["reservationSet"]["item"] as $iset){
                $tinst=$this->flattenInstance($iset["instancesSet"]["item"]);
                $this->instances[$tinst["instanceId"]]=$tinst;
            }
            $ret=true;
        }
        return $ret;
    } /*}}}*/
    private function flattenInstance($iarr) /*{{{*/
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
    } /*}}}*/
    private function copyKeys($keys,$arr) /*{{{*/
    {
        $oarr=array();
        foreach($keys as $key){
            if(isset($arr[$key])){
                $oarr[$key]=$arr[$key];
            }
        }
        return $oarr;
    } /*}}}*/
    private function flattenSet($arr,$namekey,$datakey) /*{{{*/
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
    } /*}}}*/
}
?>
