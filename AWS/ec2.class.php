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
 * Last Modified: Sunday 24 November 2013, 12:46:06
 * Version: $Id$
 */

class EC2 extends Base
{
    protected $params;
    protected $gstr;
    protected $qstr;
    protected $sig;
    protected $url;
    protected $accesskey;
    protected $secretkey;
    protected $region;
    protected $rawxml;
    protected $rawdata;
    protected $data;
    protected $ckeys;
    protected $csets;

    public function __construct($logg=false,$accesskey="",$secretkey="",$region="eu-west-1") /*{{{*/
    {
        parent::__construct($logg);
        $this->setAccessKey($accesskey);
        $this->setSecretKey($secretkey);
        $this->setRegion($region);
        $this->ckeys=array(
            "instances"=>array("instanceId","imageId","privateDnsName","keyName","instanceType","launchTime","kernelId","subnetId","vpcId","privateIpAddress","architecture","rootDeviceType","rootDeviceName","virtualizationType","hypervisor","ebsOptimized"),
            "iamages"=>array("imageId","imageLocation","imageState","imageOwnerId","isPublic","architecture","imageType","platform","imageOwnerAlias","rootDeviceType","blockDeviceMapping","virtualizationType","hypervisor")
        );
        $this->csets=array(
            "instances"=>array(
                array("key"=>"securityGroups","xkey"=>"groupSet","namekey"=>"groupName","datakey"=>"groupId"),
                array("key"=>"tags","xkey"=>"tagSet","namekey"=>"key","datakey"=>"value")
            ),
            "images"=>array(
                array("key"=>"tags","xkey"=>"tagSet","namekey"=>"key","datakey"=>"value")
            )
        );
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
    public function getRawXML()/*{{{*/
    {
        return $this->rawxml;
    }/*}}}*/
    public function getRawData() /*{{{*/
    {
        return $this->rawdata;
    } /*}}}*/
    protected function initParams() /*{{{*/
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
    protected function addParam($key,$var)/*{{{*/
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
    protected function addFilterParam($filter)/*{{{*/
    {
        if(false!==($cn=$this->ValidArray($filter))){
            $iter=1;
            foreach($filter as $key=>$val){
                if(false!==($cn=$this->ValidArray($val))){
                    $miter=1;
                    $name="Filter" . ". " . $iter . ".";
                    foreach($val as $item){
                        $this->addParam($name . "Name",$key);
                        $this->addParam($name . "Value" . "." . $miter,$item);
                        $miter++;
                    }
                    $iter++;
                }else{
                    $name="Filter" . ". " . $iter . ".";
                    $this->addParam($name . "Name",$key);
                    $this->addParam($name . "Value.1",$val);
                    $iter++;
                }
            }
        }
    }/*}}}*/
    protected function buildGetString() /*{{{*/
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
    protected function signRequest()/*{{{*/
    {
        $this->buildGetString();
        $this->qstr="GET\nec2." . $this->region . ".amazonaws.com\n/\n" . $this->gstr;
        $this->sig=urlencode(base64_encode(hash_hmac("sha256",$this->qstr,$this->secretkey,true)));
        $this->url="https://ec2." . $this->region . ".amazonaws.com/?" . $this->gstr . "&Signature=" . $this->sig;
    }/*}}}*/
    protected function doCurl() /*{{{*/
    {
        $this->signRequest();
        $ch=curl_init();
        curl_setopt($ch,CURLOPT_URL,$this->url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        if(false!==($this->rawxml=curl_exec($ch))){
            /*
             * the result is xml
             * the json dance will convert that into a php array
             */
            $arr=json_decode(json_encode(simplexml_load_string($res)),true);
            return $arr;
        }
        return false;
    } /*}}}*/
    protected function flattenInstance($iarr) /*{{{*/
    {
        $ret=false;
        if(false!==($tinst=$this->flattenData($iarr,"instances"))){
            $tinst["state"]=$iarr["instanceState"]["name"];
            $tinst["statecode"]=$iarr["instanceState"]["code"];
            $tinst["availabilityZone"]=$iarr["placement"]["availabilityZone"];
            if(isset($tinst["tags"]["Name"])){
                $tinst["Name"]=$tinst["tags"]["Name"];
            }else{
                $tinst["Name"]=$tinst["instanceId"];
            }
            $ret=$tinst;
        }
        return $ret;
    } /*}}}*/
    protected function flattenData($iarr,$datatype)/*{{{*/
    {
        $ret=false;
        if(false!==($cn=$this->ValidArray($iarr))){
            if(isset($this->ckeys[$datatype])){
                if(false!==($tinst=$this->copyKeys($this->ckeys[$datatype],$iarr))){
                    if(isset($this->csets[$datatype])){
                        foreach($this->csets[$datatype] as $set){
                            $tinst[$set["key"]]=flattenSet($iarr[$set["xkey"]],$set["namekey"],$set["datakey"]);
                        }
                    }
                }
            }
            $ret=$tinst;
        }
        return $ret;
    }/*}}}*/
    protected function flattenSet($arr,$namekey,$datakey) /*{{{*/
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
    protected function copyKeys($keys,$arr) /*{{{*/
    {
        $oarr=false;
        if(false!==($cn=$this->ValidArray($arr))){
            $oarr=array();
            foreach($keys as $key){
                if(isset($arr[$key])){
                    $oarr[$key]=$arr[$key];
                }
            }
        }
        return $oarr;
    } /*}}}*/
}
?>
