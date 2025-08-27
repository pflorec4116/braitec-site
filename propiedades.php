<?php
ini_set('memory_limit', '256M'); // Avoid OOM error

// Get filter parameters from URL
$operation    = $_GET['operation_type'] ?? '';
$propertyType = $_GET['property_type']   ?? '';
$minPrice     = $_GET['price_from']      ?? '';
$maxPrice     = $_GET['price_to']        ?? '';
$keywords     = trim($_GET['keywords'] ?? '');
$bedrooms     = $_GET['bedrooms']        ?? [];
$sortOrder    = $_GET['sort_order']      ?? 'newest'; // Default to newest

$bedroomValues = array_filter(array_map('intval', (array)$bedrooms));
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage  = 10;
$offset   = ($page - 1) * $perPage;

$apiKey = 'ce96841d3848c65e5e7b2ca2d13bd6069b45f4c7';

// -------------------- BUILD DATA ARRAY ACCORDING TO TOKKO SPECS --------------------
$data = [
    // Location settings - use "all locations" as default
    'current_location_id' => 0,
    'current_localization_type' => 'country',
    
    // Price range - use very broad defaults to include everything
    'price_from' => 0,
    'price_to' => 99999999999,
    
    // Operation types - empty array means "any operation type"
    'operation_types' => [],
    
    // Property types - empty array means "any property type"  
    'property_types' => [],
    
    // Currency - empty string means "any currency"
    'currency' => '',
    
    // Additional filters container
    'filters' => []
];

// -------------------- APPLY USER SELECTIONS --------------------

// Set operation type if selected
if ($operation !== '' && is_numeric($operation) && (int)$operation > 0) {
    $data['operation_types'] = [(int)$operation];
}

// Set property type if selected
if ($propertyType !== '' && is_numeric($propertyType) && (int)$propertyType > 0) {
    $data['property_types'] = [(int)$propertyType];
}

// Set price range if specified
if (is_numeric($minPrice) && (int)$minPrice > 0) {
    $data['price_from'] = (int)$minPrice;
}

if (is_numeric($maxPrice) && (int)$maxPrice > 0) {
    $data['price_to'] = (int)$maxPrice;
}

// Set sorting order
$orderBy = '-created_on'; // Default to newest first
if ($sortOrder === 'price_asc') {
    $orderBy = 'price';
} elseif ($sortOrder === 'price_desc') {
    $orderBy = '-price';
}
$data['order_by'] = $orderBy;

// -------------------- BUILD API REQUEST --------------------
$dataJson = json_encode($data);
$queryString = http_build_query([
    'data' => $dataJson,
    'limit' => 200,  // Get more results for better filtering
    'offset' => 0,
    'key' => $apiKey,
    'lang' => 'es_ar'
]);

$url = "https://www.tokkobroker.com/api/v1/property/search/?" . $queryString;

// -------------------- MAKE API REQUEST WITH CURL --------------------
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);           // 15 second timeout
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);    // 5 second connection timeout
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PropertySearch/1.0)');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$allProperties = [];
$totalCount = 0;

