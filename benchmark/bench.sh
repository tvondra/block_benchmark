#!/bin/sh

# check amount of RAM
mem=`cat /proc/meminfo | grep MemTotal | awk '{print $2}'`

if [ "2051752" != "$mem" ]; then
	echo "ERROR : invalid amount of memory"
	exit;
fi

DEVICE=/dev/sdg1
MOUNTPOINT=/mnt/pgdata
DATADIR=$MOUNTPOINT/pgbench
RESULTS=./results-hdd-3
STOPFILE=bench.stop
READ_AHEAD=8192

# delay between stats collections (iostat, vmstat, ...)
DELAY=15 
GROUP=users

PGB_SCALE=300

# pgbench warmup
PGB_WCLIENTS=10
PGB_WTIME=300 # 5-minute run for warmup

# pgbench benchmark 
PGB_CONFIG=./postgresql-hdd-pgbench.conf
PGB_CLIENTS=10
PGB_TIME_RO=300 # 5-minute read-only benchmark
PGB_TIME_RW=600 # 10-minute read-write benchmark

# DSS queries timeout (5 minutes or something like that)
DSS_CONFIG=./postgresql-hdd-tpch.conf
DSS_TIMEOUT=300 # 5 minutes in seconds

# progress
RUNS_TOTAL=360
RUN=0

# log
LOGFILE=bench.log

# Prepare, run and clean the benchmark.
#
# call:
#    benchmark_run pgdir config logdir
#
# example:
#    benchmark_run /opt/postgresql postgresql.conf /mnt/pgdata/logs 
#
function benchmark_run() {

  local bs=$1
  local logs=$2

  # you'll have to change this
  local pgdir="pg-9.0.4/bs-$bs"

  ##### PGBENCH #####
  benchmark_pgbench_run $bs $logs

  ##### TPC-H #####
  benchmark_tpch_run $bs $logs

}

