(function (Drupal, once) {
  'use strict';

  // ─── State ────────────────────────────────────────────────────────────────
  let leafletMap     = null;
  let googleMap      = null;
  let googleOverlays = [];
  let previewMap     = null;
  let hoverCard      = null;
  let hideTimer      = null;
  let fadeTimer      = null;
  let filterCloned   = false;

  // ─── Style injection ──────────────────────────────────────────────────────
  function injectStyles() {
    if (document.getElementById('gm-map-styles')) return;
    const s = document.createElement('style');
    s.id = 'gm-map-styles';
    s.textContent = [
      /* Pill marker */
      '.gm-pill-marker { background: none !important; border: none !important; }',
      '.gm-pill-wrap { display: inline-flex; flex-direction: column; align-items: center; cursor: pointer; transform-origin: center bottom; transition: transform 0.15s ease; }',
      '.gm-pill-marker--active .gm-pill-wrap { transform: scale(1.12); }',
      '.gm-pill-inner { display: inline-flex; align-items: center; justify-content: center; background: #fff; color: #111; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 13px; font-weight: 700; line-height: 1; padding: 7px 12px; border-radius: 20px; border: 1.5px solid #ddd; box-shadow: 0 2px 8px rgba(0,0,0,0.18); white-space: nowrap; transition: background 0.15s, color 0.15s, border-color 0.15s, box-shadow 0.15s; }',
      '.gm-pill-tail { width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-top: 7px solid #fff; margin-top: -1px; filter: drop-shadow(0 2px 2px rgba(0,0,0,0.10)); transition: border-top-color 0.15s; }',
      '.gm-pill-marker--active .gm-pill-inner { background: #1a73e8; color: #fff; border-color: #1a73e8; box-shadow: 0 4px 14px rgba(26,115,232,0.35); }',
      '.gm-pill-marker--active .gm-pill-tail { border-top-color: #1a73e8; }',

      /* Preview dot */
      '.gm-marker { background: none !important; border: none !important; }',
      '.gm-marker-pin { width: 18px; height: 18px; background: #1a73e8; border: 2.5px solid #fff; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.3); }',

      /* Hover card */
      '#gm-hover-card { position: fixed; z-index: 10000; width: 240px; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,0.18); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; pointer-events: auto; display: none; opacity: 0; transition: opacity 0.15s ease; }',
      '#gm-hover-card.visible { opacity: 1; }',
      '.gm-hc-img { width: 100%; height: 140px; background-size: cover; background-position: center; background-color: #eee; }',
      '.gm-hc-img--placeholder { display: flex; align-items: center; justify-content: center; font-size: 40px; background: #f0f0f0; }',
      '.gm-hc-body { padding: 12px 14px 14px; }',
      '.gm-hc-price { font-size: 18px; font-weight: 800; color: #111; line-height: 1.2; margin-bottom: 4px; }',
      '.gm-hc-night { font-size: 13px; font-weight: 400; color: #666; }',
      '.gm-hc-title { font-size: 13px; font-weight: 600; color: #222; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }',
      '.gm-hc-address { font-size: 12px; color: #888; margin-bottom: 10px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }',
      '.gm-hc-link { display: inline-block; font-size: 12px; font-weight: 600; color: #1a73e8; text-decoration: none; }',
      '.gm-hc-link:hover { text-decoration: underline; }',

      /* Modal layout */
      '.map-modal-wrapper { position: relative; z-index: 1; width: 92vw; max-width: 1200px; height: 85vh; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 80px rgba(0,0,0,0.35); display: flex; flex-direction: column; }',
      '.map-modal-inner { display: flex; flex: 1; overflow: hidden; }',
      '#property-map { flex: 1; min-width: 0; height: 100%; }',

      /* Filter sidebar */
      '.map-filter-sidebar { width: 280px; flex-shrink: 0; display: flex; flex-direction: column; border-left: 1px solid #eee; background: #fff; overflow: hidden; }',
      '.map-filter-sidebar__header { display: flex; align-items: center; justify-content: space-between; padding: 16px 18px 12px; border-bottom: 1px solid #eee; flex-shrink: 0; }',
      '.map-filter-sidebar__title { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 15px; font-weight: 700; color: #111; }',
      '.map-filter-sidebar__clear { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 12px; font-weight: 600; color: #1a73e8; background: none; border: none; cursor: pointer; padding: 0; }',
      '.map-filter-sidebar__clear:hover { text-decoration: underline; }',
      '.map-filter-sidebar__body { flex: 1; overflow-y: auto; padding: 14px 18px 20px; scrollbar-width: thin; scrollbar-color: #ddd transparent; }',
      '.map-filter-sidebar__body::-webkit-scrollbar { width: 4px; }',
      '.map-filter-sidebar__body::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }',
      '.map-filter-loading { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 13px; color: #aaa; padding: 20px 0; text-align: center; }',

      /* Cloned filter overrides */
      '.map-filter-sidebar__body .views-exposed-form { margin: 0; }',
      '.map-filter-sidebar__body .form-item { margin-bottom: 6px; }',
      '.map-filter-sidebar__body .views-widget { margin-bottom: 0; }',
      '.map-filter-sidebar__body .form-actions { display: none; }',
      '.map-filter-sidebar__body .filter-actions { display: none; }',

      /* Filter groups */
      '.map-filter-group { margin-bottom: 18px; }',
      '.map-filter-group__label { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 13px; font-weight: 700; color: #111; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; }',
      '.map-filter-group__label svg { transition: transform 0.2s; }',
      '.map-filter-group--collapsed .map-filter-group__label svg { transform: rotate(-90deg); }',
      '.map-filter-group__items { display: flex; flex-direction: column; gap: 6px; }',
      '.map-filter-group--collapsed .map-filter-group__items { display: none; }',

      /* Checkboxes */
      '.map-filter-sidebar__body .form-type-checkbox { display: flex; align-items: center; gap: 8px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 13px; color: #333; cursor: pointer; }',
      '.map-filter-sidebar__body .form-type-checkbox input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: #1a73e8; flex-shrink: 0; }',
      '.map-filter-sidebar__body .form-type-checkbox label { cursor: pointer; margin: 0; }',

      /* Apply button */
      '.map-filter-apply-wrap { padding: 14px 18px; border-top: 1px solid #eee; background: #fff; flex-shrink: 0; }',
      '.map-filter-apply { display: block; width: 100%; padding: 11px; background: #1a73e8; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 14px; font-weight: 700; border: none; border-radius: 10px; cursor: pointer; transition: background 0.15s; }',
      '.map-filter-apply:hover { background: #1557b0; }',

      /* Responsive */
      '@media (max-width: 700px) { .map-filter-sidebar { display: none; } }'
    ].join('\n');
    document.head.appendChild(s);
  }

  // ─── Hover card ───────────────────────────────────────────────────────────
  function getHoverCard() {
    if (!hoverCard) {
      hoverCard = document.createElement('div');
      hoverCard.id = 'gm-hover-card';
      document.body.appendChild(hoverCard);
      hoverCard.addEventListener('mouseenter', () => { clearTimeout(hideTimer); clearTimeout(fadeTimer); });
      hoverCard.addEventListener('mouseleave', () => scheduleHide());
    }
    return hoverCard;
  }

  function showCard(item, anchorEl) {
    clearTimeout(hideTimer);
    clearTimeout(fadeTimer);
    const card = getHoverCard();
    card.innerHTML =
      (item.image
        ? '<div class="gm-hc-img" style="background-image:url(\'' + item.image + '\')"></div>'
        : '<div class="gm-hc-img gm-hc-img--placeholder">\uD83C\uDFE0</div>') +
      '<div class="gm-hc-body">' +
        (item.price   ? '<div class="gm-hc-price">\u20B9' + item.price + '<span class="gm-hc-night"> / night</span></div>' : '') +
        (item.title   ? '<div class="gm-hc-title">' + item.title + '</div>' : '') +
        (item.address ? '<div class="gm-hc-address">' + item.address + '</div>' : '') +
        (item.url     ? '<a class="gm-hc-link" href="' + item.url + '">View Property \u2192</a>' : '') +
      '</div>';

    card.style.display = 'block';
    card.classList.remove('visible');
    requestAnimationFrame(function () {
      positionCard(card, anchorEl);
      requestAnimationFrame(function () { card.classList.add('visible'); });
    });
  }

  function positionCard(card, anchorEl) {
    var rect  = anchorEl.getBoundingClientRect();
    var cardW = 240;
    var cardH = card.offsetHeight;
    var gap   = 6;
    var left  = rect.left + rect.width / 2 - cardW / 2;
    var top   = rect.top - cardH - gap;
    left = Math.max(8, Math.min(left, window.innerWidth - cardW - 8));
    top  = top < 8 ? rect.bottom + gap : top;
    card.style.left = left + 'px';
    card.style.top  = top  + 'px';
  }

  function scheduleHide() {
    clearTimeout(hideTimer);
    clearTimeout(fadeTimer);
    hideTimer = setTimeout(function () {
      var card = getHoverCard();
      card.classList.remove('visible');
      fadeTimer = setTimeout(function () { card.style.display = 'none'; }, 150);
    }, 100);
  }

  // ─── Filter sidebar ───────────────────────────────────────────────────────
  function initFilterSidebar() {
    if (filterCloned) return;

    var sidebar = document.getElementById('map-filter-body');
    if (!sidebar) return;

    // Find the exposed filter form — try your custom class first, then Drupal defaults
    var sourceForm = document.querySelector('.property-filter-form') ||
      document.querySelector('.views-exposed-form') ||
      document.querySelector('form[data-drupal-selector*="views-exposed-form"]');

    if (!sourceForm) {
      sidebar.innerHTML = '<div class="map-filter-loading">No filters found.</div>';
      return;
    }

    // Deep-clone so page form stays functional
    var clone = sourceForm.cloneNode(true);

    // Prefix all IDs to avoid conflicts
    clone.querySelectorAll('[id]').forEach(function (el) { el.id = 'map-' + el.id; });
    clone.querySelectorAll('[for]').forEach(function (el) { el.setAttribute('for', 'map-' + el.getAttribute('for')); });

    // Build collapsible groups from .filter-block divs (matching your twig structure)
    sidebar.innerHTML = '';
    var filterBlocks = clone.querySelectorAll('.filter-block');

    if (filterBlocks.length > 0) {
      filterBlocks.forEach(function (block) {
        // Get the label from the first visible label/legend inside the block
        var labelEl = block.querySelector('label, legend, .fieldset-legend');
        var labelText = labelEl ? labelEl.textContent.trim() : '';
        // Skip hidden/empty blocks
        if (!block.innerHTML.trim()) return;

        var wrapper = document.createElement('div');
        wrapper.className = 'map-filter-group';
        var header = document.createElement('div');
        header.className = 'map-filter-group__label';
        header.innerHTML = (labelText || 'Filter') +
          '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>';
        var items = document.createElement('div');
        items.className = 'map-filter-group__items';
        items.appendChild(block);
        header.addEventListener('click', function () { wrapper.classList.toggle('map-filter-group--collapsed'); });
        wrapper.appendChild(header);
        wrapper.appendChild(items);
        sidebar.appendChild(wrapper);
      });
    } else {
      // Fallback — drop the whole form in
      sidebar.appendChild(clone);
    }

    // Sync cloned inputs back to real form on change
    sidebar.querySelectorAll('input, select').forEach(function (input) {
      input.addEventListener('change', function () {
        var realId = input.id.replace(/^map-/, '');
        var realInput = document.getElementById(realId) ||
                        sourceForm.querySelector('[name="' + input.name + '"]');
        if (realInput) {
          if (input.type === 'checkbox' || input.type === 'radio') {
            realInput.checked = input.checked;
          } else {
            realInput.value = input.value;
          }
          realInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    });

    // Clear all
    var clearBtn = document.getElementById('map-filter-clear');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        sidebar.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(function (cb) {
          cb.checked = false;
          cb.dispatchEvent(new Event('change', { bubbles: true }));
        });
        sidebar.querySelectorAll('input[type="text"], input[type="search"]').forEach(function (inp) {
          inp.value = '';
          inp.dispatchEvent(new Event('change', { bubbles: true }));
        });
        sidebar.querySelectorAll('select').forEach(function (sel) {
          sel.selectedIndex = 0;
          sel.dispatchEvent(new Event('change', { bubbles: true }));
        });
      });
    }

    // Apply — clicks the real form submit
    var applyBtn = document.getElementById('map-filter-apply');
    if (applyBtn) {
      applyBtn.addEventListener('click', function () {
        var submitBtn = sourceForm.querySelector('[type="submit"], .form-submit');
        if (submitBtn) submitBtn.click();
      });
    }

    filterCloned = true;
  }

  // ─── Icon factory (Leaflet) ───────────────────────────────────────────────
  var _iconCache = {};
  function makePricePillIcon(price, isActive) {
    var key = (price || '') + (isActive ? '_a' : '_i');
    if (_iconCache[key]) return _iconCache[key];
    var label = price ? '\u20B9' + price : '\u25CF';
    _iconCache[key] = L.divIcon({
      className: 'gm-pill-marker' + (isActive ? ' gm-pill-marker--active' : ''),
      html: '<div class="gm-pill-wrap"><div class="gm-pill-inner">' + label + '</div><div class="gm-pill-tail"></div></div>',
      iconSize:   null,
      iconAnchor: [40, 39]
    });
    return _iconCache[key];
  }

  // ─── Pill HTML (Google Maps) ──────────────────────────────────────────────
  function pillHTML(price) {
    var label = price ? '\u20B9' + price : '\u25CF';
    return '<div class="gm-pill-wrap"><div class="gm-pill-inner">' + label + '</div><div class="gm-pill-tail"></div></div>';
  }

  // ─── Drupal behavior ──────────────────────────────────────────────────────
  Drupal.behaviors.propertyMap = {

    attach: function (context, settings) {
      var self = this;
      once('map-styles',       'body',                 context).forEach(function () { injectStyles(); });
      once('map-portal',       '[data-map-modal]',     context).forEach(function (modal) {
        if (modal.parentElement !== document.body) document.body.appendChild(modal);
      });
      once('map-preview-init', '#property-map-preview', context).forEach(function () {
        self.initPreviewMap(settings);
      });
      once('open-map', '[data-map-open]', context).forEach(function (btn) {
        btn.addEventListener('click', function () {
          var modal = document.querySelector('[data-map-modal]');
          if (!modal) return;
          modal.hidden = false;
          document.body.classList.add('map-modal-open');
          initFilterSidebar();
          setTimeout(function () { self.initMap(settings); }, 300);
        });
      });
      once('map-close-listener', 'body', context).forEach(function () {
        document.addEventListener('click', function (e) {
          if (!e.target.closest('[data-map-close]')) return;
          var modal = document.querySelector('[data-map-modal]');
          if (!modal) return;
          modal.hidden = true;
          document.body.classList.remove('map-modal-open');
          scheduleHide();
        });
      });
    },

    // ── Preview map ──────────────────────────────────────────────────────────
    initPreviewMap: function (settings) {
      if (previewMap) return;
      var el = document.getElementById('property-map-preview');
      if (!el) return;
      previewMap = L.map(el, {
        zoomControl: false, attributionControl: false,
        dragging: false, scrollWheelZoom: false,
        doubleClickZoom: false, boxZoom: false, keyboard: false
      });
      L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO',
        subdomains: 'abcd', maxZoom: 20
      }).addTo(previewMap);
      var data   = settings.property_map || [];
      var bounds = L.latLngBounds();
      data.slice(0, 6).forEach(function (item) {
        if (!item.lat || !item.lng) return;
        L.marker([item.lat, item.lng], {
          icon: L.divIcon({ className: 'gm-marker', html: '<div class="gm-marker-pin"></div>', iconSize: [18, 18], iconAnchor: [9, 9] }),
          interactive: false
        }).addTo(previewMap);
        bounds.extend([item.lat, item.lng]);
      });
      if (bounds.isValid()) previewMap.fitBounds(bounds, { padding: [30, 30] });
      setTimeout(function () { previewMap.invalidateSize(); }, 200);
    },

    // ── Full map ─────────────────────────────────────────────────────────────
    initMap: function (settings) {
      var el   = document.getElementById('property-map');
      var data = settings.property_map || [];
      if (!el || !data.length) return;
      var self = this;
      var apiKey = (drupalSettings.google_maps_api_key) || '';
      if (!apiKey) { self.initLeafletMap(el, data); return; }

      if (!window.google || !window.google.maps) {
        if (!document.getElementById('gm-api-script')) {
          window.__googleMapsCallback = function () {};
          var script  = document.createElement('script');
          script.id   = 'gm-api-script';
          script.src  = 'https://maps.googleapis.com/maps/api/js?key=' + apiKey + '&loading=async&callback=__googleMapsCallback';
          script.async = true;
          document.head.appendChild(script);
        }
      }

      var waitForMaps = function () {
        google.maps.importLibrary('maps').then(function () {
          google.maps.importLibrary('marker').then(function () {
            self.initGoogleMap(el, data);
          }).catch(function () { self.initGoogleMap(el, data); });
        }).catch(function () { self.initLeafletMap(el, data); });
      };

      if (window.google && window.google.maps && window.google.maps.importLibrary) {
        waitForMaps();
      } else {
        var attempts = 0;
        var poll = setInterval(function () {
          attempts++;
          if (window.google && window.google.maps && window.google.maps.importLibrary) {
            clearInterval(poll);
            waitForMaps();
          } else if (attempts > 40) {
            clearInterval(poll);
            self.initLeafletMap(el, data);
          }
        }, 150);
      }
    },

    // ── Google Maps ──────────────────────────────────────────────────────────
    initGoogleMap: function (el, data) {
      var LatLngBounds = google.maps.LatLngBounds;
      var LatLng       = google.maps.LatLng;
      var Map          = google.maps.Map;
      var bounds       = new LatLngBounds();
      data.forEach(function (i) { if (i.lat && i.lng) bounds.extend({ lat: i.lat, lng: i.lng }); });

      if (googleMap) { googleMap.fitBounds(bounds); return; }

      var first = data.find(function (i) { return i.lat && i.lng; }) || { lat: 12.9716, lng: 77.5946 };
      googleMap = new Map(el, {
        center: { lat: first.lat, lng: first.lng },
        zoom: 11,
        gestureHandling: 'greedy',
        zoomControl: true,
        streetViewControl: false,
        mapTypeControl: false,
        fullscreenControl: false,
        styles: [{ stylers: [{ hue: '#67c4e6' }, { saturation: -70 }, { lightness: -2 }] }]
      });

      data.forEach(function (item) {
        if (!item.lat || !item.lng) return;
        var pillDiv = document.createElement('div');
        pillDiv.className = 'gm-pill-marker';
        pillDiv.innerHTML = pillHTML(item.price);
        pillDiv.style.cssText = 'position:absolute;cursor:pointer;transform:translate(-50%,-100%);';

        var overlay = new google.maps.OverlayView();
        overlay.onAdd    = function () { this.getPanes().overlayMouseTarget.appendChild(pillDiv); };
        overlay.onRemove = function () { if (pillDiv.parentNode) pillDiv.parentNode.removeChild(pillDiv); };
        overlay.draw     = function () {
          var proj = this.getProjection();
          if (!proj) return;
          var p = proj.fromLatLngToDivPixel(new LatLng(item.lat, item.lng));
          if (!p) return;
          pillDiv.style.left = p.x + 'px';
          pillDiv.style.top  = p.y + 'px';
        };
        overlay.setMap(googleMap);
        googleOverlays.push(overlay);

        pillDiv.addEventListener('mouseenter', function () { pillDiv.classList.add('gm-pill-marker--active');    showCard(item, pillDiv); });
        pillDiv.addEventListener('mouseleave', function () { pillDiv.classList.remove('gm-pill-marker--active'); scheduleHide(); });
      });

      googleMap.fitBounds(bounds);
    },

    // ── Leaflet fallback ─────────────────────────────────────────────────────
    initLeafletMap: function (el, data) {
      if (leafletMap) { leafletMap.invalidateSize(); return; }
      leafletMap = L.map(el, { scrollWheelZoom: true, minZoom: 5, maxZoom: 18 });
      L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CartoDB',
        subdomains: 'abcd', maxZoom: 20
      }).addTo(leafletMap);

      var applyFilter = function () {
        var pane = leafletMap.getPanes().tilePane;
        if (pane) pane.style.filter = 'sepia(1) hue-rotate(155deg) saturate(0.6) brightness(1.05)';
      };
      leafletMap.whenReady(applyFilter);
      setTimeout(applyFilter, 100);

      var bounds = L.latLngBounds();
      data.forEach(function (item) {
        if (!item.lat || !item.lng) return;
        var iconNormal = makePricePillIcon(item.price, false);
        var iconActive = makePricePillIcon(item.price, true);
        var marker = L.marker([item.lat, item.lng], { icon: iconNormal, riseOnHover: true }).addTo(leafletMap);
        marker.on('mouseover', function () {
          this.setIcon(iconActive);
          var el = this.getElement();
          if (el) showCard(item, el);
        });
        marker.on('mouseout', function () {
          this.setIcon(iconNormal);
          scheduleHide();
        });
        bounds.extend([item.lat, item.lng]);
      });

      if (bounds.isValid()) leafletMap.fitBounds(bounds, { padding: [80, 80] });
      setTimeout(function () { leafletMap.invalidateSize(); }, 300);
    }
  };

})(Drupal, once);