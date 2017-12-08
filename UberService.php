<?php 
	include_once("Service.php");
	
	class UberService extends Service{
		
		protected $config;
		protected $config_name;
		
		function __construct($token = null, $permission = null, $claim = null, $config = null, $configname = null) {
			if(is_null($config))
				$config = "config.php";
			$this->config = $config;
			if(is_null($configname))
				$configname = "data_config";
			$this->config_name = $configname;
			
			include_once($this->config);
			
			$directory = constant($this->config_name."::tabledirectory");
			
			$query = $_SERVER['QUERY_STRING'];
			parse_str($query, $qarray);					
			$path = $directory.$qarray['table'].".json";
			unset($qarray['table']);
			$query = http_build_query($qarray);
			parent::__construct($path, $token, $query, $permission, $claim);
		}		
	}
?>