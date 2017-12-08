<?php
include_once("../Service.php");

$books = new Service("books.json");
$books->register(new Handler("POST", function() use ($books) {
	$books->table->proc("ALTER TABLE books AUTO_INCREMENT=1;");
}, ['reset']));
$books->go();
?>