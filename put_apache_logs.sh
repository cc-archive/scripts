#!/bin/bash

#
# A script that will upload apache log files to Amazon's S3 Internet storage
#

S3CMD_DIR="/home/everett/s3cmd"  # where s3cmd files and scripts reside 
BUCKET="ccommons"  # the "bucket" name to upload to at S3
PREFIX="/var/log/apache"  # a prefix for the S3 key, provides a pseudo directory-like structure
HOSTNAME=$(hostname -s) # the short hostname of this machine

# figure out where the apache logs reside on this machine based on hostname
case $HOSTNAME in
	"apps" ) LOGDIR="/var/log/apache2";;
	"a2" ) LOGDIR="/var/log/httpd";;
	"a3" ) LOGDIR="/var/log/httpd";;
	"a4" ) LOGDIR="/usr/local/apache223/logs";;
	"a5" ) LOGDIR="/var/log/apache2";;
	"a6" ) LOGDIR="/var/log/apache2";;
	* ) echo "Unrecognized hostname $HOSTNAME.  Exiting ...";  exit;;
esac

# get into the right dir so that the s3cmd script can find all the files it needs
# in the pwd
cd $S3CMD_DIR

for LOG in $(find $LOGDIR -name "*.gz")
do
	echo -n "Uploading $LOG ... "
	# since we have our apache logs in subdirs based on vhost name we
	# want to upload each log file to a "subdir" of the main host. for this
	# we just use sed to remove the LOGDIR and then we append the remainder
	# to our PREFIX, but sed will choke on the slashes in LOGDIR, so we've
	# got to escape them, thus the crazy bash substitution
	FILENAME=$(echo $LOG | sed -e "s/"${LOGDIR//\//\\\/}"\///")

	$S3CMD_DIR/s3cmd put $LOG s3://$BUCKET/$PREFIX/$HOSTNAME/$FILENAME &> /dev/null

	# Delete original archive if upload was successful
	if [ $? == 0 ]
	then
		echo "SUCCESS.  Removing local copy."
		rm $LOG
	else
		echo "FAILED.  Leaving local copy in place."
	fi
done
