<?php


function parse_calendars($raw_calendars)
{
	$calendars = [];
	foreach ($raw_calendars['calendars'] as $calendar) {
		$calendars[] = determine_days($calendar);
	}
	return $calendars;
}
function parse_models($calendar)
{
	$models = [];
	foreach ($calendar['models'] as $model) {
		$models[$model['name']] = determine_model_days($model['days']);
	}
	return $models;
}
function determine_model_days($days_str)
{
	$days = [];
	$days_str = preg_replace('/\s+/', '', $days_str);
	$days_arr = str_split($days_str, 4);
	foreach ($days_arr as $index => $day_str) {
		$days[$index] = parse_day_str($day_str);
	}
	return $days;
}
function parse_day_str($day_str)
{
	$day_arr = str_split($day_str);
	$result = [];
	foreach ($day_arr as $index_part => $daypart_str) {
		if ($daypart_str === '+') {
			$result[$index_part] = true;
		} else {
			$result[$index_part] = false;
		}
	}
	return $result;
}
function determine_days($calendar)
{
	$models = parse_models($calendar);
	$days = [];

	$start_date_str = false;
	$end_date_str = false;
	foreach ($calendar['periods'] as $period_start_str => $model_id) {
		if ($start_date_str === false)
			$start_date_str = $period_start_str;
		if ($model_id === '_end_')
			$end_date_str = $period_start_str;
	}

	$current_date = $start_date = strtotime($start_date_str);
	$end_date = strtotime($end_date_str);
	$current_model = false;

	while ($current_date < $end_date) {
		$current_date_str = date('Y-m-d', $current_date);
		if (
			array_key_exists($current_date_str, $calendar['periods'])
			&& array_key_exists($calendar['periods'][$current_date_str], $models)
		) {
			$current_model = $calendar['periods'][$current_date_str];
			$index_model = 0;
		}
		if ($current_model === false) continue;



		if (
			array_key_exists($current_date_str, $calendar['exceptions'])
		) {
			$days[$current_date_str] = parse_day_str($calendar['exceptions'][$current_date_str]);
		} else {
			if ($index_model >= sizeof($models[$current_model]))
				$index_model = 0;
			$days[$current_date_str] = $models[$current_model][$index_model];
		}

		$current_date = strtotime('+1 day', $current_date);
		$index_model++;
	}

	return $days;
}
function day_to_char($day)
{
	$day_str = "";
	foreach ($day as $day_part) {
		if ($day_part) {
			$day_str .= "1";
		} else {
			$day_str .= "0";
		}
	}
	if ($day_str === "1111") return "█";
	if ($day_str === "1110") return "▛";
	if ($day_str === "1100") return "▛";
	if ($day_str === "1000") return "▀";
	if ($day_str === "0000") return " ";
	if ($day_str === "0001") return "▗";
	if ($day_str === "0011") return "▗";
	if ($day_str === "0111") return "▄";
	if (substr($day_str, 0, 1) === "0") return "▛";
	if (substr($day_str, 0, 1) === "1") return "▖";
	return "--";
}

function colorify_calendar($index, $str)
{
	$i = 31;

	return "\033[" . ($i + $index) . ";1;1m" . $str . "\033[0m";
}

function display_percentage($val, $total)
{
	return $val . '/' . $total . ' (' . (floor($val * 100 * 10 / $total) / 10) . '%)';
}

