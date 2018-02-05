<?php 
include_once(dirname(__FILE__) ."/../../Table.php");

/*
 * Reference class is responsible for fetching in-line references to other tables from data objects
 * a good example is lookup values
 * it is instantiated as a part of fetching data so that multiple references to the same table only cause a single fetch
 * 
 * It should be expanded to support references to other tables (lookup only right now)
 */

class Reference {
    static $config = '/../Tables/Lookup.json';
    private $table;
    private $contents;
    
    function __construct() {
        $reference_config = json_decode(file_get_contents(dirname(__FILE__) .Reference::$config), true);
        $this->table = new Table($reference_config);
        $this->contents = [];
    }
    
    function fetch($type) {
        if(!isset($this->contents[$type])) {
            $result = $this->table->read(new Query(["LookupType" => ["=" => $type]]));
            $this->contents[$type] = $result;
            return $result;
        } else {
            return $this->contents[$type];
        }
    }
    
    function all() {
        return $this->contents;
    }
}
?>