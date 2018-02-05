<?php 
    include_once(dirname(__FILE__)."/../../../Table.php");
    include_once(dirname(__FILE__)."/../Actions.php");

    class SaveSource implements iAction {
        public static function getDefinition(){
            return [
                "Name" => "Save",
                "Description" => "Saves the data to the database, along with all associated records",
                "Parameters" => [
                    ["Name" => "Source", "Type" => "Default"],
                    ["Name" => "Data", "Type" => "Default"]
                ]
            ];
        }
        
        public function execute($args) {
            //write the data into the appropriate tables!
            $source = $this->safeGet($args, "Source");
            $data = $this->safeGet($args, "Data");
            $root = $this->safeGet($source, "Table");
            $root_config = json_decode(file_get_contents(dirname(__FILE__)."/../../Tables/".$root.".json"), true);
            $root_table = new Table($root_config);
            $joins = [];
            foreach($this::safeGet($source, "Joins") as $join) {
                if($this::safeGet($join, "Type") == "Repeater") {
                    $join_config = json_decode(file_get_contents(dirname(__FILE__)."/../../Tables/".$this->safeGet($join, "Table").".json"), true);
                    $joins[$this->safeGet($join, "Alias")] = new Table($join_config);
                }            
            }
            $table_name = $this->safeGet($source, 'Table');
            //in theory, this data is already formatted for the database, yes?
            $conn = $root_table->transaction("open");
            foreach($joins as $alias => $table) {
                $table->transaction('set', $conn);
            }          
            
            $object = $root_table->create($data);
            
            foreach($joins as $alias => $table) {
                //iterate through the appropriate corresponding data
                $object[$alias] = [];
                foreach($data[$alias] as $item) {
                    //$item = json_decode(json_encode($item), true);
                    $item[$table_name] = $object['ID'];
                    if($this->safeGet($root_config, 'table_type') == 'VERSIONED') {
                        $item[$table_name."_VERSION"] = $object['VERSION'];
                    }
                    $object[$alias][] = $table->create($item);
                }
            }
            
            $root_table->transaction("commit");
            return $object;
        }
        
        private function safeGet($data, $key) {
            if(!is_array($key)) {
                return !empty($data[$key]) ? $data[$key] : null;
            } else {
                $item = $data;
                foreach($key as $index) {
                    $item = !empty($item[$index]) ? $item[$index] : null;
                }
                return $item;
            }
        }
    }
?>