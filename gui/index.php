<?php

	require('include/init.php');

	$cols = array(
		'fs',
		'cpu_desc',
		'drive_desc',
		'ram',
		'shared_buffers',
		'work_mem',
		'effective_cache',
		'pg_version',
		'kernel_version'
	);

	$type = (isset($_REQUEST['type']) && ($_REQUEST['type'] == 'tpch')) ? 'tpch' : 'pgbench';
	$sort = (isset($_REQUEST['sort'])) ? max(min((int)$_REQUEST['sort'], count($cols)-1),0) : 0;

	include('include/header.php');
?>
		<script type="text/javascript">
			function selectAll(obj,formName) {
				for (i = 0; i < document.forms[formName].elements.length; i++) {
					if (document.forms[formName].elements[i].type == 'checkbox') {
						document.forms[formName].elements[i].checked=obj.checked;
					}
				}
			}

			function selectConfig(formName, id) {
				for (i = 0; i < document.forms[formName].elements.length; i++) {
					if (document.forms[formName].elements[i].type == 'checkbox') {
						var tmp = document.forms[formName].elements[i].value.split(':');
						if (id == tmp[1]) {
							document.forms[formName].elements[i].checked='checked';
						} else {
							document.forms[formName].elements[i].checked='';
						}
					}
				}
			}

		</script>
<?php

if ($type == 'pgbench') {

?>

		<div class="tabs">
			<a class="tab active" href="?type=pgbench">pgbench</a>
			<a class="tab" href="?type=tpch">tpch</a>
		</div>

		<form action="compare-pgbench.php" name="pgbenchForm">
			<table>
				<thead>
					<tr>
						<th class="checkbox"><input type="checkbox" onchange="selectAll(this,'pgbenchForm')"/></th>
						<th><a href="?sort=0">file system</a></th>
						<th><a href="?sort=1">CPU</a></th>
						<th><a href="?sort=2">drive</a></th>
						<th><a href="?sort=3">RAM</a></th>
						<th><a href="?sort=4">buffers</a></th>
						<th><a href="?sort=5">work mem</a></th>
						<th><a href="?sort=6">fs cache</a></th>
						<th><a href="?sort=7">version</a></th>
						<th><a href="?sort=8">kernel</a></th>
					</tr>
				</thead>
				<tbody>

<?php

	$result = pg_query(
					'SELECT DISTINCT config_id, title, fs, cpu_desc, cpu_cores, read_ahead, ram, drive_desc, drive_rpm, ' .
					'kernel_version, pg_version, shared_buffers, effective_cache, work_mem, maint_mem, checkp_segments, ' .
					'checkp_target FROM results_pgbench JOIN config ON (results_pgbench.config_id = config.id) ORDER BY ' . $cols[$sort]
				);

	while ($row = pg_fetch_assoc($result)) {
		echo '<tr>',"\n";
		echo '<td><input type="checkbox" name="type[]" value="',$row['fs'],':',$row['config_id'],'" /></td>',"\n";
		echo '<td><a href="pgbench.php?type=',$row['fs'],':',$row['config_id'],'">',$row['fs'],'</a></td>',"\n";
		echo '<td class="cpu">',$row['cpu_desc'],'</td>',"\n";
		echo '<td class="drive"><a href="javascript:selectConfig(\'pgbenchForm\',',$row['config_id'],')">',$row['drive_desc'],'</a></td>',"\n";
		echo '<td class="ram">',$row['ram'],' MB</td>',"\n";
		echo '<td class="buffers">',$row['shared_buffers'],' MB</td>',"\n";
		echo '<td class="workmem">',$row['work_mem'],' MB</td>',"\n";
		echo '<td class="fscache">',$row['effective_cache'],' MB</td>',"\n";
		echo '<td class="version">',$row['pg_version'],'</td>',"\n";
		echo '<td class="kernel">',$row['kernel_version'],'</td>',"\n";
		echo '</tr>',"\n";
	}

	pg_free_result($result);

?>
				</tbody>
			</table>

			<input type="submit" value="compare" onclick="document.forms['pgbenchForm'].action='compare-pgbench.php'" />
			<input type="submit" value="csv" onclick="document.forms['pgbenchForm'].action='csv-pgbench.php'" />
			<input type="reset" value="reset" />

		</form>

<?php } else if ($type =='tpch') { ?>

		<div class="tabs">
			<a class="tab" href="?type=pgbench">pgbench</a>
			<a class="tab active" href="?type=tpch">tpch</a>
		</div>

		<form action="compare-tpch.php" name="tpchForm">
			<table>
				<thead>
					<tr>
						<th class="checkbox"><input type="checkbox" onchange="selectAll(this,'tpchForm')"/></th>
						<th><a href="?sort=0">file system</a></th>
						<th><a href="?sort=1">CPU</a></th>
						<th><a href="?sort=2">drive</a></th>
						<th><a href="?sort=3">RAM</a></th>
						<th><a href="?sort=4">buffers</a></th>
						<th><a href="?sort=5">work mem</a></th>
						<th><a href="?sort=6">fs cache</a></th>
						<th><a href="?sort=7">version</a></th>
						<th><a href="?sort=8">kernel</a></th>
					</tr>
				</thead>
				</tbody>

<?php

	$result = pg_query(
					'SELECT DISTINCT config_id, title, fs, cpu_desc, cpu_cores, read_ahead, ram, drive_desc, drive_rpm, ' .
					'kernel_version, pg_version, shared_buffers, effective_cache, work_mem, maint_mem, checkp_segments, ' .
					'checkp_target FROM results_tpch JOIN config ON (results_tpch.config_id = config.id) ORDER BY ' . $cols[$sort]
				);

	while ($row = pg_fetch_assoc($result)) {
		echo '<tr>',"\n";
		echo '<td><input type="checkbox" name="type[]" value="',$row['fs'],':',$row['config_id'],'" /></td>',"\n";
		echo '<td><a href="tpch.php?type=',$row['fs'],':',$row['config_id'],'">',$row['fs'],'</a></td>',"\n";
		echo '<td class="cpu">',$row['cpu_desc'],'</td>',"\n";
		echo '<td class="drive"><a href="javascript:selectConfig(\'tpchForm\',',$row['config_id'],')">',$row['drive_desc'],'</a></td>',"\n";
		echo '<td class="ram">',$row['ram'],' MB</td>',"\n";
		echo '<td class="buffers">',$row['shared_buffers'],' MB</td>',"\n";
		echo '<td class="workmem">',$row['work_mem'],' MB</td>',"\n";
		echo '<td class="fscache">',$row['effective_cache'],' MB</td>',"\n";
		echo '<td class="version">',$row['pg_version'],'</td>',"\n";
		echo '<td class="kernel">',$row['kernel_version'],'</td>',"\n";
		echo '</tr>',"\n";
	}

	pg_free_result($result);

?>
				</tbody>
			</table>

			<input type="submit" value="compare" onclick="document.forms['tpchForm'].action='compare-tpch.php'" />
			<input type="submit" value="csv" onclick="document.forms['tpchForm'].action='csv-tpch.php'" />
			<input type="reset" value="reset" />

		</form>
<?php
	}

	include('include/footer.php');

?>