#!/usr/bin/php

# A small script to fix a CiviCRM database where by error
# some contacts have multiple email addresses marked as
# is_primary, which can cause all sorts of issues with
# reporting and searches.

<?php

$fh = fopen($argv[1], 'r');

if ( ! $fh ) {
	echo "First argument must be a valid CSV file";
	exit;
}

mysql_connect('localhost', 'root', '1712nd');
mysql_select_db('civicrm');

while ( $row = fgetcsv($fh, 0, "\t") ) {
	$primaries = array();
	$has_billing = false;
	$sql = "SELECT * FROM civicrm_email WHERE contact_id = '{$row[0]}'";
	$result = mysql_query($sql);
	while ( $record = mysql_fetch_assoc($result) ) {
		if ( $record['is_primary'] == '1' ) {
			if ( $record['location_type_id'] == '5' ) {
				$has_billing = true;
			}
			$primaries[] = $record;
		}
	}
	foreach ( $primaries as $primary ) {
		if ( $has_billing && $primary['location_type_id'] != '5' ) {
			$sql = "UPDATE civicrm_email SET is_primary = '0' WHERE id = {$primary['id']}";
			#echo "UPDATE civicrm_email SET is_primary = '0' WHERE id = {$primary['id']}\n";
			mysql_query($sql);
		}
	}
}

fclose($fh);

?>
