<?php
	include_once("../Table.php");
	$config = json_decode(file_get_contents("warriors.json"), true);
	$table = new Table($config);
	$table->refresh();
?>
<html>
	<head></head>
	<body>
		<div id="output"></div>
		<script type="text/javascript">
			var el = document.getElementById('output');			

			
			var readAll = function(after) {
				el.innerHTML += "<h2>get all records in table:<h2>";
				
				var wr = new XMLHttpRequest();
				wr.open('GET', 'SimpleService.php');
				wr.onload = function() {
					el.innerHTML += "<p>Response Code: " + wr.status + "</p>";
					var items = JSON.parse(wr.responseText);
					for(var i = 0; i<items.length; i++) {
						el.innerHTML += "<p>"+JSON.stringify(items[i])+"</p>";
					}		
					if(after) {var next = after.shift(); next(after);}	
				}
				wr.send();
			}
			
			var insertTest = function (after) {
				el.innerHTML += "<h2>insert some new records into the table:</h2>";

				function insert(item) {
					var wi = new XMLHttpRequest();
					wi.open('POST', 'SimpleService.php');
					wi.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
					wi.onload = function() {
						el.innerHTML += "<p>Response Code: " + wi.status + "</p>";
						el.innerHTML += "<p>" + wi.responseText + "</p>";	
					}
					wi.send(JSON.stringify(item));
				}

				insert({name: 'Eric the red', age: 20});
				setTimeout(function() {insert({name: 'Jakob the blue', age: 30});}, 1000);
				setTimeout(function() {insert({name: 'Olaf the ripe', age: 55});}, 2000);
				if(after) {
					setTimeout(function() {var next = after.shift(); next(after);}, 3000);
				}				
			}

			var updateTest = function(after) {
				el.innerHTML += "<h2>Update records in the table:</h2>";

				var wu = new XMLHttpRequest();
				wu.open('POST', 'SimpleService.php?age=20');
				wu.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
				wu.onload = function() {
					el.innerHTML += "<p>Response Code: " + wu.status + "</p>";
					var items = JSON.parse(wu.responseText);
					for(var i = 0; i<items.length; i++) {
						el.innerHTML += "<p>"+JSON.stringify(items[i])+"</p>";
					}	
					if(after) {var next = after.shift(); next(after);}
				}
				wu.send(JSON.stringify({name:"Eric the updated", age: 45}));
			}

			var deleteTest = function(after) {
				el.innerHTML += "<h2>Delete all records in the table:</h2>";
				
				var wd = new XMLHttpRequest();
				wd.open('DELETE', 'SimpleService.php');
				wd.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
				wd.onload = function() {
					el.innerHTML += "<p>Response Code: " + wd.status + "</p>";
					el.innerHTML += "<p>" + wd.responseText + "</p>";	
					if(after) {var next = after.shift(); next(after);}
				}
				wd.send();
			}

			var lastChange = function(after) {
				el.innerHTML += "<h2>Last update to the table:</h2>";
				
				var wd = new XMLHttpRequest();
				wd.open('GET', 'SimpleService.php?last_change');
				wd.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
				wd.onload = function() {
					el.innerHTML += "<p>Response Code: " + wd.status + "</p>";
					el.innerHTML += "<p>" + wd.responseText + "</p>";	
					if(after) {var next = after.shift(); next(after);}
				}
				wd.send();
			}

			var exec = [lastChange, readAll, updateTest, readAll, lastChange, deleteTest];
			insertTest(exec);
		</script>
	</body>
</html>