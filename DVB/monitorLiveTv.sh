#!/bin/bash
# $Id: monitorLiveTv.sh 613 2011-06-08 17:05:09Z chris $
#
pgrep livetv >/dev/null 2>&1
if [ $? -eq 1 ]
then
    # livetv is not running
    pgrep dvbstreamer >/dev/null 2>&1
    if [ $? -eq 0 ]
    then
        # but dvbstreamer is
        logger -t LiveTvMonitor "Dvbstreamer running but livetv is not, will attempt to start it"
        # start livetv to check for orphaned recordings
        /home/chris/bin/livetv
    else
        logger -t LiveTvMonitor "OK"
    fi
else
    logger -t LiveTvMonitor "OK"
fi
