#!/usr/bin/env php
<?php

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker:
 *
 * testDVBCtrlClass.php
 *
 * C.C.Allison
 * chris.allison@hotmail.com
 *
 * Started: Tuesday  3 June 2014, 07:44:38
 * Last Modified: Thursday  5 June 2014, 17:20:22
 * Revision: $Id$
 * Version: 0.00
 */

require_once "DVB2/dvbstreamer.class.php";
require_once "DVB2/dvbctrl.class.php";
require_once "LOG/logging.class.php";
date_default_timezone_set("Europe/London");
$logg=new Logging(true,"DVB",0,LOG_DEBUG,true);
$dvb=new DvbStreamer($logg,0,"tvc","tvc");
sleep(1);
$logg->debug("dvbctrl instantiating");
$ctrl=new DVBCtrl($logg,"127.0.0.1","tvc","tvc",0);
$logg->debug("dvbctrl should now be instantiated");
$arr=$ctrl->request("lsmuxes");
print_r($arr);
// sleep(60);
$ctrl=null;
$dvb=null;
?>
