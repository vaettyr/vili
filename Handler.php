<?php
	//a handler is a single method at an end-point
	//it only responds to a single http method
	//it can expect or require specific querystring parameters to be present
	//it can also specify an optional 'permissions' function, which receives the header and returns true or an error to pass back
	//then it calls a function with arguments for query string, body, and file parameters (if present)
	//the function is responsible for returning the response with appropriate headers, etc.
	class Handler {
		private $method;	//the type of http method this handler expects
		private $query;		//any query string params or values this handler expects
		private $function;	//the body of the function for this handler
		private $permission;//the permission handler
		
		function __construct($methodType, $responseFunction, $queryParams = null, $permissionFunction = null){
			$this->method = $methodType;
			$this->function = $responseFunction;
			$this->query = $queryParams;
			$this->permission = $permissionFunction;
		}
		
		function check($method, $query) {
			$match = 0;
			//$ismethod = strtoupper($method) === strtoupper($this->method);
			if(strtoupper($method) === strtoupper($this->method)) { $match++; }
			$isquery = is_null($this->query);
			
			if($isquery === false && is_null($query) === false){
				$isquery = true;
				foreach($this->query as &$qr)
				{
					if(in_array($qr, $query) === false)
					{
						$isquery = false;
						$match = 0;
						break;
					} else {
						$match++;
					}
				}
			}
			//return $ismethod === true && $isquery === true;
			return $match;
		}
	
		function go() {
			//if there are permissions issues, handle those first
			
			if(!is_null($this->permission))
			{
				$allowed = $this->permission->__invoke();
				$this->function->__invoke($allowed);
				return true;
			}
			else
			{
				$this->function->__invoke();
				return true;
			}
		}

	}		
?>