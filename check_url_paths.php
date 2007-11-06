#!/usr/bin/php

<?php

if ( $argc == 1 || $argv[1] == 'help' || $argv[2] == '-h' ) {
	echo <<<HELP
This is a script for checking various and/or many URL paths against two 
different hosts.  The impetus behind making this script was frequent and 
sometimes major upgrades to Wordpress.  When you upgrade Wordpress or otherwise 
change the rewrite rules, how can you be sure that all of the URLs and 
permalinks will continue to resolve?  This script expects three arguments with 
an optional 4th.  The 4th argument specifies how many URLs to fetch in 
parallel.  If not specified it defaults to 10.  I set the connection timeout 
for cURL to 10 seconds, set the parallel option too high and you'll start 
getting timeouts.  Setting this too high may also overwhelm host1 and host2 
with connections.  The file containing URL paths can be generated from an 
Apache combined log with the following command:

$ cut -d' ' -f 7 <logfile> | sort | uniq > <output>

The text file must have one URL path per line (NOT including any of the 
protocol identifier OR the host).  It will append this path to the two 
specified hosts in turn, fetch the resulting URL and inspect the return code 
from the web server.  If the codes are different it will log the URL to a file 
called 'url_discrepancies' in the same directory from which the script was run, 
otherwise it will assume that the path is functionally equivalent between the 
two hosts.

Usage: check_url_paths.php <path file> <host 1> <host 2> [parallels]
Usage: check_url_paths.php [help] [-h]


HELP;

	exit;

}

# the file to which we log discrepancies
$diffs = fopen("./url_discrepancies", "w");

# dump the returned headers from cURL to /dev/null
$trash = fopen("/dev/null", "w");

# get the file that contains the list of urls
if ( is_file($argv[1]) ) {
	$url_paths = file($argv[1]);
} else {
	echo "No such file: {$argv[1]}\n";
	exit;
}

# get and cleanup the specified hosts
if ( strpos($argv[2], "http://") ) {
	$host1 = trim($argv[2], '/');
} else {
	$host1 = "http://" . trim($argv[2], '/');
}
if ( strpos($argv[3], "http://") ) {
	$host2 = trim($argv[3], '/');
} else {
	$host2 = "http://" . trim($argv[3], '/');
}

# Define host1 and host2 in the discrepancy file
fwrite($diffs, "host1: $host1\n");
fwrite($diffs, "host2: $host2\n\n");

# Shared curl options for both hosts
$curl_options = array(
	CURLOPT_HEADER => 1,
	CURLOPT_NOBODY => 1,
	CURLOPT_FILE => $trash,
	CURLOPT_CONNECTTIMEOUT => 10
);

# Make sure that both $host1 and $host2 are reachable before we start.
$host_init = curl_init();
curl_setopt($host_init, CURLOPT_URL, $host1);
foreach ( $curl_options as $opt => $value ) {
	curl_setopt($host_init, $opt, $value);
}
$is_up = curl_exec($host_init);
if ( ! $is_up ) {
	echo "Host $host1 doesn't appear to be reachable.  Exiting ...\n";
	exit;
}

$host_init = curl_init();
curl_setopt($host_init, CURLOPT_URL, $host2);
foreach ( $curl_options as $opt => $value ) {
	curl_setopt($host_init, $opt, $value);
}
$is_up = curl_exec($host_init);
if ( ! $is_up ) {
	echo "Host $host2 doesn't appear to be reachable.  Exiting ...\n";
	exit;
}

# Determine how many URLs to fetch in parallel.  Be careful how high you set 
# this number.  Too high and you might cause a slowdown on either host1 or 
# host2, not to mention you will likely start to get timeouts.  You can try 
# setting the CURLOPT_CONNECTTIMEOUT to something higher in the $curl_options 
# array in this file.  By default it is 10 seconds.
if ( isset($argv[4]) ) {
	if ( is_numeric($argv[4]) ) {
		$parallels = $argv[4];
	} else {
		echo "Argument 4 must be an integer.\n";
		exit;
	}
} else {
	$parallels = 10;
}

$path_count = count($url_paths);

echo <<<PARAMS
URL path file: {$argv[1]}
Number of URL paths: $path_count
Host 1: $host1
Host 2: $host2
URLs to fetch in parallel: $parallels

