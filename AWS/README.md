AWS Classes and Examples
========================

Fledgeling classes for chatting to the amazon aws apis.

put your aws credentials in $HOME/.aws.conf.php (see template).  See aws-example.php for initial usage.

.aws.conf.php template
----------------------

```php
<?php
$accesskey="<AWS ACCESS KEY ID>";
$secret="<AWS SECRET KEY>";
$region="eu-west-1";
?>
```

Example usage
-------------

```php
/*
 * get ami list
 */
$images=new EC2Images(false,$accesskey,$secret,$region);
if(false===($arr=$images->da(false,false,"self"))){
    $tmp=$images->getRawData();
    print_r($tmp);
}else{
    print_r($arr);
}
/*
 * get instances list
 */
$instances=new EC2Instances(false,$accesskey,$secret,$region);
if(false===($arr=$instances->di())){
    print "failed\n";
    $tmp=$instances->getRawXML();
    print "$tmp\n";
}else{
    print_r($arr);
}
/*
 * create tags
 */
$tags=new EC2Tags(false,$accesskey,$secret,$region);
if(false===($ret=$tags->ct("<INSTANCEID>",array("group"=>"search")))){
    print "failed\n";
    print_r($tags->getRawXML());
    print "\n";
}else{
    $arr=$tags->dt(array("resource-id"=>"<INSTANCEID>","resource-type"=>"instance"));
    print_r($arr);
}
```
