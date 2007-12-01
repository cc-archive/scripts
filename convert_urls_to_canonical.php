#!/usr/bin/php

<?php

/**
 * This script takes a single file as an argument.  The file should be a list 
 * of URLs, one per line.  The purpose of the script is to convert all of the 
 * URLs to their canonical form, so if there are any redirects in the path, the 
 * original URL will be replaced by the URL of the final redirect.
 *
 * In addition to converting URLs to their canonical form, it will also exclude
 * URLs whose final HTTP return code was anything other than 200.
 *
 * Please not that this is not an exact science and that misconfigured web 
 * servers may not always redirect to something sensible, though the URL is 
 * guaranteed to be reacheable, it may not be what you expected.
 */

# The file to which we will write the new URLs
$canonical_urls = fopen("./canonical_urls", "w");

# Dump the returned headers from cURL to /dev/null
$trash = fopen("/dev/null", "w");

# Get the file that contains the list of urls
if ( is_file($argv[1]) ) {
	$urls = file($argv[1]);
} else {
	echo "No such file: {$argv[1]}\n";
	exit;
}

# Curl options ... add to this array if need be.  For more options see:
# http://us3.php.net/manual/en/function.curl-setopt.php
$curl_options = array(
	CURLOPT_HEADER => 1,
	CURLOPT_NOBODY => 1,
	CURLOPT_FILE => $trash,
	CURLOPT_CONNECTTIMEOUT => 15,
	CURLOPT_FOLLOWLOCATION => 1
);

# How many URLs to fetch in parallel using a cURL multi object.  Don't set this 
# too high or you will get timeout errors.  I've got the timeout above set to 
# 15 seconds.  You might be able to raise that and then raise this number also.
# 10 is pretty moderate to low.
$parallels = 10;

$url_count = count($urls);

# Echo some simple information for the user
echo <<<PARAMS
URL file: {$argv[1]}
URL count: $url_count
URLs to fetch in parallel: $parallels

Commencing ...


PARAMS;

$start_time = time();

$i = 0;
$curls = array();

for ( $idx = 0; $idx <= $url_count; $idx++ ) {

	$url = trim($urls[$idx]);

	# First check if we need to process the URLs accumulated in $curls.
	# After $parallels number of URLs are gathered, then run through all of
	# them with a curl_multi object, also enter this if we have already
	# exhausted all the URLs
	if ( ($idx % $parallels) == 0 && $idx != 0 || $url_count == $idx ) {
		$mh = curl_multi_init();
		for ( $im = 0; $im < count($curls); $im++ ) {
			curl_multi_add_handle($mh, $curls[$im]);
		}

		# Now loop through $parallels cURL objects and fetch them
		$running = NULL;
		do {
    		curl_multi_exec($mh, $running);
		} while ($running > 0);
		
		for ( $ic = 0; $ic < count($curls); $ic++ ) {
			$return_code = curl_getinfo($curls[$ic], CURLINFO_HTTP_CODE);
			$last_url = curl_getinfo($curls[$ic], CURLINFO_EFFECTIVE_URL);

			# Check for errors
			if ( $err = curl_error($curls[$ic]) ) {
				$msg = "ERROR: $err : $last_url\n";
				echo "$msg";
				fwrite($canonical_urls, $msg);
			}

			# If it's a 404 return code, then we assume it's bad and
			# print an error.
			if ( $return_code == 404 ) {
				$msg = "ERROR 404: $last_url\n";
				echo "$msg";
				fwrite($canonical_urls, $msg);
			}

			# And if the return code wasn't 404 or 200, then issue
			# a warning. It might be most appropriate
			# to exclude any URL that didn't return 200, but I've found
			# that some servers will return a 403 or 405 error when
			# accessed with cURL using this script, probably because
			# it's only asking for HEAD and those servers refuse to 
			# do that.  So, without making this script more complicated
			# let's just issue a warning here.
			if ( ($return_code != 404) && ($return_code != 200) ) {
				$msg = "WARNING: $return_code HTTP return code : $last_url\n";
				echo "$msg";
				fwrite($canonical_urls, $msg);
			}

			# If an error was found, then don't even bother getting
			# the last URL because it's likely to be wrong anyway,
			# and an error message was logged for this URL anyway.
			# But only do this if the return code was also 200.
			if ( ! $err  && $return_code == 200 ) {
				fwrite($canonical_urls, "$last_url\n");
			}

			curl_close($curls[$ic]);

		}

		# Close the curl multi handle objects
		curl_multi_close($mh);

		# Set $i back to zero and also reinitialize the $curls array in
		# preparation for the next wave of parallel fetching.
		$i = 0;
		$curls = array();

		# Echo some stats to ther user
		$elapsed = time() - $start_time;
		echo "$idx of $url_count URLs processed.  Seconds since start: $elapsed\n";

	}

	# Add another cURL object to the $curls array and set the
	# right options for it
	$curls[$i] = curl_init();
	curl_setopt($curls[$i], CURLOPT_URL, "$url");
	foreach ( $curl_options as $opt => $value ) {
		curl_setopt($curls[$i], $opt, $value);
	}

	$i++;

}

fclose($trash);
fclose($canonical_urls);

?>
