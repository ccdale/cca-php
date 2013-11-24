<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * ec2-tags.class.php
 *
 * Started: Sunday 24 November 2013, 19:18:17
 * Last Modified: Sunday 24 November 2013, 20:08:46
 * Revision: $Id$
 * Version: 0.00
 */

require_once "ec2.class.php";

class EC2Tags extends EC2
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
    }/*}}}*/
    public function __destruct()/*{{{*/
    {
        parent::__destruct();
    }/*}}}*/
    /** ct {{{
     * shorthand for createTags
     */
    public function ct($resourceid,$tags)
    {
        return $this->createTags($resourceid,$tags);
    }/*}}}*/
    /** createTags {{{
     * create or overwrite resource tags
     *
     * $resourceid: string the resourceid of the tags to create/update
     * $tags: array of key=>val pairs
     */
    public function createTags($resourceid,$tags)
    {
        $ret=false;
        if(false!==($cn=$this->ValidStr($resourceid))){
            if(false!==($cn=$this->ValidArray($tags))){
                $this->initParams("CreateTags");
                $this->addFilterParam($tags,true);
                if(false!==($this->rawdata=$this->doCurl())){
                    if(isset($this->rawdata["requestId"]) && isset($this->rawdata["return"])){
                        if($this->rawdata["return"]=="true"){
                            $ret=true;
                        }
                    }
                }
            }
        }
        return $ret;
    }/*}}}*/
    /** describeTags {{{
     * returns all the tags for a resource
     *
     * see: http://docs.aws.amazon.com/AWSEC2/latest/APIReference/ApiReference-query-DescribeTags.html
     *
     * $filter: array of key=>val pairs
     */
    public function describeTags($filter)
    {
        $ret=false;
        if(false!==($cn=$this->ValidArray($filter))){
            $this->initParams("DescribeTags");
            $this->addFilterParam($filter);
            if(false!==($this->rawdata=$this->doCurl())){
                if(isset($this->rawdata["requestId"]) && isset($this->rawdata["tagSet"])){
                    $this->data=array("tags"=>$this->flattenSet($this->rawdata["tagSet"],"key","value"));
                    $ret=$this->data;
                }
            }
        }
        return $ret;
    }/*}}}*/
    /** dt {{{
     * shorthand for describeTags
     */
    public function dt($filter)
    {
        return $this->describeTags($filter);
    }/*}}}*/
}
?>
