# Makefile for ccabackup
# $Id: Makefile 4 2011-06-29 10:52:12Z chris $
#
all:
	@echo "Nothing to compile"

clean:
	@echo "Nothing to clean"

install:
	install -d $(DESTDIR)/usr/share/cca-php
	install -d $(DESTDIR)/usr/share/cca-php/Unix
	install -d $(DESTDIR)/usr/share/cca-php/Rip
	install -d $(DESTDIR)/usr/share/cca-php/HTML
	install -d $(DESTDIR)/usr/share/cca-php/DB
	install -d $(DESTDIR)/usr/share/cca-php/Shell
	install -d $(DESTDIR)/usr/share/cca-php/LOG
	install -d $(DESTDIR)/usr/share/cca-php/DVB
	install -d $(DESTDIR)/usr/share/cca-php/App
	install -m +r cli.php $(DESTDIR)/usr/share/cca-php/
	install -m +r file.php $(DESTDIR)/usr/share/cca-php/
	install -m +r local.php $(DESTDIR)/usr/share/cca-php/
	install -m +r php.php $(DESTDIR)/usr/share/cca-php/
	install -m +r string.php $(DESTDIR)/usr/share/cca-php/
	install -m +r time.php $(DESTDIR)/usr/share/cca-php/
	install -m +r video.php $(DESTDIR)/usr/share/cca-php/
	install -m +r App/debug.php $(DESTDIR)/usr/share/cca-php/App/
	install -m +r App/www.php $(DESTDIR)/usr/share/cca-php/App/
	install -m +r DB/data.class.php $(DESTDIR)/usr/share/cca-php/DB/
	install -m +r DB/mysql.class.php $(DESTDIR)/usr/share/cca-php/DB/
	install -m +r DVB/actor.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/adapterdb.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/channel.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/channelrecording.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/config.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/convertts.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/dvbctrl.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/dvblistings.functions.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/dvbstreamer.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/epgdb.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/ffprobe.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/lame.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +rx DVB/livetv.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +rx DVB/livetvts.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +rx DVB/monitorLiveTv.sh $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/mplex.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/previousrecorded.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/program.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/projectx.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/recordedfile.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/recording.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/rfile.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/rprog.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/series.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/stream.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/tsfile.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/tsprocessor.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r DVB/tvddb.class.php $(DESTDIR)/usr/share/cca-php/DVB/
	install -m +r HTML/class.table.php $(DESTDIR)/usr/share/cca-php/HTML/
	install -m +r HTML/form.class.php $(DESTDIR)/usr/share/cca-php/HTML/
	install -m +r HTML/input_field.class.php $(DESTDIR)/usr/share/cca-php/HTML/
	install -m +r HTML/link.class.php $(DESTDIR)/usr/share/cca-php/HTML/
	install -m +r HTML/option_field.class.php $(DESTDIR)/usr/share/cca-php/HTML/
	install -m +r HTML/select_field.class.php $(DESTDIR)/usr/share/cca-php/HTML/
	install -m +r HTML/tag.class.php $(DESTDIR)/usr/share/cca-php/HTML/
	install -m +r LOG/logging.class.php $(DESTDIR)/usr/share/cca-php/LOG/
	install -m +r Rip/audiobookripper.class.php $(DESTDIR)/usr/share/cca-php/Rip/
	install -m +r Rip/cdripper.class.php $(DESTDIR)/usr/share/cca-php/Rip/
	install -m +r Rip/lame.class.php $(DESTDIR)/usr/share/cca-php/Rip/
	install -m +r Shell/background.class.php $(DESTDIR)/usr/share/cca-php/Shell/
	install -m +r Shell/extern.class.php $(DESTDIR)/usr/share/cca-php/Shell/
	install -m +r Unix/passwd.class.php $(DESTDIR)/usr/share/cca-php/Unix/
	install -m +r Unix/process.class.php $(DESTDIR)/usr/share/cca-php/Unix/
	install -m +r Unix/processtable.class.php $(DESTDIR)/usr/share/cca-php/Unix/
