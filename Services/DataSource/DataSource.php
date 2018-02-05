<?php 
include_once(dirname(__FILE__) ."/../../Table.php");
include_once(dirname(__FILE__) ."/Reference.php");


class DataSource {
    static $table_directory = "/../Tables/";
    static $source_config = '/../Tables/DataSource.json';
    static $join_config = '/../Tables/DataSourceJoin.json';
    static $trigger_config = '/../Tables/DataSourceTrigger.json';
    
    
    public static function getDataSource($id, $name = null) {
        $source_table = new Table(json_decode(file_get_contents(dirname(__FILE__).DataSource::$source_config), true));       
        $join_table = new Table(json_decode(file_get_contents(dirname(__FILE__).DataSource::$join_config), true));
        $trigger_table = new Table(json_decode(file_get_contents(dirname(__FILE__).DataSource::$trigger_config), true));
        
        $query = (is_null($id) && !is_null($name)) ? new Query(["Name" => ["=" => $name]]) : new Query(["ID" => ["=" => $id]]);
        $source_result = $source_table->read($query);
        
        if($source_result && count($source_result)) {
            $source_result = $source_result[0];
        }
        $source = [
            "ID" => DataSource::safeGet($source_result, "ID"),
            "Name" => DataSource::safeGet($source_result, "Name"),
            "Table" => DataSource::safeGet($source_result, "Root"),
            "Structure" => [
                "Table" => DataSource::safeGet($source_result, "Definition"),
                "Key" => DataSource::safeGet($source_result, "Reference")
            ]
        ];

        $joins_result = $join_table->read(new Query(["DataSource" => ["=" => $source['ID']]]));
        $formatted_joins = [];
        foreach($joins_result as $join) {
            $formatted_joins[] = [
                "Table" => DataSource::safeGet($join, 'Root'),
                "Type" => DataSource::safeGet($join, 'Type'),
                "Alias" => DataSource::safeGet($join, 'Name')
            ];
        }
        $source["Joins"] = $formatted_joins;
        
        $triggers_result = $trigger_table->read(new Query(["DataSource" => ["=" => $source['ID']]]));
        $source["Triggers"] = $triggers_result;
        
        return $source;
    }
    
    public static function getDataType($source, $id, $version = null) {
        $root_table_config = json_decode(file_get_contents(dirname(__FILE__).DataSource::$table_directory.DataSource::safeGet($source, "Table").".json"), true);
        $root_table = new Table($root_table_config);
        $structure_table = new Table(json_decode(file_get_contents(dirname(__FILE__).DataSource::$table_directory.DataSource::safeGet($source, ["Structure", "Table"]).".json"), true));
        $root_table->join($structure_table, [DataSource::safeGet($source, ["Structure", "Key"]) => 'ID']);
        $versioned = $root_table_config['table_type'] == 'VERSIONED';
        $read_query = $versioned ? new Query(['AND' => [['ID' => ['=' => $id]], ['VERSION' => ['=' => $version]]]]): new Query(['ID' => ['=' => $id]]);
        $data = $root_table->read($read_query);
        if($data && count($data)) {
            $data = $data[0];
        }
        $data_type = [];
        $type_name = DataSource::safeGet($source,['Structure','Table']);
        foreach($data as $column => $value) {
            if(strpos($column, $type_name.".") === 0) {
                $data_type[str_replace($type_name.".", "", $column)] = $value;
            }
        }
        if(!is_null(DataSource::safeGet($data_type, "Structure"))) {
            $data_type["Structure"] = json_decode($data_type["Structure"], true);
        }
        if(!is_null(DataSource::safeGet($data_type, "Actions"))) {
            $data_type["Actions"] = json_decode($data_type["Actions"], true);
        }
        return $data_type;
    }
    
    public static function getType($source, $data) {
        $structure_table = new Table(json_decode(file_get_contents(dirname(__FILE__).DataSource::$table_directory.DataSource::safeGet($source, ["Structure", "Table"]).".json"), true));
        $read_query = new Query(['ID' => ['=' => DataSource::safeGet($data, DataSource::safeGet($source, ["Structure", "Key"]))]]);
        $data = $structure_table->read($read_query);
        if($data && count($data)) {
            $data = $data[0];
        }
        if(!is_null(DataSource::safeGet($data, "Structure"))) {
            $data["Structure"] = json_decode($data["Structure"], true);
        }
        if(!is_null(DataSource::safeGet($data, "Actions"))) {
            $data["Actions"] = json_decode($data["Actions"], true);
        }
        return $data;
    }
    
