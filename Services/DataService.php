<?php 
	include_once("../UberService.php");
	include_once("./Helper.php");
	include_once("/DataSource/DataSource.php");
	
	class DataService extends UberService{
		
	    private static $table_dir = "/Tables/";
	    function __construct() {
			$query = $_SERVER['QUERY_STRING'];
			parse_str($query, $qarray);	
			$open_tables = json_decode(file_get_contents("./Permission_Lookup.json"), true);
			
			parent::__construct("Authorization", function($token, $claim=null) {
				return $this->permissionFunction($token, $claim);
			}, function($flag, $query, $config) use ($open_tables) {
				return $this->claimFunction($flag, $query, $config, $open_tables);
			});
			
			$this->register(new Handler("GET", function() use ($qarray) {
				$column = $qarray['column'];
				$proc = "SELECT DISTINCT " .$column." FROM ".$this->table->getName()." WHERE ".$column." <> '' AND ".$column." IS NOT NULL;";
				$result = $this->table->proc($proc);
				//process this down to a simple array;
				$tags = array();
				foreach($result as $item) {
					$tags[] = $item[$column];
				}
				$response = new Response(200, ["data" => $tags]);
				$response->send();
			}, ["column"]));
			
			$this->register(new Handler("DELETE", function($auth) {				
				//get references
				$references = [];
				if(isset($this->config['references'])) {
					$references = $this->config['references'];
				}
				$referenced = [];				
				$children = [];
				if(isset($this->config['children'])) {
					$children = $this->config['children'];
				}
				$dependents = [];
				//don't do this for simple, only basic, maybe versioned
				$refquery = [$this->table->getName() => "ID"];
				$childquery = $refquery;
				if(strtoupper($this->config['table_type']) == 'VERSIONED') {
					$childquery = ["AND" => [$refquery, [$this->table->getName()."_VERSION" => "VERSION"]]];
				} 
				if(strtoupper($this->config['table_type']) != 'SIMPLE') {
					if(count($references) > 0) {
						foreach($references as $reference) {
							$this->recursiveJoin($this->table, $reference, $this->config['table_type'], 'references', $referenced, $this->query);
						}
					}
					if(count($children) > 0) {
						foreach($children as $child) {
							$this->recursiveJoin($this->table, $child, $this->config['table_type'], 'children', $dependents, $this->query);
						}
					}
					if(count($referenced) > 0 || count($dependents) > 0) {
						$auth['data'] = ["children" => $dependents, "references" => $referenced];
						$response = new Response(300, $auth);
						$response->send();
					} else {
						$result = $this->table->delete($this->query);
						$auth['data'] = $result;
						$response = new Response(200, $auth);
						$response->send();
					}
				} else {
					$result = $this->table->delete($this->query);
					$auth['data'] = $result;
					$response = new Response(200, $auth);
					$response->send();
				}			
			}, ["check"], function() use ($open_tables) {
				$claim = $this->evaluateClaim(function($flag, $query, $config) use ($open_tables) {
					return $this->claimFunction($flag, $query, $config, $open_tables);
				}, "Delete", '/'.$this->config['table_name'].'.json');
				return $this->permissionFunction($this->token, $claim);
			}));
			
		}
		
		function recursiveJoin($root, $name, $tabletype, $checktype, &$items, $query = null) {
			//$root is the base table
			//we create a new table, join the root to it, and run a null query
			//the first time through, we tack on the query to the root table
		    $schema = json_decode(file_get_contents(dirname(__FILE__).DataService::$table_dir.$name.".json"), true);
			$table = new Table($schema);
			$joinquery = [$root->getName() => "ID"];
			if($tabletype == "VERSIONED" && $checktype == "children") {
				//$joinquery = ["AND" =>[$joinquery, [$name."_VERSION" => "VERSION"]]];
			    $joinquery[$root->getName()."_VERSION"] = "VERSION";
			}
			$table->join($root, $joinquery, "INNER", null, $query);
			$result = $table->read(null);			
			if(count($result) > 0) {	
				//clean up the results
				$results = [];
				foreach($result as $bit) {
					$newbit = [];
					foreach($bit as $column => $value) {
						$bits = explode('.', $column);
						if($bits[0] == $name) {
							$newbit[$bits[1]] = $value;
						}
					}
					$results[] = $newbit;
				}
				$items[$name] = $results;
				//recur, if needed
				if(isset($schema[$checktype]) && count($schema[$checktype]) > 0) {
					foreach($schema[$checktype] as $sub) {
						if(!isset($items[$sub])) {
							$this->recursiveJoin($table, $sub, $schema['table_type'], $checktype, $items);
						}
					}
				}
			}			
		}
		
		function permissionFunction($token, $claim = null ) {
			return Helper::checkAuthorization($token, $claim);
		}
		
		function claimFunction($flag, $query, $config=null, $open_tables) {
			$trim = substr($config, strrpos($config, '/') + 1);
			$trim = substr($trim, 0, strrpos($trim, '.json'));
			
			$permission = ["Permission" => $trim, "Flag" => $flag];
			if(array_key_exists($trim, $open_tables)) {
				//check for our flag (or access, if no flag specified)
				$lookup = $open_tables[$trim];
				if(array_key_exists(!is_null($flag)? $flag: "Access", $lookup)) {
					$permission = $lookup[!is_null($flag)? $flag: "Access"];
				}
			}			
			return $permission;
		}
	}
	
	$dataService = new DataService();
	$dataService->go();
?>