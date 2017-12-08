<?php 
include_once("../UberService.php");
include_once("./Helper.php");

class PublicDataService extends UberService {
	function __construct() {
		$query = $_SERVER['QUERY_STRING'];
		parse_str($query, $qarray);
		$open_tables = json_decode(file_get_contents("./Permission_Public.json"), true);
		
		parent::__construct("Authorization", function($token, $claim=null) {
			return $this->permissionFunction($token, $claim);
		}, function($flag, $query, $config) use ($open_tables) {
			return $this->claimFunction($flag, $query, $config, $open_tables);
		});
	}
	//claim can be an array, a function, or a string
	//the result is passed into the permission function
	//if it is a function, it is passed a flag, the query, and a config
	//the config is the name of the json file that has the table structure
	function claimFunction($flag, $query, $config=null, $open_tables) {
		$permission = false;
		if(!is_null($config)) {
			$trim = substr($config, strrpos($config, '/') + 1);
			$trim = substr($trim, 0, strrpos($trim, '.json'));
			if(array_key_exists($trim, $open_tables)) {
				$lookup = $open_tables[$trim];
				if(array_key_exists(!is_null($flag)? $flag: "Access", $lookup)) {
					$permission = $lookup[!is_null($flag)? $flag: "Access"];
				}
			}
		}
		return $permission;
	}
	
	function permissionFunction($token, $claim = null ) {
		//token will always be empty, just go by the claim
		if($claim) {
			return Array();
		} else {
			$response = new Response(403, 'Access denied.');
			$response->send();
		}
	}
}
$dataService = new PublicDataService();
$dataService->go();
?>