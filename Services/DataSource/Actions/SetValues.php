<?php 
include_once(dirname(__FILE__)."/../Actions.php");

    class SetValues implements iAction {
        public static function getDefinition(){
            return [
                "Name" => "Defaults",
                "Description" => "Sets default values",
                "Parameters" => [
                    ["Name" => "Values", "Type" => "Hash"],
                    ["Name" => "Data", "Type" => "Default"]
                ]
            ];
        }
        
        public function execute($args) {
            //goes through and applies the default values to the underlying incoming data
        }
    }
?>