#!/usr/bin/perl -w

use strict;

#
# Script for pulling mysqldumps down from various remote servers
#

# predefine various variables
my $program = "/usr/bin/scp";
my $program_flags = "";
my @remote_hosts = ('apps', 'a2', 'a3', 'a4', 'a5', 'a6');
my $remote_host;
my $data_dir = "/home/everett/mysql-backups"; # were data is kept on this machine
my $pid_file = "/home/everett/.fetch_mysqldumps.pid";

# check to see if an instance of the script is already running.  if so, then
# exit, else continue. 
if ( -s "$pid_file" && system('ps `cat $pid_file` > /dev/null') == 0 ) {
	print "It seems that another instance of $0 is still/already running.  Exiting ...\n";
	exit;
}

# drop this processes pid into a file.
system("echo $$ > $pid_file");

# where the dump file should reside on the remote side
my %host_dumps = (
	'apps' => "/home/everett/mysqldumps-apps.tgz",
	'a2' => "/home/everett/mysqldumps-a2.tgz",
	'a3' => "/home/everett/mysqldumps-a3.tgz",
	'a4' => "/home/everett/mysqldumps-a4.tgz",
	'a5' => "/home/everett/mysqldumps-a5.tgz",
	'a6' => "/home/everett/mysqldumps-a6.tgz"
);

# the mysql root password for each host.  include the -p too if necessary
# as this whole string will passed to mysql.  on boxes with no root mysql
# password just put an empty double quote.
my %host_mysql_pwds = (
	'apps' => '-pccadmin',
	'a2' => '',
	'a3' => '',
	'a4' => '',
	'a5' => '',
	'a6' => ''
);

# some of installations of mysql were done from source, others from packages,
# so the directory where the database files exists will vary from machine to
# machine
my %host_mysql_dirs = (
	'apps' => '/var/lib/mysql',
	'a2' => '/usr/local/mysql/var',
	'a3' => '/usr/local/mysql/var',
	'a4' => '/var/lib/mysql',
	'a5' => '/var/lib/mysql',
	'a6' => '/var/lib/mysql'
);

# step through each remote host
foreach $remote_host ( @remote_hosts ) {

	print "=> Fetching mysqldump from $remote_host:\n";

	# create the mysqldumps on the remote side
	print "Creating mysqldumps of all databases on $remote_host ...\n";
	system ("ssh $remote_host-mysql 'for db in \$(find $host_mysql_dirs{$remote_host} -maxdepth 1 -mindepth 1 ! -name \".*\" -type d -printf \"%f\n\"); do mysqldump -u root $host_mysql_pwds{$remote_host} --databases \$db > \$db.mysqldump; done; tar --remove-files -czf $host_dumps{$remote_host} *.mysqldump'");

	# now copy that dump over here
	print "Copying the tar-gzipped mysqldumps from $remote_host to this host ...\n";
	system ("$program $program_flags $remote_host-mysql\:$host_dumps{$remote_host} $data_dir/$remote_host/");

	print "\n";
	
}

# now that we are done, delete the pid file
system("rm $pid_file");
