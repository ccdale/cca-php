DVB2
====

Classes and functions to control dvbstreamer from http://sourceforge.net/projects/dvbstreamer/

dvbstreamer.class.php
---------------------

````
    public function __construct($logg=false,$adaptor=0,$user="tvc",$pass="tvc")
````

This will start a new dvbstreamer process or use an already running one.


dvbctrl.class.php
-----------------

````
    public function __construct($logg=false,$host="",$user="",$pass="",$adaptor=0,$dvb=false)
````

Class to control a running dvbstreamer process (via the dvbstreamer class
above). It will start a new process if a DvbStreamer object is not passed to
it. This is probably not what you want, so instantiate a DvbStreamer object
first and pass it to the class, as this class will kill any dvbstreamer
process it starts when it closes.

````
    public function __construct($logg=false,$host="",$user="",$pass="",$adaptor=0,$dvb=false)
````
instantiate this class
if a DvbStreamer object is not passed then a new one is created which will
    be destroyed when this class exits.

$logg logging object
$host ip address or name of host running dvbstreamer
$user username to connect to dvbstreamer as
$pass password for same
$apaptor index number of DVB adaptor
$dvb DvbStreamer object

````
    public function __destruct()
````
destroy this class

````
    public function request($cmd="",$argarr="",$auth=true)
````
send a request to the dvbstreamer

$cmd the command to send
$argarr string or array of arguments. If array each member will be added to
the command separated by a space
$auth whether to check if we have authenticated or not yet. some commands
will not be processed if we haven't authenticated.

````
    public function serviceInfo($service)  
````
retrieves information about the named service. returns an array or false.

$service string the service to get information about.

````
    public function select($service,$filternumber=0,$nowait=false) 
````
tunes to the selected service using the selected filter

$service string the service name to tune to
$filternumber int the filter to use
$nowait bool whether to wait for a signal lock or not before returning

````
    public function setmrl($file,$filternumber=0) 
````
Set the file output for the filter

$file string filename to write to
$filternumber int which filter to set this as the output file for

````
    public function setUdpMrl($port,$filternumber=0) 
````
Set the filter to output to a udp socket

$port int port number to output to
$filternumber int which filter to set this as the output socket

````
    public function lsRecording($usecache=true) 
````
return information about what is currently being recorded in an array

$usecache bool whether to use the recording cache or request current
information

````
    public function safeToRecordService($service) 
````
Check whether it will be necessary to returne to record the named service

$service string name of service to check

    public function filterNumberForFile($file="") 
    public function serviceForFile($file="") 
    public function recordNewService($service,$file) 
    public function stopRecording($file="") 
    public function stopByFilterNumber($filternumber=false) 
    public function split($file="",$newfilename="") 
    public function streamNewService($service,$port) 
    public function stopFilter($filter) 
    public function cleanupServiceFilters($force=false) 
    public function setFavsonlyOn() 
    public function setFavsonlyOff() 
````
