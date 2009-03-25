#!/usr/bin/php

<?php

$db = mysql_connect('localhost','civicrm','Civicrm.');
mysql_select_db('civicrm', $db);
$output = "";

list($year,$month,$day) = explode('-', date('Y-m-d'));

function get_donation_total($year,$month,$day) {
	$sql = sprintf("
		SELECT SUM(total_amount)
		FROM civicrm_contribution
		WHERE receive_date BETWEEN CAST('%s-01-01' AS DATE) AND CAST('%s' AS DATE)
			AND contribution_status_id = '1'
			AND contribution_type_id <> '8'
		",
		$year,
		"$year-$month-$day"
	);
	$res = mysql_query($sql);
	$row = mysql_fetch_row($res);
	return $row[0];
}

function get_all_donations($year,$month,$day) {
	$sql = sprintf("
		SELECT total_amount
		FROM civicrm_contribution
		WHERE receive_date BETWEEN CAST('%s-01' AS DATE) AND CAST('%s' AS DATE)
			AND contribution_status_id = '1'
			AND contribution_type_id <> '8'
		ORDER BY total_amount ASC
		",
		"$year-$month",
		"$year-$month-$day"
	);
	$res = mysql_query($sql);
	$donations = array();
	while ( $row = mysql_fetch_row($res) ) {
		$donations[] = $row[0];
	}
	return $donations;
}

function get_number_of_donors($year, $month, $status='all') {

	if ( $status == 'new' ) {
		$additional_where = sprintf("
			AND NOT EXISTS
				(SELECT *
				FROM civicrm_contribution c2
				WHERE c2.receive_date < CAST('%s' AS DATE)
					AND c2.contribution_status_id = '1'
					AND c2.contact_id = c1.contact_id)
			",
			"$year-$month-01"
		);
	}
			
	$sql = sprintf("
		SELECT DISTINCT contact_id
		FROM civicrm_contribution c1
		WHERE
			(YEAR(c1.receive_date) = '%s'
			AND MONTH(c1.receive_date) = '%s'
			AND c1.contribution_status_id = '1'
			AND c1.contribution_type_id <> '8')
			%s
		",
		$year,
		$month,
		$additional_where
	);
	$res = mysql_query($sql);

	# Uncomment these lines to verify that the SQL is in fact
	# returning only first-time donors.
	/*
	while ( $row = mysql_fetch_array($res) ) {
    		$sql2 = sprintf("
			SELECT contact_id
			FROM civicrm_contribution
			WHERE contact_id = '$row[0]'
				AND receive_date < CAST('%s' AS DATE)
			",
			"$year-$month-01"
		);
		$res2 = mysql_query($sql2);
		if ( mysql_num_rows($res2) > 1 ) {
			$row2 = mysql_fetch_row($res2);
			echo "Found more than 1 previous donations for {$row2[0]}!\n";
		}
	}
	*/

	return mysql_num_rows($res);
}

# Get donation totals and differences
$last_year_total = get_donation_total($year-1, $month, $day);
$this_year_total = get_donation_total($year, $month, $day);
$total_difference = ($last_year_total - $this_year_total);
if ( $total_difference < 0 ) {
	$total_difference = number_format(($total_difference * -1), 2);
	$more_or_less = "more";
} else {
	$total_difference = number_format($total_difference, 2);
	$more_or_less = "less";
}
$last_year_total = number_format($last_year_total, 2);
$this_year_total = number_format($this_year_total, 2);

# Get total donation counts
$total_donors = get_number_of_donors($year, $month, 'all');
$new_donors = get_number_of_donors($year, $month, 'new');
$returning_donors = ($total_donors - $new_donors);

# Get mean, median and mode of all donations for this period
$donations = get_all_donations($year,$month,$day);
$count = count($donations);
# mean
$sum = array_sum($donations);
$mean = round(($sum/$count), 2);
# median
if ( is_int($count/2) ) {
	# subtract 1 to align the number with the array indexes.
	$first_num = (floor($count/2) - 1);
	$median = (($donations[$first_num] + $donations[$first_num+1]) / 2);
} else {
	$median = $donations[ceil($count/2)];
}
# mode
$amount_counts = array_count_values($donations);
arsort($amount_counts);
$mode = key($amount_counts);
$mode_count = array_shift($amount_counts);

# Output something
echo <<<OUTPUT
\$$this_year_total has been donated so far this year, which is
\$$total_difference $more_or_less than had been donated at this same time
last year. At this same time last year \$$last_year_total had
been donated.

For the timeframe $year-$month-01 to $year-$month-$day:

    $total_donors = total number of donations.
    $new_donors = first time donors. 
    $returning_donors = returning donors.

    \$$mean = mean donation.
    \$$median = median donation.
    \$$mode = mode of all donations ($mode_count times).


OUTPUT;

?>
