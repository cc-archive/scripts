#!/usr/bin/perl -w

use strict;

#
# Script for pulling backups down from various remote servers
#

my $rdiff_prog = "/usr/bin/rdiff-backup";
my $rdiff_flags = "--preserve-numerical-ids";
my @remote_hosts = ('a4');
my $remote_host;
my $data_dir = "/home/everett/backups"; # were backups are kept on this machine
my $pid_file = "/home/everett/.fetch_backups.pid";
my $remove_older_than = "7"; # number of days to keep backup data (diffs) for any given file
my $time_format = "\nTime: %E, CPU: %P"; # formatting for output of /usr/bin/time command

# check to see if an instance of the script is already running.  if so, then
# exit, else continue.  this could be necessary because sometimes a backup
# of a massive directory may take several days if it hasn't been backed up
# before.  in this case we don't want to launch another instance of this
# script until the other is done.
if ( -s "$pid_file" && system('ps `cat $pid_file`') == 0 ) {
	print "It seems that another instance of $0 is still/already running.  Exiting ...\n";
	exit;
}

# drop this processes pid into a file.
system("echo $$ > $pid_file");

# here you can specify the command to use to connect to the remote host.
# mostly this is so that you may specify a port other than 22 for ssh
# but could conceivably be used for any other option that ssh supports
# as well as using something other than ssh altogether.
my %remote_schemas = (
	'apps' => 'ssh -C -F /home/everett/.ssh/config %s rdiff-backup --server',
	'a2' => 'ssh -C -F /home/everett/.ssh/config %s rdiff-backup --server',
	'a3' => 'ssh -C -F /home/everett/.ssh/config %s rdiff-backup --server',
	'a4' => 'ssh -C -F /home/everett/.ssh/config %s rdiff-backup --server'
);
my $remote_schema;

# dirs to backup that are common to all remote_hosts.
my @global_includes = (
	'/etc',
	'/var',
	'/web',
	'/usr/local'
);
my $global_include;

# dirs NOT to backup that are common to all remote_hosts.
# this should only include subdirectories of included
# directories because everything is excluded by default
my @global_excludes = (
	'/tmp',
	'/var/log'
);
my $global_exclude;

# host specific includes
my %host_includes = (
	'apps' => [],
	'a2' => [],
	'a3' => [],
	'a4' => [] 
);
my $host_include;

# host specific excludes
my %host_excludes = (
	'apps' => [
		'/web/archives',
		'/web/mirrors'
	],
	'a2' => [
		'/var/lib/aolserver',
		'/var/lib/zope',
		'/var/local',
		'/usr/local'
	],
	'a3' => [
		'/usr/local'
	],
	'a4'=> [
		'/usr/local/apache220',
		'/usr/local/backup'
	] 
);
my $host_exclude;

my $includes;
my $excludes;
my $exclude;
my $include;

# step through each remote host
foreach $remote_host ( @remote_hosts ) {

	# clear out include/exclude variables for new remote host
	$includes = "";
	$excludes = "";

	# go adding all the various includes/excludes to a single include and exclude
	# string and also print some hopefully useful information

	print "=> Backing up host $remote_host:\n";

	# put --include and --exclude in front of all the specified directories.
	# host specific includes and excludes come first so that they take precedence
 	# over the globals

	print "\t -- Host specific excludes:\n";
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

	print "\t ++ Host specific includes:\n";
	foreach $host_include ( $host_includes{$remote_host} ) {
		if ( @$host_include != 0 ) {
			foreach $include ( @$host_include ) {
				print "\t\t  $include\n";
				$includes .= " --include $include ";
			}
		} else {
			print "\t\t(none)\n";
		}
	}

	print "\t ++ Global includes:\n";
	foreach $global_include ( @global_includes ) {
		print "\t\t  $global_include\n";
		$includes .= " --include $global_include ";
	}

	print "\t -- Global excludes:\n";
	foreach $global_exclude ( @global_excludes ) {
		print "\t\t  $global_exclude\n";
		$excludes .= " --exclude $global_exclude ";
	}

	$remote_schema = $remote_schemas{$remote_host};
		
	# backup the data
	system ("/usr/bin/time -f '$time_format' $rdiff_prog $rdiff_flags --remote-schema '$remote_schema' $excludes $includes --exclude '**' $remote_host\-backup\:\:/ $data_dir/$remote_host");

	# remove any diff/backup data that is older than $remove_older_than days
	print "\nNow removing any diff/backup data older than $remove_older_than days.\n";
	system ("$rdiff_prog --force --remove-older-than ${remove_older_than}D $data_dir/$remote_host");

	print "\n";
	
}

# now that we are done, delete the pid file
system("rm $pid_file");