function generate_report($raw_calendars, $calendars)
{
	$data = [];
	$graph = [];
	$raw_data = [];

	foreach ($calendars as $index => $calendar) {
		foreach ($calendar as $date_str => $day) {
			if (!array_key_exists($date_str, $graph))
				$graph[$date_str] = [];
			if (!array_key_exists($date_str, $raw_data))
				$raw_data[$date_str] = [];

			$graph[$date_str][$index] = colorify_calendar($index, day_to_char($day));
			$raw_data[$date_str][$index] = $day;
		}
	}
	$index++;
	foreach ($raw_calendars['important_days'] as $important_day_date_str => $important_day_label) {
		$graph[$important_day_date_str][$index] = " ← " . $important_day_label;
	}

	ksort($graph);
	ksort($raw_data);

	$time_spent_together = [];
	$time_spent_at_home = [];
	$time_spent_with_no_one = 0;
	$time_spent_with_everyone = 0;
	$total_days = sizeof($raw_data);
	foreach ($raw_data as $date_str => $calendar_arr) {

		foreach ([0, 1, 2, 3] as $index_day) {
			$is_there_no_one = true;
			$is_there_every_one = true;
			foreach ($calendar_arr as $calendar_index_from => $day_from) {
				if ($day_from[$index_day]) {
					$is_there_no_one = false;
				} else {
					$is_there_every_one = false;
				}
			}
			if ($is_there_no_one) $time_spent_with_no_one += 0.25;
			if ($is_there_every_one) $time_spent_with_everyone += 0.25;
		}
		foreach ($calendar_arr as $calendar_index_from => $day_from) {

			if (!array_key_exists($calendar_index_from, $time_spent_at_home))
				$time_spent_at_home[$calendar_index_from] = 0;
			foreach ($day_from as $day_from_index => $value) {
				if ($value)
					$time_spent_at_home[$calendar_index_from] += 0.25;
			}

			foreach ($calendar_arr as $calendar_index_to => $day_to) {

				foreach ($day_from as $day_from_index => $value) {

					if (!array_key_exists($calendar_index_from, $time_spent_together))
						$time_spent_together[$calendar_index_from] = [];
					if (!array_key_exists($calendar_index_to, $time_spent_together[$calendar_index_from]))
						$time_spent_together[$calendar_index_from][$calendar_index_to] = 0;
					if ($day_to[$day_from_index] === $value && $value)
						$time_spent_together[$calendar_index_from][$calendar_index_to] += 0.25;
				}
			}
		}
	}

	$data[] = [
		'title' => 'Days with no one',
		'value' => display_percentage($time_spent_with_no_one, $total_days)
	];
	$data[] = [
		'title' => 'Days with everyone',
		'value' => display_percentage($time_spent_with_everyone, $total_days)
	];

	foreach ($raw_calendars['calendars'] as $calendar_index => $calendar) {
		$data[] = [
			'title' => 'Days ' . colorify_calendar($calendar_index, $calendar['name']) . ' spend at home',
			'value' => display_percentage($time_spent_at_home[$calendar_index], $total_days)
		];
		foreach ($raw_calendars['calendars'] as $calendar_index_sec => $calendar_sec) {
			if ($calendar_index_sec !== $calendar_index) {
				$data[] = [
					'title' => 'Days ' . colorify_calendar($calendar_index, $calendar['name']) . ' spend with ' . colorify_calendar($calendar_index_sec, $calendar_sec['name']) . ' at home',
					'value' => display_percentage($time_spent_together[$calendar_index][$calendar_index_sec], $total_days)
				];
			}
		}
	}
	foreach ($raw_calendars['important_days'] as $important_day_date_str => $important_day_label) {
		$list = '';
		foreach ($raw_data[$important_day_date_str] as $calendar_index => $day) {
			$list .= colorify_calendar($calendar_index, day_to_char($day));
		}
		$data[] = [
			'title' => 'Important day : ' . $important_day_label,
			'value' => $list
		];
	}
	return ['data' => $data, 'graph' => $graph];
}

function colonify($str)
{
	$str = trim($str);
	$result = '';
	global $argv;
	$nb_col = 3;
	if (sizeof($argv) > 2)
		$nb_col = (int) $argv[2];
	if ($nb_col < 1) $nb_col = 1;

	$max_length = 0;
	$lines = explode("\n", $str);

	foreach ($lines as $line) {
		if (grapheme_strlen($line) > $max_length)
			$max_length = grapheme_strlen($line);
	}
	$max_length += 2;

	$nb_lines_per_col = ceil(sizeof($lines) / $nb_col);
	$lines_done = [];
	for ($iline = 0; $iline < $nb_lines_per_col; $iline++) {
		for ($icol = 0; $icol < $nb_col; $icol++) {

			if ($icol === 0)
				$result .= "\n";


			$index = $icol * $nb_lines_per_col + $iline;

			if (!in_array($index, $lines_done)) {
				$lines_done[] = $index;

				if (array_key_exists($index, $lines))

					$result .=

						$lines[$index]
						. str_repeat(
							" ",
							$max_length - grapheme_strlen($lines[$index])
						);
			}
		}
	}
	return $result;
}

function display_report($report)
{
	$index = 0;

	$graph_result = "";
	foreach ($report['graph'] as $date_str => $day_arr) {


		$graph_result .= "\n";
		if ($index % 7 === 0) {
			$graph_result .= "\033[90;1;1m" . $date_str . "\033[0m";
		} else {
			$graph_result .= "\033[00;1;1m" . $date_str . "\033[0m";
		}

		foreach ($day_arr as $col) {
			$graph_result .= "│";
			$graph_result .= $col;
		}
		$index++;
	}
	echo colonify($graph_result);
	echo "\n";
	echo "\n";
	foreach ($report['data'] as $index => $data) {

		echo "\n";
		echo "\n";
		echo " - ";
		echo $data['title'];
		echo " : ";
		echo "\n   ";
		echo $data['value'];
	}
	echo "\n";
	echo "\n";
}
