<?php

	require('include/init.php');

	$types = (isset($_REQUEST['type'])) ? $_REQUEST['type'] : array();
	$key = (isset($_REQUEST['key'])) ? $_REQUEST['key'] : 'tpch-total';

	if (count($types) == 0) {
		$path = explode('/', $_SERVER['REQUEST_URI']);
		$path[count($path)-1] = 'index.php';
		header('Location: ' . implode('/', $path));
		exit();
	}

	$url = 'type[]=' . implode('&amp;type[]=',$types);
	$imgurl = '';

	$where = array();
	foreach ($types AS $type) {
		$tmp = explode(':', $type);
		$where[] = "(config_id = " . (int)$tmp[1] . " AND fs = '" . pg_escape_string($tmp[0]) . "')";
	}

	$mins = array();
	$queries = array();

	// modified time and score (only queries that finished in all cases using the same query plan)
	if (in_array($key, array('tpch-m-time','tpch-m-score','tpch-m-time-pct','tpch-m-score-pct'))) {

		$r = get_common_queries($where);

		// FIXME compute proper score minimum
		$min = 0;
		$max = count($r['queries']);

		$imgurl = '&amp;mins[]=' . implode('&amp;mins[]=', $r['mins']) . '&amp;queries[]=' . implode('&amp;queries[]=', $r['queries']);

	// raw score and time (all queries, plan is not compared etc.)
	} else if (in_array($key, array('tpch-time','tpch-score','tpch-time-pct','tpch-score-pct'))) {

		$r = get_all_queries($where);

		// FIXME compute proper score minimum
		$min = 0;
		$max = count($r['queries']);

		$imgurl = '&amp;mins[]=' . implode('&amp;mins[]=', $r['mins']) . '&amp;queries[]=' . implode('&amp;queries[]=', $r['queries']);

	// other columns
	} else {

		$sql = 'SELECT MIN(' . $columns[$key]['column'] . ') AS min_val, MAX(' . $columns[$key]['column'] . ') AS max_val ' .
				'FROM results_tpch WHERE ' . implode(' OR ', $where);

		$result = pg_query($sql);
		$row = pg_fetch_assoc($result);
		pg_free_result($result);

		$min = $row['min_val'];
		$max = $row['max_val'];

	}

	include('include/header.php');

?>

		<div class="menu">
			<a href="index.php?type=tpch">index</a>
		</div>

<?php

	$submenu = array();
	$keys = array_keys($columns);
	foreach ($keys AS $j) {
		if ($columns[$j]['table'] == 'results_tpch') {
			if ($j == $key) {
				$submenu[] = '<span class="active">' . $columns[$j]['title'] . '</span> ';
			} else {
				$submenu[] = '<a href="compare-tpch.php?' . $url . '&amp;key=' . $j . '">' . $columns[$j]['title'] . '</a> ';
			}
		}
	}

	echo '<div class="submenu">';
	echo '<span class="items">' . implode(' | ', $submenu) . '</span>';
	echo '</div>';

	echo '<img src="chart-average.php?',$url,'&amp;img=',$key,'&amp;min=',$min,'&amp;max=',$max,'" />';

	foreach ($types AS $type) {
		$tmp = explode(':', $type);
		$drive = $tmp[0];
		$fs = $tmp[1];
		echo '<a href="tpch.php?type=',$type,'">';
		echo '<img src="chart.php?type=',$type,'&amp;img=',$key,'&amp;min=',$min,'&amp;max=',$max,$imgurl,'" />';
		echo '</a>',"\n";
	}

	include('include/footer.php');

?>