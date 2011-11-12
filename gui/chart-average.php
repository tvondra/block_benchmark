<?php

	require('include/init.php');

	$types = (isset($_REQUEST['type'])) ? $_REQUEST['type'] : array();
	$where = array();
	foreach ($types AS $type) {
		$tmp = explode(':', $type);
		$where[] = "(config_id = " . (int)$tmp[1] . " AND fs = '" . pg_escape_string($tmp[0]) . "')";
	}

	$min = isset($_REQUEST['min']) ? $_REQUEST['min'] : null;
	$max = isset($_REQUEST['max']) ? $_REQUEST['max'] : null;

	$queries = isset($_REQUEST['queries']) ? $_REQUEST['queries'] : null;
	$mins = isset($_REQUEST['mins']) ? $_REQUEST['mins'] : null;

	$img = $_REQUEST['img'];

	// FIXME Rewrite to properly evaluate score etc. This is a mess. Probably move to tpch.php and deal only with GET parameters here.
	if (in_array($img, array('tpch-m-time','tpch-time','tpch-score','tpch-m-score','tpch-m-time-pct','tpch-time-pct','tpch-score-pct','tpch-m-score-pct'))) {

		if (is_null($queries) && is_null($mins)) {

			if (in_array($img, array('tpch-m-time','tpch-m-score','tpch-m-time-pct','tpch-m-score-pct'))) {
				$r = get_common_queries($where);
			} else {
				$r = get_all_queries($where);
			}

			$queries = $r['queries'];
			$mins = $r['mins'];
		}

		$sql = 'SELECT title, fs, fs_block, db_block';

		foreach ($queries AS $i) {
			$sql .= ", query_$i";
		}

		$sql .= ' FROM ' . $columns[$img]['table'] . ' x JOIN config ON (x.config_id = config.id) WHERE ' . implode(' OR ', $where);

	} else {
		$sql = "SELECT '' AS title, 'average' AS fs, fs_block, db_block, round(AVG(" . $columns[$img]['column'] . ')::numeric,2) AS val FROM ' . $columns[$img]['table'] . ' x JOIN config ON (x.config_id = config.id) WHERE ' . implode(' OR ', $where) . ' group by fs_block, db_block';
	}

	$result = pg_query($sql);

	$pgbs = array();
	$fsbs = array();
	$data = array();
	$count = array();

	$title = 'average';
	$subtitle = '';

	while ($row = pg_fetch_assoc($result)) {

		$fsbs[] = $row['fs_block'];
		$pgbs[] = $row['db_block'];

		if (! isset($count[$row['db_block']][$row['fs_block']])) {
			$count[$row['db_block']][$row['fs_block']] = 0;
			$data[$row['db_block']][$row['fs_block']] = 0;
		}
		$count[$row['db_block']][$row['fs_block']] += 1;

		if (in_array($img, array('tpch-m-time','tpch-time','tpch-score','tpch-m-score','tpch-m-time-pct','tpch-time-pct','tpch-score-pct','tpch-m-score-pct'))) {
			$score = 0;
			$time = 0;

			foreach ($queries AS $q => $i) {
				if (! is_null($row["query_$i"])) {
					$score += floatval($mins[$q]) / $row["query_$i"];
					$time += $row["query_$i"];
				} else if (in_array($img, array('tpch-m-time-pct','tpch-m-time'))) {
					var_dump($row["query_$i"]);
				} else {
					$time += QUERY_TIMEOUT;
				}
			}

			if (in_array($img, array('tpch-m-time','tpch-time'))) {
				$data[$row['db_block']][$row['fs_block']] += $time;
			} else if (in_array($img, array('tpch-m-score','tpch-score'))) {
				$data[$row['db_block']][$row['fs_block']] += $score;
			} else if (in_array($img, array('tpch-m-time-pct','tpch-time-pct'))) {
				$data[$row['db_block']][$row['fs_block']] += round(100*$time / array_sum($mins),2);
			} else if (in_array($img, array('tpch-score-pct','tpch-m-score-pct'))) {
				$data[$row['db_block']][$row['fs_block']] += round($score * 100 / count($queries),2);
			}
		} else {
			$data[$row['db_block']][$row['fs_block']] = $row['val'];
		}
	}

	foreach ($data AS $db => $a) {
		foreach ($a AS $fs => $c) {
			$data[$db][$fs] = $data[$db][$fs] / $count[$db][$fs];
		}
	}

	$pgbs = array_unique($pgbs);
	$fsbs = array_unique($fsbs);

	sort($pgbs);
	sort($fsbs);

	header('Content-Type: image/png');
	header('Content-Disposition: inline; filename="average-' . $img . '.png"');

	build_grid_chart($title . ' / ' . $columns[$img]['title'], $subtitle, IMAGE_WIDTH, IMAGE_HEIGHT, $fsbs, $pgbs, $data, $min, $max);

	function build_grid_chart($title, $subtitle, $width, $height, $fs, $pg, $data, $minVal = null, $maxVal = null) {

		$padding = 10;

		$titleFont = 5;
		$subtitleFont = 2;
		$labelFont = 3;

		$img = imagecreate($width, $height);

		$white = imagecolorallocate($img, 255, 255, 255);
		$black = imagecolorallocate($img,   0,   0,   0);

		$scale = array();
		for ($i = 0; $i <= 100; $i++) {
			$scale[$i] = imagecolorallocate($img, ($i * 225) / 100, 0, ((100 - $i) * 225)/100);
		}

		// max label width
		$maxWidth = 0;
		for ($i = 0; $i < count($fs); $i++) {
			$maxWidth = max($maxWidth, imagefontwidth($labelFont) * strlen($fs[$i]));
		}

		// max label height
		$maxHeight = imagefontheight($labelFont);

		// min/max value
		$max = 0;
		foreach ($data AS $a => $b) {
			foreach ($b AS $c => $d) {
				$max = max($max, $d);
			}
		}

		$min = $max;
		foreach ($data AS $a => $b) {
			foreach ($b AS $c => $d) {
				$min = min($min, $d);
			}
		}

		if (! is_null($maxVal)) { $max = max($maxVal, $max); }
		if (! is_null($minVal)) { $min = min($minVal, $min); }

		$max = ceil($max);
		$min = floor($min);

		$diff = (($max - $min) > 0) ? ($max - $min) : 1;

		$legend = imagefontwidth($labelFont) * max(strlen(round($min,2)), strlen(round($max,2)));
		$legend = max($legend, 20);

		// per-item width
		$w = ($width - 5*$padding - $maxWidth - $legend) / count($pg);
		$h = ($height - 4*$padding - $maxHeight - imagefontheight($titleFont) - imagefontheight($subtitleFont)) / count($fs);

		// legend
		$lheight = count($fs) * $h;
		$step = floatval($lheight - 2) / 101;

		for ($i = 0; $i <= 100; $i++) {
			$x = $width - $padding - $legend;
			$y = $height - 2*$padding - imagefontheight($labelFont) - $i * $step;

			imagefilledrectangle($img, $x, $y, $x+$legend, $y - $step, $scale[$i]);
		}

		imagestring($img, $labelFont, $width - $padding - imagefontwidth($labelFont)*strlen(round($min,2)), $height - $padding - imagefontheight($labelFont), floor($min), $black);
		imagestring($img, $labelFont, $width - $padding - imagefontwidth($labelFont)*strlen(round($max,2)), $padding + imagefontheight($titleFont) - imagefontheight($labelFont), ceil($max), $black);

		// grid
		for ($i = 0; $i < count($fs); $i++) {
			for ($j = 0; $j < count($pg); $j++) {
				$x = ($j * $w + $maxWidth + 2*$padding);
				$y = ($height - 2*$padding - $maxHeight - $i*$h);

				$c = $scale[floor(100 * ($data[$pg[$j]][$fs[$i]] - $min) / $diff)];

				imagefilledrectangle($img, $x, $y, $x + $w - 2, $y - $h + 2, $c);

				$val = round($data[$pg[$j]][$fs[$i]],2);

				imagestring($img, $labelFont, $x + ($w - imagefontwidth($labelFont)*strlen($val))/2, $y - ($h + imagefontheight($labelFont))/2, $val, $white);

			}
		}

		// draw labels
		for ($i = 0; $i < count($fs); $i++) {
			$y = ($height - 2*$padding - $maxHeight - $i*$h);
			imagestring($img, $labelFont, $padding, $y - $h/2 - imagefontheight($labelFont)/2, $fs[$i], $black);
		}

		for ($j = 0; $j < count($pg); $j++) {
			$x = ($j * $w + $maxWidth + 2*$padding) + $w/2 - imagefontwidth($labelFont)*strlen($pg[$j])/2;
			$y = $height - $padding - imagefontheight($labelFont);
			imagestring($img, $labelFont, $x, $y, $pg[$j], $black);
		}

		imagestring($img, $titleFont, $width/2 - imagefontwidth($titleFont)*strlen($title)/2, $padding, $title, $black);
		imagestring($img, $subtitleFont, $width/2 - imagefontwidth($subtitleFont)*strlen($subtitle)/2, $padding + imagefontheight($titleFont) + 0.25 * imagefontheight($subtitleFont), $subtitle, $black);

		imagepng($img);
		imagedestroy($img);

	}

?>
