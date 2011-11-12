<?php

	require('include/init.php');

	$types = (isset($_REQUEST['type'])) ? $_REQUEST['type'] : array();
	$key = (isset($_REQUEST['key'])) ? $_REQUEST['key'] : 'tps-ro';

	if (count($types) == 0) {
		$path = explode('/', $_SERVER['REQUEST_URI']);
		$path[count($path)-1] = 'index.php';
		header('Location: ' . implode('/', $path));
		exit();
	}

	$where = array();
	foreach ($types AS $type) {
		$tmp = explode(':', $type);
		$where[] = "(config_id = " . (int)$tmp[1] . " AND fs = '" . pg_escape_string($tmp[0]) . "')";
	}

	// get min/max of this column
	$sql = 'SELECT MIN(' . $columns[$key]['column'] . ') AS min_val, MAX(' . $columns[$key]['column'] . ') AS max_val FROM results_pgbench WHERE ' . implode(' OR ', $where);
	$result = pg_query($sql);
	$row = pg_fetch_assoc($result);

	$min = $row['min_val'];
	$max = $row['max_val'];

	// url snippet used to print menu items
	$url = 'type[]=' . implode('&amp;type[]=',$types);

	include('include/header.php');

?>
		<div class="menu">
			<a href="index.php?type=pgbench">index</a>
		</div>
<?php

	$submenu = array();
	$keys = array_keys($columns);
	foreach ($keys AS $j) {
		if ($columns[$j]['table'] == 'results_pgbench') {
			if ($j == $key) {
				$submenu[] = '<span class="active">' . $columns[$j]['title'] . '</span> ';
			} else {
				$submenu[] = '<a href="compare-pgbench.php?' . $url . '&amp;key=' . $j . '">' . $columns[$j]['title'] . '</a> ';
			}
		}
	}

	echo '<div class="submenu">';
	echo '<span class="items">' . implode(' | ', $submenu) . '</span>';
	echo '</div>';

	echo '<div class="description">',$columns[$key]['desc'],'</div>',"\n";

	echo '<img src="chart-average.php?',$url,'&amp;img=',$key,'&amp;min=',$min,'&amp;max=',$max,'" /> ',"\n";

	foreach ($types AS $type) {
		$tmp = explode(':', $type);
		$drive = $tmp[0];
		$fs = $tmp[1];
		echo '<a href="pgbench.php?type=',$type,'">';
		echo '<img src="chart.php?type=',$type,'&amp;img=',$key,'&amp;min=',$min,'&amp;max=',$max,'" />';
		echo '</a>',"\n";
	}

	include('include/footer.php');

?>
