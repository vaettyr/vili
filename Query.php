<?php
	//this is used to parse and build complex logic for comparisons
	//it can parse from a string (query)
	//it can produce a list of all variables it references
	//it can output a WHERE clause
	//formatting:
	
	//an array of comparisons
	//[{variable_name:{comparison:values}}]
	//[{and/or:[{variable_name:{comparison:values},{variable_name:{comparison:values}}]}]
	
class Query {
	private $structure;
	private $type;
	public $vars;
	
	function __construct($str, $tables = null) {
		//if this is a string, decode from json
		//or it might be a standard query string
		//if it's already an array, do nothing
		$this->vars = array();
		if(is_array($str)) {
			$this->type = "standard";
			$this->structure = $str;
			$this->_getVars($this->structure);
		} else {
			$this->type = 'simple';
			parse_str($str, $this->structure);
			$keys = array_keys($this->structure);
			
			$count = 0;
			$sub = $this->structure;
			foreach($this->structure as $key=>$value) {
				if($tables != null && in_array($key, $tables)) {
					$json = json_decode($this->structure[$key], true);
					if(json_last_error() == JSON_ERROR_NONE && is_array($json)) {
						if($count == 0) {
							$this->type = "standard";
							$sub = $this->_validate($json, $key);
						} else if($count == 1) {
							$sub = ["and" => [$sub]];
							$sub['and'][] = $this->_validate($json, $key);
						} else {
							$sub['and'][] = $this->_validate($json, $key);
						}
						$this->_getVars($json);
						$count++;
					} else {
						//throw an error
						throw new Exception(json_last_error_msg());
					}
				} else {
					$this->vars[] = $key;
				}
			}
			//echo json_encode($sub);
			$this->structure = $sub;		
		}	
	}
	
	private function _getVars($arr) {
		if(is_array($arr)) {
			foreach($arr as $key => $value) {
				if(!is_numeric($key) && strtoupper($key) != "AND" && strtoupper($key) != "OR" && !in_array($key, $this->vars)) {
					$this->vars[] = $key;
				}
				if(strtoupper($key) == "AND" || strtoupper($key) == "OR") {
					foreach($value as $sub) {
						$this->_getVars($sub);
					}										
				}
			}
		}
		
	}

	private function _validate($json, $table) {
		//root level is either and, or, or a variable
		$keys = array_keys($json);
		//it should only ever have one key
		if(count($keys) != 1) {
			throw new Exception("Invalid query string: too many keys.", 400);
		}
		if(!is_null($table)) {
			if(strtoupper($keys[0]) == 'AND' || strtoupper($keys[0]) == 'OR') {
				$sub = array();
				foreach($json[$keys[0]] as $elem) {
					$sub[] = $this->_validate($elem, $table);
				}
				return [$keys[0] => $sub];
			} else {
				return [$table.".".$keys[0] => $json[$keys[0]]];
			}
		} else {
			return $json;
		}		
	}
	
	public function toQuery($columns = null, $table = null) {
		$str = "";
		if($this->type == "simple") {
			$first = true;
			foreach($this->structure as $key => $value) {
				if((is_null($columns) || array_key_exists($key, $columns)) && !is_null($value)) {
					if(!$first) { $str = $str." AND "; } else { $first = false; }
					$str = $str.(!is_null($table)?$table.'.':'').$key."=".$this->get_value($value);
				}
			}
		} else {
			$str = $str.$this->_parseElem($this->structure, $columns, $table);
		}
		return $str;
	}
	
	private function _parseElem($elem, $columns, $table) {
		$str = "";
		$keys = array_keys($elem);
		//get the first key and mark it up
		$name = $keys[0];
		$pos = strpos($name, '.');
		if($pos !== false && $pos >= 0) {
			$name = substr($name, $pos + 1);
		}
		if(strtoupper($name) == "AND" || strtoupper($name) == "OR") {
			$str = $str."(";
			$first = true;
			foreach($elem[$keys[0]] as $item) {
				if(!$first) { $str = $str.((strtoupper($keys[0])=="AND") ? " AND ":" OR "); } else { $first = false; }
				//echo json_encode($item);
				$str = $str.$this->_parseElem($item, $columns, $table);
			}
			$str = $str.")";
		} else if(is_null($columns) || array_key_exists($name, $columns)) {
			$str = $str.(!is_null($table)?$table.'.':'').$keys[0];
			$comp = $elem[$keys[0]];
			$sub = array_keys($comp);
			$str = $str.' '.$sub[0].' '.$this->get_value($comp[$sub[0]]);
		}
		return $str;
	}
	
	private function get_value($arg) {
		$value = " ";
		if(is_numeric($arg)) {
			$value = $value.$arg;
		} else if (is_null($arg)) {
			$value = 'NULL';
		} else {
			$value = $value." '".str_replace("'", "\'", $arg)."' ";
		}
		return $value;
	}
	
}
?>