<?php
	//creates a default service end point based on a config
	include_once("Endpoint.php");
	include_once("Table.php");
	include_once("Logger.php");
	
	class Service extends Endpoint{
		protected $config;
		public $table;
		
		function __construct($config, $token = null, $query = null, $permission = null, $claim = null) {

			$this->config = json_decode(file_get_contents($config), true);
			$this->table = new Table($this->config);

			parent::__construct($token, [$this->config['table_name']], $query);
			
			$query = $this->query;
			$body = $this->body;
			$token = $this->token;
			
			//build default endpoints
			//create/update (POST)
			$this->register(new Handler("POST", function($auth) use ($query, $body, $token) {
				$operation = $this->getOperation($query);
				//echo $operation;
				switch($operation) {
					case 'CREATE':
						$result = $this->table->create($body);
						$auth['data'] = $result;
						$response = new Response(200, $auth);
						$response->send();
						break;
					case 'UPDATE':
						$result = $this->table->update($body, $query);
						$auth['data'] = $result;
						$response = new Response(200, $auth);
						$response->send();
						break;
				}
			}, null, function() use ($token, $permission, $claim, $config, $query) {
				if(!is_null($permission)) {
					$operation = $this->getOperation($query);
					switch($operation) {
						case 'CREATE':
							$claim = $this->evaluateClaim($claim, 'Create', $config);
							break;
						case 'UPDATE':
							$claim = $this->evaluateClaim($claim, 'Update', $config);
							break;
					}
					return $permission->__invoke($token, $claim);
				}
			}));
			//check (GET)
			$this->register(new Handler("GET", function($auth) use ($query, $token) {
				if(empty($query->vars)) { $query = null; }
				$result = $this->table->last_change($query);
				$auth['data'] = $result;
				$response = new Response(200, $auth);
				$response->send();
			}, ["last_change"], function() use ($token, $permission, $claim, $config) {
				if(!is_null($permission)) {
					//if claim is a function, evaluate it to determine what permission we need
					$claim = $this->evaluateClaim($claim, null, $config);
					return $permission->__invoke($token, $claim);
				}
			}));
			//read (GET)
			$this->register(new Handler("GET", function($auth) use ($query, $token) {
				//echo json_encode($query);
				if(empty($query->vars)) { $query = null;}
				$result = $this->table->read($query);
				//$result[] = $auth;
				$auth['data'] = $result;
				$response = new Response(200, $auth);
				$response->send();
			},null, function() use($token, $permission, $claim, $config) {
				if(!is_null($permission)) {
					//if claim is a function, evaluate it to determine what permission we need
					$claim = $this->evaluateClaim($claim, null, $config);
					return $permission->__invoke($token, $claim);
				}
			}));
			//delete (DELETE)
			$this->register(new Handler("DELETE", function($auth) use ($query, $token) {
				if(empty($query->vars)) { $query = null; }
				$result = $this->table->delete($query);
				$auth['data'] = $result;
				$response = new Response(200, $auth);
				$response->send();
			}, null, function() use ($token, $permission, $claim, $config) {
				$claim = $this->evaluateClaim($claim, "Delete", $config);
				return $permission->__invoke($token, $claim);
			}));			
		}
		
		private function getOperation($query) {
			$type = 'SIMPLE';
			if(array_key_exists('table_type', $this->config)) {
				$type = strtoupper($this->config['table_type']);
			}
			$operation = 'CREATE';
			switch($type) {
				case 'SIMPLE':
					if(!is_null($query->vars) && !empty($query->vars)) {
						$operation = 'UPDATE';
					}
					break;
				case 'BASIC':
					if(!is_null($query->vars) && !empty($query->vars) && in_array('ID', $query->vars)) {
						$operation = 'UPDATE';
					}
					break;
				case 'VERSIONED':
					if(!is_null($query->vars) && !empty($query->vars) && in_array('ID', $query->vars) && in_array('VERSION', $query->vars)) {
						$operation = 'UPDATE';
					}
					break;
			}
			return $operation;
		}
		
		protected function evaluateClaim($claim, $flag, $config) {
			if(is_callable($claim)) {
				return $claim->__invoke($flag, $this->query, $config);
			} else if (is_array($claim)) {
				if(array_key_exists($flag, $claim) && isset($claim[$flag])) {
					return $claim[$flag];
				} else {
					return NULL;
				}
			} else {
				return $claim;
			}
		}
	}
?>