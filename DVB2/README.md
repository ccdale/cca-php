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
