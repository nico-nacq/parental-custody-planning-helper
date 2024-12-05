<?php
include 'includes.php';
if (
    !file_exists($argv[1])
) die('Yml file does not exist : ' . $argv[1] . "\n");


$raw_calendars = yaml_parse(file_get_contents($argv[1]));
if (
    !is_array($raw_calendars)
    || !array_key_exists('calendars', $raw_calendars)
) die('Incorrect Yml file : ' . $argv[1]);

$calendars = parse_calendars($raw_calendars);
$report = generate_report($raw_calendars, $calendars);
display_report($report);
