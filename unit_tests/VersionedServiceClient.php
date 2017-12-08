<?php
	include_once("../Table.php");
	$config = json_decode(file_get_contents("characters.json"), true);
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
				wr.open('GET', 'VersionedService.php');
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

			var readTest = function(after) {
				el.innerHTML += "<h2>get records with ID greater than 1 and Version greater than 1:<h2>";
				var wr = new XMLHttpRequest();
				wr.open('GET', 'VersionedService.php?characters={"and":[{"ID":{">":1}},{"VERSION":{">":1}}]}');
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

				var count = 0;
				function insert(item) {
					var wi = new XMLHttpRequest();
					wi.open('POST', 'VersionedService.php');
					wi.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
					wi.onload = function() {
						el.innerHTML += "<p>Response Code: " + wi.status + "</p>";
						el.innerHTML += "<p>" + wi.responseText + "</p>";	
						count++;
						if(count > 2 && after) {
							var next = after.shift(); 
							next(after);
						}
					}
					wi.send(JSON.stringify(item));
				}

				insert({name: 'Rick', level: 10, data: "{'test_value':'test data'}"});
				insert({name: 'Morty', level: 3, data: "{'catchphrase':'aw geez'}"});
				insert({name: 'Summer', level: 7, data: "{'attr':'teen'}"});
			}

			var updateTest = function(after) {
				el.innerHTML += "<h2>Update records in the table:</h2>";

				var wu = new XMLHttpRequest();
				wu.open('POST', 'VersionedService.php?ID=1&VERSION=1');
				wu.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
				wu.onload = function() {
					el.innerHTML += "<p>Response Code: " + wu.status + "</p>";
					var items = JSON.parse(wu.responseText);
					for(var i = 0; i<items.length; i++) {
						el.innerHTML += "<p>"+JSON.stringify(items[i])+"</p>";
					}	
					if(after) {var next = after.shift(); next(after);}
				}
				wu.send(JSON.stringify({data:"{'memory_level':'20'}"}));
			}

			var versionTest = function(after) {
				el.innerHTML += "<h2>Create a new versions of an existing record:</h2>";

				var count = 0;
				function insert(item) {
					var wi = new XMLHttpRequest();
					wi.open('POST', 'VersionedService.php?ID=2');
					wi.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
					wi.onload = function() {
						el.innerHTML += "<p>Response Code: " + wi.status + "</p>";
						el.innerHTML += "<p>" + wi.responseText + "</p>";	
						count++;
						if(count > 2 && after) {
							var next = after.shift(); 
							next(after);
						}
					}
					wi.send(JSON.stringify(item));
				}

				insert({ID: 2, name: 'Morty', level: 4, data: "{'catchphrase':'aw geez'}"});
				insert({ID: 2, name: 'Mortimer', level: 4, data: "{'catchphrase':'aw geez','age':'10'}"});
				insert({ID: 2, name: 'Mortimer', level: 5, data: "{'catchphrase':'aw geez'}"});
			}
			
			var deleteTest = function(after) {
				el.innerHTML += "<h2>Delete all records in the table:</h2>";
				
				var wd = new XMLHttpRequest();
				wd.open('DELETE', 'VersionedService.php');
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
				wd.open('GET', 'VersionedService.php?last_change');
				wd.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
				wd.onload = function() {
					el.innerHTML += "<p>Response Code: " + wd.status + "</p>";
					el.innerHTML += "<p>" + wd.responseText + "</p>";	
					if(after) {var next = after.shift(); next(after);}
				}
				wd.send();
			}
			
			var exec = [lastChange, readAll, updateTest, readAll, lastChange, versionTest, readTest, deleteTest];
			insertTest(exec);
			//readTest();
		</script>
	</body>
</html>