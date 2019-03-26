<?php

/**
* BPMonline is a class to query a bpm'online product via DataService
*
* BPMonline is a class to build a JSON document for run queries to a
* bpm'online product via DataService (web service).
*
* Example usage:
* $bpmonline = new BPMonline();
* echo $bpmonline->select_json('Contact');
*
* @package  bpmonline-dataservice-connectors
* @author   Saul Diaz
* @version  $Revision: 0.1 $
* @access   public
* @see      https://github.com/idevol/bpmonline-dataservice-connectors
*/
class BPMonline
{
    // Debug & Log
    private $debug = FALSE;
    private $log = FALSE;

    // bpm'online URL product
    private $bpmonline_url = 'https://myproduct.bpmonline.com';

    // Login configuration
    private $login_credentials = array('UserName' => 'Supervisor', 'UserPassword' => 'secret');
    private $login_cookie_filename = 'bpmonline.session.cookie';
    
    // bpm'online DataService URI's web service (API)
    private $login_uri  = '/ServiceModel/AuthService.svc/Login';
    private $select_uri = '/0/dataservice/json/SyncReply/SelectQuery';
    private $insert_uri = '/0/dataservice/json/reply/InsertQuery';
    private $update_uri = '/0/dataservice/json/reply/UpdateQuery';

    
    function __construct() {
        $this->login_cookie();
    }

    private function log_data_unique_file_path($path, $filename, $extension) {
        $file_path = $path . DIRECTORY_SEPARATOR . $filename . date('-Ymd-U') . $extension;
        $counter = 1;
        while (file_exists($file_path)){
            $file_path = $path . DIRECTORY_SEPARATOR . $filename . date('-Ymd-U') . '.' . $counter . '.' . $extension;
            if (file_exists($file_path)){
                $counter++;
            }
            else{
                break;
            }
        }
        return $file_path;
    }

    private function log_data($log_filename, $data, $same_file = false) {
        $log_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'log' ;
        
        if (!file_exists($log_path)) {
            if(!mkdir($log_path, 0755)) {
                if ($this->debug) error_log("BPMonline\\log_data Error: mkdir error to create folder.");
            }
        }
        
        if(is_string($data)){
            $string = $data;
        }
        else {
            $string = var_export($data, TRUE);
        }
        
        if ($same_file){
            $file_path = $log_path . DIRECTORY_SEPARATOR . $log_filename . '.log';
            if (file_put_contents($file_path, date('[Y-m-d H:i:s] ') . $string . "\n", FILE_APPEND | LOCK_EX) === FALSE){
                if ($this->debug) error_log("BPMonline\\log_data Error: could not write in the same file: $file_path");
            }
        }
        else {
            $file_path = $this->log_data_unique_file_path($log_path, $log_filename, '.log');
            if (file_put_contents($file_path, $string) === FALSE){
                if ($this->debug) error_log("BPMonline\\log_data Error: could not write in file: $file_path");
            }
        }
    }

    private function bpmcsrf() {
        $out = '';
        if (file_exists($this->login_cookie_filename)) {
            $cookie_data = file_get_contents($this->login_cookie_filename);
            preg_match('/BPMCSRF\t(.*)\n/', $cookie_data, $cookie_matches);
            if (isset($cookie_matches[1])) {
                if (!empty($cookie_matches[1])){
                    $out =  $cookie_matches[1];
                }
                else {
                    if ($this->debug) error_log("BPMonline\\bpmcsrf Error: Empty BPMCSRF ");
                }
            }
            else {
                if ($this->debug) error_log("BPMonline\\bpmcsrf Error: BPMCSRF not found ");
            }
        }
        else {
            if ($this->debug) error_log("BPMonline\\bpmcsrf Error: File not exists login_cookie_filename: " . $this->login_cookie_filename);
        }
        
        return $out;
    }

