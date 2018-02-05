<?php   
    //output will be a rendered html file. This can be passed to other services for processing to other formats (such as PDF)    
    include_once(dirname(__FILE__)."/../../../Table.php");
    include_once(dirname(__FILE__)."/../../../dompdf/autoload.inc.php");
    include_once(dirname(__FILE__)."/../Actions.php");
    use Dompdf\Dompdf;
    
    class RenderForm implements iAction {
        public static function getDefinition(){
            return [
                "Name" => "Render Form",
                "Description" => "Generates a form based on a Form ID and data passed in from the source",
                "Parameters" => [
                    ["Name" => "Form", "Type" => "Table", "Table" => "Form", "Alias" => "Forms"], 
                    ["Name" => "Data", "Type" => "Default"], 
                    ["Name" => "Save", "Type" => "Bool"], 
                    ["Name" => "Output", "Type" => "Enum", "Options" => ["PDF", "HTML"]]
                ]
            ];
        }
    
        public function execute($args) {
            $data_string  = RenderForm::render($args['Form'], $args['Data'], $args['Output']);
            //if we're supposed to save the output, we should do that here also
            //file_put_contents("TEST.pdf", $data_string);
            return $data_string;
        }
        
        
        static $form_config = '/../../Tables/Form.json';
        static $source_config = '/../../Tables/DataSource.json';
        static $sourcechild_config = '/../../Tables/DataSourceJoin.json';
        
        static $html_template_open = "<!DOCTYPE html>
            <html>
                <head>
                    <meta charset='UTF-8'>
                    <title>[TITLE]</title>
                    <style>[STYLE]</style>
                </head>
                <body>";
        static $html_style = "
        @page { margin: 0px; }
        body { margin: 0px; }
        .pageBuffer {
            top: 0px;
            left: 0px;
            width: 100%;
            height: 100%;
            display: flex;
            position: absolute;
        }
        .pageContents {
            position:relative;
            width:100%;
            display:block;
        }
        .pageFix {
            height: 100%;
            display: block;
            position: relative;
        }
        .Footer {
            width: 100%;
            bottom: 0px;
            position: absolute;
        }
        .Page, .Header, .Footer {
            display:block;
        },
        .Container, .Text, .Data, .Image, .Repeater {
            display:inline-block;
        }
        ";
        static $html_template_close = "
                </body>
            </html>";

        public function render($form_id, $bindable, $output = 'PDF') {
            //$this->verifyInput($query, true);
            //$form_definition = $this->getFormDefinition($query['form']);
            $form_definition = $this->getFormDefinition($form_id);
            $type = $this->safeGet($form_definition, "source");
            
            //$bindable = DataSource::getData($type, $this->safeGet($query, 'id'), $this->safeGet($query, 'v'));
            
            $form = $this->safeGet($form_definition, 'form');
            $title = $this->safeGet($form_definition, 'Name');
            
            $size = [
                "Size" => $this->safeGet($form,'Size'),
                "Width" => $this->safeGet($form,'Width'),
                "Height" => $this->safeGet($form,'Height')
            ];
            
            switch($output) {
                case 'HTML':
                    $rendered = str_replace('[STYLE]', RenderForm::$html_style, str_replace('[TITLE]', $title, RenderForm::$html_template_open));
                    foreach($this->safeGet($form,'Pages') as $page) {
                        $pagestr = $this->startPage($size, $this->safeGet($form, 'Style'));
                        $pagestr .= $this->renderObject($this->safeGet($form,'Header'), $bindable);
                        $pagestr .= $this->renderObject($page, $bindable);
                        $pagestr .= $this->renderObject($this->safeGet($form,'Footer'), $bindable);
                        $rendered .= $this->endPage ($pagestr);
                    }
                    $rendered .= RenderForm::$html_template_close;
                    return $rendered;
                case 'PDF':
                    $dompdf = new Dompdf();
                    $style = $this->safeGet($form, 'Style');
                    $margin_bottom = true;
                    if(!empty($style)) {
                        $key = array_search('margin', array_column($style, 'Name'));
                        if($key !== false) {
                            $margin = $style[$key];
                            $sides = $this->safeGet($margin, 'Sides');
                            if(empty($sides) || !empty($this->safeGet($sides, 'Bottom'))) {
                                $margin_bottom = $this->safeGet($margin, 'Value');
                            }
                            $pagestyle = $this->renderStyle([$margin]);
                            unset($style[$key]);
                        } else {
                            $pagestyle = "margin:0px;padding:0px";
                        }
                        $bodystyle = $this->renderStyle($style);
                        $stylestring = "@page {".$pagestyle."} body {".$bodystyle."}";
                    } else {
                        $stylestring = "@page, body {margin:0px;padding:0px}";
                    }
                    
                    $html = str_replace('[STYLE]', $stylestring, str_replace('[TITLE]', $title, RenderForm::$html_template_open));
                    
                    $html .= $this->renderObject($this->safeGet($form, 'Header'), $bindable, true);
                    $html .= $this->renderObject($this->safeGet($form, 'Footer'), $bindable, $margin_bottom);
                    $pagecount = count($this->safeGet($form, 'Pages'));
                    foreach($this->safeGet($form,'Pages') as $index => $page) {
                        $pagestr = $this->startPage(null, null, $index == $pagecount - 1);
                        $html .= $this->renderObject($this->safeGet($form, 'Header'), $bindable);
                        $pagestr .= $this->renderObject($page, $bindable, $margin_bottom);
                        $html .= $this->endPage ($pagestr);
                    }
                    $html .= RenderForm::$html_template_close;
                    $dompdf->loadHtml($html);
                    switch($this->safeGet($form, 'Size')) {
                        case "8.5x11":
                            $dompdf->setPaper('letter', 'portrait');
                            break;
                        case "11x8.5":
                            $dompdf->setPaper('letter', 'landscape');
                            break;
                        case "8.5x14":
                            $dompdf->setPaper('legal', 'portrait');
                            break;
                        case "14x8.5":
                            $dompdf->setPaper('legal', 'landscape');
                            break;
                        default:
                            $dim = [0, 0, $this->safeGet($form, 'Width'), $this->safeGet($form, 'Height')];
                            $dompdf->setPaper($dim, 'portrait');
                            break;
                    }
                    $dompdf->render();
                    //$dompdf->stream($title.".pdf", ["Attachment" => false]);
                    return $dompdf->output();
                default:
                    return "Unknown file type.";
            }
        }

        //safeget
        function safeGet($data, $key) {
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

        //gets the form and source definition
        function getFormDefinition($formid) {
            $form_table = new Table(json_decode(file_get_contents(dirname(__FILE__) .RenderForm::$form_config), true));
            //join the datasource
            $source_table = new Table(json_decode(file_get_contents(dirname(__FILE__) .RenderForm::$source_config), true));
            $form_table->join($source_table, ["DataSource" => "ID"]);
            $join_table = new Table(json_decode(file_get_contents(dirname(__FILE__) .RenderForm::$sourcechild_config), true));
            
            $form_definition = $form_table->read(new Query(['ID' => ['=' => $formid]]));
            if(!$form_definition) {
                $error = new Response(500, "Invalid Form");
                $error->send();
            }
            $form = $form_definition[0];
            //query the children separately
            $source = [
                "Name" => $this->safeGet($form, "DataSource.Name"),
                "Table" => $this->safeGet($form, "DataSource.Root"),
                "Structure" => [
                    "Table" => $this->safeGet($form, "DataSource.Definition"),
                    "Key" => $this->safeGet($form, "DataSource.Reference")
                ]
            ];
            $source_id = $this->safeGet($form, "DataSource.ID");           
            $joins = $join_table->read(new Query(["DataSource" => ["=" => $source_id]]));
            $formatted_joins = [];
            foreach($joins as $join) {
                $formatted_joins[] = [
                    "Table" => $join['Root'],
                    "Type" => $join['Type'],
                    "Alias" => $join['Name']
                ];
            }
            $source["Joins"] = $formatted_joins;
            $definition = json_decode($this->safeGet($form, "Form.Data"), true);
            return ["form" => $definition, "source" => $source, 'Name' => $this->safeGet($form, "Form.Name")];
        }
        
        //----------Page Rendering--------------------------//
        //opens a new page of the form
        function startPage($size=null, $style = null, $last = false) {
            $str = "<div style='position:relative;";
            if(!$last) {
                $str .= "page-break-after:always;";
            }
            if(!is_null($size)) {
                switch($size['Size']) {
                    case "8.5x11":
                        $str.= "width:8.5in;height:11in;";
                        break;
                    case "11x8.5":
                        $str.= "width:11in;height:8.5;";
                        break;
                    case "8.5x14":
                        $str.= "width:8.5in;height:14in;";
                        break;
                    case "14:8.5":
                        $str.= "width:14in;height:8.5in;";
                        break;
                    default:
                        $str.=("width:".$size['Width']."in;height:".$size['Height']."in;");
                        break;
                }
            }          
            $str.="'>";
            $str.="<div class='pageBuffer'><div class='pageContents' "; 
            //render page style here
            if(!is_null($style)) {
                $str .= "style='".$this->renderStyle($style)."' ";
            }
            $str.="><span class='pageFix'>";
            return $str;
        }
        //closes a page
        function endPage($str) {
            return $str."</span></div></div></div>";
        }
        //renders an element on a page (headers, footers, content, etc)
        function renderObject($def, $data, $for_pdf = false) {
            $str = "<span id='".$def['ID']."' class='".$def['Type']."' style='";
            $str .= $this->renderLayout($def);
            if(isset($def['Style'])) {
                $str .= $this->renderStyle($def['Style']);
            }
            if($for_pdf) {
                switch($this->safeGet($def, 'Type')) {
                    case 'Footer':
                        $str .= "position:fixed;bottom:";
                        $str .= ($for_pdf === true) ? "0px;" : $for_pdf.";";
                        break;
                    case 'Header':
                        $str .= "position:fixed;top:0px;";
                        break;
                    case 'Page':
                        $str .= "position:absolute;top:0px;";
                        if($for_pdf !== true) {
                            $str .= "padding-bottom:".$for_pdf.";";
                        }
                        break;
                }
            }
            $str .= "'>";
            switch($def['Type']) {
                case "Page":
                case "Header":
                case "Footer":         
                case "Container":
                    foreach($def['Children'] as $child) {
                        $str .= $this->renderObject($child, $data);
                    }
                    break;
                case "Text":
                    $str .= $def['Text'];
                    break;
                case "Repeater":
                    //repeated section
                    if(isset($data[$def['Source']])) {
                        foreach($data[$def['Source']] as $row) {
                            foreach($def['Children'] as $child) {
                                $str .= $this->renderObject($child, $row);
                            }
                        }
                    }
                    break;
                case "Data":
                    if(isset($def['Label'])) {
                        $str .= ($def['Label']." ");
                    }
                    if(isset($def['Field'])) {
                        $path = explode('.', $def['Field']);
                        switch(count($path)) {
                            case 2:
                                $value = $this->safeGet($data, [$path[0],$path[1]]);
                                break;
                            case 1:
                                $value = $this->safeGet($data,$path[0]);
                                break;
                            default:
                                $value = "<span style='color:red;'>Invalid binding syntax: ".$def['Field']."</span>";
                                break;
                        }
                        if(is_array($value)) {
                            if(isset($def['Format'])) {
                                $str .= $this->stringifyData($value, $def['Format']);
                            } else {
                                foreach($value as $key => $entry) {
                                    $str .= $entry ." ";
                                }
                            }
                        } else {
                            $str .= $value;
                        }
                    }
                    break;
                case "Image":
                    //render image
                    //Source
                    break;
            }
            $str .= "</span>";
            return $str;
        }
        //formats complex structure data into a string (address, name, etc)
        function stringifyData($data, $format) {
            //[] indicates databound key
            //?a(b) indicates an optional section
            //a is the data value to check. If present, b will be rendered
            //anything else is literal
            $formatted = "";
            $seps = '?()[]';
            $tok = strtok( $format, $seps ); // return false on empty string or null
            $cur = 0;
            $dumbDone = FALSE;
            $done = (FALSE===$tok);
            $flags = ["databind" => false, "optional" => false, "check" => false];
            $checkvalue = false;
            while (!$done) {
                $posTok = $dumbDone ? strlen($format) : strpos($format, $tok, $cur );
                $skippedMany = substr( $format, $cur, $posTok-$cur ); // false when 0 width
                $lenSkipped = strlen($skippedMany); // 0 when false
                if (0!==$lenSkipped) {
                    $last = strlen($skippedMany) -1;
                    for($i=0; $i<=$last; $i++){
                        $skipped = $skippedMany[$i];
                        $cur += strlen($skipped);
                        switch($skipped) {
                            case "?":
                                $flags["check"] = true;
                                $flags["databind"] = false;
                                $flags["optional"] = false;
                                break;
                            case "(":
                                $flags["optional"] = true;
                                $flags["check"] = false;
                                break;
                            case "[":
                                $flags["databind"] = true;
                                $flags["check"] = false;
                                break;
                            case "]":
                                $flags["databind"] = false;
                                $flags["check"] = false;
                                break;
                            case ")":
                                $flags["optional"] = false;
                                $flags["check"] = false;
                                break;
                            default:
                                $flags = ["databind" => false, "optional" => false, "check" => false];
                                break;
                        }
                    }
                }
                if ($dumbDone) break; // this is the only place the loop is terminated               
                if($flags["check"]) {
                    $checkvalue = !empty($data[$tok]);
                } else {
                    if(!$flags["optional"] || $checkvalue) {
                        if($flags["databind"]) {
                            $formatted .= $data[$tok];
                        } else {
                            $formatted .= $tok;
                        }
                    }   
                }
                $cur += strlen($tok);
                if (!$dumbDone){
                    $tok = strtok($seps);
                    $dumbDone = (FALSE===$tok);
                }
            };
        }
        //renders layout elements (width, height, direction)
        function renderLayout($def) {
            $str = "";
            if(isset($def['Display'])){
                $str .= "display:".$def['Display'].";";
            }
            if(isset($def['Position'])){
                $str .= "position:".$def['Position'].";";
            }
            if(isset($def['Top'])){
                $str .= "top:".$def['Top'].";";
            }
            if(isset($def['Bottom'])){
                $str .= "bottom:".$def['Bottom'].";";
            }          
            if(isset($def['Left'])){
                $str .= "left:".$def['left'].";";
            }
            if(isset($def['Right'])){
                $str .= "right:".$def['Right'].";";
            }
            if(isset($def['Width'])){
                $str .= "width:".$def['Width'].";";
            }
            if(isset($def['Height'])){
                $str .= "height:".$def['Height'].";";
            }
            return $str;
        }
        //renders arbitrary style elements
        function renderStyle($styles) {
            $str = "";
            foreach($styles as $style) {
                
                switch($style['Name']) {
                    case 'fontFamily':
                        $str .= strtolower(preg_replace('/(?<=\\w)(?=[A-Z])/',"-$1", $style['Name']));
                        $str .= ":";
                        $str .= $this->renderFont($style['Value']);
                        $str .= ";";
                        break;
                    case 'border':
                        $str .= $this->renderBorder($style);
                        break;
                    case 'margin':
                    case 'padding':
                        $str .= $this->renderSide($style);
                        break;
                    default:
                        $str .= strtolower(preg_replace('/(?<=\\w)(?=[A-Z])/',"-$1", $style['Name']));
                        $str .= ":";
                        $str .= $style['Value'];
                        $str .= ";";
                        break;
                }              
                
            }
            return $str;
        }
        //renders border styles
        function renderBorder($style) {
            $str = "";
            $borderstring = ((!empty($style['Width'])?$style['Width']:"1px")." ".(!empty($style['Style'])?$style['Style']:"solid")." ".(!empty($style['Color'])?$style['Color']:"black"));
            if(isset($style['Sides']) && (!empty($style['Sides']['Top']) || !empty($style['Sides']['Bottom']) || !empty($style['Sides']['Left']) || !empty($style['Sides']['Right']))) {
                foreach($style['Sides'] as $side=>$value) {
                    if($value) {
                        $str .= "border-".strtolower($side).": ".$borderstring.";";
                    }
                }
            } else {
                $str .= "border: ";
                $str .= $borderstring;
                $str .= ";";
            }
            return $str;
        }
        //renders side-dependent styles (padding, margin)
        function renderSide($style) {
            $str = "";
            if(isset($style['Sides']) && (!empty($style['Sides']['Top']) || !empty($style['Sides']['Bottom']) || !empty($style['Sides']['Left']) || !empty($style['Sides']['Right']))) {
                foreach($style['Sides'] as $side=>$value) {
                    if($value) {
                        $str .= strtolower($style['Name']."-".$side).": ".$style['Value'].";";
                    }
                }
            } else {
                $str = strtolower($style['Name']);
                $str .= ": ";
                $str .= $style['Value'];
                $str .= ";";
            }
            return $str;
        }
        //renders font descriptions
        function renderFont($font) {
            return str_replace("'", "\"", $font);
        }
    }
?>