-- using 1311891608 as a seed to the RNG


explain select
	sum(l_extendedprice * l_discount) as revenue
from
	lineitem
where
	l_shipdate >= date '1994-01-01'
	and l_shipdate < date '1994-01-01' + interval '1' year
	and l_discount between 0.02 - 0.01 and 0.02 + 0.01
	and l_quantity < 24
LIMIT 1;
