<?php 
include_once("../Endpoint.php");
include_once("../Table.php");
include_once("../Logger.php");
include_once("./DataSource/DataSource.php");
include_once("./DataSource/Actions.php");

class SequenceHandler {
    static function executeSequence($sequence, $source, $data) {
        //each time through can return data and add it to a growing bank that can be bound to parameters for any action
        //also builds an array response object which is consumed by the client to execute bits on their end?
        $aggregate = [];
        foreach($sequence as $action) {
           //include the appropriate php file
            include_once(dirname(__FILE__) ."/DataSource/Actions/".$action['Action'].'.php');           
           //instantiate the class
            $step = new $action['Action']();
           //call the execute with the appropriate arguments
           $args = $action['Parameters'];
           $args['Data'] = $data;
           $args['Source'] = $source;
           //save the results
           $result = $step->execute($args);
           //aggregate them
           $aggregate[$action['Action']] = $result;
           //return the aggregate?        
        }
        
        $response = new Response(200, $aggregate);
        $response->send();
    }
}

class ActionHandler extends Endpoint{
    
    function __construct($token = null) {
        parent::__construct($token);
        
        parse_str($_SERVER['QUERY_STRING'], $query);
        $body = json_decode(file_get_contents('php://input'),true);
        
        $token = $this->token;
        
        //post handler is used to execute sequences against data that has not yet been saved (preview)
        $this->register(new Handler("GET", function() use($query) {
            $id = empty($query['id']) ? null : $query['id'];
            $name = empty($query['DataSource']) ? null : $query['DataSource'];
            $response = new Response(200, DataSource::getDataSource($id, $name));
            $response->send();
        },["DataSource"]));
        
        $this->register(new Handler("GET", function() {
            $response = new Response(200, ["data" => Actions::getActionTypes()]);
            $response->send();
        }, ['ActionTypes']));
        
        $this->register(new Handler("POST", function() use($query, $body) {
            $source_id = $query['source'];
            $trigger_id = $query['trigger'];
            
            $source = DataSource::getDataSource($source_id);
            //we can get our key type from the incoming data
            
            $type = DataSource::getType($source, $body);
            $type_id = $type['ID'];
            
            $sequence = $type['Actions'][$trigger_id];

            SequenceHandler::executeSequence($sequence, $source, $body);
        }));
        
        //this handles executing a sequence for an item which is already saved in the database. 
        $this->register(new Handler("GET", function() use($query) {
            $source_id = $query['source'];
            $id = $query['id'];
            $version = $query['v'];
            $trigger_id = $query['trigger'];

            $source = DataSource::getDataSource($source_id);
            $type = DataSource::getDataType($source, $id, $version);
            $type_id = $type['ID'];

            $sequence = $type['Actions'][$trigger_id];
            $data = DataSource::getData($source, $id, $version);
            
            //$response = new Response(200, $sequence);
            //$response->send();  
            SequenceHandler::executeSequence($sequence, $source, $data);
        }));
    }
}

$service = new ActionHandler();
$service->go();
?>