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
			<a href="index.php?type=tpch">index</a>
		</div>
<?php

	foreach ($columns AS $key => $vals) {
		if ($vals['table'] == 'results_tpch') {
			echo '<img src="chart.php?type=',$type,'&amp;img=',$key,'" />',"\n";
		}
	}

	include('include/footer.php');

?>