    private function get($url, $post_json = '', $header = array(), $cookie_jar = NULL) {
        $out = FALSE;

        if (!empty($url)){
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

            if(!is_array($header)){
                if (is_string($header)){
                    $header = array($header);
                }
                else{
                    $header = array();
                }
            }

            if (!empty($cookie_jar)) {
                curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar);
            }
            elseif (file_exists($this->login_cookie_filename)) {
                curl_setopt($ch, CURLOPT_COOKIEFILE, $this->login_cookie_filename);
                $header[] = 'BPMCSRF: ' . $this->bpmcsrf();
            }

            if (!empty($post_json)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);
                $header[] = 'Content-Type: application/json';
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

            $curl_result = curl_exec($ch);
            
            $curl_errno = curl_errno($ch);
            $curl_error = curl_error($ch);
            
            curl_close($ch);
            
            if ($curl_errno > 0) {
                if ($this->debug) error_log("BPMonline\\get Error: ($curl_errno): $curl_error ");
            }
            else {
                //if ($this->log) $this->log_data('bpmonline-curl-result', $curl_result);
                $out = $curl_result;
            }
        }
        else{
            if ($this->debug) error_log("BPMonline\\get Error: Empty URL\n");
        }

        return $out;
    }

    private function file_lifetime($file){
        if (file_exists($file)) {
            return (time() - filemtime($file));
        }
        else{
            return FALSE;
        }
    }

    private function login_cookie() {
        $login_cookie_lifetime = $this->file_lifetime($this->login_cookie_filename);
        if ($login_cookie_lifetime ===  FALSE){
            $this->login();
        }
        else{
            if ($login_cookie_lifetime > 60){
                $this->login();
            }
        }
    }

    private function login() {
        $login_json = json_encode($this->login_credentials);
        $login_result_json = $this->get($this->bpmonline_url . $this->login_uri, $login_json, null, $this->login_cookie_filename);
        $login_result = json_decode($login_result_json);
        if (is_array($login_result)) {
            if (isset($login_result['Code'])){
                if ($login_result['Code'] != 0){
                    if ($this->debug) error_log("BPMonline\\login Error: login_result: $login_result_json ");
                }
            }
            else {
                if ($this->debug) error_log("BPMonline\\login Error: login_result['Code']: $login_result_json ");
            }
        }
        else {
            if ($this->debug) error_log("BPMonline\\login Error: No array login_result: $login_result_json ");
        }
    }

    private function filters($Query = array(), $Filters = NULL) {
        
        /*
        $Filters = '00000000-0000-0000-0000-000000000000';

        // Or

        $Filters = array(
            'logicalOperation' => 0,
            'items' => array(
                'Id' => array(
                    'comparisonType' => 3,
                    'dataValueType' => 0, 
                    'value' => '00000000-0000-0000-0000-000000000000'
                ),
                'Name' => array(
                    'comparisonType' => 11,
                    'dataValueType' => 0, 
                    'value' => 'John'
                )
            )
        );

        // Or

        $Filters = array('filters' => array(
            // Custoum
        ));
        */

        if ($Filters != NULL){
            if (is_string($Filters)){
                $Query['filters'] = array(
                    'items' => array(
                        'primaryColumnFilter' => array(
                            'filterType' => 1,
                            'comparisonType' => 3,
                            'isEnabled' => true,
                            'trimDateTimeParameterToDate' => false,
                            'leftExpression' => array(
                                'expressionType' => 1,
                                'functionType' => 1,
                                'macrosType' => 34
                            ),
                            'rightExpression' => array(
                                'expressionType' => 2,
                                'parameter' => array(
                                    'dataValueType' => 0,
                                    'value' => $Filters
                                )
                            )
                        )
                    ),
                    'logicalOperation' => 0,
                    'isEnabled' => true,
                    'filterType' => 6
                );
            }
            elseif (is_array($Filters)){
                if (isset($Filters['items'])){
                    if (count($Filters['items']) > 0){
                        if (isset($Filters['logicalOperation'])) {
                            $LogicalOperatorType = $Filters['logicalOperation'];
                        }
                        else {
                            // AND
                            $LogicalOperatorType = 0;
                        }
                        $Query['filters'] = array (
                            'logicalOperation' => 0,
                            'isEnabled' => true,
                            'filterType' => 6,
                            'items' => array (
                                'CustomFilters' => array (
                                    'logicalOperation' => $LogicalOperatorType,
                                    'isEnabled' => true,
                                    'filterType' => 6,
                                    'items' => array (),
                                )
                            )
                        );

                        foreach ($Filters['items'] as $Column => $parameter) {
                            // https://academy.bpmonline.com/api/jscoreapi/7.12.0/index.html?_ga=2.104770368.1168104844.1543204589-1794740920.1543204589#!/api/Terrasoft.core.enums.ComparisonType
                            if (isset($parameter['comparisonType'])){
                                $comparisonType = $parameter['comparisonType'];
                            }
                            else {
                                // EQUAL
                                $comparisonType = 3;
                            }
                            $Query['filters']['items']['CustomFilters']['items'] = array_merge(
                                $Query['filters']['items']['CustomFilters']['items'],
                                array(
                                    'customFilter' . $Column . '_PHP' => array (
                                        'filterType' => 1,
                                        'comparisonType' => $comparisonType,
                                        'isEnabled' => true,
                                        'trimDateTimeParameterToDate' => false,
                                        'leftExpression' => array (
                                            'expressionType' => 0,
                                            'columnPath' => $Column,
                                        ),
                                        'rightExpression' => array (
                                            'expressionType' => 2,
                                            'parameter' => array (
                                                'dataValueType' => $parameter['dataValueType'],
                                                'value' => $parameter['value'],
                                            ),
                                        ),
                                    ),
                                )
                            );
                        }
                    }
                }
                elseif (isset($Filters['filters'])){
                    $Query['filters'] = $Filters['filters'];
                }
            }
        }
        return $Query;
    }

    public function select_json($RootSchemaName, $Columns = array('Name'), $Filters = NULL) {
        /*
        $Columns = array('Id',Name','CreatedBy');

        $Filters = '00000000-0000-0000-0000-000000000000';

        // Or

        $Filters = array(
            'logicalOperation' => 0,
            'items' => array(
                'Id' => array(
                    'comparisonType' => 3,
                    'dataValueType' => 0, 
                    'value' => '00000000-0000-0000-0000-000000000000'
                ),
                'Name' => array(
                    'comparisonType' => 11,
                    'dataValueType' => 0, 
                    'value' => 'John'
                )
            )
        );

        // Or

        $Filters = array('filters' => array(
            // Custoum
        ));
        */

        $select_url = $this->bpmonline_url . $this->select_uri;
        $select_query = array(
            'RootSchemaName' => $RootSchemaName,
            'OperationType' => 0,
            'Columns' => array(
                'Items' => array()
            ),
            'allColumns' => false,
            'useLocalization' => true
        );

        foreach($Columns as &$Column){
            if ($Column == 'Name'){
                $select_query['Columns']['Items'] = array_merge(
                    $select_query['Columns']['Items'], 
                    array(
                        $Column => array(
                            'OrderDirection' => 1,
                            'Expression' => array(
                                'ExpressionType' => 0,
                                'ColumnPath' => $Column
                            )
                        )
                    )
                );
            }
            elseif ($Column == 'CreatedOn'){
                $select_query['Columns']['Items'] = array_merge(
                    $select_query['Columns']['Items'], 
                    array(
                        $Column => array(
                            'OrderDirection' => 2,
                            'Expression' => array(
                                'ExpressionType' => 0,
                                'ColumnPath' => $Column
                            )
                        )
                    )
                );
            }
            else {
                $select_query['Columns']['Items'] = array_merge(
                    $select_query['Columns']['Items'], 
                    array(
                        $Column => array(
                            'Expression' => array(
                                'ExpressionType' => 0,
                                'ColumnPath' => $Column
                            )
                        )
                    )
                );
            }
        }

        if ($Filters != NULL) {
            $select_query = $this->filters($select_query, $Filters);
        }

        //if ($this->debug) error_log('BPMonline\\schema query_data: ' . var_export($select_query, TRUE));

        $select_query_json = json_encode($select_query);
        //if ($this->log) $this->log_data('bpmonline-select-query-json', $select_query_json);
        $select_result_json = $this->get($select_url, $select_query_json);
        //if ($this->log) $this->log_data('bpmonline-select-result-json', $select_result_json);
        return $select_result_json;
    }

    public function select($RootSchemaName, $Columns = array('Name'), $Filters = NULL) {
        $out = FALSE;
        $select_result_json = $this->select_json($RootSchemaName, $Columns, $Filters);
        $select_result = json_decode($select_result_json, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $out = $select_result;
            //if ($this->log) $this->log_data('bpmonline-select-result-array', var_export($select_result, true));
        }
        return $out;
    }

    public function insert_json($RootSchemaName, $ColumnValuesItems = array()) {
        /*
        $ColumnValuesItems = array(
            'Column1' => array(
                'ExpressionType' => 2,
                'Parameter' => array(
                    'DataValueType' => 1,
                    'Value' => 'New Text Value'
                )
            )
        );
        */

        $insert_url = $this->bpmonline_url . $this->insert_uri;
        $insert_query = array(
            'RootSchemaName' => $RootSchemaName,
            'OperationType' => 1,
            'ColumnValues' => array(
                'Items' => $ColumnValuesItems
            )
        );
        $insert_query_json = json_encode($insert_query);
        if ($this->log) $this->log_data('bpmonline-insert-query-json', $insert_query_json);
        $insert_result_json = $this->get($insert_url, $insert_query_json);
        if ($this->log) $this->log_data('bpmonline-insert-result-json', $insert_result_json);
        return $insert_result_json;
    }

    public function insert($RootSchemaName, $ColumnValuesItems = array()){
        $out = FALSE;
        $insert_result_json = $this->insert_json($RootSchemaName, $ColumnValuesItems);
        $insert_result = json_decode($insert_result_json, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $out = $insert_result;
            if ($this->log) $this->log_data('bpmonline-insert-result-array', var_export($insert_result, true));
        }
        return $out;
    }

    public function update_json($RootSchemaName, $ColumnValuesItems = array(), $Filters = NULL) {
        $out = FALSE;
        
        /*
        $ColumnValuesItems = array(
            'Column1' => array(
                'ExpressionType' => 2,
                'Parameter' => array(
                    'DataValueType' => 1,
                    'Value' => 'New Text Value'
                )
            )
        );

        $Filters = '00000000-0000-0000-0000-000000000000';

        // Or

        $Filters = array(
            'logicalOperation' => 0,
            'items' => array(
                'Id' => array(
                    'comparisonType' => 3,
                    'dataValueType' => 0, 
                    'value' => '00000000-0000-0000-0000-000000000000'
                ),
                'Name' => array(
                    'comparisonType' => 11,
                    'dataValueType' => 0, 
                    'value' => 'John'
                )
            )
        );

        // Or

        $Filters = array('filters' => array(
            // Custoum
        ));
        */

        if (!empty($ColumnValuesItems)) {
            $update_url = $this->bpmonline_url . $this->update_uri;
            $update_query = array(
                'RootSchemaName' => $RootSchemaName,
                'OperationType' => 1,
                'ColumnValues' => array(
                    'Items' => $ColumnValuesItems
                )
            );

            if ($Filters != NULL) {
                $update_query = $this->filters($update_query, $Filters);
            }

            $update_query_json = json_encode($update_query);
            //if ($this->log) $this->log_data('bpmonline-update-query-json', $update_query_json);
            $update_result_json = $this->get($update_url, $update_query_json);
            //if ($this->log) $this->log_data('bpmonline-update-result-json', $update_result_json);
            $out = $update_result_json;
            //$out = $update_json;
        }

        return $out;
    }

    public function update($RootSchemaName, $ColumnValuesItems = array(), $Filters = NULL){
        $out = FALSE;
        $update_result_json = $this->update_json($RootSchemaName, $ColumnValuesItems, $Filters);
        $update_result = json_decode($update_result_json, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $out = $update_result;
            //if ($this->log) $this->log_data('bpmonline-update-result-array', var_export($update_result, true));
        }
        return $out;
    }

    public function lookup_json($RootSchemaName, $Columns = array('Id', 'Name')){
        return $this->lookup_json($RootSchemaName, $Columns);
    }

    public function lookup($RootSchemaName, $Columns = array('Id', 'Name')){
        $out = FALSE;
        $lookup_result_json = $this->lookup_json($RootSchemaName, $Columns);
        $lookup_result = json_decode($lookup_result_json, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $out = $lookup_result;
            //if ($this->log) $this->log_data('bpmonline-lookup-result-array', var_export($lookup_result, true));
        }
        return $out;
    }
}

?>