    public static function getData($source, $id, $version = null) {
        $root_table_config = json_decode(file_get_contents(dirname(__FILE__).DataSource::$table_directory.DataSource::safeGet($source, "Table").".json"), true);
        $root_table = new Table($root_table_config);
        $structure_table = new Table(json_decode(file_get_contents(dirname(__FILE__).DataSource::$table_directory.DataSource::safeGet($source, ["Structure", "Table"]).".json"), true));
        $root_table->join($structure_table, [DataSource::safeGet($source, ["Structure", "Key"]) => 'ID']);
        
        $versioned = $root_table_config['table_type'] == 'VERSIONED';
        if(!is_null(DataSource::safeGet($source, 'Joins'))) {
            foreach(DataSource::safeGet($source, 'Joins') as $join) {
                if(DataSource::safeGet($join, 'Type') !== 'Repeater') {
                    $join_table = new Table(json_decode(file_get_contents(dirname(__FILE__).DataSource::$table_directory.DataSource::safeGet($join, "Table").".json"), true));
                    $join_params = [DataSource::safeGet($source,'Table') => 'ID'];
                    if($versioned) {
                        $join_params[DataSource::safeGet($source,'Table')."_VERSION"] = 'VERSION';
                    }
                    $root_table->join($join_table, $join_params);
                }
            }
        }       
        
        $read_query = $versioned ? new Query(['AND' => [['ID' => ['=' => $id]], ['VERSION' => ['=' => $version]]]]): new Query(['ID' => ['=' => $id]]);
        $data = $root_table->read($read_query);
        if($data && count($data)) {
            $data = $data[0];
        }
        if(!is_null(DataSource::safeGet($source, 'Joins'))) {
            $join_query = $versioned ? new Query(['AND' => [[DataSource::safeGet($source,'Table').'_VERSION' => ['=' => $id]], [DataSource::safeGet($source, 'Table') => ['=' => $id]]]]): new Query([DataSource::safeGet($source,'Table') => ['=' => $id]]);
            foreach(DataSource::safeGet($source, 'Joins') as $join) {
                if(DataSource::safeGet($join, 'Type') == 'Repeater') {
                    $join_table = new Table(json_decode(file_get_contents(dirname(__FILE__).DataSource::$table_directory.DataSource::safeGet($join, "Table").".json"), true));
                    $results = $join_table->read($join_query);
                    if($results) {
                        $data[DataSource::safeGet($join,'Alias')] = $results;
                    }
                }
            }
        } 
        $reference = new Reference();
        $data = DataSource::formatData($source, $data, $reference);
        return $data;
    }
    
    private static function safeGet($data, $key) {
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
    
    private static function formatData($source, $data, $reference) {
        $structure = json_decode(DataSource::safeGet($data, DataSource::safeGet($source,['Structure','Table']).".Structure"), true);
        $extended_data = json_decode(DataSource::safeGet($data, DataSource::safeGet($source,'Table').".Data"), true);
        $formatted = [];
        foreach($structure as $section => $definition) {
            $formatted[$section] = [];
            $section_base = !empty($definition['Base']) ? $definition['Base'] : false;
            if(DataSource::safeGet($definition,"Type") == "flat") {
                foreach(DataSource::safeGet($definition,'Contents') as $item) {
                    $item_base = !empty($item['Base']) ? $item['Base'] : false;
                    $value = DataSource::formatItem($source, $data, $extended_data, ['Name' => $section, 'Base' => $section_base], $item, $reference);
                    $formatted[$section][$item['Name']] = $value;
                }
            } else if(DataSource::safeGet($definition,"Type") == 'list') {
                $row_data = $section_base? DataSource::safeGet($data, $section) : DataSource::safeGet($extended_data,$section);
                foreach($row_data as $row) {
                    $frow = [];
                    foreach(DataSource::safeGet($definition,'Contents') as $item) {
                        $item_base = !empty($item['Base']) ? $item['Base'] : false;
                        $extended_row_data = $section_base?[$section => json_decode(DataSource::safeGet($row, 'Data'), true)]:$row;
                        $value = DataSource::formatItem(null, $row, $extended_row_data, ['Name' => $section, 'Base' => true], $item, $reference);
                        $frow[$item['Name']] = $value;
                    }
                    $formatted[$section][] = $frow;
                }
            }
        }
        return $formatted;
    }
    
    private static function formatItem($source, $data, $extended_data, $section, $item, $reference) {
        $base = !empty($item['Base']) && !empty($section['Base']) && $item['Base'] && $section['Base'];
        $table = (!is_null($source)) ? $source['Table']."." : "";
        $key =  $base ? ([$table.str_replace(" ", "_", $item['Name'])]): [str_replace(" ", "_", $section['Name']), str_replace(" ", "_", $item['Name'])];
        switch($item['Type']) {
            case 'address':
                return [
                "Line1" => $base?$data[$key[0]."_Line1"]:$extended_data[$key[0]][$key[1]."_Line1"],
                "Line2" => $base?$data[$key[0]."_Line2"]:$extended_data[$key[0]][$key[1]."_Line2"],
                "Line3" => $base?$data[$key[0]."_Line3"]:$extended_data[$key[0]][$key[1]."_Line3"],
                "City" => $base?$data[$key[0]."_City"]:$extended_data[$key[0]][$key[1]."_City"],
                "State" => $base?$data[$key[0]."_State"]:$extended_data[$key[0]][$key[1]."_State"],
                "Zip" => $base?$data[$key[0]."_Zip"]:$extended_data[$key[0]][$key[1]."_Zip"]
                ];
                break;
            case 'name':
                return [
                "Prefix" => $base?$data[$key[0]."_Prefix"]:$extended_data[$key[0]][$key[1]."_Prefix"],
                "First" => $base?$data[$key[0]."_First"]:$extended_data[$key[0]][$key[1]."_First"],
                "Middle" => $base?$data[$key[0]."_Middle"]:$extended_data[$key[0]][$key[1]."_Middle"],
                "Last" => $base?$data[$key[0]."_Last"]:$extended_data[$key[0]][$key[1]."_Last"],
                "Suffix" => $base?$data[$key[0]."_Suffix"]:$extended_data[$key[0]][$key[1]."_Suffix"]
                ];
                break;
            case 'select':
                $options = $reference->fetch($item["LookupType"]);
                $sort = $base?$data[$key[0]]:$extended_data[$key[0]][$key[1]];
                $index = array_search($sort, array_column($options, 'ID'));
                return ($index >= 0) ? $options[$index] : null;
            case 'checkbox':
                $data = $base ? (!empty($data[$key[0]]) ? $data[$key[0]] : false) : (!empty($extended_data[$key[0]][$key[1]]) ? $extended_data[$key[0]][$key[1]] : false);
                return $data ? true : false;
            default:
                return $base?$data[$key[0]]:$extended_data[$key[0]][$key[1]];
        }
    }
}

?>