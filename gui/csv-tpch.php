<?php

	require('include/init.php');

	$types = (isset($_REQUEST['type'])) ? $_REQUEST['type'] : array();

	$where = array();
	foreach ($types AS $type) {
		$tmp = explode(':', $type);
		$where[] = "(config_id = " . (int)$tmp[1] . " AND fs = '" . pg_escape_string($tmp[0]) . "')";
	}

	$sql = 'SELECT fs, fs_block, db_block, tpch_load, tpch_fkeys, tpch_indexes, tpch_analyze, tpch_total, query_1, query_2, ' .
			'query_3, query_4, query_5, query_6, query_7, query_8, query_9, query_10, query_11, query_12, query_13, query_14, ' .
			'query_15, query_16, query_17, query_18, query_19, query_20, query_21, query_22, query_1_hash, query_2_hash, ' .
			'query_3_hash, query_4_hash, query_5_hash, query_6_hash, query_7_hash, query_8_hash, query_9_hash, query_10_hash, ' .
			'query_11_hash, query_12_hash, query_13_hash, query_14_hash, query_15_hash, query_16_hash, query_17_hash, query_18_hash, '
			'query_19_hash, query_20_hash, query_21_hash, query_22_hash, db_cache_hit_ratio ' .
			'FROM results_tpch WHERE ' . implode(' OR ', $where) . ' ORDER BY fs, fs_block, pg_block';

	header('Content-type: text/plain');
	header('Content-Disposition: attachment; filename="tpch-' . time() . '.csv"');

	echo '"fs";"fs block";"db block";"load";"fkeys";"indexes";"analyze";"total";"query 1";"query 2";"query 3";"query 4";"query 5";' .
		'"query 6";"query 7";"query 8";"query 9";"query 10";"query 11";"query 12";"query 13";"query 14";"query 15";"query 16";"query 17";' .
		'"query 18";"query 19";"query 20";"query 21";"query 22";"query 1 hash";"query 2 hash";"query 3 hash";"query 4 hash";"query 5 hash";' .
		'"query 6 hash";"query 7 hash";"query 8 hash";"query 9 hash";"query 10 hash";"query 11 hash";"query 12 hash";"query 13 hash";' .
		'"query 14 hash";"query 15 hash";"query 16 hash";"query 17 hash";"query 18 hash";"query 19 hash";"query 20 hash";"query 21 hash";' .
		'"query 22 hash";"db cache hit ratio"',"\n";

	$result = pg_query($sql);
	while ($row = pg_fetch_assoc($result)) {
		echo '"',$row['fs'],'";';
		echo $row['fs_block'],';';
		echo $row['db_block'],';';

		echo $row['tpch_load'],';';
		echo $row['tpch_fkeys'],';';
		echo $row['tpch_indexes'],';';
		echo $row['tpch_analyze'],';';
		echo $row['tpch_total'],';';
		echo $row['query_1'],';';
		echo $row['query_2'],';';
		echo $row['query_3'],';';
		echo $row['query_4'],';';
		echo $row['query_5'],';';
		echo $row['query_6'],';';
		echo $row['query_7'],';';
		echo $row['query_8'],';';
		echo $row['query_9'],';';
		echo $row['query_10'],';';
		echo $row['query_11'],';';
		echo $row['query_12'],';';
		echo $row['query_13'],';';
		echo $row['query_14'],';';
		echo $row['query_15'],';';
		echo $row['query_16'],';';
		echo $row['query_17'],';';
		echo $row['query_18'],';';
		echo $row['query_19'],';';
		echo $row['query_20'],';';
		echo $row['query_21'],';';
		echo $row['query_22'],';';
		echo $row['query_1_hash'],';';
		echo $row['query_2_hash'],';';
		echo $row['query_3_hash'],';';
		echo $row['query_4_hash'],';';
		echo $row['query_5_hash'],';';
		echo $row['query_6_hash'],';';
		echo $row['query_7_hash'],';';
		echo $row['query_8_hash'],';';
		echo $row['query_9_hash'],';';
		echo $row['query_10_hash'],';';
		echo $row['query_11_hash'],';';
		echo $row['query_12_hash'],';';
		echo $row['query_13_hash'],';';
		echo $row['query_14_hash'],';';
		echo $row['query_15_hash'],';';
		echo $row['query_16_hash'],';';
		echo $row['query_17_hash'],';';
		echo $row['query_18_hash'],';';
		echo $row['query_19_hash'],';';
		echo $row['query_20_hash'],';';
		echo $row['query_21_hash'],';';
		echo $row['query_22_hash'],';';
		echo $row['db_cache_hit_ratio'],';';

		echo "\n";
	}
	pg_free_result($result);

?>