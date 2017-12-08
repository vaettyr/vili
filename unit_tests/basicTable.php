<?php
include_once("../Table.php");

//create a table
$basic = [
		"table_name" => 'basic_table',
		"table_type" => 'BASIC',
		"columns" => [
				"name" => ['type' => "VARCHAR(50)"],
				"description" => ['type' => 'TEXT'],
				"value" => ['type' => 'INT(11)']
		]
]; //auto increment primary key
$basicTable = new Table($basic);
$basicTable->refresh();
echo "<p>Basic table created successfully</p>";

//insert some records
echo "<p>Insert record: ".json_encode($basicTable->create(['name'=>'Filed', 'value'=>1, 'description'=>'Records which have been filed to state.']))."</p>";
sleep(1);
echo "<p>Insert record: ".json_encode($basicTable->create(['name'=>"Unfiled", 'value'=>2, 'description'=>'Assigned but unfiled records.']))."</p>";
sleep(1);
echo "<p>Insert record: ".json_encode($basicTable->create(['name'=>"Pending", 'value'=>3, 'description'=>'Records which have been updated.']))."</p>";
echo "<p>Records inserted successfully</p>";

//read the data back
echo "<p>Read records greater than 1: ".json_encode($basicTable->read([['name' => 'value', 'comparison' => '>', 'value' => 1]]))."</p>";

//update a record
echo "<p>Update record: ".json_encode($basicTable->update(['description' => 'Records which are in-Progress'], [['name'=>'ID', 'value' => 3]]))."</p>";

//delete some records
$basicTable->delete([['name'=>'ID', 'value' => 3]]);
echo "<p>Record deleted successfully</p>";

//read the data back
echo "<p>".json_encode($basicTable->read())."</p>";

//change the table structure
$basic = [
		"table_name" => 'basic_table',
		"table_type" => 'BASIC',
		"columns" => [
				"name" => ['type' => "VARCHAR(150)"],
				"description" => ['type' => 'TEXT'],
				"birthday" => ['type' => 'DATETIME']
		]
]; //auto increment primary key
$basicTable = new Table($basic);
$basicTable->refresh();
echo "<p>Table structure updated successfully</p>";

echo "<p>Update all records: ".json_encode($basicTable->update(['birthday' => '1970-03-01 12:00:00']))."</p>";

echo "<p>last table update: ".$simpleTable->last_change()->format('Y-m-d H:i:s')."</p>";

//destroy the table
$basicTable->destroy();
echo "<p>Table destroyed successfully</p>";
?>