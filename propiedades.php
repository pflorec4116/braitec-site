<?php
// -------------------- 1) Filtros desde el usuario --------------------
$operation    = $_GET['operation_type'] ?? '';
$propertyType = $_GET['property_type']   ?? '';
$minPrice     = $_GET['price_from']      ?? '';
$maxPrice     = $_GET['price_to']        ?? '';
$keywords     = $_GET['keywords']        ?? '';
$bedrooms     = $_GET['bedrooms']        ?? '';
$priceOrder   = $_GET['price_order']     ?? ''; // nuevo

// -------------------- 2) Paginación --------------------
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage  = 10;
$offset   = ($page - 1) * $perPage;

// -------------------- 3) API Key --------------------
$apiKey = 'ce96841d3848c65e5e7b2ca2d13bd6069b45f4c7';

// -------------------- 4) Filtros para el parámetro `data` --------------------
$filters = [];

if ($operation !== '' && is_numeric($operation)) {
  $filters["operation_types"] = [(int)$operation];
}
if ($propertyType !== '' && is_numeric($propertyType)) {
  $filters["property_types"] = [(int)$propertyType];
}
if ($bedrooms !== '' && is_numeric($bedrooms)) {
  $filters["suite_amount"] = [(int)$bedrooms];
}

$filters["price_from"] = is_numeric($minPrice) ? (int)$minPrice : 0;
$filters["price_to"]   = is_numeric($maxPrice) ? (int)$maxPrice : 999999999;

// -------------------- 5) Armar la URL correctamente --------------------
$dataJson = json_encode($filters);

$queryString = http_build_query([
  'data'             => $dataJson,
  'limit'            => 200,  // fetch more to sort manually
  'offset'           => 0,
  'key'              => $apiKey
]);

$url = "https://www.tokkobroker.com/api/v1/property/search/?" . $queryString;

// -------------------- 6) Ejecutar request --------------------
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// -------------------- 7) Procesar respuesta --------------------
$paginatedProperties = [];
$totalCount = 0;

if ($httpCode === 200 && $response) {
  $json = json_decode($response, true);
  $paginatedProperties = $json['objects'] ?? [];
  $totalCount = $json['meta']['total_count'] ?? count($paginatedProperties);

  // -------------------- 7.1) Ordenar por precio en PHP --------------------
  if (in_array($priceOrder, ['asc', 'desc'])) {
    usort($paginatedProperties, function ($a, $b) use ($priceOrder) {
      $priceA = $a['operations'][0]['prices'][0]['price'] ?? 0;
      $priceB = $b['operations'][0]['prices'][0]['price'] ?? 0;
      return $priceOrder === 'asc' ? $priceA <=> $priceB : $priceB <=> $priceA;
    });
  }

  // -------------------- 7.2) Paginar después de ordenar --------------------
  $totalCount = count($paginatedProperties);
  $paginatedProperties = array_slice($paginatedProperties, $offset, $perPage);
}

// -------------------- 8) Debug --------------------
$startIndex = $offset;
$endIndex   = min($offset + $perPage, $totalCount);

echo "<!-- DEBUG: page=$page, offset=$offset, totalCount=$totalCount, limit=$perPage -->";
echo "<!-- URL: " . htmlspecialchars($url) . " -->";
echo "<!-- JSON filtros: " . json_encode($filters, JSON_PRETTY_PRINT) . " -->";
echo "<!-- Orden actual: $priceOrder -->";

foreach ($paginatedProperties as $p) {
  echo "<!-- ID: " . ($p['id'] ?? 'N/A') . " -->";
}
?>



<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Propiedades</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <nav class="navbar">
    <a href="index.html" class="logo">
      <img src="assets/braiteclogoclean.png" alt="Braitec Logo" />
    </a>
    <div class="menu-toggle" id="mobile-menu">
      <span class="bar"></span>
      <span class="bar"></span>
      <span class="bar"></span>
    </div>
    <ul class="nav-links">
      <li><a href="propiedades.php">Propiedades</a></li>
      <li><a href="servicios.html">Servicios</a></li>
      <li><a href="contacto.html">Contacto</a></li>
    </ul>
  </nav>

  <div class="page-content">
    <!-- Filtros -->
    <div class="propiedades-filters">
      <form method="GET" action="propiedades.php" class="propiedades-form">
        <label>Filtros</label>
        <input type="text" name="keywords" placeholder="Ubicación, código o palabras clave..." value="<?= htmlspecialchars($keywords) ?>">

        <select name="operation_type">
          <option value="">Tipo de Operación</option>
          <option value="1" <?= $operation == 1 ? 'selected' : '' ?>>Venta</option>
          <option value="2" <?= $operation == 2 ? 'selected' : '' ?>>Renta</option>
        </select>

        <select name="property_type">
          <option value="">Tipo de Propiedad</option>
          <option value="3" <?= $propertyType == 3 ? 'selected' : '' ?>>Casa</option>
          <option value="2" <?= $propertyType == 2 ? 'selected' : '' ?>>Departamento</option>
          <option value="1" <?= $propertyType == 1 ? 'selected' : '' ?>>Terreno</option>
          <option value="4" <?= $propertyType == 4 ? 'selected' : '' ?>>Local</option>
          <option value="5" <?= $propertyType == 5 ? 'selected' : '' ?>>Oficina</option>
        </select>

        <input type="number" name="price_from" placeholder="Precio Mínimo" value="<?= htmlspecialchars($minPrice) ?>">
        <input type="number" name="price_to" placeholder="Precio Máximo" value="<?= htmlspecialchars($maxPrice) ?>">

        <select name="bedrooms">
          <option value="">Recámaras</option>
          <option value="1" <?= $bedrooms == 1 ? 'selected' : '' ?>>1+</option>
          <option value="2" <?= $bedrooms == 2 ? 'selected' : '' ?>>2+</option>
          <option value="3" <?= $bedrooms == 3 ? 'selected' : '' ?>>3+</option>
          <option value="4" <?= $bedrooms == 4 ? 'selected' : '' ?>>4+</option>
        </select>

        <button type="submit" class="apply-btn">Aplicar</button>
        <button type="reset" class="clear-btn" onclick="window.location='propiedades.php'">Limpiar</button>
        <select name="price_order" style="min-width: 180px;">
  <option value="">Ordenar por Precio</option>
  <option value="asc" <?= $priceOrder === 'asc' ? 'selected' : '' ?>>Precio: Menor a Mayor</option>
