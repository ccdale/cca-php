#!/usr/bin/php
<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * filldb.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Friday 30 May 2014, 05:01:50
 * Last Modified: Sunday  1 June 2014, 08:20:07
 * Revision: $Id$
 * Version: 0.00
 */

require_once "DB/simple-mysql.class.php";
require_once "LOG/logging.class.php";
require_once "RadioTimes/radiotimes.class.php";
require_once "RadioTimes/filldb.class.php";

date_default_timezone_set("Europe/London");
ini_set('user_agent',"BGLM-Guide/0.1");

$processstart=time();

$logg=new Logging(false,"FILLDB",0,LOG_INFO);
$mx=new Mysql($logg,"localhost","tvapp","tvapp","tv");
$rt=new RadioTimes($logg);
$fdb=new FillDB($logg,$mx,$rt);
?>
