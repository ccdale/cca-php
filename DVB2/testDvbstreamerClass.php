#!/usr/bin/env php
<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * testDvbstreamerClass.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Sunday  1 June 2014, 19:49:12
 * Last Modified: Sunday  1 June 2014, 19:53:43
 * Revision: $Id$
 * Version: 0.00
 */

require_once "DVB2/dvbstreamer.class.php";
require_once "LOG/logging.class.php";
date_default_timezone_set("Europe/London");
$logg=new Logging(true,"DVB",0,LOG_DEBUG,true);
$dvb=new DvbStreamer($logg,0,"tvc","tvc");
sleep(10);
$dvb=null;
$logg=null;
?>
