<?php

class BPMonline
{
    private $debug = FALSE;
    private $log = FALSE;
    private $bpmonline_url = 'https://myproduct.bpmonline.com';
    private $login_uri = '/ServiceModel/AuthService.svc/Login';
    private $login_credentials = array('UserName' => 'Supervisor', 'UserPassword' => 'secret');
    private $login_cookie_filename = 'bpmonline.session.cookie';

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

    public function select_json($RootSchemaName, $Columns = array('Name'), $Filters = NULL) {
        /*
        $Columns = array('Id',Name','CreatedBy');

        $Filters = array(
            'logicalOperation' => 0,
            'items' => array(
                'Id' => array(
                    'comparisonType' => 3,
                    'dataValueType' => 0, 
                    'value' => '00000000-0000-0000-0000-000000000000'
                )
            )
        );
        */

        $query_url = $this->bpmonline_url . '/0/dataservice/json/SyncReply/SelectQuery';
        $query_data = array(
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
                $query_data['Columns']['Items'] = array_merge(
                    $query_data['Columns']['Items'], 
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
            else {
                $query_data['Columns']['Items'] = array_merge(
                    $query_data['Columns']['Items'], 
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

        if (is_array($Filters)) {
            if (isset($Filters['items'])){
                if (count($Filters['items']) > 0) {
                    if (isset($Filters['logicalOperation'])) {
                        $LogicalOperatorType = $Filters['logicalOperation'];
                    }
                    else {
                        // AND
                        $LogicalOperatorType = 0;
                    }
                    $query_data['filters'] = array (
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
                        $query_data['filters']['items']['CustomFilters']['items'] = array_merge(
                            $query_data['filters']['items']['CustomFilters']['items'],
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
        }

        //if ($this->debug) error_log('BPMonline\\schema query_data: ' . var_export($query_data, TRUE));

        $query_json = json_encode($query_data);
        //if ($this->log) $this->log_data('bpmonline-select-query-json', $query_json);
        $query_result = $this->get($query_url, $query_json);
        //if ($this->log) $this->log_data('bpmonline-select-result-json', $query_result);
        return $query_result;
    }

    public function select($RootSchemaName, $Columns = array('Name'), $Filters = NULL) {
        $out = FALSE;
        $select_json = $this->select_json($RootSchemaName, $Columns, $Filters);
        $select = json_decode($select_json, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $out = $select;
            //if ($this->log) $this->log_data('bpmonline-select-result-array', var_export($select, true));
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

        $insert_url = $this->bpmonline_url . '/0/dataservice/json/reply/InsertQuery';
        $insert_data = array(
            'RootSchemaName' => $RootSchemaName,
            'OperationType' => 1,
            'ColumnValues' => array(
                'Items' => $ColumnValuesItems
            )
        );
        $insert_json = json_encode($insert_data);
        if ($this->log) $this->log_data('bpmonline-insert-json-query', $insert_json);
        $insert_result = $this->get($insert_url, $insert_json);
        if ($this->log) $this->log_data('bpmonline-insert-result-json', $insert_result);
        return $insert_result;
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

        $Filters = array(
            'logicalOperation' => 0,
            'items' => array(
                'Id' => array(
                    'comparisonType' => 3,
                    'dataValueType' => 0, 
                    'value' => '00000000-0000-0000-0000-000000000000'
                )
            )
        );
        */

        if (!empty($ColumnValuesItems)) {
            $update_url = $this->bpmonline_url . '/0/dataservice/json/reply/UpdateQuery';
            $update_data = array(
                'RootSchemaName' => $RootSchemaName,
                'OperationType' => 1,
                'ColumnValues' => array(
                    'Items' => $ColumnValuesItems
                )
            );

            if (is_array($Filters)) {
                if (isset($Filters['items'])){
                    if (count($Filters['items']) > 0) {
                        if (isset($Filters['logicalOperation'])) {
                            $LogicalOperatorType = $Filters['logicalOperation'];
                        }
                        else {
                            // AND
                            $LogicalOperatorType = 0;
                        }
                        $update_data['filters'] = array (
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
                            $update_data['filters']['items']['CustomFilters']['items'] = array_merge(
                                $update_data['filters']['items']['CustomFilters']['items'],
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
            }

            $update_json = json_encode($update_data);
            //if ($this->log) $this->log_data('bpmonline-update-json-query', $update_json);
            $update_result = $this->get($update_url, $update_json);
            //if ($this->log) $this->log_data('bpmonline-update-result-json', $update_result);
            $out = $update_result;
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
}

//$bpmonline = new BPMonline();
//echo $bpmonline->select_json('Contact');
?>