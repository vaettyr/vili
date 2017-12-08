<?php 
	include_once("Response.php");
	include_once("Table.php");
	//responsible for handingling exceptions generally throughout the application

	class Logger {
		private $config;
		private $config_name;
		
		function __construct($config = null, $configname = null) {
			if(is_null($config))
				$config = "config.php";
			$this->config = $config;
			
			if(is_null($configname))
				$configname = "data_config";
			
			$this->config_name = $configname;		
			
			include_once($this->config);					
		}
		
		function handleException($ex) {
			//log the exception
			if(!is_dir(constant($this->config_name."::exceptionlog"))) {
				mkdir(constant($this->config_name."::exceptionlog"), '0777', true);
			}
			$log = fopen(constant($this->config_name."::exceptionlog").'/'.date('Y-m-d').'.log', 'a+');
			//build the message
			//need the date, time, maybe connection details of where it originated from
			$entry = $_SERVER['REQUEST_TIME_FLOAT'].": ".$_SERVER['REMOTE_ADDR']."\r";
			$entry .= "\t".$_SERVER['HTTP_USER_AGENT']."\r";
			$entry .= "\t".$_SERVER['REQUEST_URI']."\r";
			$entry .= "\t".$ex->getMessage()."\r\r";
			fwrite($log, $entry);
			fclose($log);
			//return a 500 error
			$response = new Response(500, "Unhandled exception. See exception log for details");
			$response->send();
		}
		
		function logActivity($action) {
			//can log various levels of general activity
			//can be configuted to log activity in a local file
			//or log it onto the database
			//also supports multiple log levels
			//0 - don't log any activity (exceptions only)
			//1 - log data manipulation (insert, update, delete), but not reads
			//2 - log everything

			switch(constant($this->config_name.'::loglevel')) {
				case 1:
					if($action['TYPE'] != 'READ' && $action['TYPE'] != 'CHANGE') {
						return $this->handleLogActivity($action);
					}
					break;
				case 2:
					return $this->handleLogActivity($action);
					break;
				default:
					return;
			}
		}
		
		private function handleLogActivity($action) {
			switch(constant($this->config_name."::loglocation")) {
				case "DB":
					return $this->logDBEntry($action);
					break;
				default:	
					$this->logFileEntry($action);
					break;
			}
		}
		
		private function logFileEntry($action) {
			if(!is_dir(constant($this->config_name."::loglocation"))) {
				mkdir(constant($this->config_name."::loglocation"), '0777', true);
			}
			$log = fopen(constant($this->config_name."::loglocation").'/'.date('Y-m-d').'.log', 'a+');
			$entry = "TIME: ". microtime(true).", ACTION: ".$action['TYPE']." ".$action['NAME'].' '.$action['PROCESS']."\r";
			$entry .= "\t PARAMS: ".(isset($action['PARAMS']) ? json_encode($action['PARAMS']) : '')."\r";
			$entry .= "\t PAYLOAD: ".(isset($action['PAYLOAD']) ? json_encode($action['PAYLOAD']) : '')."\r";
			$entry .= "\t RESULT: ".(isset($action['RESULT']) ? json_encode($action['RESULT']) : '')."\r\r";
			fwrite($log, $entry);
			fclose($log);
		}
		
		private function logDBEntry($action) {			
			$config = json_decode(file_get_contents("LOG.json"), true);			
			$table = new Table($config);		
			if(isset($action['ID']) && is_numeric($action['ID'])) {				
				$params = ['STATUS' => 'CLOSED'];
				if(isset($action['RESULT'])) {
					$params['RESULT'] = json_encode($action['RESULT']);
				}				
				$table->update($params, new Query(['ID'=>['=' => $action['ID']]]), true);
				
			} else {				
				$params = ['TYPE' => $action['TYPE'], 'NAME' => $action['NAME'], 'STATUS' => 'OPEN'];
				if(isset($action['PARAMS'])) { $params['PARAMS'] = json_encode($action['PARAMS']); }
				if(isset($action['PAYLOAD'])) { $params['PAYLOAD'] = json_encode($action['PAYLOAD']); }
				$response = $table->create($params, true);
				return $response['ID'];
			}
		}
	}
	
	//global error handler
	set_error_handler("interceptError", E_WARNING);
	function interceptError($errno, $errstr) {
		$logger = new Logger();
		$logger->handleException(new Exception($errstr));
	}
?>