<?php
	class Response {
		//this is just to standardize all responses sent back from services
		protected $code;
		protected $content;
		protected $type;
		
		function __construct($code = 200, $content = 'OK', $type = null) {
			$this->code = $code;
			$this->content = $content;
			if(is_null($type)) {
				if(is_array($content)) {
					$this->type = "application/json";
				} else {
					$this->type = "text/plain";
				}
			} else {
				$this->type = $type;
			}
		}
		
		function send() {
			//set the headers, http code, and send the response
			header('Content-Type: '.$this->type);
			header('Access-Control-Allow-Origin: *');
			header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
			http_response_code($this->code);
			switch($this->type) {
				case "application/json":
					echo json_encode($this->content);
					break;
				default:
					echo $this->content;
					break;
			}
			exit();
		}
	}
 ?>