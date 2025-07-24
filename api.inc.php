<?php
/**
 * Reverse Engineered TokkoBroker PHP SDK
 * Based on documentation patterns
 */

class TokkoAuth {
    private $apiKey;
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    public function getApiKey() {
        return $this->apiKey;
    }
}

class TokkoProperty {
    private $data;
    private $auth;
    
    public function __construct($field, $value, $auth) {
        $this->auth = $auth;
        
        if ($field === 'id') {
            $this->loadById($value);
        } elseif ($field === 'data') {
            $this->data = $value;
        }
    }
    
    public function setData($data) {
        $this->data = $data;
    }
    
    private function loadById($id) {
        $url = "https://www.tokkobroker.com/api/v1/property/{$id}/?key=" . $this->auth->getApiKey();
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $this->data = json_decode($response, true);
        }
    }
    
    public function get_field($fieldName) {
        if (!$this->data) return null;
        
        switch ($fieldName) {
            case 'publication_title':
                return $this->data['publication_title'] ?? $this->data['address'] ?? '';
            case 'reference_code':
                return $this->data['reference_code'] ?? $this->data['id'] ?? '';
            case 'web_price':
                $price = $this->data['operations'][0]['prices'][0]['price'] ?? null;
                return $price ? number_format($price) : null;
            case 'room_amount':
                return $this->data['room_amount'] ?? 'N/D';
            case 'bathroom_amount':
                return $this->data['bathroom_amount'] ?? 'N/D';
            case 'surface':
                return $this->data['surface'] ?? 'N/D';
            case 'roofed_surface':
                return $this->data['roofed_surface'] ?? 'N/D';
            case 'description':
                return $this->data['description'] ?? '';
            case 'type':
                return (object)['name' => $this->data['type']['name'] ?? ''];
            case 'location':
                return (object)['short_location' => $this->data['location']['name'] ?? ''];
            case 'id':
                return $this->data['id'] ?? '';
            default:
                return $this->data[$fieldName] ?? null;
        }
    }
    
    public function get_available_operations() {
        $operations = [];
        foreach ($this->data['operations'] ?? [] as $op) {
            $price = $op['prices'][0]['price'] ?? 0;
            $currency = $op['prices'][0]['currency'] ?? 'USD';
            $operations[] = number_format($price) . ' ' . $currency;
        }
        return $operations;
    }
    
    public function get_cover_picture() {
        $photos = $this->data['photos'] ?? [];
        if (!empty($photos)) {
            return (object)['thumb' => $photos[0]['image'] ?? ''];
        }
        return null;
    }
}

class TokkoSearch {
    private $auth;
    private $searchData;
    private $results;
    private $currentPage;
    private $limit;
    private $orderBy;
    private $order;
    private $totalCount;
    private $totalPages;
    
    public function __construct($auth) {
    $this->auth = $auth;
    $this->currentPage = (int)($_GET['page'] ?? 1);
    $this->limit = 10;
        $this->orderBy = $_GET['order_by'] ?? 'price';
        $this->order = $_GET['order'] ?? 'desc';
        
        echo "<!-- SDK DEBUG: Constructor - Page: {$this->currentPage}, Limit: {$this->limit} -->";
        
        // Parse search data from URL
        if (isset($_GET['data'])) {
            $this->searchData = json_decode($_GET['data'], true);
        } else {
            // Create from individual parameters
            $this->searchData = $this->createSearchDataFromParams();
        }
    }
    
    private function createSearchDataFromParams() {
        $data = [
            "current_localization_id" => 0,
            "current_localization_type" => "country",
            "price_from" => 0,
            "price_to" => 999999999,
            "operation_types" => [1,2,3],
            "property_types" => [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25],
            "currency" => "ANY",
            "filters" => []
        ];
        
        // Override with actual filters from URL
        if (isset($_GET['operation_type'])) {
            $data['operation_types'] = [(int)$_GET['operation_type']];
        }
        if (isset($_GET['property_type'])) {
            $data['property_types'] = [(int)$_GET['property_type']];
        }
        if (isset($_GET['price_from']) && $_GET['price_from'] > 0) {
            $data['price_from'] = (int)$_GET['price_from'];
        }
        if (isset($_GET['price_to']) && $_GET['price_to'] > 0) {
            $data['price_to'] = (int)$_GET['price_to'];
        }
        
        return $data;
    }
    
