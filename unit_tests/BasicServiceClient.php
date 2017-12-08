<?php
	include_once("../Table.php");
	$config = json_decode(file_get_contents("books.json"), true);
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
				wr.open('GET', 'BasicService.php');
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
				el.innerHTML += "<h2>get records with ID greater than 1 that have been read:<h2>";
				var wr = new XMLHttpRequest();
				wr.open('GET', 'BasicService.php?ID={"comparison":">","value":1,"and":[{"has_read":true}]}');
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
					wi.open('POST', 'BasicService.php');
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

				insert({title: 'One fish two fish', author: 'Dr Suess', has_read: true});
				insert({title: 'Pride and Prejudice', author: 'Jane Austen', has_read: false});
				insert({title: 'Garfield pigs out', author: 'Dave Thomas', has_read: false});			
			}

			var updateTest = function(after) {
				el.innerHTML += "<h2>Update records in the table:</h2>";

				var wu = new XMLHttpRequest();
				wu.open('POST', 'BasicService.php?ID=2');
				wu.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
				wu.onload = function() {
					el.innerHTML += "<p>Response Code: " + wu.status + "</p>";
					var items = JSON.parse(wu.responseText);
					for(var i = 0; i<items.length; i++) {
						el.innerHTML += "<p>"+JSON.stringify(items[i])+"</p>";
					}	
					if(after) {var next = after.shift(); next(after);}
				}
				wu.send(JSON.stringify({has_read:true}));
			}

			var deleteTest = function(after) {
				el.innerHTML += "<h2>Delete all records in the table:</h2>";
				
				var wd = new XMLHttpRequest();
				wd.open('DELETE', 'BasicService.php');
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
				wd.open('GET', 'BasicService.php?last_change');
				wd.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
				wd.onload = function() {
					el.innerHTML += "<p>Response Code: " + wd.status + "</p>";
					el.innerHTML += "<p>" + wd.responseText + "</p>";	
					if(after) {var next = after.shift(); next(after);}
				}
				wd.send();
			}

			var reset = function(after) {
				el.innerHTML += "<h2>Reset auto-increment counter on table:</h2>";
				
				var wd = new XMLHttpRequest();
				wd.open('POST', 'BasicService.php?reset');
				wd.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
				wd.onload = function() {
					el.innerHTML += "<p>Response Code: " + wd.status + "</p>";
					el.innerHTML += "<p>" + wd.responseText + "</p>";	
					if(after) {var next = after.shift(); next(after);}
				}
				wd.send();
			}
			
			var exec = [lastChange, readAll, updateTest, readAll, lastChange, readTest, deleteTest, reset];
			insertTest(exec);
			//readTest();
		</script>
	</body>
</html>