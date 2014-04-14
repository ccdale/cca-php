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
 * Last Modified: Monday 14 April 2014, 03:59:04
 * Revision: $Id$
 * Version: 0.00
 */

$AWSLIB=dirname(__FILE__);
$LIB=dirname($AWSLIB);
require_once "$LIB/base.class.php";
require_once "$AWSLIB/ec2.class.php";
require_once "$AWSLIB/ec2-secgroups.class.php";
// require_once "$AWSLIB/ec2-instances.class.php";
// require_once "$AWSLIB/ec2-images.class.php";
// require_once "$AWSLIB/ec2-tags.class.php";
// require_once "$AWSLIB/ec2-create-instance.class.php";

$home=getenv("HOME");
$credentials="$home/.aws.conf.php";
if(file_exists($credentials)){
    require_once $credentials;
}

/*
 * get ami list
 *
$images=new EC2Images(false,$accesskey,$secret,$region);
if(false===($arr=$images->da(false,false,"self"))){
    $tmp=$images->getRawData();
    print_r($tmp);
}else{
    print_r($arr);
}
 */
/*
 * get instances list
 *
$instances=new EC2Instances(false,$accesskey,$secret,$region);
if(false===($arr=$instances->di())){
    print "failed\n";
    $tmp=$instances->getRawXML();
    print "$tmp\n";
}else{
    print_r($arr);
}
*/
/*
 * create tags
 *
$tags=new EC2Tags(false,$accesskey,$secret,$region);
if(false===($ret=$tags->ct("<INSTANCEID>",array("group"=>"search")))){
    print "failed\n";
    print_r($tags->getRawXML());
    print "\n";
}else{
    $arr=$tags->dt(array("resource-id"=>"<INSTANCEID>","resource-type"=>"instance"));
    print_r($arr);
}
 */
/*
 * create instance
 */
/*
$settings=array(
    "SecurityGroupId"=>array(
        "sg-01e3f563"
    ),
    "KeyName"=>"ccaaws",
    "InstanceType"=>"t1.micro",
    // "SubnetId"=>"subnet-d5d5fea1",
    "UserData"=>"instancesetup.sh",
    "ImageId"=>"ami-c7ec0eb0",
    "Hostnames"=>array(
        "prod-test-3",
        "prod-test-4"
    )
);
$ci=new EC2CreateInstance(false,$accesskey,$secret,$region,$settings);
$arr=$ci->runInstance();
// print_r($arr);
 */
/*
 * get instances list
 */
/*
$instances=new EC2Instances(false,$accesskey,$secret,$region);
if(false===($arr=$instances->di())){
    print "failed\n";
    $tmp=$instances->getRawXML();
    print "$tmp\n";
}else{
    print_r($arr);
}
 */
$sg=new EC2SecGroups(false,$accesskey,$secret,$region);
if(false===($arr=$sg->describeSecurityGroups())){
    print "failed\n";
    $tmp=$sg->getRawXML();
    print "$tmp\n";
}else{
    print_r($arr);
    print "\n";
}
?>
