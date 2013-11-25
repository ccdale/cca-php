<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * aws-example.php
 *
 * Started: Sunday 24 November 2013, 12:48:56
 * Last Modified: Monday 25 November 2013, 04:04:23
 * Revision: $Id$
 * Version: 0.00
 */

$AWSLIB=dirname(__FILE__);
$LIB=dirname($AWSLIB);
require_once "$LIB/base.class.php";
require_once "$AWSLIB/ec2.class.php";
require_once "$AWSLIB/ec2-instances.class.php";
require_once "$AWSLIB/ec2-images.class.php";
require_once "$AWSLIB/ec2-tags.class.php";

$home=getenv("HOME");
$credentials="$home/.aws.conf.php";
if(file_exists($credentials)){
    require_once $credentials;
}

/*
$images=new EC2Images(false,$accesskey,$secret,$region);
if(false===($arr=$images->da(false,false,"self"))){
    $tmp=$images->getRawData();
    print_r($tmp);
}else{
    print_r($arr);
}
$instances=new EC2Instances(false,$accesskey,$secret,$region);
if(false===($arr=$instances->di())){
    print "failed\n";
    $tmp=$instances->getRawXML();
    print "$tmp\n";
}else{
    print_r($arr);
}
 */
$tags=new EC2Tags(false,$accesskey,$secret,$region);
$arr=$tags->dt(array("resource-id"=>"i-90b954de","resource-type"=>"instance"));
print_r($tags->getRawXML());
print "\n";
print_r($tags->getRawData());
print_r($arr);
?>
