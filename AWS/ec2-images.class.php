<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * ec2-images.class.php
 *
 * Started: Sunday 24 November 2013, 12:34:44
 * Last Modified: Sunday 24 November 2013, 12:44:49
 * Revision: $Id$
 * Version: 0.00
 */

require_once "ec2.class.php";

class EC2Images extends EC2
{
    public function __construct($logg=false,$accesskey="",$secretkey="",$region="eu-west-1")/*{{{*/
    {
        parent::__construct($logg,$accesskey,$secretkey,$region);
        $this->ckeys["images"]=array("imageId","imageLocation","imageState","imageOwnerId","isPublic","architecture","imageType","platform","imageOwnerAlias","rootDeviceType","blockDeviceMapping","virtualizationType","hypervisor");
        $this->csets["images"]=array(
            array("key"=>"tags","xkey"=>"tagSet","namekey"=>"key","datakey"=>"value")
        );
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    /**
     * da
     * shorthand for describeAMIs (images)
     */
    public function da($executableby=false,$imageid=false,$owner=false,$filter=false)/*{{{*/
    {
        return $this->describeAMIs($executableby,$imageId,$owner,$filter);
    }/*}}}*/
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
        if(false!==($this->rawdata=$this->doCurl())){
            if($this->decodeRawAMIs()){
                $ret=$this->data;
            }
        }
        return $ret;
    }/*}}}*/
    private function decodeRawAMIs()/*{{{*/
    {
        $ret=false;
        if(isset($this->rawdate["requestId"]) && isset($this->rawdata["imagesSet"])){
            $this->data=array();
            if(isset($this->rawdata["imagesSet"]["item"])){
                foreach($this->rawdata["imagesSet"]["item"] as $iset){
                    $tinst=$this->flattenData($iset,"images");
                    $this->data[$tinst["imageId"]]=$tinst;
                }
            }
        }
        return $ret;
    }/*}}}*/
}
?>
