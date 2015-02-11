<?php

namespace Craft;

/** @var $app WebApp */
$app = require('craft/app/bootstrap.php');

// Make sure that Craft's app folder and DB are compatible
if (CRAFT_SCHEMA_VERSION !== $app->getSchemaVersion())
{
	throw new Exception('Invalid schema version');
}

// Make sure we have a FK dump from the same Craft version
$version = $app->getVersion().'.'.$app->getBuild();
$fkDumpFile = "fkdumps/$version.json";

if (!file_exists($fkDumpFile))
{
	throw new Exception('No FK dump file exists');
}

// Load the FK dump
$fksJson = file_get_contents($fkDumpFile);
$fks = JsonHelper::decode($fksJson);

// Are there any Matrix fields?
$matrixFields = $app->db->createCommand()
	->from('fields')
	->where('type = :type', array(':type' => 'Matrix'))
	->queryAll();

foreach ($matrixFields as $result)
{
	if ($result['settings'])
	{
		$result['settings'] = JsonHelper::decode($result['settings']);
	}

	$field = new FieldModel($result);
	$tableName = $app->matrix->getContentTableName($field);

	$fks[$tableName] = array(
		'elementId' => array('elements', 'id', false),
		'locale' => array('locales', 'locale', false),
	);
}

// What are we doing?
$run = !empty($_POST['run']);
$backupDb = !empty($_POST['backupdb']);

if ($backupDb)
{
	$path = $app->db->backup(false);

	if ($path === false)
	{
		throw new Exception('The DB backup failed');
	}
}

// Find (and possibly restore) the missing FKs
$missingFks = array();
$tablePrefix = $app->db->tablePrefix;

/** @var $tables \CDbTableSchema[] */
$tables = $app->db->getSchema()->getTables();

foreach ($fks as $tableName => $tableFks)
{
	$rawTableName = $tablePrefix.$tableName;

	if (!isset($tables[$rawTableName]))
	{
		throw new Exception("Table $rawTableName doesn't exist");
	}

	foreach ($tableFks as $columnName => $fk)
	{
		if (!isset($tables[$rawTableName]->foreignKeys[$columnName]))
		{
			list($refTableName, $refColumnName, $allowNull) = $fk;

			// Find the invalid values for this FK
			$invalidValues = $app->db->createCommand()
				->selectDistinct("t.$columnName")
				->from("$tableName t")
				->leftJoin("$refTableName r", "t.$columnName = r.$refColumnName")
				->where(array('and', "t.$columnName is not null", "r.$refColumnName is null"))
				->queryColumn();

			if ($run)
			{
				if ($invalidValues)
				{
					// Deal with them
					$condition = array('in', $columnName, $invalidValues);

					if ($allowNull)
					{
						$app->db->createCommand()->update($tableName, array($columnName => null), $condition, array(), false);
					}
					else
					{
						$app->db->createCommand()->delete($tableName, $condition);
					}
				}

				// Add the FK
				$app->db->createCommand()->addForeignKey($tableName, $columnName, $refTableName, $refColumnName);
			}

			$missingFks[] = array($tableName, $columnName, $refTableName, $refColumnName, $allowNull, $invalidValues);
		}
	}
}

?>

<html>
<head>
	<title>Restore Missing FKs</title>
	<style type="text/css">
		body { font-family: sans-serif; font-size: 13px; line-height: 1.4; }
		li p { margin-left: 40px; }
		hr, form { margin: 40px 0; }
		form input[type='submit'] { font-size: large; }
	</style>
</head>
<body>
	<h1>
		<? if ($run): ?>
			FK Restoration Report
		<? else: ?>
			Missing FK Report
		<? endif ?>
	</h1>

	<? if ($missingFks): ?>
		<p><?=$run?'Restored':'Found'?> <?=count($missingFks)?> missing FKs:</p>

		<ul>
			<? foreach ($missingFks as $fk): ?>
				<li><code>`<?=$fk[0]?>`.`<?=$fk[1]?>`</code> &rarr; <code>`<?=$fk[2]?>`.`<?=$fk[3]?>`</code>
					<? if ($fk[5]): ?>
						<p>
							<strong><?=count($fk[5])?> invalid values
								(<?=$fk[4]?'set null':'delete'?>):
							</strong>
							<?=implode(', ', $fk[5])?>
						</p>
					<? endif ?>
				</li>
			<? endforeach ?>
		</ul>
	<? else: ?>
		<p>No missing FKs.</p>
	<? endif ?>

	<hr>

	<form method="post">
		<input type="submit" name="run" value="Restore missing FKs">
		<label><input type="checkbox" name="backupdb" value="1" checked> Backup DB first</label>
	</form>
</body>
</html>
