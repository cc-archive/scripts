#!/usr/bin/perl -w

use strict;

#
# Script for pulling apache log files down from various remote servers and then
# removing those same log files from the remote host
#

# predefine various variables
my $program = "/usr/bin/rsync";
my $program_flags = "-v -t --remove-source-files";
my @remote_hosts = ('apps', 'a2', 'a3', 'a4');
my $remote_host;
my $data_dir = "/home/everett/apache_logs"; # were data is kept on this machine
my $pid_file = "/home/everett/.fetch_apache_logs.pid";
my $time_format = "\nTime: %E, CPU: %P"; # output format of the time command

# check to see if an instance of the script is already running.  if so, then
# exit, else continue. 
if ( -s "$pid_file" && system('ps `cat $pid_file` > /dev/null') == 0 ) {
	print "It seems that another instance of $0 is still/already running.  Exiting ...\n";
	exit;
}

# drop this processes pid into a file.
system("echo $$ > $pid_file");

# host specific apache log file locations.  this is an associative array, so you
# may specify multiple locations. 
# NOTE: since this script is run as root on the remote side and because we are
# actually removing the files once they are copied here then there are some
# restrictions as to what directories we may pull and then delete files from.
# these restrictions are located in $remote_host:/root/.ssh/authorized_keys in
# front of the relevant key in the "command=" section.  This means that the 
# paths below are RELATIVE to that path.  the "command" on the remote side is
# a perl script called /home/everett/bin/rrsync and it comes as part of some
# rsync installs.  For the sake of clarity, please define in comments the root
# part of the relative paths below:
#
# apps: [/var/log/]apache2/*.gz
# a2: [/var/log/]httpd/*.gz
# a3: [/var/log/]httpd/*.gz
# a4: [/var/log/]httpd/*.gz

my %host_logs = (
	'apps' => [
		'apache2/*.gz'
	],
	'a2' => [
		'httpd/*.gz'
	],
	'a3' => [
		'httpd/*.gz'
	],
	'a4' => [
		'httpd/*.gz'
	] 
);
my $host_log;

# host specific excludes.  if there is some subset of files and/or directoriee
# that are contained within the %host_logs above, then may specify those here 
# on a per-host basis
my %host_excludes = (
	'apps' => [
	],
	'a2' => [
	],
	'a3' => [
	],
	'a4'=> [
	] 
);
my $host_exclude;

my $logs;
my $log;
my $excludes;
my $exclude;

# step through each remote host
foreach $remote_host ( @remote_hosts ) {

	# clear out the per-host logs and excludes with each loop
	$logs = "";
	
	$excludes = "";

	print "=> Fetching Apache logs from $remote_host:\n";

	# collect the various log files and dirs
	print "\t ++ Pulling log files:\n";
	foreach $host_log ( $host_logs{$remote_host} ) {
		if ( $host_log ) {
			foreach $log ( @$host_log ) {
				print "\t\t  $log\n";
				$logs .= "$log ";
			}
		}
	}

	# put --exclude in front of all the specified directories.
	print "\t -- Excludes:\n";
	foreach $host_exclude ( $host_excludes{$remote_host} ) {
		if ( @$host_exclude != 0 ) {
			foreach $exclude ( @$host_exclude ) {
				print "\t\t  $exclude\n";
				$excludes .= " --exclude $exclude ";
			}
		} else {
			print "\t\t(none)\n";
		}
	}

	print "\n";

	system ("/usr/bin/time -f '$time_format' $program $program_flags $excludes $remote_host-apache-logs\:'$logs' $data_dir/$remote_host");

	print "\n";
	
}

# now that we are done, delete the pid file
system("rm $pid_file");
