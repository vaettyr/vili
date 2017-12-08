<?php
include_once("../Table.php");

//create a table
$simple = [
	"table_name" => 'simple_table',
	"table_type" => 'SIMPLE',
	"columns" => [
		"name" => ['type' => "VARCHAR(50)"],
		"number" => ['type' => 'INT(11)'],
		"date" => ['type' => 'DATETIME']
	]		
]; //no primary key
$simpleTable = new Table($simple);
$simpleTable->refresh();
echo "<p>Simple table created successfully</p>";

//insert some records
echo "<p>Insert record: ".json_encode($simpleTable->create(['name'=>'bob', 'number'=>1, 'date'=>'2000-01-01 05:35:28']))."</p>";
sleep(1);
echo "<p>Insert record: ".json_encode($simpleTable->create(['name'=>"jeff", 'number'=>2, 'date'=>'2001-01-01 05:35:28']))."</p>";
sleep(1);
echo "<p>Insert record: ".json_encode($simpleTable->create(['name'=>"moe", 'number'=>3, 'date'=>'2002-01-01 05:35:28']))."</p>";
echo "<p>Records inserted successfully</p>";

//read the data back
echo "<p>Read records not in (1,3) and named jeff or 1: ".json_encode($simpleTable->read([['name' => 'number', 'comparison' => 'NOT IN', 'values' => [1, 3], 'and' => [['name' => 'name', 'value' => 'jeff']]], ['name' => 'number', 'value' => 1]]))."</p>";

//update a record
echo "<p>Update record: ".json_encode($simpleTable->update(['name' => 'bradley'], [['name'=>'number', 'value' => 2]]))."</p>";

//delete some records
$simpleTable->delete([['name'=>'number', 'value' => 1]]);
echo "<p>Record deleted successfully</p>";

//read the data back
echo "<p>".json_encode($simpleTable->read())."</p>";

//change the table structure
$simple = [
		"table_name" => 'simple_table',
		"table_type" => 'SIMPLE',
		"columns" => [
				"name" => ['type' => "VARCHAR(20)"], //change a column
				"number" => ['type' => 'INT(11)'],
				"abbr" => ['type' => 'TEXT'] //remove and add a column
		]
];
$simpleTable = new Table($simple);
$simpleTable->refresh();
echo "<p>Table structure updated successfully</p>";
echo "<p>Update all records: ".json_encode($simpleTable->update(['abbr' => 'sample text description']))."</p>";

//get the last update
echo "<p>last table update: ".$simpleTable->last_change()->format('Y-m-d H:i:s')."</p>";

//destroy the table
$simpleTable->destroy();
echo "<p>Table destroyed successfully</p>";
?>