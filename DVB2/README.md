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

````
    public function filterNumberForFile($file="") 
````
Which filter is currently recording to the named file

$file string file name to check for

````
    public function serviceForFile($file="") 
````
Which service is currently being streamed into the named file

$file string file name to check for

````
    public function recordNewService($service,$file) 
````
Start to record a new service to a new file

$service string Channel name to record
$file string file name to record into

````
    public function stopRecording($file="") 
````
Stop recording the named file

$file string file name to stop recording

````
    public function stopByFilterNumber($filternumber=false) 
````
Stop recording the file indicated by the filter number supplied

$filternumber int the number of the filter to stop recording

````
    public function split($file="",$newfilename="") 
````
Stop recording into the current named file and continue into the newly named
file

$file string currently recorded file
$newfilename string name of the new file to continue recording into

````
    public function streamNewService($service,$port) 
````
Start streaming the named service to the named udp port

$service string the service to start streaming
$port int the udp port number to stream to

````
    public function stopFilter($filter) 
````
Stop the named filter

$filter int the filter to stop recording/streaming

````
    public function setFavsonlyOn() 
````
Set favsonly on

````
    public function setFavsonlyOff() 
````
Set favsonly off