// Handle API response
if ($curlError) {
    echo "<!-- CURL Error: " . htmlspecialchars($curlError) . " -->";
} elseif ($httpCode !== 200) {
    echo "<!-- HTTP Error: $httpCode -->";
} elseif ($response) {
    $json = json_decode($response, true);
    if ($json && isset($json['objects'])) {
        $allProperties = $json['objects'];
        
        // -------------------- CLIENT-SIDE FILTERING --------------------
        
        // Filter by bedrooms (since API doesn't have this filter)
        if (!empty($bedroomValues)) {
            $allProperties = array_filter($allProperties, function ($p) use ($bedroomValues) {
                return isset($p['suite_amount']) && in_array((int)$p['suite_amount'], $bedroomValues);
            });
        }
        
        // Enhanced keyword/location filtering
        if ($keywords !== '') {
            $k = mb_strtolower($keywords);
            $allProperties = array_filter($allProperties, function ($p) use ($k) {
                // Build comprehensive search blob with all location fields
                $searchFields = [
                    // Basic property info
                    $p['address'] ?? '',
                    $p['short_location'] ?? '',
                    $p['description'] ?? '',
                    $p['publication_title'] ?? '',
                    
                    // Branch/Agency info
                    $p['branch']['display_name'] ?? '',
                    $p['branch']['name'] ?? '',
                    
                    // Operation type
                    $p['operations'][0]['operation_type']['name'] ?? '',
                    
                    // Enhanced Location Fields
                    $p['location']['full_location'] ?? '',
                    $p['location']['name'] ?? '',
                    $p['location']['short_location'] ?? '',
                    
                    // Parent location (for nested locations like neighborhoods within cities)
                    $p['location']['parent_location']['name'] ?? '',
                    $p['location']['parent_location']['full_location'] ?? '',
                    $p['location']['parent_location']['short_location'] ?? '',
                    
                    // Grandparent location (for deeper nesting like neighborhood > city > state)
                    $p['location']['parent_location']['parent_location']['name'] ?? '',
                    $p['location']['parent_location']['parent_location']['full_location'] ?? '',
                    
                    // Alternative location fields that might exist
                    $p['location']['city'] ?? '',
                    $p['location']['state'] ?? '',
                    $p['location']['zone'] ?? '',
                    $p['location']['neighborhood'] ?? '',
                ];
                
                $searchBlob = mb_strtolower(implode(' ', array_filter($searchFields)));
                return strpos($searchBlob, $k) !== false;
            });
        }
        
        // Additional client-side sorting for price (if API sorting doesn't work as expected)
        if (in_array($sortOrder, ['price_asc', 'price_desc'])) {
            usort($allProperties, function ($a, $b) use ($sortOrder) {
                $priceA = $a['operations'][0]['prices'][0]['price'] ?? 0;
                $priceB = $b['operations'][0]['prices'][0]['price'] ?? 0;
                return $sortOrder === 'price_asc' ? $priceA <=> $priceB : $priceB <=> $priceA;
            });
        }
        
        $totalCount = count($allProperties);
    } else {
        echo "<!-- JSON Error: " . json_last_error_msg() . " -->";
    }
}

// -------------------- PAGINATION --------------------
$paginatedProperties = array_slice($allProperties, $offset, $perPage);
$startIndex = $offset;
$endIndex = min($offset + $perPage, $totalCount);

// -------------------- DEBUG OUTPUT --------------------
echo "<!-- DEBUG: page=$page, offset=$offset, totalCount=$totalCount, limit=$perPage -->";
echo "<!-- URL: " . htmlspecialchars($url) . " -->";
echo "<!-- Data sent to API: " . htmlspecialchars($dataJson) . " -->";
echo "<!-- Formatted data: " . json_encode($data, JSON_PRETTY_PRINT) . " -->";
echo "<!-- HTTP Response Code: $httpCode -->";
if ($curlError) {
    echo "<!-- CURL Error: " . htmlspecialchars($curlError) . " -->";
}
echo "<!-- User filters: operation=$operation, propertyType=$propertyType, minPrice=$minPrice, maxPrice=$maxPrice -->";
echo "<!-- Keywords: '$keywords', Bedrooms: " . json_encode($bedroomValues) . ", Sort: $sortOrder -->";

