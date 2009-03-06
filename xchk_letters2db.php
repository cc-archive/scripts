#!/usr/bin/php

<?php

/**
 * A small, and fairly ugly, script to take a CSV file of first
 * and last names and perhaps other information and then searches
 * for those names in the CiviCRM contact database, then determines
 * whether they donated during some period of time.  It creates a
 * directory with various CSV files as output.
 *
 * xchk_letters2db = Cross-check letters to the database
 *
 */

$db = mysql_connect('localhost','civicrm','Civicrm.');
mysql_select_db('civicrm', $db);

$duplicates = array();
$no_matches = array();
$matches = array();
$unknowns = array();
$letter_no_donate = array();
$letter_donate = array();

if ( ! is_dir('./xchk_letters2db') ) {
	mkdir('./xchk_letters2db', 0755);
}

if ( $argv[1] ) {
	$fh = fopen($argv[1], 'r');
	if ( ! $fh ) {
		echo "Failed to open file {$arvg[1]}.";
		exit;
	}
} else {
	echo "You must pass the script a CSV file.\n";
	exit;
}

function find_contact($fname, $lname) {
	$sql = sprintf("
		SELECT * FROM civicrm_contact
		WHERE first_name = '%s'
			AND last_name = '%s'
		",
		addslashes(trim($fname)),
		addslashes(trim($lname))
	);
	$res = mysql_query($sql);

	return $res;
}

while ( ($values = fgetcsv($fh, 0)) !== FALSE ) {
	$first_name = $values[0];
	$last_name = $values[1];
	$third_name = $values[2];
	$fourth_name = $values[3];
	$receive_date = $values[4];
	$address = $values[5];
	$address_extra1 = $values[6];
	$address_extra2 = $values[7];
	$city = $values[8];
	$postal_code = $values[9];
	$postal_code_suffix = $values[10];
	$state = $values[11];
	$country = $values[12];
	$send_letter = $values[13];

	$res = find_contact($first_name, $last_name);
	$num_rows = mysql_num_rows($res);
	if ( $num_rows > 1 ) {
		$dupes = array();
		$dupes[] = "$last_name, $first_name";
		while ( $row = mysql_fetch_assoc($res) ) {
			$dupes[] = $row['id'];
		}
		$duplicates[] = $dupes;
	} elseif ( $num_rows == 0 ) {
		# Try appending $third_name to $last_name
		$last_and_third = trim($last_name) . ' ' . trim($third_name);
		$res = find_contact($first_name, $last_and_third);
		if ( mysql_num_rows($res) == 1 ) {
			$row = mysql_fetch_assoc($res);
			$matches[] = array($row['last_name'], $row['first_name'], $row['id']);
			continue;
		}

		# Try removing dots from first name abbreviations
		$first_no_dot = rtrim($first_name, '.');
		$res = find_contact($first_no_dot, $last_name);
		if ( mysql_num_rows($res) == 1 ) {
			$row = mysql_fetch_assoc($res);
			$matches[] = array($row['last_name'], $row['first_name'], $row['id']);
			continue;
		}

		$no_matches[] = array($last_name, $first_name);
	} elseif ( $num_rows == 1 )  {
		$row = mysql_fetch_assoc($res);
		$matches[] = array($row['last_name'], $row['first_name'], $row['id']);
	} else {
		$unknowns[] = $values;
	}
	
}

fclose($fh);

echo "Unknown: " . count($unknowns) . "\n";

echo "Duplicates: " . count($duplicates) . "\n";
$fh_dupes = fopen('./xchk_letters2db/ambiguous_matches.csv', 'w');
foreach ( $duplicates as $duplicate ) {
		fputcsv($fh_dupes, $duplicate, ',', '"');
}
fclose($fh_dupes);

echo "No matches: " . count($no_matches) . "\n";
$fh_no_matches = fopen('./xchk_letters2db/no_matches.csv', 'w');
foreach ( $no_matches as $no_match ) {
		fputcsv($fh_no_matches, $no_match, ',', '"');
}
fclose($fh_no_matches);

echo "Matches: " . count($matches) . "\n";
$fh_donate = fopen('./xchk_letters2db/got_letter_donation.csv', 'w');
$fh_no_donate = fopen('./xchk_letters2db/got_letter_no_donation.csv', 'w');
foreach ( $matches as $match ) {
	# Did they give during the Fall campaign
	$sql = sprintf("
		SELECT * FROM civicrm_contribution
		WHERE contact_id = '%d'
			AND receive_date >= '2008-10-15'
			AND receive_date <= '2008-12-31'
		",
		$match[2]
	);
	$res = mysql_query($sql);
	
	if ( mysql_num_rows($res) >= '1' ) {
		$letter_donate[] = $match;
		fputcsv($fh_donate, $match, ',', '"');
	} else {
		$letter_no_donate[] = $match;
		fputcsv($fh_no_donate, $match, ',', '"');
	}
}
fclose($fh_donate);
fclose($fh_no_donate);

echo "Got letter, didn't donate in 2008: " . count($letter_no_donate) . "\n";
echo "Got letter, donated in 2008: " . count($letter_donate) . "\n";

?>
