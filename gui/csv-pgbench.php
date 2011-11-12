<?php

	require('include/init.php');

	$types = (isset($_REQUEST['type'])) ? $_REQUEST['type'] : array();

	$where = array();
	foreach ($types AS $type) {
		$tmp = explode(':', $type);
		$where[] = "(config_id = " . (int)$tmp[1] . " AND fs = '" . pg_escape_string($tmp[0]) . "')";
	}

	$sql = 'SELECT fs, fs_block, db_block, tps_ro, transactions_ro, latency_avg_ro, latency_var_ro, db_cache_hit_ratio_ro, ' .
			'fs_cache_hit_ratio_ro, buffers_checkpoint_ro, buffers_clean_ro, maxwritten_clean_ro, buffers_backend_ro, ' .
			'buffers_alloc_ro, tps_rw, transactions_rw, latency_avg_rw, latency_var_rw, db_cache_hit_ratio_rw, ' .
			'fs_cache_hit_ratio_rw, buffers_checkpoint_rw, buffers_clean_rw, maxwritten_clean_rw, buffers_backend_rw, ' .
			'buffers_alloc_rw FROM results_pgbench WHERE ' . implode(' OR ', $where) . ' ORDER BY fs, fs_block, pg_block';

	header('Content-type: text/plain');
	header('Content-Disposition: attachment; filename="pgbench-' . time() . '.csv"');

	echo '"fs";"fs block";"db block";"tps (ro)";"transactions (ro)";"latency avg (ro)";"latency var (ro)";"db cache hit ratio (ro)";' . 
		'"fs cache hit ratio (ro)";"buffers checkpoint (ro)";"buffers clean (ro)";"maxwritten clean (ro)";"buffers backend (ro)";' . 
		'"buffers alloc (ro)";"tps (rw)";"transactions (rw)";"latency avg (rw)";"latency var (rw)";"db cache hit ratio (rw)";' .
		'"fs cache hit ratio (rw)";"buffers checkpoint (rw)";"buffers clean (rw)";"maxwritten clean (rw)";"buffers backend (rw)";' .
		'"buffers alloc (rw)"',"\n";

	$result = pg_query($sql);
	while ($row = pg_fetch_assoc($result)) {
		echo '"',$row['fs'],'";';
		echo $row['fs_block'],';';
		echo $row['db_block'],';';
		echo $row['tps_ro'],';';
		echo $row['transactions_ro'],';';
		echo $row['latency_avg_ro'],';';
		echo $row['latency_var_ro'],';';
		echo $row['db_cache_hit_ratio_ro'],';';
		echo $row['fs_cache_hit_ratio_ro'],';';
		echo $row['buffers_checkpoint_ro'],';';
		echo $row['buffers_clean_ro'],';';
		echo $row['maxwritten_clean_ro'],';';
		echo $row['buffers_backend_ro'],';';
		echo $row['buffers_alloc_ro'],';';
		echo $row['tps_rw'],';';
		echo $row['transactions_rw'],';';
		echo $row['latency_avg_rw'],';';
		echo $row['latency_var_rw'],';';
		echo $row['db_cache_hit_ratio_rw'],';';
		echo $row['fs_cache_hit_ratio_rw'],';';
		echo $row['buffers_checkpoint_rw'],';';
		echo $row['buffers_clean_rw'],';';
		echo $row['maxwritten_clean_rw'],';';
		echo $row['buffers_backend_rw'],';';
		echo $row['buffers_alloc_rw'];
		echo "\n";
	}
	pg_free_result($result);

?>