Commencing ...


PARAMS;

$start_time = time();

$i = 0;
$h1_curls = array();
$h2_curls = array();

for ( $idx = 0; $idx < count($url_paths); $idx++ ) {

	$url_path = trim($url_paths[$idx]);

	# First check if we need to process the URLs accumulated in $h1_curls and 
	# $h2_curls. After $parallels number of URLs are gathered, then
	# run through all of them with a curl_multi object, also enter this if we 
	# have already exhausted all the URLs
	if ( ($idx % $parallels) == 0 && $idx != 0 || (count($url_paths) -1) == $idx ) {
		$mh_h1 = curl_multi_init();
		$mh_h2 = curl_multi_init();
		for ( $ih1 = 0; $ih1 < count($h1_curls); $ih1++ ) {
			curl_multi_add_handle($mh_h1, $h1_curls[$ih1]);
		}
		for ( $ih2 = 0; $ih2 < count($h2_curls); $ih2++ ) {
			curl_multi_add_handle($mh_h2, $h2_curls[$ih2]);
		}

		$running = NULL;
		do {
    		curl_multi_exec($mh_h1, $running);
		} while ($running > 0);
		
		$running = NULL;
		do {
    		curl_multi_exec($mh_h2, $running);
		} while ($running > 0);
		

		for ( $ii = 0; $ii < count($h1_curls); $ii++ ) {
			$h1_code = curl_getinfo($h1_curls[$ii], CURLINFO_HTTP_CODE);
			$h2_code = curl_getinfo($h2_curls[$ii], CURLINFO_HTTP_CODE);

			# Determine the original path based on the URL that cURL knows 
			# about
			$curr_url = curl_getinfo($h1_curls[$ii], CURLINFO_EFFECTIVE_URL);
			$url_parts = parse_url($curr_url);
			$curr_path = $url_parts['path'];
			$curr_path .= ( empty($url_parts['query']) ) ? "" : "?{$url_parts['query']}";
			$curr_path .= ( empty($url_parts['fragment']) ) ? "" : "#{$url_parts['fragment']}";

			# Check for errors
			if ( $err = curl_error($h1_curls[$ii]) ) {
				$msg = "ERROR (host1): $curr_path : $err\n";
				echo "$msg";
				fwrite($diffs, $msg);
			}
			if ( $err = curl_error($h2_curls[$ii]) ) {
				$msg = "ERROR (host2): $curr_path : $err\n";
				echo "$msg";
				fwrite($diffs, $msg);
			}

			# If an error was found, then don't even bother comparing codes 
			# because they are likely to be wrong anyway, and an error 
			# message was logged for this URL anyway.
			if ( ! $err ) {
				if ( $h1_code != $h2_code ) {
					$discrepancy = "host1: $h1_code, host2: $h2_code  :  path $curr_path\n";
					echo "$discrepancy";
					fwrite($diffs, "$discrepancy");
				}
			}

			curl_close($h1_curls[$ii]);
			curl_close($h2_curls[$ii]);

		}

		# close the curl multi handle objects
		curl_multi_close($mh_h1);
		curl_multi_close($mh_h2);

		# set $i back to zero and also reinitialize the curl arrays in
		# preparation for the next wave of parallel fetching.
		$i = 0;
		$h1_curls = array();
		$h2_curls = array();

		$elapsed = time() - $start_time;
		echo "$idx of $path_count URLs processed.  Seconds since start: $elapsed\n";

	}

	# setup cURL for host 1
	$h1_curls[$i] = curl_init();
	curl_setopt($h1_curls[$i], CURLOPT_URL, "$host1{$url_path}");
	foreach ( $curl_options as $opt => $value ) {
		curl_setopt($h1_curls[$i], $opt, $value);
	}

	# setup cURL for host 2
	$h2_curls[$i] = curl_init();
	curl_setopt($h2_curls[$i], CURLOPT_URL, "$host2{$url_path}");
	foreach ( $curl_options as $opt => $value ) {
		curl_setopt($h2_curls[$i], $opt, $value);
	}

	$i++;

}

fclose($trash);
fclose($diffs);

?>