    public function do_search() {
        // STEP 1: Get search summary with total count from the special endpoint
        $summaryParams = [];
        
        // Add search criteria for summary
        if (isset($this->searchData['operation_types'])) {
            $summaryParams["operation_types"] = $this->searchData['operation_types'];
        }
        if (isset($this->searchData['property_types'])) {
            $summaryParams["property_types"] = $this->searchData['property_types'];
        }
        if (isset($this->searchData['price_from'])) {
            $summaryParams["price_from"] = $this->searchData['price_from'];
        }
        if (isset($this->searchData['price_to'])) {
            $summaryParams["price_to"] = $this->searchData['price_to'];
        }
        
        $summaryDataParam = urlencode(json_encode($summaryParams));
        $summaryUrl = "https://www.tokkobroker.com/api/v1/property/get_search_summary/?data={$summaryDataParam}&key=" . $this->auth->getApiKey();
        
        echo "<!-- SDK DEBUG: Getting search summary from: $summaryUrl -->";
        
        $ch = curl_init($summaryUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $summaryResponse = curl_exec($ch);
        $summaryHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $realTotalCount = 0;
        if ($summaryHttpCode === 200) {
            $summaryResult = json_decode($summaryResponse, true);
            $realTotalCount = $summaryResult['total_count'] ?? $summaryResult['count'] ?? 0;
            echo "<!-- SDK DEBUG: Search summary returned total count: $realTotalCount -->";
        } else {
            echo "<!-- SDK DEBUG: Search summary failed with HTTP $summaryHttpCode -->";
        }
        
        // STEP 2: Try the main /property/ endpoint with JSON data parameter (like search endpoint)
        $mainParams = $this->searchData;
$mainParams["limit"] = 100;
$mainDataParam = urlencode(json_encode($mainParams));
$mainUrl = "https://www.tokkobroker.com/api/v1/property/?data={$mainDataParam}&key=" . $this->auth->getApiKey();

        
        // Use the exact same parameters that work in the search endpoint
        if (isset($this->searchData['operation_types'])) {
            $mainParams["operation_types"] = $this->searchData['operation_types'];
        }
        if (isset($this->searchData['property_types'])) {
            $mainParams["property_types"] = $this->searchData['property_types'];
        }
        if (isset($this->searchData['price_from'])) {
            $mainParams["price_from"] = $this->searchData['price_from'];
        }
        if (isset($this->searchData['price_to'])) {
            $mainParams["price_to"] = $this->searchData['price_to'];
        }
        
        // Try main endpoint with data parameter (same format as search)
        $mainParams["limit"] = 100;
        $mainDataParam = urlencode(json_encode($mainParams));
        $mainUrl = "https://www.tokkobroker.com/api/v1/property/?data={$mainDataParam}&key=" . $this->auth->getApiKey();
        
        echo "<!-- SDK DEBUG: Trying main endpoint with data param: $mainUrl -->";
        echo "<!-- SDK DEBUG: Main endpoint parameters: " . json_encode($mainParams) . " -->";
        
        $ch = curl_init($mainUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $allProperties = $result['objects'] ?? $result ?? [];
            
            // Handle case where result might be a direct array
            if (!is_array($allProperties) || (isset($allProperties[0]) && !is_array($allProperties[0]))) {
                $allProperties = [];
            }
            
            echo "<!-- SDK DEBUG: Main endpoint returned " . count($allProperties) . " properties -->";
            
            // Check if filtering worked by looking at property types
            if (!empty($allProperties)) {
                $propertyTypes = array_unique(array_map(function($p) { 
                    return $p['type']['name'] ?? 'unknown'; 
                }, array_slice($allProperties, 0, 5)));
                echo "<!-- SDK DEBUG: Property types found: " . implode(', ', $propertyTypes) . " -->";
                
                // Check if we have the right property type
                $expectedType = isset($this->searchData['property_types']) && $this->searchData['property_types'][0] == 3 ? 'Casa' : 'any';
                $hasCorrectType = in_array('Casa', $propertyTypes) || $expectedType === 'any';
                
                if (!$hasCorrectType && count($allProperties) > 30) {
                    echo "<!-- SDK DEBUG: Main endpoint not filtering correctly, using search endpoint -->";
                    $this->fallbackToSearchEndpoint($realTotalCount);
                    return;
                }
            }
            
            // Apply client-side pagination
            $startIndex = ($this->currentPage - 1) * $this->limit;
            $this->results = array_slice($allProperties, $startIndex, $this->limit);
            
            // Use the real total count if we got it, otherwise use what we have
            $this->totalCount = $realTotalCount > 0 ? $realTotalCount : count($allProperties);
            $this->totalPages = ceil($this->totalCount / $this->limit);
            
            echo "<!-- SDK DEBUG: Using total count: {$this->totalCount}, showing page {$this->currentPage} -->";
            echo "<!-- SDK DEBUG: Page properties: " . count($this->results) . " (items " . ($startIndex + 1) . "-" . ($startIndex + count($this->results)) . ") -->";
            
        } else {
            echo "<!-- SDK DEBUG: Main endpoint with data param failed with HTTP $httpCode -->";
            $this->fallbackToSearchEndpoint($realTotalCount);
        }
    }
    
    private function fallbackToSearchEndpoint($realTotalCount) {
        echo "<!-- SDK DEBUG: Using search endpoint as fallback -->";
        $params = [
            "limit" => 50,
            "offset" => 0
        ];
        
        if (isset($this->searchData['operation_types'])) {
            $params["operation_types"] = $this->searchData['operation_types'];
        }
        if (isset($this->searchData['property_types'])) {
            $params["property_types"] = $this->searchData['property_types'];
        }
        if (isset($this->searchData['price_from'])) {
            $params["price_from"] = $this->searchData['price_from'];
        }
        if (isset($this->searchData['price_to'])) {
            $params["price_to"] = $this->searchData['price_to'];
        }
        
        $dataParam = urlencode(json_encode($params));
        $fallbackUrl = "https://www.tokkobroker.com/api/v1/property/search/?data={$dataParam}&key=" . $this->auth->getApiKey();
        
        $ch = curl_init($fallbackUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $allProperties = $result['objects'] ?? [];
            
            $startIndex = ($this->currentPage - 1) * $this->limit;
            $this->results = array_slice($allProperties, $startIndex, $this->limit);
            $this->totalCount = $realTotalCount > 0 ? $realTotalCount : count($allProperties);
            $this->totalPages = ceil($this->totalCount / $this->limit);
            
            echo "<!-- SDK DEBUG: Fallback successful: " . count($allProperties) . " properties -->";
        } else {
            $this->results = [];
            $this->totalCount = 0;
            $this->totalPages = 0;
        }
    }
    
    public function get_result_count() {
        return $this->totalCount;
    }
    
    public function get_current_page() {
        return $this->currentPage;
    }
    
    public function get_result_page_count() {
        return $this->totalPages;
    }
    
    public function get_previous_page_or_null() {
        return $this->currentPage > 1 ? $this->currentPage - 1 : null;
    }
    
    public function get_next_page_or_null() {
        return $this->currentPage < $this->totalPages ? $this->currentPage + 1 : null;
    }
    
    public function get_url_for_page($page) {
        $params = $_GET;
        $params['page'] = $page;
        return '?' . http_build_query($params);
    }
    
    public function get_properties() {
        $properties = [];
        foreach ($this->results as $propertyData) {
            $property = new TokkoProperty('data', $propertyData, $this->auth);
            $properties[] = $property;
        }
        return $properties;
    }
    
    public function get_search_order_by() {
        return $this->orderBy;
    }
    
    public function get_search_order() {
        return $this->order;
    }
    
    public function deploy_reorder_selects($name, $labels, $options, $class, $selected) {
        echo "<select name='{$name}_by' class='{$class}' onchange='reorderResults()'>";
        echo "<option value=''>{$labels[0]}</option>";
        foreach ($options as $value => $label) {
            $sel = $selected[0] === $value ? 'selected' : '';
            echo "<option value='{$value}' {$sel}>{$label}</option>";
        }
        echo "</select>";
        
        echo "<select name='{$name}_order' class='{$class}' onchange='reorderResults()'>";
        echo "<option value='asc'" . ($selected[1] === 'asc' ? ' selected' : '') . ">Ascendente</option>";
        echo "<option value='desc'" . ($selected[1] === 'desc' ? ' selected' : '') . ">Descendente</option>";
        echo "</select>";
        
        echo "<script>
        function reorderResults() {
            var orderBy = document.querySelector('select[name=\"{$name}_by\"]').value;
            var order = document.querySelector('select[name=\"{$name}_order\"]').value;
            var url = new URL(window.location);
            url.searchParams.set('order_by', orderBy);
            url.searchParams.set('order', order);
            url.searchParams.set('page', 1);
            window.location = url;
        }
        </script>";
    }
}
?>