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
        }
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
        $this->currentPage = $_GET['page'] ?? 1;
        $this->limit = $_GET['limit'] ?? 20;
        $this->orderBy = $_GET['order_by'] ?? 'price';
        $this->order = $_GET['order'] ?? 'desc';
        
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
        // Build API parameters
        $params = [
            "limit" => $this->limit,
            "offset" => ($this->currentPage - 1) * $this->limit
        ];
        
        // Add search criteria
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
        
        // Add ordering
        if ($this->orderBy && $this->order) {
            $orderParam = $this->order === 'desc' ? '-' . $this->orderBy : $this->orderBy;
            $params["ordering"] = $orderParam;
        }
        
        // Make API call
        $dataParam = urlencode(json_encode($params));
        $url = "https://www.tokkobroker.com/api/v1/property/search/?data={$dataParam}&key=" . $this->auth->getApiKey();
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $this->results = $result['objects'] ?? [];
            $this->totalCount = $result['meta']['total_count'] ?? count($this->results);
            $this->totalPages = ceil($this->totalCount / $this->limit);
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
            $property->setData($propertyData); // Set data directly
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

// Extend TokkoProperty to allow setting data directly
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
    
    // ... rest of methods same as above
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
?>