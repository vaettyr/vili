<?php
	include_once("Handler.php");
	include_once("Response.php");
	include_once("Query.php");
	//contains a series of handlers
	//finds the correct handler and calls it
	//maybe utility functions like general responses?
	
class Endpoint {
	protected $method;						//get http method
	protected $query; 						//get query string parameters
	protected $body;						//get json body parameters
	protected $token;						//authorization token for handlers with permissions
	
	protected $handlers;
	
	function __construct($token = null, $tables = null, $query = null) {
		$this->method = $_SERVER["REQUEST_METHOD"];	
		if(is_null($query)) {
			$query = $_SERVER['QUERY_STRING'];
		}
		$this->query = new Query($query, $tables);
		$this->body = json_decode(file_get_contents('php://input'),TRUE);
		
		$this->token = null;
		if(!is_null($token)) {
			$this->token = $this->getToken($token);
		}		
		
		//default handler every service needs
		$preflight = new Handler("OPTIONS", function(){
			header('Access-Control-Allow-Origin: *');
			header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
			header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
			exit();
		});
		$this->handlers[] = $preflight;
	}
	
	function register($handler) {
		$this->handlers[] = $handler;
	}

	private function getToken($token) {
		try {
			$headers = getallheaders();
			if(array_key_exists($token, $headers) && isset($headers[$token]) )
			{
				return $headers[$token];
			}
			else
			{
				return null;
			}
		}
		catch (Exception $ex) {
			return null;
		}
	}
	
	function token() {
		return $this->token;
	}
	
	function go() {
		$best_match = 0;
		$match = 0;
		$target = null;
		foreach($this->handlers as &$handler)
		{
			$match = $handler->check($this->method, $this->query->vars);
			if($match > $best_match){
				$best_match = $match;
				$target = $handler;
			}
		}
		if($best_match > 0) {
			try {
				$target->go();
			}
			catch (Exception $ex) {
				$response = new Response($ex->getCode(), $ex->getMessage());
				$response->send();
				exit();
			}
			exit();
		} else {
			$response = new Response(500, "No handler found.");
			$response->send();
			exit();
		}
	}
}
?>