// Helper function to build comprehensive keywords for frontend filtering
function buildPropertyKeywords($property) {
    $keywordFields = [
        $property['address'] ?? '',
        $property['branch']['display_name'] ?? '',
        $property['branch']['name'] ?? '',
        
        // All location fields
        $property['location']['full_location'] ?? '',
        $property['location']['name'] ?? '',
        $property['location']['short_location'] ?? '',
        $property['location']['parent_location']['name'] ?? '',
        $property['location']['parent_location']['full_location'] ?? '',
        $property['location']['parent_location']['parent_location']['name'] ?? '',
        $property['location']['city'] ?? '',
        $property['location']['state'] ?? '',
        $property['location']['zone'] ?? '',
        $property['location']['neighborhood'] ?? '',
        
        // Property details for general search
        $property['suite_amount'] ?? '',
        $property['bathroom_amount'] ?? '',
        $property['operations'][0]['prices'][0]['price'] ?? '',
        $property['operations'][0]['prices'][0]['currency'] ?? '',
    ];
    
    return strtolower(implode(' ', array_filter($keywordFields)));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Propiedades</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="mobile.css" media="(max-width: 768px)">
  <style>
    /* Preview Description Truncation */
    .preview-description {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      text-overflow: ellipsis;
      line-height: 1.4;
      max-height: calc(1.4em * 4); /* 4 lines * line-height */
    }
   

  </style>
</head>
<body>
  <nav class="navbar" data-mobile-nav>
  <a href="index.html" class="logo">
    <img src="assets/braiteclogoclean.png" alt="Braitec Logo" />
  </a>

  <!-- Drawer menu (desktop styles will still treat this as the row menu) -->
  <ul class="nav-links" id="mobile-menu">
    <li><a href="propiedades.php">Propiedades</a></li>
    <li><a href="servicios.html">Servicios</a></li>
    <li><a href="contacto.html">Contacto</a></li>
  </ul>

  <!-- Same hamburger used on index -->
  <button class="hamburger" aria-label="Abrir menú" aria-controls="mobile-menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</nav>
 
<div class="page-content">
  <!-- Mobile Filter Toggle Button -->
  <div class="mobile-filter-container" style="text-align: center;">
    <button class="mobile-filter-toggle" id="mobileFilterToggle">
      <span id="filterToggleText">Filtros</span>
      <span id="filterToggleIcon">▼</span>
    </button>
  </div>

  <!-- Filters Container -->
  <div class="propiedades-filters" id="propiedadesFilters">
    <form method="GET" action="propiedades.php" class="propiedades-form" id="filter-form">
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

      <input type="text" id="price_from" name="price_from" placeholder="Precio Mínimo" value="<?= $minPrice !== '' ? '$' . number_format((int)$minPrice) : '' ?>">
      <input type="text" id="price_to" name="price_to" placeholder="Precio Máximo" value="<?= $maxPrice !== '' ? '$' . number_format((int)$maxPrice) : '' ?>">

      <div class="custom-multiselect">
        <div class="multiselect-trigger">Recámaras</div>
        <div class="multiselect-options">
          <?php foreach ([1, 2, 3, 4] as $num): ?>
            <label>
              <input type="checkbox" name="bedrooms[]" value="<?= $num ?>" <?= in_array($num, $bedroomValues) ? 'checked' : '' ?>>
              <?= $num ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      
      <div class="bottomfilters">
        <div class="orderfilterwrapper">
          <select name="sort_order">
            <option value="">Ordenar por...</option>
            <option value="newest" <?= $sortOrder === 'newest' ? 'selected' : '' ?>>Más recientes</option>
            <option value="price_asc" <?= $sortOrder === 'price_asc' ? 'selected' : '' ?>>Precio: Menor a Mayor</option>
            <option value="price_desc" <?= $sortOrder === 'price_desc' ? 'selected' : '' ?>>Precio: Mayor a Menor</option>
          </select>
        </div>
        <div class="actionbuttons">
          <button type="submit" class="apply-btn">Aplicar</button>
          <a href="propiedades.php" class="clear-btn">Limpiar</a>
        </div>
      </div>
    </form>
  </div>

    <!-- Property List Container -->
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

      <div class="propiedades-main-container">
        <!-- Left Panel: Property List -->
        <div class="propiedades-left-panel">
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
              $price     = $priceVal ? "$ " . number_format($priceVal) . " " . $currency : 'Precio no disponible';
              $image     = $p['photos'][0]['image'] ?? '';
              
              // Extract coordinates and additional data for preview
              $lat = $p['geo_lat'] ?? null;
              $lng = $p['geo_long'] ?? null;
              $description = htmlspecialchars($p['description'] ?? $address);
              $title = htmlspecialchars($p['publication_title'] ?? $address);
              /* NEW: hard cap for left-card title (adjust 60 as you like) */
$shortTitle = function_exists('mb_strimwidth')
  ? mb_strimwidth($title, 0, 65, '…')
  : (strlen($title) > 65 ? substr($title, 0, 57) . '…' : $title);
              // Use the enhanced keyword builder
              $keywordsBlob = buildPropertyKeywords($p);
            ?>
              <div class="property-card" 
                   data-keywords="<?= htmlspecialchars($keywordsBlob) ?>"
                   data-id="<?= $p['id'] ?>"
                   data-title="<?= htmlspecialchars($title) ?>"
                   data-address="<?= htmlspecialchars($address) ?>"
                   data-description="<?= htmlspecialchars($description) ?>"
                   data-suites="<?= htmlspecialchars($suites) ?>"
                   data-bathrooms="<?= htmlspecialchars($bathrooms) ?>"
                   data-price="<?= $priceVal ?? 0 ?>"
                   data-currency="<?= htmlspecialchars($currency) ?>"
                   data-image="<?= htmlspecialchars($image) ?>"
                   data-lat="<?= $lat ?? '' ?>"
                   data-lng="<?= $lng ?? '' ?>">
                
                <div class="property-image-container">
                  <?php if ($image): ?>
                    <img src="<?= htmlspecialchars($image) ?>" alt="Imagen de la propiedad" class="property-image">
                  <?php endif; ?>
                </div>
                <div class="property-info">
                  <h3 class="property-title" title="<?= $title ?>"><?= $shortTitle ?></h3>
                  <p class="property-location"><?= $branch ?></p>
                  <p class="property-description"><?= $address ?></p>
                  <div class="property-details">
                    <span><strong><?= $suites ?></strong> Recámaras</span>
                    <span><strong><?= $bathrooms ?></strong> Baños</span>
                  </div>
                  <p class="property-price"><?= $price ?></p>
                </div>
                <a class="property-btn-black" href="detalle.php?id=<?= $p['id'] ?>&<?= http_build_query($_GET) ?>">Más detalles</a>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Right Panel: Property Preview -->
        <div class="propiedades-right-panel">
          <div class="property-preview-card" id="property-preview">
            <div class="preview-placeholder">
              <div class="preview-placeholder-icon">🏠</div>
              <p>Selecciona una propiedad para ver los detalles y ubicación</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
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
    </div>
  </div>

  <!-- Google Maps API -->
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCA0Z-kfOhEkoXusS1TJzmM1ZpNvCnBZow&libraries=geometry"></script>

<script>
  /* ===== NAV: wire both nav variants ===== */
(function () {
  // Wire one nav: find button + menu list inside it
  function wireNav(nav) {
    const btn  = nav.querySelector('.hamburger, .menu-toggle');
    const menu = nav.querySelector('.nav-links-index, .nav-links');

    if (!btn || !menu) return;

    // For reliable mobile behavior with blurred/sticky nav, "portal" the UL to body on mobile.
    const mq = window.matchMedia('(max-width: 768px)');
    const placeholder = document.createComment('menu-anchor');
    if (!menu.nextSibling) nav.appendChild(placeholder); else nav.insertBefore(placeholder, menu.nextSibling);

    function placeForViewport() {
      if (mq.matches) {
        // Move menu out of nav so it's not clipped by navbar stacking context
        if (menu.parentElement !== document.body) document.body.insertBefore(menu, nav.nextSibling);
      } else {
        // Move back inside nav on desktop
        if (placeholder.parentNode && menu.parentElement !== nav) placeholder.parentNode.insertBefore(menu, placeholder);
        // Reset drawer state on desktop
        menu.classList.remove('open');
        btn.classList.remove('is-open');
        document.body.classList.remove('no-scroll');
        btn.setAttribute('aria-expanded','false');
      }
    }
    placeForViewport();
    mq.addEventListener('change', placeForViewport);

    const close = () => {
      menu.classList.remove('open');
      btn.classList.remove('is-open');
      btn.setAttribute('aria-expanded','false');
      document.body.classList.remove('no-scroll');
    };

    btn.addEventListener('click', () => {
      const opening = !menu.classList.contains('open');
      menu.classList.toggle('open', opening);
      btn.classList.toggle('is-open', opening);
      btn.setAttribute('aria-expanded', opening ? 'true' : 'false');
      document.body.classList.toggle('no-scroll', opening);
    });

    // Close on link click + ESC
    menu.querySelectorAll('a').forEach(a => a.addEventListener('click', close));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
  }

  function init() {
    document.querySelectorAll('nav.navbarindex, nav.navbar').forEach(wireNav);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();


class PropertyPreview {
  constructor() {
    this.previewCard = document.getElementById('property-preview');
    this.currentMap = null;
    this.selectedCard = null;
    this.init();
  }

  init() {
    document.querySelectorAll('.property-card').forEach(card => {
      card.addEventListener('click', (e) => {
        // Only prevent default if clicking the card itself, not the "Más detalles" button
        if (!e.target.classList.contains('property-btn-black')) {
          e.preventDefault();
          this.selectProperty(card);
        }
      });
    });
  }

  isMobile() {
    return window.innerWidth <= 768;
  }

  selectProperty(card) {
    if (this.isMobile()) {
      // On mobile, just add selected styling but don't show preview
      if (this.selectedCard) {
        this.selectedCard.classList.remove('selected');
      }
      card.classList.add('selected');
      this.selectedCard = card;
      return;
    }

    // Desktop behavior
    if (this.selectedCard) {
      this.selectedCard.classList.remove('selected');
    }

    card.classList.add('selected');
    this.selectedCard = card;

    this.showLoading();

    const propertyData = {
      id: card.dataset.id,
      title: card.dataset.title,
      address: card.dataset.address,
      description: card.dataset.description,
      suites: card.dataset.suites,
      bathrooms: card.dataset.bathrooms,
      price: card.dataset.price,
      currency: card.dataset.currency,
      image: card.dataset.image,
      lat: parseFloat(card.dataset.lat),
      lng: parseFloat(card.dataset.lng)
    };

    setTimeout(() => {
      this.renderPreview(propertyData);
    }, 300);
  }

  showLoading() {
    this.previewCard.innerHTML = `
      <div class="preview-placeholder">
        <div class="preview-loading-spinner"></div>
        <p style="margin-top: 16px;">Cargando información...</p>
      </div>
    `;
    this.previewCard.classList.add('loading');
  }

  renderPreview(data) {
    const formattedPrice = `$ ${Number(data.price).toLocaleString()} ${data.currency}`;
    
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('id', data.id);
    const detailsUrl = `detalle.php?${urlParams.toString()}`;
    
    this.previewCard.innerHTML = `
      <div class="preview-map-container" id="preview-map">
        <div class="preview-map-loading">Cargando mapa...</div>
      </div>
      <div class="preview-content">
        <h3 class="preview-title">${data.title}</h3>
        <p class="preview-address">${data.address}</p>
        <p class="preview-description">${data.description}</p>
        <div class="preview-details">
          <div class="preview-detail-item">
            <strong>${data.suites}</strong> Recámaras
          </div>
          <div class="preview-detail-item">
            <strong>${data.bathrooms}</strong> Baños
          </div>
        </div>
        <div class="preview-price">${formattedPrice}</div>
        <a href="${detailsUrl}" class="preview-btn">Más detalles</a>
      </div>
    `;

    this.previewCard.classList.remove('loading');
    this.previewCard.classList.add('active');

    if (data.lat && data.lng && !isNaN(data.lat) && !isNaN(data.lng)) {
      this.initMap(data.lat, data.lng, data.title);
    } else {
      document.getElementById('preview-map').innerHTML = '<div class="preview-map-loading">Ubicación no disponible</div>';
    }
  }

  initMap(lat, lng, title) {
    if (this.currentMap) {
      this.currentMap = null;
    }

    const mapContainer = document.getElementById('preview-map');
    
    if (typeof google === 'undefined') {
      mapContainer.innerHTML = '<div class="preview-map-loading">Google Maps no disponible</div>';
      return;
    }

    this.currentMap = new google.maps.Map(mapContainer, {
      zoom: 16,
      center: { lat, lng },
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: false,
      styles: [
        {
          featureType: 'poi',
          elementType: 'labels',
          stylers: [{ visibility: 'off' }]
        }
      ]
    });

    new google.maps.Marker({
      position: { lat, lng },
      map: this.currentMap,
      title: title,
      icon: {
        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
          <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <circle cx="16" cy="16" r="12" fill="#2c5aa0" stroke="white" stroke-width="3"/>
            <circle cx="16" cy="16" r="6" fill="white"/>
          </svg>
        `),
        scaledSize: new google.maps.Size(32, 32),
        anchor: new google.maps.Point(16, 16)
      }
    });
  }
}

// Mobile Filter Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
  // Initialize PropertyPreview
  new PropertyPreview();

  // Mobile filter toggle
  const mobileToggle = document.getElementById('mobileFilterToggle');
  const filtersContainer = document.getElementById('propiedadesFilters');
  const toggleText = document.getElementById('filterToggleText');
  const toggleIcon = document.getElementById('filterToggleIcon');
  
  if (mobileToggle && filtersContainer) {
    mobileToggle.addEventListener('click', function(e) {
      e.preventDefault();
      
      const isActive = filtersContainer.classList.contains('mobile-active');
      
      if (isActive) {
        // Hide filters
        filtersContainer.classList.remove('mobile-active');
        mobileToggle.classList.remove('active');
        toggleText.textContent = 'Filtros';
        toggleIcon.textContent = '▼';
      } else {
        // Show filters
        filtersContainer.classList.add('mobile-active');
        mobileToggle.classList.add('active');
        toggleText.textContent = 'Ocultar Filtros';
        toggleIcon.textContent = '▲';
      }
    });
  }
  
  // Handle window resize
  window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
      // Desktop view - ensure filters are visible and reset mobile state
      if (filtersContainer) {
        filtersContainer.classList.remove('mobile-active');
        filtersContainer.style.display = 'block';
      }
      if (mobileToggle) {
        mobileToggle.classList.remove('active');
      }
      if (toggleText) toggleText.textContent = 'Filtros';
      if (toggleIcon) toggleIcon.textContent = '▼';
    } else {
      // Mobile view - respect current toggle state
      if (filtersContainer && !filtersContainer.classList.contains('mobile-active')) {
        filtersContainer.style.display = 'none';
      }
    }
  });
  
  // Initial setup based on screen size
  if (window.innerWidth <= 768 && filtersContainer && !filtersContainer.classList.contains('mobile-active')) {
    filtersContainer.style.display = 'none';
  }
});

// Enhanced client-side filtering
const keyword = new URLSearchParams(window.location.search).get("keywords")?.toLowerCase().trim() ?? "";

if (keyword !== "") {
  document.querySelectorAll(".property-card").forEach(card => {
    const data = (card.getAttribute("data-keywords") || "").toLowerCase();
    
    const keywordMatches = keyword.split(' ').every(word => 
      word === '' || data.includes(word.trim())
    );
    
    if (!keywordMatches) {
      card.style.display = "none";
    }
  });
}

// Price formatting functions
function formatNumberToCurrency(input) {
  let value = input.value.replace(/\D/g, "");
  if (!value) return input.value = "";
  input.value = "$" + Number(value).toLocaleString("en-US");
}

function unformatCurrencyToNumber(input) {
  input.value = input.value.replace(/\D/g, "");
}

const from = document.getElementById('price_from');
const to = document.getElementById('price_to');

from?.addEventListener("input", () => formatNumberToCurrency(from));
to?.addEventListener("input", () => formatNumberToCurrency(to));

document.querySelector('.propiedades-form')?.addEventListener("submit", () => {
  unformatCurrencyToNumber(from);
  unformatCurrencyToNumber(to);
});

// Multi-select functionality
document.querySelectorAll('.custom-multiselect').forEach(wrapper => {
  const trigger = wrapper.querySelector('.multiselect-trigger');
  
  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    wrapper.classList.toggle('open');
  });

  document.addEventListener('click', (e) => {
    if (!wrapper.contains(e.target)) {
      wrapper.classList.remove('open');
    }
  });
});

// Function to check for active filters
function checkForActiveFilters() {
  const urlParams = new URLSearchParams(window.location.search);
  const filterParams = ['keywords', 'operation_type', 'property_type', 'price_from', 'price_to', 'bedrooms', 'sort_order'];
  
  return filterParams.some(param => {
    const value = urlParams.get(param);
    return value && value.trim() !== '';
  });
}
</script>
</body>
</html>