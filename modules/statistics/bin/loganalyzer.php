#!/usr/bin/env php
<?php

require_once( dirname(dirname(dirname(dirname(__FILE__)))) . '/www/_include.php');


$progName = array_shift($argv);
$debug = FALSE;
$dryrun = FALSE;

foreach($argv as $a) {
	if(strlen($a) === 0) continue;

	if(strpos($a, '=') !== FALSE) {
		$p = strpos($a, '=');
		$v = substr($a, $p + 1);
		$a = substr($a, 0, $p);
	} else {
		$v = NULL;
	}

	/* Map short options to long options. */
	$shortOptMap = array(
		'-d' => '--debug',
	);
	if(array_key_exists($a, $shortOptMap))  $a = $shortOptMap[$a];

	switch($a) {
		case '--help':
			printHelp();
			exit(0);
		case '--debug':
			$debug = TRUE;
			break;
		case '--dry-run':
			$dryrun = TRUE;
			break;
		default:
			echo('Unknown option: ' . $a . "\n");
			echo('Please run `' . $progName . ' --help` for usage information.' . "\n");
			exit(1);
		}
}

$aggregator = new sspmod_statistics_Aggregator();
$aggregator->dumpConfig();
$results = $aggregator->aggregate($debug);

if (!$dryrun) {
	$aggregator->store($results);
}


foreach ($results AS $slot => $val) {
	 foreach ($val AS $sp => $no) {
	 	echo $sp . " " . count($no) . " - ";
	 }
	 echo "\n";
}




/**
 * This function prints the help output.
 */
function printHelp() {
	global $progName;

	/*   '======================================================================' */
	echo('Usage: ' . $progName . ' [options]

This program parses and aggregates SimpleSAMLphp log files.

Options:
	-d, --debug			Used when configuring the log file syntax. See doc.
	-dry-run			Aggregate but do not store the results.

');
}

