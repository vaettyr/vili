<?php
//loads a configuration
//compares config structure to table structure
//has definitions for default crud operations

include_once("Logger.php");

	class Table {
		private $schema;
		private $columns;
		private $references;
		private $children;
		private $config;
		private $config_name;	
		private $joins;
		private $logger;
		
		private $transaction;
		
		function __construct($schema, $config = null, $configname = null) {
			//load the config from the file path
			$this->schema = $schema;
			//optional: load a config
			if(is_null($config))
				$config = "config.php";
			$this->config = $config;
			if(is_null($configname))
				$configname = "data_config";
			$this->config_name = $configname;
			$this->columns = $this->schema['columns'];
			$this->references = [];			
			if(isset($this->schema['references'])) {
				$this->references = $this->schema['references'];
			}
			$this->children = [];
			if(isset($this->schema['children'])) {
				$this->children = $this->schema['children'];
			}
			$type = "SIMPLE";
			if(array_key_exists('table_type', $this->schema)) {
				$type = $this->schema['table_type'];
			}
			if($type != 'SIMPLE') {
				$this->columns['ID'] = ['type' => 'INT(11)'];
			}
			if($type == 'VERSIONED') {
				$this->columns['VERSION'] = ['type' => 'INT(11)'];
				$this->columns['LOCKED'] = ['type' => 'BIT(1)'];
			}
			$this->joins = array();
			$this->logger = new Logger();
		}
		
		function refresh() {
			try {
				$conn = $this::connect();
				if($conn === false)
					return false;
				$query = "SELECT COUNT(*) AS num
					FROM information_schema.TABLES
					WHERE TABLE_NAME = '" . $this->schema['table_name'] . "'
					AND TABLE_SCHEMA = '".data_config::db."'";
				$result = mysqli_query($conn, $query) or die(mysqli_error($conn));
				$exists = false;
				if($result) {
					//query ran successfully, check the count
					$count = mysqli_fetch_assoc($result);
					$exists = $count['num'];
				} 
				if(!$exists) {
					//create the table
					$this::create_table($conn);
				} else {
					//check the structure of the table
					$this::update_table($conn);
				}
				mysqli_close($conn);
			} 
			catch(Exception $ex) {
				$this->logger->handleException($ex);
			}
		}
		
		//for unit test purposes only
		function destroy() {
			try {
				$query = "DROP TABLE ".$this->schema['table_name'].";";
				$conn = $this::connect();
				if($conn === false)
					return false;
				$result = mysqli_query($conn, $query);
				if(!$result) {
					$error = mysqli_error($conn);
					mysqli_close($conn);
					return $error;
				}
				mysqli_close($conn);
			}
			catch (Exception $ex) {
				$this->logger->handleException($ex);
			}
			
		}
		
		function getName() {
			return $this->schema['table_name'];
		}
		
		function getColumns() {
			return $this->columns;
		}
		
		function getColumnsForQuery($isjoin = false) {
			try {
				$str = "";
				$first = true;
				$joins = count($this->joins);
				foreach($this->columns as $name => $value) {
					if(!$first) { $str = $str.", "; } else { $first = false; }
					$str = $str.$this->getName().".".$name." ";
					if($joins > 0 || $isjoin) {
						$str = $str."AS '".$this->getName().".".$name."'";
					}
				}
				return $str;
			}
			catch (Exception $ex) {
				$this->logger->handleException($ex);
			}		
		}
		
		//format for on is an array: [parent_column_name => child_column_name]
		function join($table, $on, $type = null, $columns = null, $query = null) {
			$join = ['table'=>$table, 'on' => $on, 'query' => $query];
			if(is_null($type)) {
				$join['type'] = 'LEFT';
			} else {
				$join['type'] = strtoupper($type);
			}
			if(!is_null($columns)) {
				$join['columns'] = $columns;
			} else {
				$join['columns'] = $table->getColumns();				
			}
			$this->joins[] = $join;
		}
		
		//create ignores joins and only inserts to the primary table
		function create($args, $bypass_log = false) {
			//build the insert query
			try {
				$conn = $this::connect();
				if($conn === false)
					return false;
					
				$params = $this->get_params($args);
				
				$count = 2;
				//check for versioned table to get id
				$table_type = 'BASIC';
				if(array_key_exists('table_type', $this->schema) && strtoupper($this->schema['table_type']) != 'BASIC') {
					$table_type = strtoupper($this->schema['table_type']);
				}
				$getid = false;
				$getversion = false;
				if($table_type == 'VERSIONED') {
					if(array_key_exists('ID', $args)) {
						$getversion = true;
					} else {
						$getid = true;
					}
				}
				$query = "INSERT INTO ".$this->schema['table_name']."(
			CREATE_TIMESTAMP";
				foreach($params as $param) {
					$query = $query.", ".$param['name'];
				}
				if($getid) {
					$query = $query.", ID, VERSION";
				}
				if($getversion) {
					$query = $query.", VERSION";
				}
				$query = $query.") VALUES (
			UTC_TIMESTAMP";
				foreach($params as $param) {
					$query = $query.", ".$param['value']." ";
				}
				if($getid) {
					$count++;
					$query = "SELECT @id := COALESCE(MAX(ID), 0) + 1 FROM ".$this->schema['table_name'].";".$query.", @id, 1";
				}
				if($getversion) {
					$count++;
					$query = "SELECT @v := MAX(VERSION) + 1 FROM ".$this->schema['table_name']." WHERE ID = ".$args['ID'].";".$query.", @v";
				}
				$query = $query.");";
				//echo $query;
				//put the select statement in here also!
				switch($table_type) {
					case "SIMPLE":
						//get the last inserted record by timestamp (for simple)
						$query = $query."SELECT ".$this->getColumnsForQuery()."FROM ".$this->schema['table_name']." WHERE CREATE_TIMESTAMP =
					(SELECT MAX(CREATE_TIMESTAMP) FROM ".$this->schema['table_name'].");";
						break;
					case "BASIC":
						//get the last inserted record by id (for basic)
						$query = $query."SELECT ".$this->getColumnsForQuery()."FROM ".$this->schema['table_name']." WHERE ID =
					(SELECT MAX(ID) FROM ".$this->schema['table_name'].");";
						break;
					case "VERSIONED":
						if(array_key_exists('ID', $args)) {
							//get the last inserted record by version
							$query = $query."SELECT ".$this->getColumnsForQuery()."FROM ".$this->schema['table_name']." WHERE VERSION =
						(SELECT MAX(VERSION) FROM ".$this->schema['table_name'].") AND ID = ".$args['ID'].";";
						} else {
							//get the last inserted record by id
							$query = $query."SELECT ".$this->getColumnsForQuery()."FROM ".$this->schema['table_name']." WHERE ID =
						(SELECT MAX(ID) FROM ".$this->schema['table_name'].");";
						}
						break;
				}
				if(!$bypass_log) {
					$action = ['TYPE' => 'CREATE', 'NAME' => $this->schema['table_name'], 'PROCESS' => 'BEGIN', 'PARAMS' => null, 'PAYLOAD' => $args];
					$log = $this->logger->logActivity($action);
				}
				
				$result = mysqli_multi_query($conn, $query);
				
				if($result) {
					do {
						if(mysqli_more_results($conn)) {
							mysqli_next_result($conn);
							$result = mysqli_store_result($conn);
							if(gettype($result) == 'object') {
								$response = mysqli_fetch_assoc($result);
								$output = array();
								foreach($response as $key => $field) {
									$output[$key] = $this->get_column($key, $field);
								}
							}
							if ($result && mysqli_more_results($conn)) {
								mysqli_free_result($result);
							}
							$count--;
						} else if($error = mysqli_error($conn)) {
							$response = new Response(500, mysqli_error($conn));
							if(!is_null($this->transaction) && isset($this->transaction)) {
								$this->transaction('rollback');
								mysqli_close($conn);
							} else {
								mysqli_close($conn);
							}
							$response->send();
							break;
						} else {
							$count = 0;
						}
					}
					while ($count>0);
				} else {
					$response = new Response(500, mysqli_error($conn));
					if(!is_null($this->transaction) && isset($this->transaction)) {
						$this->transaction('rollback');
						mysqli_close($conn);
					} else {
						mysqli_close($conn);
					}
					$response->send();
				}
				if(is_null($this->transaction) || !isset($this->transaction)) {
					mysqli_close($conn);
				}				
				if(!$bypass_log) {
					$action['PROCESS'] = 'END';
					$action['RESULT'] = $output;
					$action['ID'] = $log;
					$this->logger->logActivity($action);
				}
				
				return $output;	
			}
			catch(Exception $ex) {
				$this->logger->handleException($ex);
			}
		}
		
		//update this to handle joins
		function read($selection = null, $options = ["FORCE_ALL" => false, 'MOST_RECENT' => true], $bypass_log = false, $order = null) { //force_all will get 'deleted' entries
			try {	
				$conn = $this::connect();
				if($conn === false)
					return false;
				//for now, always select all columns
				$query = "SELECT ".$this->getColumnsForQuery();
				if(count($this->joins) > 0) {
					$this->join_columns($query);
				}
				$query = $query."FROM ".$this->schema['table_name']." ";
				if(count($this->joins) > 0) {
					$this->join_table($query);
				}
				//default where clause for easier joins
				$query = $query." WHERE 1=1 ";
				$force_all = false;
				if(!is_null($options) && !empty($options['FORCE_ALL'] && $options['FORCE_ALL'])) {
				    $force_all = true; 
				}
				if(!$force_all) {
				    $query = $query."AND IFNULL(".$this->schema['table_name'].".DELETED, 0) <> 1 ";
				}
                $table_type = 'SIMPLE';
                if(!empty($this->schema['table_type'])) {
                    $table_type = $this->schema['table_type'];
                }
                if($table_type == 'VERSIONED' && (is_null($selection) || !is_null($selection->vars['VERSION']))) {
                    if(!is_null($options) && !empty($options['MOST_RECENT']) && $options['MOST_RECENT']) {
                        $query .= "AND ".$this->getName().".VERSION=(SELECT MAX(VERSION) FROM ".$this->getName()." AS V WHERE V.ID = ".$this->getName().".ID) ";
                    }
                }
				if(!is_null($selection) || count($this->joins) > 0) {
					//echo json_encode($selection);
					//get a complete list of columns
					$columns = $this->columns;
					if(count($this->joins) > 0) {
						foreach($this->joins as $join) {
							foreach($join['table']->getColumns() as $name => $value) {
								if(!array_key_exists($name, $columns)) {
									$columns[$name] = $value;
								}
							}
						}
					}
					if(!is_null($selection)) {
						$clause = $selection->toQuery($columns, $this->schema['table_name']);
					}					
					if(!empty($clause)) {
						$query = $query."AND ".$clause;
						$this->join_selections($query, false, $force_all);
					} else {				
						$subclause = "";
						$this->join_selections($subclause, true, $force_all);
						if(!empty($subclause)) {
							$query = $query."AND ".$subclause;
						}
					}
				}		
				if(!is_null($order)) {
				    $query = $query." ORDER BY ";
				    foreach($order as $column => $direction) {
				        $query = $query.$column." ".$direction;
				    }
				}
				$query = $query.";";
	
				//echo $query;
				if(!$bypass_log) {
					$action = ['TYPE' => 'READ', 'NAME' => $this->schema['table_name'], 'PROCESS' => 'BEGIN', 'PARAMS' => $selection, 'PAYLOAD' => null];
					$log = $this->logger->logActivity($action);
				}				
				
				$result = mysqli_query($conn, $query);
				if(!$result) {
					$response = new Response(500, ["error"=>mysqli_error($conn), "query"=>$query]);
					if(!is_null($this->transaction) && isset($this->transaction)) {
						$this->transaction('rollback');
						mysqli_close($conn);
					} else {
						mysqli_close($conn);
					}
					$response->send();
				}
				if(is_null($this->transaction) || !isset($this->transaction)) {
					mysqli_close($conn);
				}	
				//pull out the results and return them
				$records = array();
				while($row = mysqli_fetch_assoc($result)) {
					//parse the row to see if it contains a boolean value
					foreach($row as $key => $field) {
						$row[$key] = $this->get_column($key, $field);
					}
					$records[] = $row;
				}
				if(!$bypass_log) {
					$action['PROCESS'] = 'END';
					$action['RESULT'] = $records;
					$action['ID'] = $log;
					$this->logger->logActivity($action);
				}
				
				return $records;
			}
			catch(Exception $ex) {
				$this->logger->handleException($ex);
			}
		}
		
		//update ignores joins and only updates the primary table
		function update($args, $selection = null, $bypass_log = false) {
			try {
			    //if this is a versioned table and the record we're updating is locked, create an incremented version instead
			    $table_type = 'BASIC';
			    if(array_key_exists('table_type', $this->schema) && strtoupper($this->schema['table_type']) != 'BASIC') {
			        $table_type = strtoupper($this->schema['table_type']);
			    }	
				$records = array();				
				//build the update query
				$conn = $this::connect();
				if($conn === false)
				    return false;
				$params = $this->get_params($args);
				$query = "UPDATE ".$this->schema['table_name']."
					SET UPDATE_TIMESTAMP = UTC_TIMESTAMP";
				foreach($params as $param) {
					if(!is_null($param['value'])) {
						$query = $query.", ".$param['name']." = ".$param['value']." ";
					}
				}
				
				//never updated locked records for any reason
				if(!is_null($selection)) {					
					$clause = $selection->toQuery($this->columns);
					if(!empty($clause)) {
						$query = $query." WHERE ".$clause;
						if($table_type == 'VERSIONED') {
						    $query .= ' AND COALESCE('.$this->getName().".LOCKED, 0) <> 1";
						}
					} else if($table_type == 'VERSIONED') {
					    $query .= ' WHERE COALESCE('.$this->getName().".LOCKED, 0) <> 1";
					}
				} else if($table_type == 'VERSIONED') {
				    $query .= ' WHERE COALESCE('.$this->getName().".LOCKED, 0) <> 1";
				}
				$query = $query.";";
				//echo $query;
				if(!$bypass_log) {
					$action = ['TYPE' => 'UPDATE', 'NAME' => $this->schema['table_name'], 'PROCESS' => 'BEGIN', 'PARAMS' => $selection, 'PAYLOAD' => $args];
					$log = $this->logger->logActivity($action);
				}
				
				$result = mysqli_query($conn, $query);
				if(!$result) {
					$error = mysqli_error($conn);
					mysqli_close($conn);
					return $error;
				}
				if(mysqli_affected_rows($conn)) {
				    //get the last inserted record by timestamp (for simple)
				    $selquery = "SELECT ".$this->getColumnsForQuery()."FROM ".$this->schema['table_name'];
				    if(!empty($clause)) {
				        $selquery = $selquery." WHERE ".$clause.";";
				    } else {
				        $selquery = $selquery." WHERE UPDATE_TIMESTAMP =
					(SELECT MAX(UPDATE_TIMESTAMP) FROM ".$this->schema['table_name'].");";
				    }
				    $result = mysqli_query($conn, $selquery);
				    if(!$result) {
				        $response = new Response(500, mysqli_error($conn));
				        if(!is_null($this->transaction) && isset($this->transaction)) {
				            $this->transaction('rollback');
				            mysqli_close($conn);
				        } else {
				            mysqli_close($conn);
				        }
				        $response->send();
				    }
				    //select all of them
				    while($row = mysqli_fetch_assoc($result)) {
				        //parse the row to see if it contains a boolean value
				        foreach($row as $key => $field) {
				            $row[$key] = $this->get_column($key, $field);
				        }
				        $records[] = $row;
				    }
				}				
				if(is_null($this->transaction) || !isset($this->transaction)) {
					mysqli_close($conn);
				}					
				$new_records = $this->updateLocked($table_type, $args, $selection, $bypass_log);
				foreach($new_records as $new_record) {
				    $records[] = $new_record;
				}
				if(!$bypass_log) {
					$action['PROCESS'] = 'END';
					$action['RESULT'] = $records;
					$action['ID'] = $log;
					$this->logger->logActivity($action);
				}
				return $records;
			} 
			catch (Exception $ex) {
				$this->logger->handleException($ex);
			}
		}
		
		//delete ignores joins and only deletes from the primary table
		//update this to check for conflicts and to delete child records if needed
		function delete($selection = null, $hard = false, $bypass_log = false) {			
			try {
				//build the delete query
				$conn = $this::connect();
				if($conn === false)
					return false;
				if(!$hard) {
					$query = "UPDATE ".$this->schema['table_name']." SET DELETED = 1, UPDATE_TIMESTAMP = UTC_TIMESTAMP ";
				} else {
					$query = "DELETE FROM ".$this->schema['table_name']." ";
				}		
				if(!is_null($selection)) {
					$clause = $selection->toQuery($this->columns);
					if(!empty($clause)) {
						$query = $query."WHERE ".$clause.";";
					}			
				}		
				//echo $query;
				if(!$bypass_log) {
					$action = ['TYPE' => 'DELETE', 'NAME' => $this->schema['table_name'], 'PROCESS' => 'BEGIN', 'PARAMS' => $selection, 'PAYLOAD' => null];
					$log = $this->logger->logActivity($action);
				}
				$result = mysqli_query($conn, $query);
				if(!$result) {
					$response = new Response(500, mysqli_error($conn));
					if(!is_null($this->transaction) && isset($this->transaction)) {
						$this->transaction('rollback');
						mysqli_close($conn);
					} else {
						mysqli_close($conn);
					}
					$response->send();
				}
				if(is_null($this->transaction) || !isset($this->transaction)) {
					mysqli_close($conn);
				}	
				if(!$bypass_log) {
					$action['PROCESS'] = 'END';
					$action['RESULT'] = $result;
					$action['ID'] = $log;
					$this->logger->logActivity($action);
				}
			}
			catch (Exception $ex) {
				$this->logger->handleException($ex);
			}
		}
		
		function proc($statement, $selection = null, $bypass_log = false) {
			try {
				//executes arbitrary sql statement and returns the results
				$conn = $this::connect();
				if($conn === false)
					return false;
				if(!is_null($selection)) {
					$statement = $statement." WHERE ".$selection->toQuery($this->columns);
				}
				if(!$bypass_log) {
					$action = ['TYPE' => 'PROC', 'NAME' => $this->schema['table_name'], 'PROCESS' => 'BEGIN', 'PARAMS' => $selection, 'PAYLOAD' => ['statement' => $statement]];
					$log = $this->logger->logActivity($action);
				}
				$result = mysqli_query($conn, $statement);
				if(!$result) {
					$response = new Response(500, mysqli_error($conn));
					if(!is_null($this->transaction) && isset($this->transaction)) {
						$this->transaction('rollback');
						mysqli_close($conn);
					} else {
						mysqli_close($conn);
					}
					$response->send();
				}
				if(is_null($this->transaction) || !isset($this->transaction)) {
					mysqli_close($conn);
				}	
				//pull out the results and return them
				$records = array();
				if($result === true) {
					return $records;
				} else {
					while($row = mysqli_fetch_assoc($result)) {
						//parse the row to see if it contains a boolean value
						foreach($row as $key => $field) {
							$row[$key] = $this->get_column($key, $field);
						}
						$records[] = $row;
					}
				}
				if(!$bypass_log) {
					$action['PROCESS'] = 'END';
					$action['RESULT'] = $records;
					$action['ID'] = $log;
					$this->logger->logActivity($action);
				}
				return $records;
			}
			catch (Exception $ex) {
				$this->logger->handleException($ex);
			}
		}
		
		//primary table only
		function last_change($selection = null, $bypass_log = false) {
			try {
				$conn = $this::connect();
				if($conn === false)
					return false;
				$query = "SELECT MAX(COALESCE(UPDATE_TIMESTAMP, CREATE_TIMESTAMP)) AS last_change
					FROM ".$this->schema['table_name'];	
				if(!is_null($selection)) {
					$clause = $selection->toQuery($this->columns);
					if(!empty($clause)) {
						$query = $query." WHERE ".$clause;
					}				
				}
				//echo $query;
				if(!$bypass_log) {
					$action = ['TYPE' => 'CHANGE', 'NAME' => $this->schema['table_name'], 'PROCESS' => 'BEGIN', 'PARAMS' => $selection, 'PAYLOAD' => null];
					$log = $this->logger->logActivity($action);
				}
				$result = mysqli_query($conn, $query);
				
				if($result) {
					$date = mysqli_fetch_assoc($result)['last_change'];
					if(is_null($this->transaction) || !isset($this->transaction)) {
						mysqli_close($conn);
					}	
					if(!$bypass_log) {
						$action['PROCESS'] = 'END';
						$action['RESULT'] = ['last_change' => DateTime::createFromFormat('Y-m-d G:i:s', $date)];
						$action['ID'] = $log;
						$this->logger->logActivity($action);
					}
					return ['last_change' => DateTime::createFromFormat('Y-m-d G:i:s', $date)];
				} else {
					$response = new Response(500, mysqli_error($conn));
					if(!is_null($this->transaction) && isset($this->transaction)) {
						$this->transaction('rollback');
						mysqli_close($conn);
					} else {
						mysqli_close($conn);
					}
					$response->send();
				}
			}
			catch (Exception $ex) {
				$this->logger->handleException($ex);
			}
		}
		
		function join_table(&$query) {
			foreach($this->joins as $join) {
				$query = $query.$join['type']." JOIN ".$join['table']->getName()." ON ";
				$first = true;
				foreach($join['on'] as $parent => $child) {
					if(!$first) { $query = $query."AND "; } else { $first = false; }
					$query = $query.$this->getName().".".$parent." = ".$join['table']->getName().".".$child." ";
				}
				if(count($join['table']->joins) > 0) {
					$join['table']->join_table($query);
				}
			}
		}
		
		function join_columns(&$query) {
			foreach($this->joins as $join) {
				$query = $query.", ".$join['table']->getColumnsForQuery(true);
			}
			if(count($join['table']->joins) > 0) {
				$join['table']->join_columns($query);
			}
		}
		
		function join_selections(&$query, $first, $force_all = false) {
			foreach($this->joins as $join) {
				if(!is_null($join['query'])) {
					if(!$first) { $query = $query." AND "; } else { $first = false; }
					if(!$force_all) { $query = $query."IFNULL(".$join['table']->getName().".DELETED, 0) <> 1 AND "; }
					$query = $query.$join['query']->toQuery($join['columns'], $join['table']->getName());
					
					if(count($join['table']->joins) > 0) {
						$join['table']->join_selections($query, $first, $force_all);
					}
				}
			}
		}
		
		function transaction($mode, $connection = null) {
			try {
				if(is_null($connection)) {
					$conn = $this::connect();
					if($conn === false)
						return false;
				}
				switch($mode) {
					case "open":
						$query = 'START TRANSACTION;';
						$this->transaction = $conn;
						break;
					case "commit":
						$query = 'COMMIT;';
						unset($this->transaction);
						break;
					case "rollback":
						$query = 'ROLLBACK;';
						unset($this->transaction);
						break;
					case "set":
						$this->transaction = $connection;
						break;
				}
				
				if($mode != 'set') {
					$result = mysqli_query($conn, $query);
					
					if(!$result) {
						$response = new Response(500, mysqli_error($conn));
						mysqli_close($conn);
						$response->send();
					}
					
					if($mode != 'open') {
						mysqli_close($conn);
					}
				}			
				if(isset($this->transaction)) {
					return $this->transaction;
				}
				
			}
			catch (Exception $ex) {
				$this->logger->handleException($ex);
			}
			
			
		}
		
		private function updateLocked($table_type, $args, $selection, $bypass_log = false) {
		    $records = array();
		   	    
		    //check to see if we're attempting to update a locked versioned record
		    if($table_type == 'VERSIONED' && !is_null($selection)) {
		        $locked_query = "SELECT ID, VERSION, LOCKED FROM ".$this->schema['table_name'];
		        $clause = $selection->toQuery($this->columns);
		        if(!empty($clause)) {
		            $locked_query .= " WHERE ".$clause.";";
		        }
		        //build the update query
		        $conn = $this::connect();
		        if($conn === false)
		            return false;
	            $is_locked = mysqli_query($conn, $locked_query);
	            if(!$is_locked) {
	                $response = new Response(500, mysqli_error($locked_conn));
	                if(!is_null($this->transaction) && isset($this->transaction)) {
	                    $this->transaction('rollback');
	                    mysqli_close($locked_conn);
	                } else {
	                    mysqli_close($locked_conn);
	                }
	                $response->send();
	            }
	            $create_rows = array();
	            while($row = mysqli_fetch_assoc($is_locked)) {
	                if($row && !empty($row['LOCKED'])) {
	                    $temp_args = $args;
	                    $temp_args['ID'] = $row['ID'];
	                    if(!empty($temp_args['LOCKED'])) {
	                        unset($temp_args['LOCKED']);
	                    }
	                    $create_rows[] = $temp_args;
	                }
	            }
	            mysqli_close($conn);
	            foreach($create_rows as $crow) {
	               $records[] = $this->create($crow, $bypass_log);
	            }
		    }
		    return $records;
		}
		
		private function get_params($args) {
			$params = Array();			
			foreach($args as $name => $value) {
				if(array_key_exists($name, $this->schema['columns']) || $name == 'ID' || ($name == 'LOCKED' && $this->schema['table_type'] == 'VERSIONED')) {
					$params[] = ['name' => $name, 'value' => $this->get_value($value, $name)];
				}
			}					
			return $params;
		}
		
		private function get_value($arg, $name) {			
			$value = " ";
			if(is_numeric($arg)) { 
				$value = $value.$arg;				
			} else if (is_null($arg)) {
				$value = null;
			} else if (array_key_exists($name, $this->schema['columns']) && strtoupper($this->schema['columns'][$name]['type']) == "BIT(1)") {
				$value = ($arg === true) ? 1 : 0;
			} else { 
				//replace quotes in the string
				$value = $value." '".str_replace("'", "\'", $arg)."' ";				
			}
			return $value;
		}
		
		//update this to read column lists of joined tables
		private function get_column($name, $value) {
			if(array_key_exists($name, $this->columns) && strtoupper($this->columns[$name]['type']) == "BIT(1)") {
				return $value == 1;
			} else {
				return $value;
			}
		}
		
		private function create_table($conn) {
			//there are 3 basic table types:
			//basic (none): has an auto-increment primary key
			//simple: has no primary key defined
			//versioned: has a composite primary key of 2 ints
			$query = "CREATE TABLE ".$this->schema['table_name']." (
			CREATE_TIMESTAMP DATETIME,
			UPDATE_TIMESTAMP DATETIME,
			DELETED BIT(1)";
			//create the primary key
			$table_type = 'BASIC';
			if(array_key_exists('table_type', $this->schema) && strtoupper($this->schema['table_type']) != 'BASIC') {
				$table_type = strtoupper($this->schema['table_type']);
			} 
			if($table_type == "BASIC") {
				$query = $query.", ID INT NOT NULL AUTO_INCREMENT";
			} else if($table_type == "VERSIONED") {
				$query = $query.", ID INT NOT NULL, VERSION INT NOT NULL, LOCKED BIT NULL";
			}
			//add the columns
			foreach($this->schema['columns'] as $name => $data) {
				$query = $query.", ".$name." ".$data['type'];
			}
			//set the primary key
			if($table_type == "BASIC") {
				$query = $query.", PRIMARY KEY (ID)";
			} else if($table_type == "VERSIONED") {
				$query = $query.", PRIMARY KEY (ID, VERSION)";
			}
			$query = $query.") ENGINE=InnoDB;";
			$result = mysqli_query($conn, $query);
			if(!$result) {
				$error = mysqli_error($conn);
				if(!is_null($this->transaction) && isset($this->transaction)) {
					$this->transaction('rollback');
					mysqli_close($conn);
				} else {
					mysqli_close($conn);
				}
				throw new Exception($error, 500);
			}
		}
		
		private function update_table($conn) { //, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_KEY, EXTRA
			$query = "SELECT COLUMN_NAME, COLUMN_TYPE
				FROM information_schema.COLUMNS
				WHERE TABLE_NAME = '" . $this->schema['table_name'] . "'
				AND TABLE_SCHEMA = '".data_config::db."';";
			$result = mysqli_query($conn, $query) or die(mysqli_error($conn));
			$columns = array();
			while($row = mysqli_fetch_assoc($result)) {
				$columns[$row['COLUMN_NAME']] = ['type' => $row['COLUMN_TYPE']];
			}
			$add = array();
			$update = array();
			$delete = array();
			//go through the structure first to see if the database is missing any or if any need to be updated
			//build a modified list of columns to correct for default changes (deleted flag)
			$schema_columns = $this->schema['columns'];
			$schema_columns['CREATE_TIMESTAMP'] = ['type' => 'DATETIME'];
			$schema_columns['UPDATE_TIMESTAMP'] = ['type' => 'DATETIME'];
			$schema_columns['DELETED'] = ['type' => 'BIT(1)'];
			foreach($schema_columns as $name => $data) {
				if(!array_key_exists($name, $columns)) {
					$add[] = ['name' => $name, 'data' => $data];
				} else if(strtoupper($data['type']) != strtoupper($columns[$name]['type'])) {
					$update[] = ['name' => $name, 'data' => $data];
				}
			}
			//go through the database structure to see if any need to be deleted, skip the default columns
			foreach($columns as $name => $data) {
				if(!array_key_exists($name, $this->schema['columns']) && $name != 'ID' && $name != 'CREATE_TIMESTAMP' 
						&& $name != 'UPDATE_TIMESTAMP' && $name != 'VERSION' && $name != 'DELETED' && $name != 'LOCKED') {
					$delete[] = ['name' => $name, 'data' => $data];
				}
			}
			$table_type = 'BASIC';
			if(array_key_exists('table_type', $this->schema) && strtoupper($this->schema['table_type']) != 'BASIC') {
				$table_type = strtoupper($this->schema['table_type']);
			}
			//detect table type change
			$addVERSION = false;
			if($table_type == "SIMPLE") {
				//if there's ID or VERSION columns, remove them
				if(array_key_exists('ID', $columns)) {
					$delete[] = ['name' => 'ID', 'data' => $columns['ID']];
				}
				if(array_key_exists('VERSION', $columns)) {
					$delete[] = ['name' => 'VERSION', 'data' => $columns['VERSION']];
				}
				if(array_key_exists('LOCKED', $columns)) {
				    $delete[] = ['name' => 'LOCKED', 'data' => $columns['LOCKED']];
				}
			} else if ($table_type == "BASIC") {
				if(!array_key_exists('ID', $columns)) {
					$add[] = ['name' => 'ID', 'data' => ['type' => 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY']];
				}
				//set a default value for the id
				if(array_key_exists('VERSION', $columns)) {
					$delete[] = ['name' => 'VERSION', 'data' => $columns['VERSION']];
				}
				if(array_key_exists('LOCKED', $columns)) {
				    $delete[] = ['name' => 'LOCKED', 'data' => $columns['LOCKED']];
				}
			} else {
				if(!array_key_exists('ID', $columns)) {
					echo "Table type Simple to Versioned is not supported!";
					return false;
				}
				//set a default value for the id
				if(!array_key_exists('VERSION', $columns)) {
					$add[] = ['name' => 'VERSION', 'data' => ['type' => 'INT NOT NULL']];
					$addVERSION = true;
				}
				if(!array_key_exists('LOCKED', $columns)) {
				    $add[] = ['name' => 'LOCKED', 'data' => ['type' => 'BIT NULL']];
				}
			}
			//build the query	
			$modquery = "ALTER TABLE ".$this->schema['table_name'];
			$first = true;
			foreach($add as $column) {
				if(!$first) {$modquery = $modquery.",";} else {$first = false;}
				$modquery = $modquery." ADD ".$column['name']." ".$column['data']['type'];
			}
			foreach($update as $column) {
				if(!$first) {$modquery = $modquery.",";} else {$first = false;}
				$modquery = $modquery." MODIFY ".$column['name']." ".$column['data']['type'];
			}
			foreach($delete as $column) {
				if(!$first) {$modquery = $modquery.",";} else {$first = false;}
				$modquery = $modquery." DROP COLUMN ".$column['name'];
			}
			$modquery = $modquery.";";
			//echo $modquery;
			//run the query
			$result = mysqli_query($conn, $modquery);
			if(!$result) {
				$error = mysqli_error($conn);			
				if(!is_null($this->transaction) && isset($this->transaction)) {
					$this->transaction('rollback');
					mysqli_close($conn);
				} else {
					mysqli_close($conn);
				}
				throw new Exception($error, 500);
			}
			//if we've added a version column, initialize them all to 1
			if($addVERSION) {
				$vquery = "UPDATE ".$this->schema['table_name']." SET VERSION = 1";
				$result = mysqli_query($conn, $vquery);
				if(!$result) {
					$error = mysqli_error($conn);
					if(!is_null($this->transaction) && isset($this->transaction)) {
						$this->transaction('rollback');
						mysqli_close($conn);
					} else {
						mysqli_close($conn);
					}
					throw new Exception($error, 500);
				}
			}
		}
		
		private function connect() {
			try {
				//establish the connection
				if(is_null($this->config))
					include_once("config.php");
				else
					include_once($this->config);
				if(!is_null($this->transaction) && isset($this->transaction)) {
					return $this->transaction;
				}
				if(is_null($this->config_name))
					$conn = mysqli_connect(data_config::servername, data_config::username, data_config::password);
				else
					$conn = mysqli_connect(constant($this->config_name.'::servername'), constant($this->config_name."::username"), constant($this->config_name."::password"));
				if (!$conn) {
					$response = new Response(500, mysqli_connect_error());
					$response->send();
				}
				//make sure we're only returning valid utf-8 data
				mysqli_set_charset($conn, "utf8");
				if(is_null($this->config_name)) {
					mysqli_select_db ($conn, data_config::db);
				} else {
					$result = mysqli_select_db($conn, constant($this->config_name."::db"));
				}
				return $conn;
			}
			catch (Exception $ex) {
				$this->logger->handleException($ex);
			}
		}
	
	}
?>