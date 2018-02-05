<?php 
include_once(dirname(__FILE__)."/../Actions.php");

    class SendEmail implements iAction {
        public static function getDefinition(){
            return [
                "Name" => "Email",
                "Description" => "Saves the data to the database, along with all associated records",
                "Parameters" => [
                    ["Name" => "Recipients", "Type" => "Data"],
                    ["Name" => "Correspondence", "Type" => "Table", "Table" => "Correspondence", "Alias" => "Correspondences"],
                    ["Name" => "Data", "Type" => "Default"]
                ]
            ];
        }
        
        public function execute($args) {
            //get the correspondence template, bind our data to it, and send to the recipient
        }
    }
?>