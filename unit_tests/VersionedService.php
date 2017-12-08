<?php
include_once("../Service.php");

$chars = new Service("characters.json");
$chars->go();
?>