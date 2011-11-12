COPY part FROM '/home/tomas/work/pg-benchmark/dss-bench/part.csv' WITH (FORMAT csv, DELIMITER '|');
COPY region FROM '/home/tomas/work/pg-benchmark/dss-bench/region.csv' WITH (FORMAT csv, DELIMITER '|');
COPY nation FROM '/home/tomas/work/pg-benchmark/dss-bench/nation.csv' WITH (FORMAT csv, DELIMITER '|');
COPY supplier FROM '/home/tomas/work/pg-benchmark/dss-bench/supplier.csv' WITH (FORMAT csv, DELIMITER '|');
COPY customer FROM '/home/tomas/work/pg-benchmark/dss-bench/customer.csv' WITH (FORMAT csv, DELIMITER '|');
COPY partsupp FROM '/home/tomas/work/pg-benchmark/dss-bench/partsupp.csv' WITH (FORMAT csv, DELIMITER '|');
COPY orders FROM '/home/tomas/work/pg-benchmark/dss-bench/orders.csv' WITH (FORMAT csv, DELIMITER '|');
COPY lineitem FROM '/home/tomas/work/pg-benchmark/dss-bench/lineitem.csv' WITH (FORMAT csv, DELIMITER '|');
