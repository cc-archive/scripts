#!/bin/bash

#
# A script that will upload squid log files to Amazon's S3 Internet storage
#

S3CMD_DIR="/home/everett/s3cmd"  # where s3sync files and scripts reside 
BUCKET="ccommons"  # the "bucket" name to upload to at S3
HOSTNAME=$(hostname -s) # the short hostname of this machine

# figure out where the apache logs reside on this machine based on hostname
case $HOSTNAME in
	"a2" )
		LOGDIR="/usr/local/squid/var/logs/combined"
		PREFIX="/var/log/squid"
		;;
	"a3" )
		LOGDIR="/usr/local/squid/var/logs/combined"
		PREFIX="/var/log/squid"
		;;
	"a5" )
		LOGDIR="/var/log/varnish"
		PREFIX="/var/log/varnish"
		;;
	"a6" )
		LOGDIR="/var/log/varnish"
		PREFIX="/var/log/varnish"
		;;
	* ) echo "Unrecognized hostname $HOSTNAME.  Exiting ...";  exit;;
esac

# get into the right dir so that the s3cmd.rb script can find all the files it needs
cd $S3CMD_DIR

for LOG in $(find $LOGDIR/*.gz)
do
	echo -n "Uploading $LOG ... "
	$S3CMD_DIR/s3cmd put $LOG s3://$BUCKET/$PREFIX/$HOSTNAME/$(basename $LOG) &> /dev/null

	# Delete original archive if upload was successful
	if [ $? == 0 ]
	then
		echo "SUCCESS.  Removing local copy."
		rm $LOG
	else
		echo "FAILED.  Leaving local copy in place."
	fi
done
