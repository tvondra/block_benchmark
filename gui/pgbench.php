<?php

	require('include/init.php');

	$imgdir = 'images';

	$type = (isset($_REQUEST['type'])) ? $_REQUEST['type'] : null;

	$tmp = explode(':',$type);
	$fs = $tmp[0];
	$config = $tmp[1];

	include('include/header.php');

?>
		<div class="menu">
			<a href="index.php?type=pgbench">index</a>
		</div>
<?php

	// print all the on-the-fly generated pgbench charts
	foreach ($columns AS $key => $vals) {
		if ($vals['table'] == 'results_pgbench') {
			echo '<img src="chart.php?type=',$type,'&amp;img=',$key,'" alt="',$vals['desc'],'" title="',$vals['desc'],'" />',"\n";
		}
	}

	// print all the prebuilt pgbench charts (latency, tps, ...)
	// get block sizes and image directories for each combination
	$sql = 'SELECT db_block, fs_block, img_dir FROM results_pgbench ' . 
			'WHERE config_id = ' . (int)$config . " AND fs = '" . pg_escape_string($fs) . "'";
	$result = pg_query($sql);

	$fsbs = array();
	$pgbs = array();
	$dirs = array();

	while ($row = pg_fetch_assoc($result)) {
		$fsbs[] = $row['fs_block'];
		$pgbs[] = $row['db_block'];
		$dirs[$row['db_block']][$row['fs_block']] = $row['img_dir'];
	}

	$pgbs = array_unique($pgbs);
	$fsbs = array_unique($fsbs);

	sort($pgbs);
	sort($fsbs);

	// for each combination of block sizes, output the charts
	echo '<h2>read-only tps</h2>';
	echo '<a name="tps-ro"></a>';
	echo '<table>';
	foreach ($pgbs AS $a) {
		echo '<tr>',"\n";
		foreach ($fsbs AS $b) {
			echo '<td><img src="images/',$dirs[$a][$b],'/',$fs,'-',$b,'-',$a,'-tps-ro.png" alt="',$fs,'-',$b,'-',$a,'" title="',$fs,'-',$b,'-',$a,'" /></td>',"\n";
		}
		echo '</tr>',"\n";
	}
	echo '</table>';

	echo '<h2>read-only latency</h2>';
	echo '<a name="latency-ro"></a>';
	echo '<table>';
	foreach ($pgbs AS $a) {
		echo '<tr>',"\n";
		foreach ($fsbs AS $b) {
			echo '<td><img src="images/',$dirs[$a][$b],'/',$fs,'-',$b,'-',$a,'-latency-ro.png" alt="',$fs,'-',$b,'-',$a,'" title="',$fs,'-',$b,'-',$a,'" /></td>',"\n";
		}
		echo '</tr>',"\n";
	}
	echo '</table>';

	echo '<h2>read-only latency (log)</h2>';
	echo '<a name="latency-log-ro"></a>';
	echo '<table>';
	foreach ($pgbs AS $a) {
		echo '<tr>',"\n";
		foreach ($fsbs AS $b) {
			echo '<td><img src="images/',$dirs[$a][$b],'/',$fs,'-',$b,'-',$a,'-latency-log-ro.png" alt="',$fs,'-',$b,'-',$a,'" title="',$fs,'-',$b,'-',$a,'" /></td>',"\n";
		}
		echo '</tr>',"\n";
	}
	echo '</table>';

	echo '<h2>read-write tps</h2>';
	echo '<a name="tps-rw"></a>';
	echo '<table>';
	foreach ($pgbs AS $a) {
		echo '<tr>',"\n";
		foreach ($fsbs AS $b) {
			echo '<td><img src="images/',$dirs[$a][$b],'/',$fs,'-',$b,'-',$a,'-tps-rw.png" alt="',$fs,'-',$b,'-',$a,'" title="',$fs,'-',$b,'-',$a,'" /></td>',"\n";
		}
		echo '</tr>',"\n";
	}
	echo '</table>';

	echo '<h2>read-write latency</h2>';
	echo '<a name="latency-rw"></a>';
	echo '<table>';
	foreach ($pgbs AS $a) {
		echo '<tr>',"\n";
		foreach ($fsbs AS $b) {
			echo '<td><img src="images/',$dirs[$a][$b],'/',$fs,'-',$b,'-',$a,'-latency-rw.png" alt="',$fs,'-',$b,'-',$a,'" title="',$fs,'-',$b,'-',$a,'" /></td>',"\n";
		}
		echo '</tr>',"\n";
	}
	echo '</table>';

	echo '<h2>read-write latency (log)</h2>';
	echo '<a name="latency-log-rw"></a>';
	echo '<table>';
	foreach ($pgbs AS $a) {
		echo '<tr>',"\n";
		foreach ($fsbs AS $b) {
			echo '<td><img src="images/',$dirs[$a][$b],'/',$fs,'-',$b,'-',$a,'-latency-log-rw.png" alt="',$fs,'-',$b,'-',$a,'" title="',$fs,'-',$b,'-',$a,'" /></td>',"\n";
		}
		echo '</tr>',"\n";
	}
	echo '</table>';

	include('include/footer.php');

?>