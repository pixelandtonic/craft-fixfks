<?php

namespace Craft;

// Path to your craft/ folder
$craftPath = '../craft';

// Do this thing
$bootstrapPath = rtrim($craftPath, '/').'/app/bootstrap.php';
if (!file_exists($bootstrapPath))
{
	throw new Exception('Could not locale a Craft bootstrap file. Make sure that `$craftPath` is set correctly.');
}

/** @var $app WebApp */
$app = require($bootstrapPath);

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

	$fks[] = array(
		$tableName,
		'elementId',
		'elements',
		'id',
		'CASCADE',
		null,
		$app->db->getForeignKeyName($tableName, array('elementId')),
	);

	$fks[] = array(
		$tableName,
		'locale',
		'locales',
		'locale',
		'CASCADE',
		'CASCADE',
		$app->db->getForeignKeyName($tableName, array('locale')),
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

$report = array();

foreach ($fks as $fk)
{
	list($tableName, $columns, $refTableName, $refColumns, $onDelete, $onUpdate, $fkName) = $fk;

	// Make sure the table exists
	if (MigrationHelper::getTable($tableName) === null)
	{
		throw new Exception("Table $tableName doesn't exist");
	}

	$columns = explode(',', $columns);
	$refColumns = explode(',', $refColumns);

	if (count($columns) > 1 || count($refColumns) > 1)
	{
		throw new Exception('Foreign keys spanning multiple columns is not supported.');
	}

	$columnName = $columns[0];
	$refColumnName = $refColumns[0];
	$allowNull = ($onDelete == 'SET NULL');

	// Find the invalid values for this FK
	$invalidValues = $app->db->createCommand()
		->selectDistinct("t.$columnName")
		->from("$tableName t")
		->leftJoin("$refTableName r", "t.$columnName = r.$refColumnName")
		->where(['and', "t.$columnName is not null", "r.$refColumnName is null"])
		->queryColumn();

	if ($run)
	{
		// Drop the existing FK if it exists
		// Even if it does, we want to recreate it with the proper ON DELETE and ON UPDATE values
		MigrationHelper::dropForeignKeyIfExists($tableName, $columns);

		// Deal with any invalid values
		if ($invalidValues)
		{
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
		$app->db->createCommand()->addForeignKey($tableName, $columnName, $refTableName, $refColumnName, $onDelete, $onUpdate);
	}

	$report[] = array($tableName, $columnName, $refTableName, $refColumnName, $allowNull, $invalidValues);
}

?>

<html>
<head>
	<title>Restore FKs</title>
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
			FK Restoration Plan
		<? endif ?>
	</h1>

	<? if ($report): ?>
		<p><?=count($report)?> FKs <?=$run?'have been':'will be'?> restored:</p>

		<ul>
			<? foreach ($report as $fk): ?>
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
		<p>No FKs.</p>
	<? endif ?>

	<hr>

	<form method="post">
		<input type="submit" name="run" value="Restore FKs">
		<label><input type="checkbox" name="backupdb" value="1" checked> Backup DB first</label>
	</form>
</body>
</html>
