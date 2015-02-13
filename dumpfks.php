<?php

namespace Craft;

// Path to your craft/ folder
$craftPath = '../craft';

// Have Craft use the 'fresh' environment configs
define('CRAFT_ENVIRONMENT', 'fresh');

// Do this thing
$bootstrapPath = rtrim($craftPath, '/').'/app/bootstrap.php';
if (!file_exists($bootstrapPath))
{
	throw new Exception('Could not locale a Craft bootstrap file. Make sure that `$craftPath` is set correctly.');
}

/** @var $app WebApp */
$app = require($bootstrapPath);
$database = $app->config->get('database', ConfigFile::Db);
$tablePrefix = $app->config->get('tablePrefix', ConfigFile::Db);
$version = $app->getVersion().'.'.$app->getBuild();

if ($tablePrefix)
{
	throw new Exception('This tool requires Craft to be configured without a DB table prefix.');
}

$run = !empty($_POST['run']);

if ($run)
{
	$fks = array();

	$tables = MigrationHelper::getTables();

	foreach ($tables as $tableName => $table)
	{
		if ($table->fks)
		{
			foreach ($table->fks as $fk)
			{
				$fks[] = array(
					$fk->table->name,
					implode(',', $fk->columns),
					$fk->refTable,
					implode(',', $fk->refColumns),
					$fk->onDelete,
					$fk->onUpdate,
					$fk->name,
				);
			}
		}
	}

	$output = JsonHelper::encode($fks);

	if (!file_exists('fks'))
	{
		mkdir('fks');
	}

	file_put_contents('fkdumps/'.$version.'.json', $output);
}

?>

<html>
<head>
	<title>Dump FKs</title>
	<style type="text/css">
		body { font-family: sans-serif; font-size: 13px; line-height: 1.4; }
		hr, form { margin: 40px 0; }
		form input[type='submit'] { font-size: large; }
	</style>
</head>
<body>
	<? if ($run): ?>
		<p>Done.</p>
		<hr>
	<? endif ?>

	<h1>Dump FKs</h1>

	<p>Make sure that your <code>`<?=$database?>`</code> database has a fresh Craft <?=$version?> install.</p>

	<form method="post">
		<input type="submit" name="run" value="Dump FKs for Craft <?=$version?>">
	</form>
</body>
</html>