function benchmark_pgbench_run() {

	local bs=$1
	local logs=$2

	print_log "preparing cluster for pgbench"

	# make the logdir
	mkdir -p $logs/pgbench

	# prepare the environment: initialize and start the server
	$pgdir/bin/pg_ctl -D $DATADIR init > $logs/pgbench/init.log 2> $logs/pgbench/init.err
	cp $PGB_CONFIG $DATADIR/postgresql.conf

	# let's move the pgstat directory to a tmpfs
	rm -Rf $DATADIR/pg_stat_tmp
	ln -s /tmp/pg $DATADIR/pg_stat_tmp
	rm -f $DATADIR/pg_stat_tmp/*

	# start the postgresql db
	$pgdir/bin/pg_ctl -D $DATADIR -l $logs/pgbench/pg.log start > $logs/pgbench/start.log 2> $logs/pgbench/start.err

	# time needed to start the server (not to get connection error)
	sleep 5

	# create user and database
	$pgdir/bin/createdb -h localhost pgbench >> $logs/pgbench/bench.log 2>> $logs/pgbench/bench.err
	$pgdir/bin/createuser -h localhost -I -R -S -D pgbench >> $logs/pgbench/bench.log 2>> $logs/pgbench/bench.err

	print_log "  preparing pgbench data"

	# init the database
	pgbench -h localhost -i -s $PGB_SCALE -U pgbench >> $logs/pgbench/bench.log 2>> $logs/pgbench/bench.err

	print_log "  analyzing"

	$pgdir/bin/psql -h localhost pgbench -c "analyze" >> $logs/pgbench/bench.log 2>> $logs/pgbench/bench.err

	###### READ-ONLY benchmark ######
	print_log "running read-only benchmark"
	benchmark_ro $pgdir $logs/pgbench/ro

	###### READ-WRITE benchmark ######
	print_log "running read-write benchmark"
	benchmark_rw $pgdir $logs/pgbench/rw

	##### cleanup #####

	$pgdir/bin/psql -h localhost postgres -c "select * from pg_stat_activity order by datname" > $logs/pgbench/activity.log 2>&1

	# stop the database
	print_log "cleaning"
	$pgdir/bin/pg_ctl -D $DATADIR -m immediate stop >> $logs/pgbench/bench.log 2>> $logs/pgbench/bench.err

	lsof > $logs/pgbench/lsof.log
	lsof -t >> $logs/pgbench/lsof.log

	ps ax > $logs/pgbench/ps.log

	pids=`lsof -t $DATADIR`
	if [ "$pids" != "" ]; then
		print_log "  killing PIDs: $pids"
		kill -KILL $pids;
	fi;

	sleep 15;

	# remove the datadir
	print_log "  removing $DATADIR"
	rm -Rf $DATADIR
}

function benchmark_tpch_run() {

	local bs=$1
	local logs=$2

	mkdir -p $logs/tpch

	print_log "preparing cluster for TPC-H"

	# prepare the environment: initialize and start the server
	$pgdir/bin/pg_ctl -D $DATADIR init > $logs/tpch/init.log 2>> $logs/tpch/init.err
	cp $DSS_CONFIG $DATADIR/postgresql.conf

	# let's move the pgstat directory to a tmpfs
	rm -Rf $DATADIR/pg_stat_tmp
	ln -s /tmp/pg $DATADIR/pg_stat_tmp
	rm -f $DATADIR/pg_stat_tmp/*

	# start the postgresql db
	$pgdir/bin/pg_ctl -D $DATADIR -l $logs/tpch/pg.log start >> $logs/tpch/start.log 2>> $logs/tpch/start.err

	# time needed to start the server (not to get connection error)
	sleep 5

	# create user and database
	$pgdir/bin/createdb tpch >> $logs/tpch/bench.log 2>> $logs/tpch/bench.err
	$pgdir/bin/createuser -I -R -S -D tpch >> $logs/tpch/bench.log 2>> $logs/tpch/bench.err

	print_log "preparing TPC-H database"

	# create database, populate it with data and set up foreign keys
	$pgdir/bin/psql -h localhost tpch < dss-bench/tpch-create.sql > $logs/tpch/create.log 2> $logs/tpch/create.err

	print_log "  loading data"

	$pgdir/bin/psql -h localhost tpch < dss-bench/tpch-load.sql > $logs/tpch/load.log 2> $logs/tpch/load.err

	print_log "  creating primary keys"

	$pgdir/bin/psql -h localhost tpch < dss-bench/tpch-pkeys.sql > $logs/tpch/pkeys.log 2> $logs/tpch/pkeys.err

	print_log "  creating foreign keys"

	$pgdir/bin/psql -h localhost tpch < dss-bench/tpch-alter.sql > $logs/tpch/alter.log 2> $logs/tpch/alter.err

	print_log "  creating indexes"

	$pgdir/bin/psql -h localhost tpch < dss-bench/tpch-index.sql > $logs/tpch/index.log 2> $logs/tpch/index.err

	print_log "  analyzing"

	$pgdir/bin/psql -h localhost tpch -c "analyze" > $logs/tpch/analyze.log 2> $logs/tpch/analyze.err

	print_log "running TPC-H benchmark"
	benchmark_dss $pgdir $logs/tpch

	$pgdir/bin/psql -h localhost postgres -c "select * from pg_stat_activity order by datname" >> $logs/pgbench/activity.log 2>> $logs/pgbench/activity.err

	# stop the database
	print_log "cleaning"
	$pgdir/bin/pg_ctl -D $DATADIR -m immediate stop >> $logs/tpch/bench.log 2>> $logs/tpch/bench.err

	lsof > $logs/tpch/lsof.log
	lsof -t >> $logs/tpch/lsof.log

	lsof $DATADIR > $logs/tpch/lsof-mount.log
	lsof -t $DATADIR >> $logs/tpch/lsof-mount.log

	pids=`lsof -t $DATADIR`
	if [ "$pids" != "" ]; then
		print_log "  killing PIDs: $pids"
		kill -KILL $pids;
	fi;

	sleep 15;

	# remove the datadir
	print_log "  removing $DATADIR"
	rm -Rf $DATADIR

}

function benchmark_prepare_ro() {

	local pgdir=$1
	local logdir=$2

	mkdir $logdir

	# checkpoint
	print_log "  checkpoint"
	$pgdir/bin/psql postgres -c "checkpoint" > /dev/null 2>&1;

	# drop caches and warm-up the shared buffers
	print_log "  dropping fs page cache"
	sync && sudo ./drop-cache.sh

	# warm up
	print_log "  warming up"
	pgbench -h localhost -S -c $PGB_WCLIENTS -T $PGB_WTIME -l -U pgbench >> $logdir/warmup.log 2>> $logdir/warmup.err

	# move the pgbench log to the proper log directory
	mv pgbench_log.* $logdir/pgbench.warmup.log

}

function benchmark_prepare_rw() {

	local pgdir=$1
	local logdir=$2

	mkdir $logdir

	# checkpoint
	print_log "  checkpoint"
	$pgdir/bin/psql postgres -c "checkpoint" > /dev/null 2>&1;

}

function benchmark_ro() {

	local pgdir=$1
	local logdir=$2

	benchmark_prepare_ro $pgdir $logdir

	# get bgwriter stats
	$pgdir/bin/psql postgres -c "select * from pg_stat_bgwriter" > $logdir/stats-before.log 2>> $logdir/stats-before.err
	$pgdir/bin/psql postgres -c "select * from pg_stat_database where datname = 'pgbench'" >> $logdir/stats-before.log 2>> $logdir/stats-before.err

	vmstat -s > $logdir/vmstat-s-before.log 2>&1
	vmstat -d > $logdir/vmstat-d-before.log 2>&1

	print_log "  running benchmark"

	# run the pgbench read-only-test
	pgbench -h localhost -S -c $PGB_CLIENTS -T $PGB_TIME_RO -l -U pgbench >> $logdir/results.log 2>> $logdir/results.err

	# move the pgbench log to the proper log directory
	mv pgbench_log.* $logdir/pgbench.log

	# collect stats again
	$pgdir/bin/psql postgres -c "select * from pg_stat_bgwriter" > $logdir/stats-after.log 2>> $logdir/stats-after.err
	$pgdir/bin/psql postgres -c "select * from pg_stat_database where datname = 'pgbench'" >> $logdir/stats-after.log 2>> $logdir/stats-after.err

	vmstat -s > $logdir/vmstat-s-after.log 2>&1
	vmstat -d > $logdir/vmstat-d-after.log 2>&1

}

function benchmark_rw() {

	local pgdir=$1
	local logdir=$2

	benchmark_prepare_rw $pgdir $logdir

	# get bgwriter stats
	$pgdir/bin/psql postgres -c "select * from pg_stat_bgwriter" > $logdir/stats-before.log 2>> $logdir/stats-before.err
	$pgdir/bin/psql postgres -c "select * from pg_stat_database where datname = 'pgbench'" >> $logdir/stats-before.log 2>> $logdir/stats-before.err

	vmstat -s > $logdir/vmstat-s-before.log 2>&1
	vmstat -d > $logdir/vmstat-d-before.log 2>&1

	print_log "  running benchmark"

	# run the pgbench read-only-test
	pgbench -h localhost -c $PGB_CLIENTS -T $PGB_TIME_RW -l -U pgbench >> $logdir/results.log 2>> $logdir/results.err

	# move the pgbench latency log to the log directory
	mv pgbench_log.* $logdir/pgbench.log

	# collect stats again
	$pgdir/bin/psql postgres -c "select * from pg_stat_bgwriter" > $logdir/stats-after.log 2>> $logdir/stats-after.err
	$pgdir/bin/psql postgres -c "select * from pg_stat_database where datname = 'pgbench'" >> $logdir/stats-after.log 2>> $logdir/stats-after.err

	vmstat -s > $logdir/vmstat-s-after.log 2>&1
	vmstat -d > $logdir/vmstat-d-after.log 2>&1

}

function benchmark_dss() {

	local pgdir=$1
	local logdir=$2

	mkdir -p $logdir
	mkdir $logdir/vmstat-s $logdir/vmstat-d $logdir/explain $logdir/results $logdir/errors

	# get bgwriter stats
	$pgdir/bin/psql postgres -c "select * from pg_stat_bgwriter" > $logdir/stats-before.log 2>> $logdir/stats-before.err
	$pgdir/bin/psql postgres -c "select * from pg_stat_database where datname = 'tpch'" >> $logdir/stats-before.log 2>> $logdir/stats-before.err

	vmstat -s > $logdir/vmstat-s-before.log 2>&1
	vmstat -d > $logdir/vmstat-d-before.log 2>&1

	print_log "running benchmark"

	for n in `seq 1 22`
	do

		q="dss-bench/queries/$n.sql"
		qe="dss-bench/queries/$n.explain.sql"

		if [ -f "$q" ]; then

			print_log "  running query $n"

			echo "======= query $n =======" >> $logdir/data.log 2>&1;

			# run explain
			$pgdir/bin/psql -h localhost tpch < $qe > $logdir/explain/$n 2>> $logdir/explain.err

			vmstat -s > $logdir/vmstat-s/before-$n.log 2>&1
			vmstat -d > $logdir/vmstat-d/before-$n.log 2>&1

			# run the query on background
			/usr/bin/time -a -f "$n = %e" -o $logdir/results.log $pgdir/bin/psql -h localhost tpch < $q > $logdir/results/$n 2> $logdir/errors/$n &

			# wait up to the given number of seconds, then terminate the query if still running (don't wait for too long)
			for i in `seq 0 $DSS_TIMEOUT`
			do

				# the query is still running - check the time
				if [ -d "/proc/$!" ]; then

					# the time is over, kill it with fire!
					if [ $i -eq $DSS_TIMEOUT ]; then

						print_log "    killing query $n (timeout)"

						# echo "$q : timeout" >> $logdir/results.log
						$pgdir/bin/psql -h localhost postgres -c "SELECT pg_terminate_backend(procpid) FROM pg_stat_activity WHERE datname = 'tpch'" >> $logdir/queries.err 2>&1;

						# time to do a cleanup
						sleep 10;

						# just check how many backends are there (should be 0)
						$pgdir/bin/psql -h localhost postgres -c "SELECT COUNT(*) AS tpch_backends FROM pg_stat_activity WHERE datname = 'tpch'" >> $logdir/queries.err 2>&1;

					else
						# the query is still running and we have time left, sleep another second
						sleep 1;
					fi;

				else

					# the query finished in time, do not wait anymore
					print_log "    query $n finished OK ($i seconds)"
					break;

				fi;

			done;

			vmstat -s > $logdir/vmstat-s/after-$n.log 2>&1
			vmstat -d > $logdir/vmstat-d/after-$n.log 2>&1

		fi;

	done;

	# collect stats again
	$pgdir/bin/psql postgres -c "select * from pg_stat_bgwriter" > $logdir/stats-after.log 2>> $logdir/stats-after.err
	$pgdir/bin/psql postgres -c "select * from pg_stat_database where datname = 'tpch'" >> $logdir/stats-after.log 2>> $logdir/stats-after.err

	vmstat -s > $logdir/vmstat-s-after.log 2>&1
	vmstat -d > $logdir/vmstat-d-after.log 2>&1

}

function stat_collection_start()
{

	local logdir=$1

	# run some basic monitoring tools (iotop, iostat, vmstat)
	iostat -t -x $DEVICE $DELAY >> $RESULTS/iostat.log &
	vmstat $DELAY >> $RESULTS/vmstat.log &

}

function stat_collection_stop()
{

	# wait to get a complete log from iostat etc. and then kill them
	sleep $DELAY

	for p in `jobs -p`; do
		kill $p;
	done;

}

#
# Prepare and mount the filesystem (and device).
#
# call:
#    fs_prepare type block_size mount_options
#
# example:
#    fs_prepare ext3 4096 noatime
#
function fs_prepare()
{
	local mopts=$1

	print_log "mounting device"

	sudo blockdev --setra $READ_AHEAD $DEVICE

	sudo mount -o $mopts $DEVICE $MOUNTPOINT
	sudo chgrp $GROUP $MOUNTPOINT
	sudo chmod g+w $MOUNTPOINT

}

#
# Clean the filesystem (basically just unmount).
#
# call:
#    fs_clean
#
# example:
#    fs_clean
#
function fs_clean()
{

	local logdir=$1

	lsof > $logdir/lsof.log 2>&1

	print_log "  unmounting $DEVICE"

	sudo umount $DEVICE

}

function print_log() {

	local message=$1

	echo `date +"%Y-%m-%d %H:%M:%S"` "["`date +%s`"] : $message" >> $RESULTS/$LOGFILE;

}

function check_stopfile() {

	if [ -e "$STOPFILE" ]; then
		print_log "terminating (stopfile exists)";
		stat_collection_stop;
		exit;
	fi

}

if [ ! -e "$RESULTS" ]; then
	mkdir $RESULTS;
fi

# start statistics collection
stat_collection_start $RESULTS

# ext2 (6 x 3 = 18 runs)
for pgbs in 1 2 4 8 16 32
do
	for fsbs in 1024 2048 4096
	do

		check_stopfile

		RUN=$((RUN+1))

		print_log "===== BENCHMARK: ext2 fs=$fsbs pg=$pgbs ($RUN/$RUNS_TOTAL) ====="

		logdir="$RESULTS/ext2/$pgbs/$fsbs"

		##### CHECK #####
		if [ -d "$logdir" ]; then
			print_log "directory $logdir already exists - skipping"
			continue;
		fi

		print_log "creating filesystem"
		sudo /sbin/mkfs -t ext2 -b $fsbs $DEVICE > mkfs.log 2> mkfs.err

		fs_prepare noatime
		benchmark_run $pgbs $logdir
		fs_clean $logdir

		mv mkfs.log mkfs.err $logdir

	done;

done;

# ext3 (3 x 6 x 3 = 54 runs)
for data in writeback ordered journal
do
	for btype in barrier nobarrier
	do
		for pgbs in 1 2 4 8 16 32
		do
			for fsbs in 1024 2048 4096
			do

				check_stopfile

				RUN=$((RUN+1))

				print_log "===== BENCHMARK: ext3 $data $btype fs=$fsbs pg=$pgbs ($RUN/$RUNS_TOTAL) ====="

				logdir="$RESULTS/ext3-$data-$btype/$pgbs/$fsbs"

				##### CHECK #####
				if [ -d "$logdir" ]; then
					print_log "directory $logdir already exists - skipping"
					continue;
				fi

				print_log "creating filesystem"
				sudo /sbin/mkfs -t ext3 -b $fsbs $DEVICE > mkfs.log 2> mkfs.err

				if [ "$btype" = "barrier" ]; then
					fs_prepare noatime,data=$data,barrier=1
				else
					fs_prepare noatime,data=$data,barrier=0
				fi

				benchmark_run $pgbs $logdir
				fs_clean $logdir

				mv mkfs.log mkfs.err $logdir

			done;
		done;

	done;
done;

# ext4 (3 x 2 x 6 x 3 = 108 runs)
for data in writeback ordered journal
do
	for btype in barrier nobarrier
	do
		for pgbs in 1 2 4 8 16 32
		do
			for fsbs in 1024 2048 4096
			do

				check_stopfile

				RUN=$((RUN+1))

				print_log "===== BENCHMARK: ext4 $data $btype fs=$fsbs pg=$pgbs ($RUN/$RUNS_TOTAL) ====="

				logdir="$RESULTS/ext4-$data-$btype/$pgbs/$fsbs"

				##### CHECK #####
				if [ -d "$logdir" ]; then
					print_log "directory $logdir already exists - skipping"
					continue;
				fi

				print_log "creating filesystem"
				sudo /sbin/mkfs -t ext4 -b $fsbs $DEVICE > mkfs.log 2> mkfs.err

				fs_prepare noatime,data=$data,$btype
				benchmark_run $pgbs $logdir
				fs_clean $logdir

				mv mkfs.log mkfs.err $logdir

			done;
		done;

	done;

done;


# xfs (2 x 6 x 4 = 48 runs)
for btype in barrier nobarrier
do
	for pgbs in 1 2 4 8 16 32
	do
		for fsbs in 512 1024 2048 4096
		do

			check_stopfile

			RUN=$((RUN+1))

			print_log "===== BENCHMARK: xfs $btype fs=$fsbs pg=$pgbs ($RUN/$RUNS_TOTAL) ====="

			logdir="$RESULTS/xfs-$btype/$pgbs/$fsbs"

			##### CHECK #####
			if [ -d "$logdir" ]; then
				print_log "directory $logdir already exists - skipping"
				continue;
			fi

			print_log "creating filesystem"
			sudo /sbin/mkfs -t xfs -f -b size=$fsbs $DEVICE > mkfs.log 2> mkfs.err

			fs_prepare noatime,$btype
			benchmark_run $pgbs $logdir
			fs_clean $logdir

			mv mkfs.log mkfs.err $logdir

		done;
	done;

done;

# jfs (6 runs)
for pgbs in 1 2 4 8 16 32
do
	for fsbs in 4096
	do

		check_stopfile

		RUN=$((RUN+1))

		print_log "===== BENCHMARK: jfs fs=$fsbs pg=$pgbs ($RUN/$RUNS_TOTAL) ====="

		logdir="$RESULTS/jfs/$pgbs/$fsbs"

		##### CHECK #####
		if [ -d "$logdir" ]; then
			print_log "directory $logdir already exists - skipping"
			continue;
		fi

		print_log "creating filesystem"
		sudo /sbin/mkfs.jfs -q $DEVICE > mkfs.log 2> mkfs.err

		fs_prepare noatime
		benchmark_run $pgbs $logdir
		fs_clean $logdir

		mv mkfs.log mkfs.err $logdir
	done;
done;

# reiserfs (2 x 6 x 1 = 12 runs)
for barrier in none flush
do
	for pgbs in 1 2 4 8 16 32
	do
		for fsbs in 4096
		do

			check_stopfile

			RUN=$((RUN+1))

			print_log "===== BENCHMARK: reiserfs $barrier fs=$fsbs pg=$pgbs ($RUN/$RUNS_TOTAL) ====="

			logdir="$RESULTS/reiserfs-$barrier/$pgbs/$fsbs"

			##### CHECK #####
			if [ -d "$logdir" ]; then
				print_log "directory $logdir already exists - skipping"
				continue;
			fi

			print_log "creating filesystem"
			sudo /sbin/mkfs -t reiserfs -q -b $fsbs $DEVICE > mkfs.log 2> mkfs.err

			fs_prepare noatime,barrier=$barrier
			benchmark_run $pgbs $logdir
			fs_clean $logdir

			mv mkfs.log mkfs.err $logdir

		done;
	done;

done;

# btrfs (6 x 4 = 24 runs)
for dtype in datacow nodatacow
do
	for btype in barrier nobarrier
	do
		for pgbs in 1 2 4 8 16 32
		do
			# values below 4096 are illegal, values above cause the umount hang (bug #40602)
			for fsbs in 4096
			do

				check_stopfile

				RUN=$((RUN+1))

				print_log "===== BENCHMARK: btrfs $dtype $btype fs=$fsbs pg=$pgbs ($RUN/$RUNS_TOTAL) ====="

				logdir="$RESULTS/btrfs-$dtype-$btype/$pgbs/$fsbs"

				##### CHECK #####
				if [ -d "$logdir" ]; then
					print_log "directory $logdir already exists - skipping"
					continue;
				fi

				print_log "creating filesystem"
				sudo /sbin/mkfs.btrfs $DEVICE > mkfs.log 2> mkfs.err

				opts="noatime";
				if [ "$btype"  = "nobarrier" ]; then
					opts="$opts,nobarrier"
				fi

				if [ "$dtype" = "nodatacow" ]; then
					opts="$opts,nodatacow"
				fi

				fs_prepare $opts
				benchmark_run $pgbs $logdir
				fs_clean $logdir

				mv mkfs.log mkfs.err $logdir

			done;
		done;

	done;
done;

# nilfs2 (6 x 3 x 2 = 36 runs)
for btype in barrier nobarrier
do
	for pgbs in 1 2 4 8 16 32
	do
		for fsbs in 1024 2048 4096
		do

			check_stopfile

			RUN=$((RUN+1))

			print_log "===== BENCHMARK: nilfs2 $btype fs=$fsbs pg=$pgbs ($RUN/$RUNS_TOTAL) ====="

			logdir="$RESULTS/nilfs2-$btype/$pgbs/$fsbs"

			##### CHECK #####
			if [ -d "$logdir" ]; then
				print_log "directory $logdir already exists - skipping"
				continue;
			fi

			print_log "creating filesystem"
			sudo /sbin/mkfs.nilfs2 -b $fsbs $DEVICE > mkfs.log 2> mkfs.err

			fs_prepare noatime,$btype
			benchmark_run $pgbs $logdir
			fs_clean $logdir

			mv mkfs.log mkfs.err $logdir

		done;
	done;

done;

# stop statistics collection
stat_collection_stop