<option value="desc" <?= $priceOrder === 'desc' ? 'selected' : '' ?>>Precio: Mayor a Menor</option>
</select>
      </form>
    </div>

    <!-- Resultados -->
    <div id="property-list">
      <div class="propiedades-header-bar">
        <div class="propiedades-count-sort">
          <div class="propiedades-title-group">
            <h2 class="propiedades-page-title">Propiedades</h2>
            <p class="propiedades-subcount">
              <?= $totalCount > 0
                  ? ($startIndex + 1) . " a " . $endIndex . " de " . $totalCount . " Propiedades"
                  : "0 propiedades encontradas" ?>
            </p>
          </div>
        </div>
      </div>

      <?php if (empty($paginatedProperties)): ?>
        <p>No se encontraron propiedades.</p>
      <?php else: ?>
        <?php foreach ($paginatedProperties as $p):
          $address   = htmlspecialchars($p['address'] ?? 'Sin dirección');
          $bathrooms = $p['bathroom_amount'] ?? 'N/D';
          $suites    = $p['suite_amount'] ?? 'N/D';
          $branch    = htmlspecialchars($p['branch']['display_name'] ?? 'Agencia desconocida');
          $priceVal  = $p['operations'][0]['prices'][0]['price'] ?? null;
          $currency  = $p['operations'][0]['prices'][0]['currency'] ?? 'USD';
          $price     = $priceVal ? number_format($priceVal) . " " . $currency : 'Precio no disponible';
          $image     = $p['photos'][0]['image'] ?? '';
          $keywordsBlob = strtolower("$address $branch $price $suites $bathrooms");
        ?>
          <div class="property-card" data-keywords="<?= htmlspecialchars($keywordsBlob) ?>">
            <div class="property-image-container">
              <?php if ($image): ?>
                <img src="<?= htmlspecialchars($image) ?>" alt="Imagen de la propiedad" class="property-image">
              <?php endif; ?>
            </div>
            <div class="property-info">
              <h3 class="property-title"><?= $address ?></h3>
              <p class="property-location"><?= $branch ?></p>
              <p class="property-description"><?= $address ?></p>
              <div class="property-details">
                <span><strong><?= $suites ?></strong> Recámaras</span>
                <span><strong><?= $bathrooms ?></strong> Baños</span>
              </div>
              <p class="property-price"><?= $price ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Paginación -->
<div class="pagination">
  <?php
    $totalPages = max(1, ceil($totalCount / $perPage));
    $range = 1; // how many pages to show around current page

    for ($i = 1; $i <= $totalPages; $i++) {
      // Always show first 3 pages, last page, and near current
      if (
        $i <= 3 || // first 3 pages
        $i == $totalPages || // last page
        ($i >= $page - $range && $i <= $page + $range) // around current
      ) {
        $activeClass = $i === $page ? 'active' : '';
        $queryParams = $_GET;
        $queryParams['page'] = $i;
        $queryString = http_build_query($queryParams);
        echo "<a class='pagination-link $activeClass' href='?$queryString'>$i</a>";
      } elseif (
        $i == 4 && $page > 6 || // after first 3 pages but before current range
        $i == $page + $range + 1 || // after current range
        $i == $totalPages - 1 && $page < $totalPages - 4 // before last page
      ) {
        echo "<span class='pagination-link'>...</span>";
      }
    }
  ?>
</div>

  <script>
    // Filtro client-side por palabras clave
    const keyword = new URLSearchParams(window.location.search).get("keywords")?.toLowerCase() ?? "";
    if (keyword !== "") {
      document.querySelectorAll(".property-card").forEach(card => {
        const data = (card.getAttribute("data-keywords") || "").toLowerCase();
        if (!data.includes(keyword)) {
          card.style.display = "none";
        }
      });
    }
  </script>
</body>
</html>