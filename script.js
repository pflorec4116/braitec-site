window.onload = function () {
  const container = document.getElementById("property-container");
  const paginationWrapper = document.getElementById("pagination");
  const urlParams = new URLSearchParams(window.location.search);
  const pageSize = 20;
  let currentPage = parseInt(urlParams.get("page")) || 1;

  async function fetchProperties(offset = 0, limit = pageSize) {
    const url = `https://tokkobroker.com/api/v1/property/?lang=es_ar&format=json&key=ce96841d3848c65e5e7b2ca2d13bd6069b45f4c7&limit=${limit}&offset=${offset}`;
    const response = await fetch(url);
    return await response.json();
  }

  function renderCards(properties) {
    container.innerHTML = "";

    if (!properties.length) {
      container.innerHTML = "<p>No se encontraron propiedades.</p>";
      return;
    }

    properties.forEach(p => {
      const card = document.createElement("div");
      card.className = "property-card";

      const img = p.photos?.[0]?.image || "https://via.placeholder.com/400x300";
      const address = p.address || "Dirección no disponible";
      const location = p.location?.full_location || "Ubicación no disponible";
      const price = p.operations?.[0]?.prices?.[0]?.price
        ? `$${parseFloat(p.operations[0].prices[0].price).toLocaleString()}`
        : "Consultar";
      const desc = p.description?.slice(0, 200) || "Sin descripción";

      card.innerHTML = `
        <div class="property-image-container">
          <img src="${img}" alt="Imagen de propiedad" class="property-image" />
        </div>
        <div class="property-info">
          <h2>${address}</h2>
          <p><strong>Ubicación:</strong> ${location}</p>
          <p><strong>Descripción:</strong> ${desc}...</p>
          <p><strong>Precio:</strong> ${price}</p>
        </div>
      `;

      container.appendChild(card);
    });
  }

  function renderPagination(current, total) {
    paginationWrapper.innerHTML = "";

    const createBtn = (label, page, active = false) => {
      const btn = document.createElement("button");
      btn.textContent = label;
      btn.disabled = label === "...";
      if (!btn.disabled) {
        btn.onclick = () => {
          window.location.href = `?page=${page}`;
        };
      }
      if (active) btn.classList.add("active-page");
      return btn;
    };

    if (current > 1) paginationWrapper.appendChild(createBtn("←", current - 1));

    const maxVisible = 5;
    const half = Math.floor(maxVisible / 2);
    let start = Math.max(1, current - half);
    let end = Math.min(total, start + maxVisible - 1);
    if (end - start < maxVisible - 1) start = Math.max(1, end - maxVisible + 1);

    if (start > 1) {
      paginationWrapper.appendChild(createBtn("1", 1));
      if (start > 2) paginationWrapper.appendChild(createBtn("...", null));
    }

    for (let i = start; i <= end; i++) {
      paginationWrapper.appendChild(createBtn(i, i, i === current));
    }

    if (end < total) {
      if (end < total - 1) paginationWrapper.appendChild(createBtn("...", null));
      paginationWrapper.appendChild(createBtn(total, total));
    }

    if (current < total) paginationWrapper.appendChild(createBtn("→", current + 1));
  }

  async function loadPage(page) {
    const offset = (page - 1) * pageSize;
    try {
      const data = await fetchProperties(offset);
      renderCards(data.objects || []);
      const total = data.meta?.total_count || 0;
      renderPagination(page, Math.ceil(total / pageSize));
    } catch (err) {
      container.innerHTML = `<p>Error al cargar propiedades: ${err.message}</p>`;
    }
  }

  loadPage(currentPage);
}


