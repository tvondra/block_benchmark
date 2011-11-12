<?php

/*

   Parses data collected during the benchmark and produces an SQL script with
   the data and a bunch of images (latency and tps of the pgbench part).

   Expected cmdline parameters are

      $ php collect.php INPUT_DIRECTORY OUTPUT_IMAGES_DIRECTORY

   so if the benchmark data are in 'bench-results' directory, and you want to
   get images into the 'bench-images' directory, do this

      $ php collect.php ./bench-results ./bench-images

   The processing may take a lot of time (and CPU/memory), because it has to
   parse all the pgbench results and build all the images.

   This requires a working gnuplot installation (to build the images).

*/

	ini_set('memory_limit', -1);

	date_default_timezone_set('Europe/Prague');

	define('IMAGE_WIDTH', 590);
	define('IMAGE_HEIGHT', 300);
	define('BENCH_LENGTH', 600);

	if (count($argv) < 3) {
		echo "ERROR: not enough parameters\n";
		exit();
	}

	$input  = $argv[1];
	$output = $argv[2];

	if (! file_exists($input)) {
		echo "ERROR: input directory '$input' does not exist\n";
		exit();
	} else if (! is_dir($input)) {
		echo "ERROR: is not a directory: '$input'\n";
		exit();
	} else if (file_exists($output)) {
		echo "ERROR: output file '$output' already exists\n";
		exit();
	}

	/* query timeout limit */
	define('QUERY_TIMEOUT', 300);

	echo "input directory: $input\n";
	echo "output SQL file: $output\n\n";

	// load the results from directory
	$data = load_results($input);

	// add information from the shell log
	parse_log($data, "$input/bench.log");

	// write the results into SQL
	print_pgbench_sql($data, load_config("$input/pgbench.config"), $output);
	print_tpch_sql($data, load_config("$input/tpch.config"), $output);

	// build pgbench plots
	build_pgbench_plots($input, 'images', $data);

	/* FUNCTIONS */

	/* loads results from the directories */
	/* param $dir - directory with benchmark results */
	function load_results($dir) {

		$data = array();

		$fs_dir = opendir($dir);
		while ($fs = readdir($fs_dir)) {

			if ((! is_dir("$dir/$fs")) || (in_array($fs,array('.','..'))))  { continue; }

			echo "$dir/$fs\n";

			$data[$fs] = array();

			$pgbs_dir = opendir("$dir/$fs");
			while ($pgbs = readdir($pgbs_dir)) {

				if ($pgbs == '.' || $pgbs == '..') { continue; }

				echo "\t$pgbs:";

				$data[$fs][$pgbs] = array();

				$fsbs_dir = opendir("$dir/$fs/$pgbs");
				while ($fsbs = readdir($fsbs_dir)) {

					if ($fsbs == '.' || $fsbs == '..') { continue; }

					echo " $fsbs";

					$data[$fs][$pgbs][$fsbs] = array();

					$data[$fs][$pgbs][$fsbs]['hash'] = md5("$fs/$pgbs/$fsbs" . microtime(true));

					$data[$fs][$pgbs][$fsbs]['pgbench'] = array();

					$data[$fs][$pgbs][$fsbs]['pgbench']['ro'] = load_pgbench("$dir/$fs/$pgbs/$fsbs/pgbench/ro", 1);
					$data[$fs][$pgbs][$fsbs]['pgbench']['rw'] = load_pgbench("$dir/$fs/$pgbs/$fsbs/pgbench/rw", 9);

					$data[$fs][$pgbs][$fsbs]['tpch'] = load_tpch("$dir/$fs/$pgbs/$fsbs/tpch");

				}
				closedir($fsbs_dir);
				echo "\n";

			}
			closedir($pgbs_dir);

		}
		closedir($fs_dir);

		return $data;
	}

	/* loads postgresql stats from the directory (expects stats-before/stats-after log files) */
	/* param $dir - directory with benchmark results */
	function load_stats($dir) {

		$diff = array();

		$before = file("$dir/stats-before.log");
		$after = file("$dir/stats-after.log");

		// pg_stat_bgwriter

		$matches_before = array();
		preg_match('/^\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)$/', $before[2], $matches_before);

		$matches_after = array();
		preg_match('/^\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)$/', $after[2], $matches_after);

		$diff['checkpoints_timed'] = $matches_after[1] - $matches_before[1];
		$diff['checkpoints_req'] = $matches_after[2] - $matches_before[2];
		$diff['buffers_checkpoint'] = $matches_after[3] - $matches_before[3];
		$diff['buffers_clean'] = $matches_after[4] - $matches_before[4];
		$diff['maxwritten_clean'] = $matches_after[5] - $matches_before[5];
		$diff['buffers_backend'] = $matches_after[6] - $matches_before[6];
		$diff['buffers_alloc'] = $matches_after[7] - $matches_before[7];

		// pg_stat_database

		$matches_before = array();
		preg_match('/^\s+([0-9]+)\s\|\s+([a-z]+)\s+\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)$/', $before[7], $matches_before);

		$matches_after = array();
		preg_match('/^\s+([0-9]+)\s\|\s+([a-z]+)\s+\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)$/', $after[7], $matches_after);

		$diff['xact_commit'] = $matches_after[4] - $matches_before[4];
		$diff['xact_rollback'] = $matches_after[5] - $matches_before[5];
		$diff['blks_read'] = $matches_after[6] - $matches_before[6];
		$diff['blks_hit'] = $matches_after[7] - $matches_before[7];
		$diff['tup_returned'] = $matches_after[8] - $matches_before[8];
		$diff['tup_fetched'] = $matches_after[9] - $matches_before[9];
		$diff['tup_inserted'] = $matches_after[10] - $matches_before[10];
		$diff['tup_updated'] = $matches_after[11] - $matches_before[11];
		$diff['tup_deleted'] = $matches_after[12] - $matches_before[12];

		$diff['hit_ratio'] = round(floatval(100*$diff['blks_hit']) / ($diff['blks_hit'] + $diff['blks_read']),1);

		return $diff;

	}

	/* loads query stats from the directory (expects results.log file) */
	/* param $dir - directory with benchmark results */
	function load_queries($dir) {

		$queries = array();
		$results = file("$dir/results.log");

		foreach ($results AS $line) {

			if (substr_count($line, '=') > 0) {

				$tmp = explode('=', $line);
				$qn = intval($tmp[0]); /* query id */

				$queries[$qn]['duration'] = floatval($tmp[1]);
				$queries[$qn]['hash'] = get_plan_hash("$dir/explain/$qn");

			}
		}

		return $queries;

	}

	/* loads pgbench results from the directory (expects results.log file) */
	/* param $dir - directory with benchmark results */
	function load_pgbench_tps($dir, $threshold) {

		$o = array();

		$d = file("$dir/results.log");

		$matches = array();
		preg_match('/^number of transactions actually processed: ([0-9]+)$/', $d[6], $matches);

		$o['transactions'] = $matches[1];

		$matches = array();
		preg_match('/^tps = ([0-9]+\.[0-9]+) \(including connections establishing\)$/', $d[7], $matches);

		$matches = array();
		preg_match('/^tps = ([0-9]+\.[0-9]+) \(including connections establishing\)$/', $d[7], $matches);

		$o['tps'] = $matches[1];

		$x = load_pgbench_log($dir, $threshold);

		$o['hit_ratio'] = $x['hit_ratio'];
		$o['latency/avg'] = $x['avg'];
		$o['latency/var'] = $x['var'];
		$o['latency/med'] = $x['med'];

		return $o;

	}

	function load_pgbench_log($dir, $threshold, $remove = 0.0005) {

		$data = file("$dir/pgbench.log");

		$hits = 0;
		$avg = 0.0;

		// load all the values into an array and sort it
		$tmp = array();
		foreach ($data AS $row) {
			$row = trim($row);
			if ($row != '') {
				$x = explode(' ',$row);
				$tmp[] = floatval($x[2])/1000;
			}
		}
		sort($tmp);

		// remove outliers (something like 0.05% on both ends etc.)
		if ($remove > 0) {
			$c = floor($remove * count($tmp));
			$tmp = array_slice($tmp, $c, count($tmp) - 2*$c);
		}

		$median = $tmp[(int)count($tmp)/2];

		// compute average and variance
		foreach ($tmp AS $val) {
			$avg += $val;
			if ($val < $threshold) {
				$hits += 1;
			}
		}
		$avg = $avg / count($tmp);
		$hit = round(floatval($hits*100)/count($tmp),1);

		$var = 0.0;
		foreach ($tmp AS $val) {
			$var += ($val - $avg)*($val - $avg);
		}
		$var = sqrt($var / count($tmp));

		return array('avg' => $avg, 'var' => $var, 'med' => $median, 'hit_ratio' => $hit);

	}

	/* loads postgresql stats and results the directory  */
	/* param $dir - directory with benchmark results */
	function load_pgbench($dir, $threshold) {
		$out = array();
		$out['stats'] = load_stats($dir);
		$out['results'] = load_pgbench_tps($dir, $threshold);
		return $out;
	}

	/* loads tpc-h results from the directory */
	/* param $dir - directory with benchmark results */
	function load_tpch($dir) {
		$out = array();
		$out['stats'] = load_stats($dir);
		$out['queries'] = load_queries($dir);
		return $out;
	}

	function score_eval($current, $min, $max = QUERY_TIMEOUT) {

		// cancelled queries should get 0
		if ($current >= $max) {
			return 0;
		}

		// otherwise use the inverse (always "current > min")
		return $min/$current;

	}

	function time_eval($current, $max = QUERY_TIMEOUT) {

		return min(floatval($current), $max);

	}

	/* loads bench.log and appends it to the results */
	/* param $data - benchmark data */
	/* param $logfile - logfile to read data from */
	function parse_log(&$data, $logfile) {

		$log = file($logfile);

		$t = 0;

		$fs = '';
		$pgbs = '';
		$fsbs = '';

		for ($i = 0; $i < count($log); $i++) {
			$matches = array();
			if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] : ===== BENCHMARK: (.*) fs=(.*) pg=(.*) \(.*\/.*\) =====/', $log[$i], $matches)) {

				// current filesystem etc.
				$fs = str_replace(' ','-',$matches[2]);
				$fsbs = $matches[3];
				$pgbs = $matches[4];

			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] : preparing cluster for pgbench/', $log[$i], $matches)) {
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] : running read-only benchmark/', $log[$i], $matches)) {
				$data[$fs][$pgbs][$fsbs]['pgbench']['init'] = intval($matches[1]) - $t;
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] : preparing TPC-H database/', $log[$i], $matches)) {
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] :   loading data/', $log[$i], $matches)) {
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] :   creating foreign keys/', $log[$i], $matches)) {
				$data[$fs][$pgbs][$fsbs]['tpch']['load'] = intval($matches[1]) - $t;
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] :   creating indexes/', $log[$i], $matches)) {
				$data[$fs][$pgbs][$fsbs]['tpch']['fkeys'] = intval($matches[1]) - $t;
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] :   analyzing/', $log[$i], $matches)) {
				$data[$fs][$pgbs][$fsbs]['tpch']['indexes'] = intval($matches[1]) - $t;
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] : running TPC-H benchmark/', $log[$i], $matches)) {
				$data[$fs][$pgbs][$fsbs]['tpch']['analyze'] = intval($matches[1]) - $t;
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] : cleaning/', $log[$i], $matches)) {
				$data[$fs][$pgbs][$fsbs]['tpch']['benchmark'] = intval($matches[1]) - $t;
				$t = intval($matches[1]);
			}

		}

	}

	/* writes benchmark results to the CSV file */
	/* param $data - benchmark data */
	/* param $logfile - logfile to read data from */
	function print_pgbench_sql($data, $config, $outfile) {

		$fd = fopen($outfile, "a");

		fwrite($fd, "BEGIN;\n");

		$sql = 	'INSERT INTO config(title, cpu_desc, cpu_cores, ram, drive_desc, drive_rpm, kernel_version, pg_version, shared_buffers, ' .
				'effective_cache, work_mem, maint_mem, checkp_segments, checkp_target, read_ahead) ' .
				"VALUES ('%s', '%s', %d, %d, '%s', '%s', '%s', '%s', %d, %d, %d, %d, %d, %d, %d);";

		fwrite($fd, sprintf($sql,
			pg_escape_string($config['title']),
			pg_escape_string($config['cpu_desc']),
			(int)$config['cpu_cores'],
			(int)$config['ram'],
			pg_escape_string($config['drive_desc']),
			(int)$config['drive_rpm'],
			pg_escape_string($config['kernel_version']),
			pg_escape_string($config['pg_version']),
			(int)$config['shared_buffers'],
			(int)$config['effective_cache'],
			(int)$config['work_mem'],
			(int)$config['maint_mem'],
			(int)$config['checkp_segments'],
			(int)$config['checkp_target'],
			(int)$config['read_ahead']
		) . "\n");

		$sql = 'INSERT INTO results_pgbench(config_id, fs, fs_block, db_block, ' .
				'tps_ro, transactions_ro, buffers_checkpoint_ro, buffers_clean_ro, maxwritten_clean_ro, buffers_backend_ro, buffers_alloc_ro, ' .
				'latency_avg_ro, latency_var_ro, latency_med_ro, db_cache_hit_ratio_ro, fs_cache_hit_ratio_ro, ' .
				'tps_rw, transactions_rw, buffers_checkpoint_rw, buffers_clean_rw, maxwritten_clean_rw, buffers_backend_rw, buffers_alloc_rw, ' .
				'latency_avg_rw, latency_var_rw, latency_med_rw, db_cache_hit_ratio_rw, fs_cache_hit_ratio_rw, img_dir) ' .
				'VALUES (currval(\'config_id_seq\'), \'%s\', %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, ' .
				'%s, %s, %s, %s, %s, %s, %s, \'%s\');';

		foreach ($data AS $fs => $a) {
			foreach ($a AS $pgbs => $b) {
				foreach ($b AS $fsbs => $c) {

					fwrite($fd, sprintf($sql,
						$fs,
						$fsbs,
						$pgbs,

						// pgbench read-write
						$c['pgbench']['ro']['results']['tps'],
						$c['pgbench']['ro']['results']['transactions'],
						$c['pgbench']['ro']['stats']['buffers_checkpoint'],
						$c['pgbench']['ro']['stats']['buffers_clean'],
						$c['pgbench']['ro']['stats']['maxwritten_clean'],
						$c['pgbench']['ro']['stats']['buffers_backend'],
						$c['pgbench']['ro']['stats']['buffers_alloc'],

						$c['pgbench']['ro']['results']['latency/avg'],
						$c['pgbench']['ro']['results']['latency/var'],
						$c['pgbench']['ro']['results']['latency/med'],
						$c['pgbench']['ro']['stats']['hit_ratio'],
						$c['pgbench']['ro']['results']['hit_ratio'],

						// pgbench read-only
						$c['pgbench']['rw']['results']['tps'],
						$c['pgbench']['rw']['results']['transactions'],
						$c['pgbench']['rw']['stats']['buffers_checkpoint'],
						$c['pgbench']['rw']['stats']['buffers_clean'],
						$c['pgbench']['rw']['stats']['maxwritten_clean'],
						$c['pgbench']['rw']['stats']['buffers_backend'],
						$c['pgbench']['rw']['stats']['buffers_alloc'],

						$c['pgbench']['rw']['results']['latency/avg'],
						$c['pgbench']['rw']['results']['latency/var'],
						$c['pgbench']['rw']['results']['latency/med'],
						$c['pgbench']['rw']['stats']['hit_ratio'],
						$c['pgbench']['rw']['results']['hit_ratio'],
						(substr($c['hash'], 0, 2) . '/' . substr($c['hash'],2))

					) . "\n");
				}
			}
		}

		fwrite($fd, "COMMIT;\n");
		fclose($fd);

	}

	/* writes benchmark results to the CSV file */
	/* param $data - benchmark data */
	/* param $logfile - logfile to read data from */
	function print_tpch_sql($data, $config, $outfile) {

		$fd = fopen($outfile, "a");

		fwrite($fd, "BEGIN;\n");

		$sql = 	'INSERT INTO config(title, cpu_desc, cpu_cores, ram, drive_desc, drive_rpm, kernel_version, pg_version, shared_buffers, ' .
				'effective_cache, work_mem, maint_mem, checkp_segments, checkp_target, read_ahead) ' .
				"VALUES ('%s', '%s', %d, %d, '%s', '%s', '%s', '%s', %d, %d, %d, %d, %d, %d, %d);";

		fwrite($fd, sprintf($sql,
			pg_escape_string($config['title']),
			pg_escape_string($config['cpu_desc']),
			(int)$config['cpu_cores'],
			(int)$config['ram'],
			pg_escape_string($config['drive_desc']),
			(int)$config['drive_rpm'],
			pg_escape_string($config['kernel_version']),
			pg_escape_string($config['pg_version']),
			(int)$config['shared_buffers'],
			(int)$config['effective_cache'],
			(int)$config['work_mem'],
			(int)$config['maint_mem'],
			(int)$config['checkp_segments'],
			(int)$config['checkp_target'],
			(int)$config['read_ahead']
		) . "\n");

		$sql = 'INSERT INTO results_tpch(config_id, fs, fs_block, db_block, ' .
				'tpch_load, tpch_fkeys, tpch_indexes, tpch_analyze, tpch_total, ' .
				'query_1, query_2, query_3, query_4, query_5, query_6, query_7, query_8, query_9, query_10, query_11, query_12, query_13, ' .
				'query_14, query_15, query_16, query_17, query_18, query_19, query_20, query_21, query_22, '.
				'query_1_hash, query_2_hash, query_3_hash, query_4_hash, query_5_hash, query_6_hash, query_7_hash, query_8_hash, query_9_hash, ' .
				'query_10_hash, query_11_hash, query_12_hash, query_13_hash, query_14_hash, query_15_hash, query_16_hash, query_17_hash, ' .
				'query_18_hash, query_19_hash, query_20_hash, query_21_hash, query_22_hash, db_cache_hit_ratio) ' .
				"VALUES (currval('config_id_seq'), '%s', %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, " .
				"%s, %s, %s, %s, %s, %s, %s, %s, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', " .
				"'%s', '%s', '%s', '%s', '%s', '%s', '%s', %s)";

		foreach ($data AS $fs => $a) {
			foreach ($a AS $pgbs => $b) {
				foreach ($b AS $fsbs => $c) {

					fwrite($fd, sprintf($sql,
						$fs,
						$fsbs,
						$pgbs,

						// tpc-h
						$c['tpch']['load'],
						$c['tpch']['fkeys'],
						$c['tpch']['indexes'],
						$c['tpch']['analyze'],
						$c['tpch']['benchmark'],

						($c['tpch']['queries'][1]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][1]['duration'] : 'NULL',
						($c['tpch']['queries'][2]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][2]['duration'] : 'NULL',
						($c['tpch']['queries'][3]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][3]['duration'] : 'NULL',
						($c['tpch']['queries'][4]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][4]['duration'] : 'NULL',
						($c['tpch']['queries'][5]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][5]['duration'] : 'NULL',
						($c['tpch']['queries'][6]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][6]['duration'] : 'NULL',
						($c['tpch']['queries'][7]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][7]['duration'] : 'NULL',
						($c['tpch']['queries'][8]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][8]['duration'] : 'NULL',
						($c['tpch']['queries'][9]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][9]['duration'] : 'NULL',
						($c['tpch']['queries'][10]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][10]['duration'] : 'NULL',
						($c['tpch']['queries'][11]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][11]['duration'] : 'NULL',
						($c['tpch']['queries'][12]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][12]['duration'] : 'NULL',
						($c['tpch']['queries'][13]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][13]['duration'] : 'NULL',
						($c['tpch']['queries'][14]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][14]['duration'] : 'NULL',
						($c['tpch']['queries'][15]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][15]['duration'] : 'NULL',
						($c['tpch']['queries'][16]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][16]['duration'] : 'NULL',
						($c['tpch']['queries'][17]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][17]['duration'] : 'NULL',
						($c['tpch']['queries'][18]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][18]['duration'] : 'NULL',
						($c['tpch']['queries'][19]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][19]['duration'] : 'NULL',
						($c['tpch']['queries'][20]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][20]['duration'] : 'NULL',
						($c['tpch']['queries'][21]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][21]['duration'] : 'NULL',
						($c['tpch']['queries'][22]['duration'] < QUERY_TIMEOUT) ? $c['tpch']['queries'][22]['duration'] : 'NULL',

						$c['tpch']['queries'][1]['hash'],
						$c['tpch']['queries'][2]['hash'],
						$c['tpch']['queries'][3]['hash'],
						$c['tpch']['queries'][4]['hash'],
						$c['tpch']['queries'][5]['hash'],
						$c['tpch']['queries'][6]['hash'],
						$c['tpch']['queries'][7]['hash'],
						$c['tpch']['queries'][8]['hash'],
						$c['tpch']['queries'][9]['hash'],
						$c['tpch']['queries'][10]['hash'],
						$c['tpch']['queries'][11]['hash'],
						$c['tpch']['queries'][12]['hash'],
						$c['tpch']['queries'][13]['hash'],
						$c['tpch']['queries'][14]['hash'],
						$c['tpch']['queries'][15]['hash'],
						$c['tpch']['queries'][16]['hash'],
						$c['tpch']['queries'][17]['hash'],
						$c['tpch']['queries'][18]['hash'],
						$c['tpch']['queries'][19]['hash'],
						$c['tpch']['queries'][20]['hash'],
						$c['tpch']['queries'][21]['hash'],
						$c['tpch']['queries'][22]['hash'],
						$c['tpch']['stats']['hit_ratio']

					) . ";\n");
				}
			}
		}

		fwrite($fd, "COMMIT;\n");
		fclose($fd);

	}

	function load_checkpoints($logfile) {

		$log = file($logfile);
		$checkpoints = array();

		foreach ($log AS $row) {
			$row = trim($row);

			if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2}).([0-9]{3}) CEST [0-9]+ :[a-z0-9]+\.[a-z0-9]+   LOG:  checkpoint starting: (.*)$/', $row, $matches)) {
				$checkpoints[] = array('start' => mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]), 'cause' => $matches[8]);
			} else if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2}).([0-9]{3}) CEST [0-9]+ :[a-z0-9]+\.[a-z0-9]+   LOG:  checkpoint complete: wrote ([0-9]+) buffers \(([0-9]+.[0-9]+)%\); ([0-9]+) transaction log file\(s\) added, ([0-9]+) removed, ([0-9]+) recycled; write=([0-9]+.[0-9]+) s, sync=([0-9]+.[0-9]+) s, total=([0-9]+.[0-9]+) s$/', $row, $matches)) {
				$checkpoints[count($checkpoints)-1]['end'] = mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]);
				$checkpoints[count($checkpoints)-1]['buffers'] = $matches[8];
			}
		}

		return $checkpoints;

	}

	function build_pgbench_plots($dir, $images, &$data) {

		echo "building pgbench plots\n";

		foreach ($data AS $fs => $a) {
			echo "\t",$fs,"\n";
			foreach ($a AS $pgbs => $b) {
				echo "\t\t$pgbs:";
				foreach ($b AS $fsbs => $c) {
					echo " $fsbs";

					$hash = $data[$fs][$pgbs][$fsbs]['hash'];

					$dirA = substr($hash, 0, 2);
					$dirB = substr($hash, 2);

					$checkpoints = load_checkpoints("$dir/$fs/$pgbs/$fsbs/pgbench/pg.log");

					if (! file_exists("$images/$dirA")) {
						mkdir("$images/$dirA");
					}

					mkdir("$images/$dirA/$dirB");

					$log = file("$dir/$fs/$pgbs/$fsbs/pgbench/ro/pgbench.log");
					if (file_exists("$dir/$fs/$pgbs/$fsbs/pgbench/ro/pgbench.warmup.log")) {
						$log = array_merge($log, file("$dir/$fs/$pgbs/$fsbs/pgbench/ro/pgbench.warmup.log"));
					}

					build_pgbench_plots_inner($log, "ro", "$fs-$fsbs-$pgbs", "images/$dirA/$dirB", $checkpoints);

					$log = file("$dir/$fs/$pgbs/$fsbs/pgbench/rw/pgbench.log");
					build_pgbench_plots_inner($log, "rw", "$fs-$fsbs-$pgbs", "images/$dirA/$dirB", $checkpoints);

				}
				echo "\n";
			}
		}

	}

	function prepare_weights($width) {
		$weights = array();
		$weights[$width-1] = 1;

		for ($i = 0; $i < ($width-1); $i++) {
			$weights[$i] = floatval($i+1)/$width;
			$weights[2*$width - 2 - $i] = floatval($i+1)/$width;
		}

		return $weights;
	}

	function compute_averages($data, $width) {

		$weights = prepare_weights($width);

		$averages = array();

		$keys = array_keys($data);
		sort($keys);

		for ($i = 0; $i < count($keys); $i++) {

			$wfrom = 0;
			$wto   = count($weights)-1;

			$from = $i - ($width - 1);
			$to   = $i + ($width - 1);

			if ($from < 0) {
				$wfrom = - $from;
				$from = 0;
			}

			if ($to > count($keys) - 1) {
				$wto = $wto - ($to - (count($keys) - 1));
				$to = count($keys) - 1;
			}

			$val = 0;
			$sum = 0;

			for ($j = $wfrom; $j < $wto; $j++) {
				$sum += $weights[$j];
				$val += $data[$keys[$from + ($j - $wfrom)]] * $weights[$j];
			}

			$averages[$keys[$i]] = round($val / $sum,1);

		}

		return $averages;

	}

	function build_pgbench_plots_inner($log, $label, $filename, $dir, $checkpoints, $length = BENCH_LENGTH) {

		$tps = array();
		$tps2 = array();
		$latency = array();
		$min = time();
		$max = 0;

		$w = 10;

		foreach ($log AS $row) {
			$row = trim($row);
			if ($row != '') {
				$tmp = explode(' ',$row);
				$min = min($min, (int)$tmp[4]);
			}
		}

		$fd = fopen('latency.txt','w');
		foreach ($log AS $row) {
			$row = trim($row);
			if ($row != '') {
				$tmp = explode(' ',$row);
				$time = $tmp[4];
				$lat = $tmp[2];
				if (! isset($tps[$time])) {
					$tps[$time] = 0;
				}
				$tps[$time] += 1;
				if (($time - $min) < $length) {
					fwrite($fd, ($time - $min) . ' ' . ($lat/1000) . "\n");
				}
			}
		}
		fclose($fd);

		$fd = fopen('tps.txt','w');
		$keys = array_keys($tps);
		sort($keys);
		foreach ($keys AS $time) {
			if (($time - $min) < $length) {
				$max = max($max, $tps[$time]);
				fwrite($fd, ($time - $min). ' ' . $tps[$time] . "\n");
			}
		}
		fclose($fd);

		$avgs = compute_averages($tps, $w);

		$fd = fopen('tps2.txt','w');
		$keys = array_keys($avgs);
		sort($keys);
		foreach ($keys AS $time) {
			if (($time - $min) < $length) {
				fwrite($fd, ($time - $min). ' ' . $avgs[$time] . "\n");
			}
		}
		fclose($fd);

		$objects = array();
		$unsets = array();
		foreach ($checkpoints AS $checkpoint) {
			if (($checkpoint['start'] >= $min) && ($checkpoint['start'] <= $min + $length)) {
				$from = round(floatval($checkpoint['start'] - $min) / ($length),2);
				$to = (isset($checkpoint['end'])) ? round(floatval($checkpoint['end'] - $min) / ($length),2) : 1;
$to = ($to > $from) ? $to : $to + 0.005;
$to = min(1, $to);
				$bgcolor = ($checkpoint['cause'] == 'time') ? '#ccccff' : '#ffcccc';
				$bcolor  = ($checkpoint['cause'] == 'time') ? '#7777ff' : '#ff7777';
				$unsets[] = "unset object " . (count($objects)+1);
				$objects[] = "set object " . (count($objects)+1). " rect from graph $from, graph 0 to graph $to, graph 1 fc rgbcolor \"$bgcolor\" fs solid 0.66 border rgb \"$bcolor\" lw 1 behind";
			}
		}

		$max = ceil(floatval($max)/50)*50;

		$script = file_get_contents('tps.template');
		$script = str_replace('%TITLE%', "$filename ($label)", $script);
		$script = str_replace('%WIDTH%', IMAGE_WIDTH, $script);
		$script = str_replace('%HEIGHT%', IMAGE_HEIGHT, $script);
		$script = str_replace('%MAX%', $max, $script);
		$script = str_replace('%FILE%', "$dir/$filename-tps-$label.png", $script);
		$script = str_replace('%OBJECTS%', implode("\n", $objects), $script);
		$script = str_replace('%UNSETS%', implode("\n", $unsets), $script);
		file_put_contents ('tps.script', $script);

		$script = file_get_contents('latency.template');
		$script = str_replace('%TITLE%', "$filename ($label)", $script);
		$script = str_replace('%WIDTH%', IMAGE_WIDTH, $script);
		$script = str_replace('%HEIGHT%', IMAGE_HEIGHT, $script);
		$script = str_replace('%FILE%', "$dir/$filename-latency-$label.png", $script);
		$script = str_replace('%OBJECTS%', implode("\n", $objects), $script);
		file_put_contents ('latency.script', $script);

		$script = file_get_contents('latency-log.template');
		$script = str_replace('%TITLE%', "$filename ($label) log", $script);
		$script = str_replace('%WIDTH%', IMAGE_WIDTH, $script);
		$script = str_replace('%HEIGHT%', IMAGE_HEIGHT, $script);
		$script = str_replace('%FILE%', "$dir/$filename-latency-log-$label.png", $script);
		$script = str_replace('%OBJECTS%', implode("\n", $objects), $script);
		file_put_contents ('latency-log.script', $script);

		system("gnuplot tps.script > /dev/null 2>&1");
		system("gnuplot latency.script > /dev/null 2>&1");
		system("gnuplot latency-log.script > /dev/null 2>&1");

		// unlink("latency.txt");
		// unlink("tps.txt");
		// unlink("tps2.txt");

	}

	/* returns a hash (used to compare multiple plans) */
	function get_plan_hash($file) {

		$plan = file($file);

		$tmp = '';
		foreach ($plan AS $line) {
			$line = preg_replace('/[0-9]/', '', $line);
			$line = preg_replace('/^\s+/', '', $line);
			$line = preg_replace('/\s+$/', '', $line);
			$tmp .= $line;
		}

		return md5($tmp);

	}

	function load_config($filename) {

		$file = file($filename);
		$config = array();
		foreach ($file AS $row) {
			$tmp = explode('=', trim($row));
			$config[$tmp[0]] = $tmp[1];
		}

		return $config;

	}

?>