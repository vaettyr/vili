<?php
include_once("../Table.php");
include_once("../Query.php");

//create a table
$versioned = [
		"table_name" => 'versioned_table',
		"table_type" => 'VERSIONED',
		"columns" => [
				"type" => ['type' => "VARCHAR(50)"],
		        "description" => ['type' => 'TEXT'],
				"date" => ['type' => 'DATETIME'],
				"value" => ['type' => 'DECIMAL(12,2)']
		]
]; //composite primary key
$versionedTable = new Table($versioned);
$versionedTable->refresh();
echo "<p>Versioned table created successfully</p>";

//insert some records
echo "<p>Insert record: ".json_encode($versionedTable->create(['type'=>'Monetary Itemized', 'value'=>100.00, 'date'=>'2017-05-15 12:00:00']))."</p>";
sleep(1);
echo "<p>Insert record: ".json_encode($versionedTable->create(['type'=>"Monetary Unitemized", 'value'=>2.50, 'date'=>'2017-06-23 12:00:00']))."</p>";
sleep(1);
echo "<p>Insert record: ".json_encode($versionedTable->create(['type'=>"Non-Monetary Itemized", 'value'=>15000, 'date'=>'2017-08-02 03:30:30']))."</p>";
sleep(1);
echo "<p>Insert record: ".json_encode($versionedTable->create(['type'=>"Non-Monetary Unitemized", 'value'=>0.75, 'date'=>'2017-09-02 03:30:30']))."</p>";
echo "<p>Records inserted successfully</p>";

//read the data back
echo "<p>Read records value greater than 5: ".json_encode($versionedTable->read(new Query([['name' => 'value', 'comparison' => '>', 'value' => 5]])))."</p>";

//update a record
echo "<p>Update record: ".json_encode($versionedTable->update(['description' => 'Records which are in-Progress'], new Query(['ID' => ["=" => 3]])))."</p>";

//lock a record
echo "<p>Lock record: ".json_encode($versionedTable->update(['LOCKED' => 1], new Query(['AND' => [["ID" => ["=" => 2]], ["VERSION" => ["=" => 1]]]])))."</p>";

//version a record
echo "<p>Create a new version of a record: ".json_encode($versionedTable->update(['ID' => 2, 'type' => 'Monetary Unitemized', 'value' => 250.50, 'date' => '2017-06-22 05:55:55'], new Query(['AND' => [["ID" => ["=" => 2]], ["VERSION" => ["=" => 1]]]])))."</p>";

//delete some records
$versionedTable->delete(new Query(["ID" => ["=" => 3]]));
echo "<p>Record deleted successfully</p>";

//read the data back
echo "<p>".json_encode($versionedTable->read())."</p>";

//change the table structure
$versioned = [
		"table_name" => 'versioned_table',
		"table_type" => 'VERSIONED',
		"columns" => [
				"type" => ['type' => "VARCHAR(150)"],
				"description" => ['type' => 'TEXT'],
				"value" => ['type' => 'DECIMAL(12,2)']
		]
]; //auto increment primary key
$versionedTable = new Table($versioned);
$versionedTable->refresh();
echo "<p>Table structure updated successfully</p>";

echo "<p>Update all records: ".json_encode($versionedTable->update(['description' => 'herpin mah derps']))."</p>";

echo "<p>last table update: ".$versionedTable->last_change()['last_change']->format('Y-m-d H:i:s')."</p>";

//destroy the table
$versionedTable->destroy();
echo "<p>Table destroyed successfully</p>";
?>