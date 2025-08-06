<?php
$apiKey = 'ce96841d3848c65e5e7b2ca2d13bd6069b45f4c7';
$url = "https://www.tokkobroker.com/api/v1/property/by_location/?key=$apiKey";

// You must POST a JSON body, even an empty one
$data = json_encode([
  "price_from" => 0,
  "price_to" => 0,
  "operation_types" => [],
  "property_types" => []
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  'Content-Length: ' . strlen($data)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Handle response
if ($httpCode === 200 && $response !== false) {
  header('Content-Type: application/json');
  echo $response;
} else {
  echo "Error fetching location data. HTTP Code: $httpCode. Curl error: $error";
}
