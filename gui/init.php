<?php

	$conn = @pg_pconnect('host=HOSTNAME dbname=DBNAME user=USER password=PASS');
	if (! $conn) {
		die('ERROR: Database connection not opened.');
	}

	pg_exec($conn, "SET search_path TO 'bench'");

	define('QUERY_TIMEOUT', 300);
	define('IMAGE_WIDTH', 590);
	define('IMAGE_HEIGHT', 275);

	$columns = array(

		'tps-ro' => array('title' => 'tps (r/o)', 'desc' => 'Transactions per second with a read-only workload (pgbench -S).', 'table' => 'results_pgbench', 'column' => 'tps_ro'),
		'tps-rw' => array('title' => 'tps (r/w)', 'desc' => 'Transactions per second with a read-write workload (pgbench).', 'table' => 'results_pgbench', 'column' => 'tps_rw'),

		'lat-avg-ro' => array('title' => 'average latency (r/o)', 'desc' => 'Average latency with a read-only workload (pgbench -S).', 'table' => 'results_pgbench', 'column' => 'latency_avg_ro'),
		'lat-avg-rw' => array('title' => 'average latency (r/w)', 'desc' => 'Average latency with a read-write workload (pgbench).', 'table' => 'results_pgbench', 'column' => 'latency_avg_rw'),

		'lat-var-ro' => array('title' => 'latency variance (r/o)', 'desc' => 'Latency variance (standard deviation) with a read-only workload (pgbench -S).', 'table' => 'results_pgbench', 'column' => 'latency_var_ro'),
		'lat-var-rw' => array('title' => 'latency variance (r/w)', 'desc' => 'Latency variance (standard deviation) with a read-write workload (pgbench).', 'table' => 'results_pgbench', 'column' => 'latency_var_rw'),

		'buffers-checkpoint-ro' => array('title' => 'buffers checkpoint (r/o)', 'desc' => 'Number of buffers written during a checkpoint (r/o).', 'table' => 'results_pgbench', 'column' => 'buffers_checkpoint_ro'),
		'buffers-checkpoint-rw' => array('title' => 'buffers checkpoint (r/w)', 'desc' => 'Number of buffers written during a checkpoint (r/w).', 'table' => 'results_pgbench', 'column' => 'buffers_checkpoint_rw'),

		'buffers-checkpoint-mb-ro' => array('title' => 'buffers checkpoint MB (r/o)', 'desc' => 'Amount of buffers written during a checkpoint (r/o).', 'table' => 'results_pgbench', 'column' => '(buffers_checkpoint_ro * db_block / 1024)'),
		'buffers-checkpoint-mb-rw' => array('title' => 'buffers checkpoint MB (r/w)', 'desc' => 'Amount of buffers written during a checkpoint (r/w).', 'table' => 'results_pgbench', 'column' => '(buffers_checkpoint_rw * db_block / 1024)'),

		'buffers-clean-ro' => array('title' => 'buffers clean (r/o)', 'desc' => 'Number of buffers written by background writer (r/o).', 'table' => 'results_pgbench', 'column' => 'buffers_clean_ro'),
		'buffers-clean-rw' => array('title' => 'buffers clean (r/w)', 'desc' => 'Number of buffers written by background writer (r/w)', 'table' => 'results_pgbench', 'column' => 'buffers_clean_rw'),

		'buffers-clean-mb-ro' => array('title' => 'buffers clean MB (r/o)', 'desc' => 'Amount of buffers written by background writer (r/o).', 'table' => 'results_pgbench', 'column' => '(buffers_clean_ro * db_block / 1024)'),
		'buffers-clean-mb-rw' => array('title' => 'buffers clean MB (r/w)', 'desc' => 'Amount of buffers written by background writer (r/w)', 'table' => 'results_pgbench', 'column' => '(buffers_clean_rw * db_block / 1024)'),

		'maxwritten-clean-ro' => array('title' => 'maxwritten clean (r/o)', 'desc' => 'Number of times the background writer has stopped because of reaching lru_maxpages (r/o).', 'table' => 'results_pgbench', 'column' => 'maxwritten_clean_ro'),
		'maxwritten-clean-rw' => array('title' => 'maxwritten clean (r/w)', 'desc' => 'Number of times the background writer has stopped because of reaching lru_maxpages (r/w).', 'table' => 'results_pgbench', 'column' => 'maxwritten_clean_rw'),

		'buffers-backend-ro' => array('title' => 'buffers backend (r/o)', 'desc' => 'Number of buffers written by backends (r/o).', 'table' => 'results_pgbench', 'column' => 'buffers_backend_ro'),
		'buffers-backend-rw' => array('title' => 'buffers backend (r/w)', 'desc' => 'Number of buffers written by backends (r/w).', 'table' => 'results_pgbench', 'column' => 'buffers_backend_rw'),

		'buffers-backend-mb-ro' => array('title' => 'buffers backend MB (r/o)', 'desc' => 'Amount of buffers written by backends (r/o).', 'table' => 'results_pgbench', 'column' => '(buffers_backend_ro * db_block / 1024)'),
		'buffers-backend-mb-rw' => array('title' => 'buffers backend MB (r/w)', 'desc' => 'Amount of buffers written by backends (r/w).', 'table' => 'results_pgbench', 'column' => '(buffers_backend_rw * db_block / 1024)'),

		'buffers-total-ro' => array('title' => 'buffers written (r/o)', 'desc' => 'Number of buffers written (r/o).', 'table' => 'results_pgbench', 'column' => '(buffers_backend_ro + buffers_clean_ro + buffers_checkpoint_ro)'),
		'buffers-total-rw' => array('title' => 'buffers written (r/w)', 'desc' => 'Number of buffers written (r/w).', 'table' => 'results_pgbench', 'column' => '(buffers_backend_rw + buffers_clean_rw + buffers_checkpoint_rw)'),

		'buffers-total-mb-ro' => array('title' => 'buffers written MB (r/o)', 'desc' => 'Amount of buffers written (r/o).', 'table' => 'results_pgbench', 'column' => '(buffers_backend_ro + buffers_clean_ro + buffers_checkpoint_ro)*db_block/1024'),
		'buffers-total-mb-rw' => array('title' => 'buffers written MB (r/w)', 'desc' => 'Amount of buffers written (r/w).', 'table' => 'results_pgbench', 'column' => '(buffers_backend_rw + buffers_clean_rw + buffers_checkpoint_rw)*db_block/1024'),

		'buffers-alloc-ro' => array('title' => 'buffers alloc (r/o)', 'desc' => 'Total buffers allocated (r/o).', 'table' => 'results_pgbench', 'column' => 'buffers_alloc_ro'),
		'buffers-alloc-rw' => array('title' => 'buffers alloc (r/w)', 'desc' => 'Total buffers allocated (r/w).', 'table' => 'results_pgbench', 'column' => 'buffers_alloc_rw'),

		'buffers-alloc-mb-ro' => array('title' => 'buffers alloc MB (r/o)', 'desc' => 'Amount of buffers allocated (r/o).', 'table' => 'results_pgbench', 'column' => '(buffers_alloc_ro*db_block/1024)'),
		'buffers-alloc-mb-rw' => array('title' => 'buffers alloc MB (r/w)', 'desc' => 'Amount of buffers allocated (r/w).', 'table' => 'results_pgbench', 'column' => '(buffers_alloc_rw*db_block/1024)'),

		'db-hit-ratio-ro' => array('title' => 'db cache hit ratio (r/o)', 'desc' => 'Shared buffers hit ratio (using pg_stat_database, r/o).', 'table' => 'results_pgbench', 'column' => 'db_cache_hit_ratio_ro'),
		'db-hit-ratio-rw' => array('title' => 'db cache hit ratio (r/w)', 'desc' => 'Shared buffers hit ratio (using pg_stat_database, r/w).', 'table' => 'results_pgbench', 'column' => 'db_cache_hit_ratio_rw'),

		'fs-hit-ratio-ro' => array('title' => 'fs cache hit ratio (r/o)', 'desc' => 'Filesystem page cache hit ratio (using threshold on latency, r/o).', 'table' => 'results_pgbench', 'column' => 'fs_cache_hit_ratio_ro'),
		'fs-hit-ratio-rw' => array('title' => 'fs cache hit ratio (r/w)', 'desc' => 'Filesystem page cache hit ratio (using threshold on latency, r/w).', 'table' => 'results_pgbench', 'column' => 'fs_cache_hit_ratio_rw'),

		'tpch-total' => array('title' => 'total time (s)', 'desc' => 'Total time (in seconds) to perform the whole TPC-H benchmark (including queries that timed-out).', 'table' => 'results_tpch', 'column' => 'tpch_total'),
		'tpch-load' => array('title' => 'load (s)', 'desc' => 'Time (in seconds) needed to load the TPC-H data.', 'table' => 'results_tpch', 'column' => 'tpch_load'),
		'tpch-analyze' => array('title' => 'analyze (s)', 'desc' => 'Time (in seconds) needed to analyze the TPC-H data after load.', 'table' => 'results_tpch', 'column' => 'tpch_analyze'),
		'tpch-fkeys' => array('title' => 'foreign keys (s)', 'desc' => 'Time (in seconds) needed to create foreign keys after loading the TPC-H data.', 'table' => 'results_tpch', 'column' => 'tpch_fkeys'),
		'tpch-indexes' => array('title' => 'indexes (s)', 'desc' => 'Time (in seconds) needed to create indexes after loading the TPC-H data.', 'table' => 'results_tpch', 'column' => 'tpch_indexes'),

		'tpch-m-time' => array('title' => 'm-time (s)', 'desc' => 'Time to run all the queries (including queries that timed-out).', 'table' => 'results_tpch', 'column' => ''),
		'tpch-m-time-pct' => array('title' => 'm-time (%)', 'desc' => 'Time to run all the queries (including queries that timed-out), compared to an "ideal run" for each filesystem. An ideal run is when all queries finish in minimal time, so the best possible result is 100% which means that all the queries reached the best observed time.', 'table' => 'results_tpch', '' => ''),

		'tpch-time' => array('title' => 'time (s)', 'desc' => 'Score for each filesystem - fastest run of a query gains 1 point and slower runs gain inversely proportional value. So for example a run that takes twice as long gains just 1/2 point. There are 22 queries so the best possible score is 22.', 'table' => 'results_tpch', 'column' => ''),
		'tpch-time-pct' => array('title' => 'time (%)', 'desc' => 'Score for each filesystem, compared to the best possible value (22 points).', 'table' => 'results_tpch', 'column' => ''),

		'tpch-score' => array('title' => 'score', 'desc' => 'Modified score - generally the same as score, but only the queries that used the same query plan and finished in all cases are used.', 'table' => 'results_tpch', 'column' => ''),
		'tpch-score-pct' => array('title' => 'score (%)', 'desc' => 'Modified score compared to the best possible value (depends on conditions).', 'table' => 'results_tpch', 'column' => ''),

		'tpch-m-score' => array('title' => 'm-score', 'desc' => 'Modified time - only the queries that used the same query plan and finished in all cases are used.', 'table' => 'results_tpch', 'column' => ''),
		'tpch-m-score-pct' => array('title' => 'm-score (%)', 'desc' => 'Modified time compared to the best possible value (depends on conditions).', 'table' => 'results_tpch', 'column' => ''),

		'tpch-db-hit-ratio' => array('title' => 'db cache hit ratio (%)', 'desc' => 'Shared buffers hit ratio (using pg_stat_database).', 'table' => 'results_tpch', 'column' => 'db_cache_hit_ratio'),

	);

	function get_common_queries($where) {

		$sql = 'SELECT COUNT(*) AS cnt';
		for ($i = 1; $i <= 22; $i++) {
			$sql .= ", COUNT(query_$i) AS cnt_$i, COUNT(DISTINCT query_${i}_hash) AS cnt_hash_$i, MIN(query_$i) AS min_$i";
		}
		$sql .= ' FROM results_tpch WHERE ' . implode(' OR ', $where);

		$result = pg_query($sql);
		$row = pg_fetch_assoc($result);
		pg_free_result($result);

		$queries = array();
		$mins = array();

		for ($i = 1; $i <= 22; $i++) {
			if (($row['cnt'] == $row["cnt_$i"]) && ($row["cnt_hash_$i"] == '1')) {
				$queries[] = $i;
				$mins[] = $row["min_$i"];
			}
		}

		return array('queries' => $queries, 'mins' => $mins);

	}

	function get_all_queries($where) {

		$sql = 'SELECT COUNT(*) AS cnt';
		for ($i = 1; $i <= 22; $i++) {
			$sql .= ", COUNT(query_$i) AS cnt_$i, COUNT(DISTINCT query_${i}_hash) AS cnt_hash_$i, MIN(query_$i) AS min_$i";
		}
		$sql .= ' FROM results_tpch WHERE ' . implode(' OR ', $where);

		$result = pg_query($sql);
		$row = pg_fetch_assoc($result);
		pg_free_result($result);

		$queries = array();
		$mins = array();

		for ($i = 1; $i <= 22; $i++) {
			$queries[] = $i;
			$mins[] = $row["min_$i"];
		}

		return array('queries' => $queries, 'mins' => $mins);

	}

?>