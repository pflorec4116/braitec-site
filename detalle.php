<?php
// Validar ID
$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
  die("ID de propiedad inválido");
}

// API Key
$apiKey = 'ce96841d3848c65e5e7b2ca2d13bd6069b45f4c7';

// Obtener la propiedad desde la API
$url = "https://www.tokkobroker.com/api/v1/property/$id/?key=$apiKey&lang=es_ar";
$response = file_get_contents($url);
$data = json_decode($response, true);

if (!$data) {
  die("Propiedad no encontrada");
}

// Función para extraer atributos extra por nombre
function getExtraAttribute($attributes, $targetName) {
  foreach ($attributes as $attr) {
    if (strtolower(trim($attr['name'])) === strtolower(trim($targetName))) {
      return $attr['value'];
    }
  }
  return null;
}

// Datos relevantes
$address     = html_entity_decode($data['address'] ?? 'Sin dirección');
$priceVal    = $data['operations'][0]['prices'][0]['price'] ?? null;
$currency    = $data['operations'][0]['prices'][0]['currency'] ?? 'USD';
$price       = $priceVal ? "$" . number_format($priceVal) . " " . $currency : 'Precio no disponible';
$description = $data['description'] ?: getExtraAttribute($data['extra_attributes'] ?? [], 'Description') ?? 'Sin descripción';
$suites      = $data['suite_amount'] ?? 'N/D';
$bathrooms   = $data['bathroom_amount'] ?? 'N/D';
$branch      = html_entity_decode($data['branch']['display_name'] ?? 'Agencia desconocida');
$totalArea   = $data['total_surface'] ?? 'N/D';
$photos      = $data['photos'];
$videos      = $data['videos'] ?? []; // Add videos data

// Bottom Data Section 
$propertyTitle = html_entity_decode($data['address'] ?? 'Propiedad');
$propertySubtitle = html_entity_decode($data['location']['full_location'] ?? '');
$fullDescription = $data['description'] ?? '';
$shortDescription = strlen($fullDescription) > 300 ? substr($fullDescription, 0, 300) . '...' : $fullDescription;
$hasLongDescription = strlen($fullDescription) > 300;

// Dynamic price label logic
$operationType = $data['operations'][0]['operation_type'] ?? '';
$operationId = $data['operations'][0]['operation_id'] ?? null;
$priceLabel = 'Precio';

if ($operationId == 1 || strtolower($operationType) === 'sale') {
    $priceLabel = 'Precio de venta';
} elseif ($operationId == 2 || strtolower($operationType) === 'rent') {
    $priceLabel = 'Precio de renta';
}

// Price and maintenance
$priceVal = $data['operations'][0]['prices'][0]['price'] ?? null;
$currency = $data['operations'][0]['prices'][0]['currency'] ?? 'MXN';
$formattedPrice = $priceVal ? '$' . number_format($priceVal) : 'Precio no disponible';
$maintenanceFee = null;

// Look for maintenance in extra attributes
foreach ($data['extra_attributes'] ?? [] as $attr) {
    if (stripos($attr['name'], 'mantenimiento') !== false && 
        !empty($attr['value']) && 
        $attr['value'] !== 'No Incluido') {
        $maintenanceFee = $attr['value'];
        break;
    }
}

// Property details for the details section
$totalSurface = $data['total_surface'] ?? 'N/D';
$referenceCode = $data['reference_code'] ?? 'N/D';
$propertyType = $data['type']['name'] ?? 'N/D';
$floors = $data['floors_amount'] ?? 'N/D';
$parking = $data['parking_lot_amount'] ?? 'N/D';
$suites = $data['suite_amount'] ?? 'N/D';
$bathrooms = $data['bathroom_amount'] ?? 'N/D';
$situation = $data['situation'] ?? 'N/D';
$antiquity = $data['age'] ?? 'A estrenar';

// NEW: Determine the operation type and set the appropriate price label
$operationType = $data['operations'][0]['operation_type'] ?? '';
$operationId = $data['operations'][0]['operation_id'] ?? null;

