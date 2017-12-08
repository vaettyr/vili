<?php
include_once("../Table.php");
//simple
$table = [
		"table_name" => 'derp',
		"table_type" => 'SIMPLE',
		"columns" => [
				"name" => ['type' => "VARCHAR(50)"],
				"number" => ['type' => 'INT(11)']
		]
]; 
$testTable = new Table($table);
$testTable->refresh();
echo "<p>Simple table created successfully</p>";

//put a couple of records in here, just for the hell of it
echo "<p>Insert record: ".json_encode($testTable->create(['name'=>'Alpha', 'number'=>1]))."</p>";
sleep(1);
echo "<p>Insert record: ".json_encode($testTable->create(['name'=>"Beta", 'number'=>2]))."</p>";
echo "<p>Records inserted successfully</p>";

//simiple to basic
$table['table_type'] = 'BASIC';
$testTable = new Table($table);
$testTable->refresh();
echo "<p>".json_encode($testTable->read())."</p>";
echo "<p>Table type converted to Basic.</p>";

//basic to versioned
$table['table_type'] = 'VERSIONED';
$testTable = new Table($table);
$testTable->refresh();
echo "<p>".json_encode($testTable->read())."</p>";
echo "<p>Table type converted to Versioned.</p>";

//versioned to basic
$table['table_type'] = 'BASIC';
$testTable = new Table($table);
$testTable->refresh();
echo "<p>".json_encode($testTable->read())."</p>";
echo "<p>Table type converted to Basic.</p>";

//basic to simple
$table['table_type'] = 'SIMPLE';
$testTable = new Table($table);
$testTable->refresh();
echo "<p>".json_encode($testTable->read())."</p>";
echo "<p>Table type converted to Simple.</p>";

$testTable->destroy();
echo "<p>Table destroyed</p>";
?>