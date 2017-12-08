<?php
 include_once("../Service.php");
 
 $warriors = new Service("warriors.json");
 $warriors->go();
?>