$priceLabel = 'Precio'; // Default fallback

// First, try to determine by operation_id (most reliable)
if ($operationId == 1) {
    $priceLabel = 'Precio de Venta';
} elseif ($operationId == 2) {
    $priceLabel = 'Precio de Renta';
} else {
    // Fallback to operation_type string matching
    $operationTypeLower = strtolower(trim($operationType));
    
    if ($operationTypeLower === 'sale' || 
        $operationTypeLower === 'venta' ||
        strpos($operationTypeLower, 'sale') !== false) {
        $priceLabel = 'Precio de Venta';
    } elseif ($operationTypeLower === 'rent' || 
              $operationTypeLower === 'rental' ||
              $operationTypeLower === 'alquiler' ||
              $operationTypeLower === 'renta' ||
              strpos($operationTypeLower, 'rent') !== false) {
        $priceLabel = 'Precio de Renta';
    } else {
        // Final fallback: check the property title/address for clues
        $title = $data['address'] ?? '';
        if (stripos($title, 'renta') !== false) {
            $priceLabel = 'Precio de Renta';
        } elseif (stripos($title, 'venta') !== false) {
            $priceLabel = 'Precio de Venta';
        }
    }
}

// Map coordinates
$lat = $data['geo_lat'] ?? null;
$lng = $data['geo_long'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= $address ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <!-- FontAwesome for WhatsApp icon -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    /* WhatsApp Contact Form Styles */
    .contact-form {
      margin-top: 20px;
    }
    
    .contact-form h4 {
      margin-bottom: 15px;
      color: #333;
      font-size: 18px;
      font-weight: 600;
    }
    
    .form-group {
      margin-bottom: 15px;
    }
    
    .form-control {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      transition: border-color 0.3s ease;
      box-sizing: border-box;
    }
    
    .form-control:focus {
      outline: none;
      border-color: #25D366;
      box-shadow: 0 0 0 2px rgba(37, 211, 102, 0.1);
    }
    
    .form-control::placeholder {
      color: #999;
    }
    
    textarea.form-control {
      resize: vertical;
      min-height: 80px;
    }
    
    .btn-whatsapp {
      width: 100%;
      background-color: #25D366;
      color: white;
      border: none;
      padding: 14px 20px;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none;
      margin-top: 10px;
    }
    
    .btn-whatsapp:hover {
      background-color: #128C7E;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
    }
    
    .btn-whatsapp:active {
      transform: translateY(0);
      box-shadow: 0 2px 6px rgba(37, 211, 102, 0.3);
    }
    
    .btn-whatsapp i {
      font-size: 18px;
    }
    
    /* Adjust price contact card to remove extra space */
    .price-contact-card {
      background: white;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      border: 1px solid #eee;
      height: fit-content;
    }
    
    .price-section {
      border-bottom: 1px solid #eee;
      padding-bottom: 20px;
      margin-bottom: 20px;
    }
    
    .price-label {
      font-size: 14px;
      color: #666;
      margin-bottom: 5px;
    }
    
    .price-amount {
      font-size: 28px;
      font-weight: 700;
      color: #333;
      margin: 0;
    }
    
    .maintenance-fee {
      font-size: 14px;
      color: #666;
      margin-top: 8px;
    }
    
    /* Form validation styles */
    .form-control.error {
      border-color: #e74c3c;
      box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.1);
    }
    
    .error-message {
      color: #e74c3c;
      font-size: 12px;
      margin-top: 5px;
      display: none;
    }

    /* Media Toggle Buttons */
    .media-toggle-buttons {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
      justify-content: center;
    }

    .media-toggle-btn {
      background: #f8f9fa;
      border: 2px solid #e9ecef;
      color: #495057;
      padding: 12px 24px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .media-toggle-btn:hover {
      background: #e9ecef;
      transform: translateY(-1px);
    }

    .media-toggle-btn.active {
      background: #BA930C;
      border-color: #BA930C;
      color: white;
      box-shadow: 0 2px 8px rgba(186, 147, 12, 0.3);
    }

    .media-toggle-btn i {
      font-size: 16px;
    }

    /* Disabled state for video button when no videos */
    .media-toggle-btn.disabled {
      background: #f8f9fa;
      border-color: #e9ecef;
      color: #6c757d;
      cursor: not-allowed;
      opacity: 0.6;
    }

    .media-toggle-btn.disabled:hover {
      background: #f8f9fa;
      transform: none;
      cursor: not-allowed;
    }

    /* Video Container */
    .video-container {
      display: none;
      background: #000;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }

    .video-container.active {
      display: block;
    }

    .video-wrapper {
      position: relative;
      width: 100%;
      height: 0;
      padding-bottom: 56.25%; /* 16:9 aspect ratio */
    }

    .video-wrapper iframe {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      border: none;
    }

    .video-title {
      background: #f8f9fa;
      padding: 15px 20px;
      font-weight: 600;
      color: #333;
      border-top: 1px solid #e9ecef;
    }

    /* Gallery Layout adjustments */
    .gallery-layout {
      transition: all 0.3s ease;
    }

    .gallery-layout.hidden {
      display: none;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .price-contact-card {
        margin-top: 20px;
        padding: 20px;
      }
      
      .btn-whatsapp {
        padding: 12px 18px;
        font-size: 15px;
      }
      
      .price-amount {
        font-size: 24px;
      }

      .media-toggle-buttons {
        flex-direction: row;
        gap: 8px;
      }

      .media-toggle-btn {
        flex: 1;
        padding: 10px 16px;
        font-size: 13px;
        justify-content: center;
      }
    }
  </style>
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

  <div class="detalle-container">
    <div class="detalle-wrapper">
      <?php
        $backParams = $_GET;
        unset($backParams['id']);
        $backQuery = http_build_query($backParams);
      ?>
      <a href="propiedades.php?<?= htmlspecialchars($backQuery) ?>" class="volver-btn">← Volver a listado</a>

      <!-- Gallery Layout -->
      <div class="gallery-layout" id="photos-container">
        <!-- Main Image -->
        <div class="main-image">
          <?php if (!empty($photos[0]['image'])): ?>
            <a href="<?= htmlspecialchars($photos[0]['image']) ?>" data-fslightbox="gallery">
              <img src="<?= htmlspecialchars($photos[0]['image']) ?>" alt="Imagen principal">
            </a>
          <?php endif; ?>
        </div>

        <!-- Thumbnail Grid (up to 4 more images) -->
        <div class="thumbnail-grid">
          <?php for ($i = 1; $i < min(5, count($photos)); $i++): ?>
            <a href="<?= htmlspecialchars($photos[$i]['image']) ?>" data-fslightbox="gallery">
              <img src="<?= htmlspecialchars($photos[$i]['image']) ?>" alt="Miniatura <?= $i ?>">
            </a>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Video Container -->
      <?php if (!empty($videos)): ?>
      <div class="video-container" id="videos-container">
        <div class="video-wrapper">
          <iframe 
            id="video-iframe"
            src="<?= htmlspecialchars($videos[0]['player_url']) ?>"
            title="<?= htmlspecialchars($videos[0]['title'] ?? 'Video de la propiedad') ?>"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen>
          </iframe>
        </div>
        <?php if (!empty($videos[0]['title'])): ?>
        <div class="video-title">
          <?= htmlspecialchars($videos[0]['title']) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Media Toggle Buttons (Photos/Videos) -->
      <div class="media-toggle-buttons">
        <button class="media-toggle-btn active" id="photos-btn" onclick="toggleMedia('photos')">
          <i class="fas fa-images"></i>
          Fotos (<?= count($photos) ?>)
        </button>
        <button class="media-toggle-btn <?= empty($videos) ? 'disabled' : '' ?>" id="videos-btn" onclick="<?= !empty($videos) ? 'toggleMedia(\'videos\')' : '' ?>">
          <i class="fas fa-play"></i>
          Videos (<?= count($videos) ?>)
        </button>
      </div>

      <!-- Hidden lightbox images for remaining photos (if more than 5 total) -->
      <?php if (count($photos) > 5): ?>
        <div style="display: none;">
          <?php for ($i = 5; $i < count($photos); $i++): ?>
            <a href="<?= htmlspecialchars($photos[$i]['image']) ?>" data-fslightbox="gallery">
              <img src="<?= htmlspecialchars($photos[$i]['image']) ?>" alt="Imagen adicional <?= $i ?>">
            </a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>

      <!-- Property Details Section -->
      <div class="property-details-main">
          <div class="property-details-container">
              <!-- Left Column -->
              <div class="property-details-left">
                  <h1 class="property-details-title"><?= $propertyTitle ?></h1>
                  <?php if ($propertySubtitle): ?>
                      <p class="property-details-subtitle"><?= $propertySubtitle ?></p>
                  <?php endif; ?>

                  <?php if ($fullDescription): ?>
                      <div class="property-description">
                          <?= nl2br(htmlspecialchars($fullDescription)) ?>
                      </div>
                  <?php endif; ?>

                  <?php if (!empty($data['amenities'])): ?>
                      <div class="amenities-section">
                          <h3>Amenidades y Generales:</h3>
                          <ul class="amenities-list">
                              <?php foreach ($data['amenities'] as $amenity): ?>
                                  <li><?= htmlspecialchars($amenity['name']) ?></li>
                              <?php endforeach; ?>
                          </ul>
                      </div>
                  <?php endif; ?>
              </div>

              <!-- Right Column -->
              <div class="property-details-right">
                  <div class="price-contact-card">
                      <!-- Price Section -->
                      <div class="price-section">
                          <p class="price-label"><?= htmlspecialchars($priceLabel) ?></p>
                          <p class="price-amount"><?= $formattedPrice ?></p>
                          <?php if ($maintenanceFee): ?>
                              <p class="maintenance-fee">Mantenimiento: <?= htmlspecialchars($maintenanceFee) ?></p>
                          <?php endif; ?>
                      </div>

                      <!-- Contact Form -->
                      <div class="contact-form">
                          <h4>Déjanos tu consulta</h4>
                          <form id="whatsapp-form">
                              <div class="form-group">
                                  <textarea 
                                      id="message" 
                                      name="message" 
                                      class="form-control" 
                                      rows="4" 
                                      placeholder="Hola, quiero recibir más información sobre esta propiedad.">Hola, quiero recibir más información sobre esta propiedad.</textarea>
                                  <div class="error-message" id="message-error">Por favor ingresa tu consulta</div>
                              </div>
                              <div class="form-group">
                                  <input 
                                      type="text" 
                                      id="name" 
                                      name="name" 
                                      class="form-control" 
                                      placeholder="Nombre completo" 
                                      required>
                                  <div class="error-message" id="name-error">Por favor ingresa tu nombre</div>
                              </div>
                              <div class="form-group">
                                  <input 
                                      type="email" 
                                      id="email" 
                                      name="email" 
                                      class="form-control" 
                                      placeholder="Correo electrónico">
                                  <div class="error-message" id="email-error">Por favor ingresa un correo válido</div>
                              </div>
                              <div class="form-group">
                                  <input 
                                      type="tel" 
                                      id="phone" 
                                      name="phone" 
                                      class="form-control" 
                                      placeholder="Teléfono">
                              </div>
                              <button type="submit" class="btn-whatsapp">
                                  <i class="fab fa-whatsapp"></i>
                                  Consultar por WhatsApp
                              </button>
                          </form>
                      </div>
                  </div>
              </div>
          </div>

          <!-- Details Grid -->
          <div class="details-grid">
              <h3>Detalles</h3>
              <div class="details-items">
                  <div class="detail-item"><strong>m2 Totales:</strong> <?= $totalSurface ?> m²</div>
                  <div class="detail-item"><strong>Código de referencia:</strong> <?= $referenceCode ?></div>
                  <div class="detail-item"><strong>Tipo:</strong> <?= $propertyType ?></div>
                  <?php if ($floors && $floors !== 'N/D' && $floors > 0): ?>
                      <div class="detail-item"><strong>Pisos:</strong> <?= $floors ?></div>
                  <?php endif; ?>
                  <?php if ($parking && $parking !== 'N/D' && $parking > 0): ?>
                      <div class="detail-item"><strong>Estacionamientos:</strong> <?= $parking ?></div>
                  <?php endif; ?>
                  <div class="detail-item"><strong>Recámaras:</strong> <?= $suites ?></div>
                  <div class="detail-item"><strong>Baños:</strong> <?= $bathrooms ?></div>
                  <div class="detail-item"><strong>Situación:</strong> <?= $situation ?></div>
                  <div class="detail-item"><strong>Antigüedad:</strong> <?= $antiquity === 0 ? 'A estrenar' : $antiquity . ' años' ?></div>
              </div>
          </div>

          <!-- Map Section -->
          <?php if ($lat && $lng && is_numeric($lat) && is_numeric($lng)): ?>
          <div class="map-section">
              <h3>Ubicación</h3>
              <div class="map-container" id="property-map">
                  <div class="map-loading">Cargando mapa...</div>
              </div>
              <div class="map-address">
                  <p><strong>Dirección:</strong> <?= htmlspecialchars($address) ?></p>
                  <?php if ($propertySubtitle): ?>
                      <p><strong>Ubicación:</strong> <?= htmlspecialchars($propertySubtitle) ?></p>
                  <?php endif; ?>
              </div>
          </div>
          <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Google Maps API -->
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCA0Z-kfOhEkoXusS1TJzmM1ZpNvCnBZow&libraries=geometry"></script>
  
  <!-- FSLightbox Script -->
  <script src="https://cdn.jsdelivr.net/npm/fslightbox/index.js"></script>

  <script>
    // Media toggle functionality
    function toggleMedia(mediaType) {
      const photosContainer = document.getElementById('photos-container');
      const videosContainer = document.getElementById('videos-container');
      const photosBtn = document.getElementById('photos-btn');
      const videosBtn = document.getElementById('videos-btn');

      // Don't allow switching to videos if there are none
      if (mediaType === 'videos' && videosBtn && videosBtn.classList.contains('disabled')) {
        return;
      }

      if (mediaType === 'photos') {
        // Show photos, hide videos
        if (photosContainer) photosContainer.classList.remove('hidden');
        if (videosContainer) videosContainer.classList.remove('active');
        if (photosBtn) photosBtn.classList.add('active');
        if (videosBtn && !videosBtn.classList.contains('disabled')) videosBtn.classList.remove('active');
      } else if (mediaType === 'videos') {
        // Show videos, hide photos
        if (photosContainer) photosContainer.classList.add('hidden');
        if (videosContainer) videosContainer.classList.add('active');
        if (photosBtn) photosBtn.classList.remove('active');
        if (videosBtn) videosBtn.classList.add('active');
      }
    }

    function shareProperty() {
      if (navigator.share) {
        navigator.share({
          title: '<?= addslashes($address) ?>',
          url: window.location.href
        });
      } else {
        navigator.clipboard.writeText(window.location.href);
        alert('Enlace copiado al portapapeles');
      }
    }

    // WhatsApp form functionality
    function validateForm() {
      let isValid = true;
      
      // Clear previous errors
      document.querySelectorAll('.form-control').forEach(field => {
        field.classList.remove('error');
      });
      document.querySelectorAll('.error-message').forEach(error => {
        error.style.display = 'none';
      });
      
      // Validate name (always required)
      const nameField = document.getElementById('name');
      const nameValue = nameField.value.trim();
      
      if (!nameValue) {
        nameField.classList.add('error');
        document.getElementById('name-error').style.display = 'block';
        isValid = false;
      }
      
      // Validate message (always required)
      const messageField = document.getElementById('message');
      const messageValue = messageField.value.trim();
      
      if (!messageValue) {
        messageField.classList.add('error');
        document.getElementById('message-error').style.display = 'block';
        isValid = false;
      }
      
      // Validate email OR phone (at least one required)
      const emailField = document.getElementById('email');
      const phoneField = document.getElementById('phone');
      const emailValue = emailField.value.trim();
      const phoneValue = phoneField.value.trim();
      
      // Check if neither email nor phone is provided
      if (!emailValue && !phoneValue) {
        emailField.classList.add('error');
        phoneField.classList.add('error');
        
        // Show custom error message for email field
        const emailError = document.getElementById('email-error');
        emailError.textContent = 'Ingresa un correo o teléfono para que podamos contactarte.';
        emailError.style.display = 'block';
        
        isValid = false;
      } else {
        // If email is provided, validate format
        if (emailValue && !isValidEmail(emailValue)) {
          emailField.classList.add('error');
          document.getElementById('email-error').textContent = 'Ingresa un correo electrónico válido';
          document.getElementById('email-error').style.display = 'block';
          isValid = false;
        }
      }
      
      return isValid;
    }
    
    function isValidEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    }
    
    function sendWhatsAppMessage() {
      if (!validateForm()) {
        return;
      }
      
      const message = document.getElementById('message').value.trim();
      const name = document.getElementById('name').value.trim();
      const email = document.getElementById('email').value.trim();
      const phone = document.getElementById('phone').value.trim();
      const propertyTitle = "<?= addslashes($propertyTitle) ?>";
      const propertyAddress = "<?= addslashes($address) ?>";
      const propertyPrice = "<?= addslashes($formattedPrice) ?>";
      const propertyUrl = window.location.href;
      
      // Create formatted WhatsApp message
      let whatsappMessage = `Consulta sobre propiedad\n\n`;
      whatsappMessage += `\n${message}\n\n`;
      whatsappMessage += `Datos de contacto:\n`;
      whatsappMessage += `Nombre: ${name}\n`;
      if (email) {
        whatsappMessage += ` Email: ${email}\n`;
      }
      if (phone) {
        whatsappMessage += ` Teléfono: ${phone}\n`;
      }
      whatsappMessage += `\n Detalles de la propiedad:\n`;
      whatsappMessage += ` Título: ${propertyTitle}\n`;
      if (propertyAddress !== propertyTitle) {
        whatsappMessage += ` Dirección: ${propertyAddress}\n`;
      }
      whatsappMessage += ` Precio: ${propertyPrice}\n`;
      whatsappMessage += `\Ver propiedad completa:\n${propertyUrl}`;
      
      // Encode message for URL
      const encodedMessage = encodeURIComponent(whatsappMessage);
      
      // WhatsApp number (Mexican number with country code)
      const whatsappNumber = '522211353008';
      
      // Create WhatsApp URL
      const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodedMessage}`;
      
      // Open WhatsApp
      window.open(whatsappUrl, '_blank');
    }
    
    // Add event listener to form
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('whatsapp-form');
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        sendWhatsAppMessage();
      });
      
      // Real-time validation
      const inputs = document.querySelectorAll('.form-control');
      inputs.forEach(input => {
        input.addEventListener('blur', function() {
          // Handle name and message validation (always required)
          if ((this.name === 'name' || this.name === 'message') && !this.value.trim()) {
            this.classList.add('error');
            document.getElementById(this.name + '-error').style.display = 'block';
          } else if (this.name === 'name' || this.name === 'message') {
            this.classList.remove('error');
            document.getElementById(this.name + '-error').style.display = 'none';
          }
          
          // Handle email/phone validation (either/or required)
          if (this.name === 'email' || this.name === 'phone') {
            const emailField = document.getElementById('email');
            const phoneField = document.getElementById('phone');
            const emailValue = emailField.value.trim();
            const phoneValue = phoneField.value.trim();
            
            // If either field has value, clear errors from both
            if (emailValue || phoneValue) {
              emailField.classList.remove('error');
              phoneField.classList.remove('error');
              document.getElementById('email-error').style.display = 'none';
              document.getElementById('phone-error').style.display = 'none';
              
              // But still validate email format if email is provided
              if (emailValue && !isValidEmail(emailValue)) {
                emailField.classList.add('error');
                document.getElementById('email-error').textContent = 'Ingresa un correo electrónico válido';
                document.getElementById('email-error').style.display = 'block';
              }
            }
          }
        });
        
        input.addEventListener('input', function() {
          // Handle name and message real-time validation
          if ((this.name === 'name' || this.name === 'message') && this.classList.contains('error') && this.value.trim()) {
            this.classList.remove('error');
            document.getElementById(this.name + '-error').style.display = 'none';
          }
          
          // Handle email/phone real-time validation
          if (this.name === 'email' || this.name === 'phone') {
            const emailField = document.getElementById('email');
            const phoneField = document.getElementById('phone');
            const emailValue = emailField.value.trim();
            const phoneValue = phoneField.value.trim();
            
            // If either field has value, clear errors from both
            if (emailValue || phoneValue) {
              emailField.classList.remove('error');
              phoneField.classList.remove('error');
              document.getElementById('email-error').style.display = 'none';
              document.getElementById('phone-error').style.display = 'none';
            }
            
            // Real-time email format validation
            if (this.name === 'email' && emailValue && !isValidEmail(emailValue)) {
              emailField.classList.add('error');
              document.getElementById('email-error').textContent = 'Ingresa un correo electrónico válido';
              document.getElementById('email-error').style.display = 'block';
            }
          }
        });
      });
      
      // Initialize map
      initPropertyMap();
      
      // Initialize media display
      <?php if (!empty($videos) && empty($photos)): ?>
      // If only videos exist, show videos by default
      toggleMedia('videos');
      <?php else: ?>
      // Default to photos if they exist
      toggleMedia('photos');
      <?php endif; ?>
    });

    // Initialize Google Maps for property location
    function initPropertyMap() {
        <?php if ($lat && $lng && is_numeric($lat) && is_numeric($lng)): ?>
        const lat = <?= $lat ?>;
        const lng = <?= $lng ?>;
        const propertyTitle = "<?= addslashes($address) ?>";
        
        if (typeof google !== 'undefined') {
            const mapContainer = document.getElementById('property-map');
            
            const map = new google.maps.Map(mapContainer, {
                zoom: 16,
                center: { lat: lat, lng: lng },
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: true,
                styles: [
                    {
                        featureType: 'poi',
                        elementType: 'labels',
                        stylers: [{ visibility: 'simplified' }]
                    }
                ]
            });

            // Add marker for the property
            const marker = new google.maps.Marker({
                position: { lat: lat, lng: lng },
                map: map,
                title: propertyTitle,
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="20" cy="20" r="16" fill="#BA930C" stroke="white" stroke-width="4"/>
                            <circle cx="20" cy="20" r="8" fill="white"/>
                        </svg>
                    `),
                    scaledSize: new google.maps.Size(40, 40),
                    anchor: new google.maps.Point(20, 20)
                }
            });

            // Add info window
            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="padding: 8px; max-width: 250px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 16px; color: #333;">${propertyTitle}</h4>
                        <p style="margin: 0; font-size: 14px; color: #666;">Ubicación de la propiedad</p>
                    </div>
                `
            });

            marker.addListener('click', () => {
                infoWindow.open(map, marker);
            });
        } else {
            document.getElementById('property-map').innerHTML = '<div class="map-loading">Google Maps no disponible</div>';
        }
        <?php endif; ?>
    }

    function toggleMenu() {
      const navLinks = document.querySelector('.nav-links');
      navLinks.classList.toggle('show');
    }
    
  </script>
</body>
</html>