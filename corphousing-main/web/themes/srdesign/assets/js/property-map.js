(function (Drupal, once) {
  'use strict';

  let mapInstance = null;
  let previewMapInstance = null;

  Drupal.behaviors.propertyMap = {
    attach: function (context, settings) {

      /* ===============================
       * MOVE MODAL TO <body> (PORTAL FIX)
       * =============================== */
      once('map-portal', '[data-map-modal]', context).forEach(modal => {
        if (modal.parentElement !== document.body) {
          document.body.appendChild(modal);
        }
      });

      /* ===============================
       * INIT PREVIEW MAP (BLURRED)
       * =============================== */
      once('map-preview-init', '#property-map-preview', context).forEach(() => {
        Drupal.behaviors.propertyMap.initPreviewMap(settings);
      });

      /* ===============================
       * OPEN MODAL
       * =============================== */
      once('open-map', '[data-map-open]', context).forEach(btn => {
        btn.addEventListener('click', () => {
          const modal = document.querySelector('[data-map-modal]');
          modal.hidden = false;
          document.body.classList.add('map-modal-open');

          setTimeout(() => {
            Drupal.behaviors.propertyMap.initMap(settings);
          }, 300);
        });
      });

      /* ===============================
       * CLOSE MODAL
       * =============================== */
      document.addEventListener('click', function (e) {
        const closeTarget = e.target.closest('[data-map-close]');
        if (!closeTarget) return;

        const modal = document.querySelector('[data-map-modal]');
        if (!modal) return;

        modal.hidden = true;
        document.body.classList.remove('map-modal-open');
      });
    },

    /* ===============================
     * PREVIEW MAP (BLURRED, READ-ONLY)
     * =============================== */
    initPreviewMap: function (settings) {
      if (previewMapInstance) return;

      const el = document.getElementById('property-map-preview');
      if (!el) return;

      previewMapInstance = L.map(el, {
        zoomControl: false,
        attributionControl: false,
        dragging: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        boxZoom: false,
        keyboard: false
      });

      L.tileLayer(
  'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
  {
    attribution: '&copy; OpenStreetMap &copy; CARTO',
    subdomains: 'abcd',
    maxZoom: 20
  }
).addTo(previewMapInstance);

      const data = settings.property_map || [];
      const bounds = L.latLngBounds();

      data.slice(0, 6).forEach(item => {
        if (item.lat && item.lng) {
         const previewMarkerIcon = L.divIcon({
  className: 'gm-marker',
  html: '<div class="gm-marker-pin"></div>',
  iconSize: [20, 28],
  iconAnchor: [10, 28]
});

L.marker([item.lat, item.lng], {
  icon: previewMarkerIcon,
  interactive: false   // preview = no clicks
}).addTo(previewMapInstance);

          bounds.extend([item.lat, item.lng]);
        }
      });

      if (bounds.isValid()) {
        previewMapInstance.fitBounds(bounds, { padding: [30, 30] });
      }

      // Force render (important for Views AJAX)
      setTimeout(() => {
        previewMapInstance.invalidateSize();
      }, 200);
    },

    /* ===============================
     * FULL MODAL MAP
     * =============================== */
    initMap: function (settings) {
      if (mapInstance) {
        mapInstance.invalidateSize();
        return;
      }

      const el = document.getElementById('property-map');
      if (!el) return;

      mapInstance = L.map(el, {
        scrollWheelZoom: false,
        minZoom: 5,
        maxZoom: 15
      });

      L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        {
          attribution: '&copy; OpenStreetMap & CartoDB'
        }
      ).addTo(mapInstance);

      const data = settings.property_map || [];
      const bounds = L.latLngBounds();

      const markerIcon = L.divIcon({
  className: 'gm-marker',
  html: '<div class="gm-marker-pin"></div>',
  iconSize: [26, 36],
  iconAnchor: [13, 36]
});

      data.forEach(item => {
        if (item.lat && item.lng) {
          const marker = L.marker([item.lat, item.lng], { icon: markerIcon })
            .addTo(mapInstance)
            .bindPopup(`
            <div class="gm-popup">
              <a href="${item.url || '#'}" style="color: inherit; text-decoration: none; display: block;">
                <div class="gm-popup-text">
                  ${item.title ? `<strong>${item.title}</strong><br/>` : ''}
                  ${item.address ? item.address : 'View location'}
                </div>
              </a>
            </div>
          `);

          // Redirect to property detail page when marker is clicked
          marker.on('click', function() {
            if (item.url) {
              window.location.href = item.url;
            }
          });

          bounds.extend([item.lat, item.lng]);
        }
      });

      if (bounds.isValid()) {
        mapInstance.fitBounds(bounds, { padding: [80, 80] });
      }

      setTimeout(() => {
        mapInstance.invalidateSize();
      }, 300);
    }
  };

})(Drupal, once);
