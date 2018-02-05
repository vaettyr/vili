<?php 
include_once(dirname(__FILE__) ."/../../Table.php");


    interface iAction {
        public static function getDefinition();
        public function execute($args);
    }

    class Actions {        

        private static $table_directory = "../Tables/";
        private static $action_directory = "/Actions/";
        private static $action_table = "DataSourceAction.json";
        private static $action_type_table = "ActionType.json";
        
        public static function getActionTypes() {
            $directory = scandir(dirname(__FILE__).Actions::$action_directory);
            if($directory) {
                //parse through the contents
                $dir = array();
                foreach($directory as $path) {
                    $con = strpos($path, ".php");
                    if($con > 0) {
                        $class = str_replace('.php', '', $path);
                        include_once(dirname(__FILE__).Actions::$action_directory.$path);  
                        $definition = call_user_func(array($class, 'getDefinition'));
                        $definition['Handler'] = $class;
                        $definition['Parameters'] = json_encode($definition['Parameters']);
                        $dir[] = $definition;
                    }
                }
                return $dir;
            }
        }

        
        private static function safeGet($data, $key) {
            if(!is_array($key)) {
                return !is_null($data[$key]) ? $data[$key] : null;
            } else {
                $item = $data;
                foreach($key as $index) {
                    $item = !is_null($item[$index]) ? $item[$index] : null;
                }
                return $item;
            }
        }
    }
?>