var map;
var gmarkers = [];
var srInfoWindow; // global ref so the inline close button can call srCloseInfoWindow()

function srCloseInfoWindow() {
  if (srInfoWindow) srInfoWindow.close();
}

/* ── Price pill icon ─────────────────────────────────────────────────────── */
function createPriceIcon(price, isActive) {
  var label     = price || 'On Request';
  var w         = Math.max(Math.round(label.length * 7.5 + 12), 56);
  var bg        = isActive ? '#0B8D9F' : '#ffffff';   // teal on hover, white default
  var textColor = isActive ? '#ffffff' : '#192452';   // white on teal, navy on white
  var svg =
    '<svg xmlns="http://www.w3.org/2000/svg" width="' + w + '" height="30">' +
      '<rect width="' + w + '" height="30" rx="15" ry="15" fill="' + bg + '"/>' +
      '<text x="' + (w / 2) + '" y="20" font-family="Arial,sans-serif" font-size="12" font-weight="700" fill="' + textColor + '" text-anchor="middle">' + label + '</text>' +
    '</svg>';
  return {
    url:        'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg),
    scaledSize: new google.maps.Size(w, 30),
    origin:     new google.maps.Point(0, 0),
    anchor:     new google.maps.Point(w / 2, 15),
  };
}

/* ── Property card HTML — vertical layout ───────────────────────────────── */
function createInfoWindowContent(data) {
  var imgHtml = data.image
    ? '<img class="sr-iw-img" src="' + data.image + '" loading="lazy"/>'
    : '<div class="sr-iw-img-placeholder"></div>';

  var roomLine = data.bedrooms
    ? '<div class="sr-iw-rooms">' + data.bedrooms + ' bedroom &nbsp;&middot;&nbsp; ' + data.bathrooms + ' bathroom</div>'
    : '';

  var priceHtml = (data.price && data.price !== 'On Request')
    ? '<span class="sr-iw-price">' + data.price + '</span><span class="sr-iw-price-unit"> / night</span>'
    : '<span class="sr-iw-poa">Price on Request</span>';

  return (
    '<div class="sr-iw-card">' +
      '<div class="sr-iw-img-wrap">' +
        imgHtml +
        '<button class="sr-iw-close" onclick="srCloseInfoWindow()" aria-label="Close">&times;</button>' +
      '</div>' +
      '<div class="sr-iw-body">' +
        '<div class="sr-iw-title">' + data.title + '</div>' +
        roomLine +
        '<div class="sr-iw-price-row">' +
          '<div class="sr-iw-price-wrap">' + priceHtml + '</div>' +
          '<a href="' + data.link + '" class="sr-iw-link">View &rarr;</a>' +
        '</div>' +
      '</div>' +
    '</div>'
  );
}

/* ── Map Search Control ──────────────────────────────────────────────────── */
function addSearchControl(map) {
  var container = document.createElement('div');
  container.className = 'sr-map-search';

  container.innerHTML =
    '<svg class="sr-map-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="22" y2="22"/></svg>' +
    '<input class="sr-map-search-input" type="text" placeholder="Search by address or place" />' +
    '<button class="sr-map-search-clear" aria-label="Clear">&times;</button>';

  var input    = container.querySelector('.sr-map-search-input');
  var clearBtn = container.querySelector('.sr-map-search-clear');

  map.controls[google.maps.ControlPosition.TOP_CENTER].push(container);

  var autocomplete = new google.maps.places.Autocomplete(input);
  autocomplete.bindTo('bounds', map);

  autocomplete.addListener('place_changed', function () {
    var place = autocomplete.getPlace();
    if (!place.geometry) return;
    if (place.geometry.viewport) {
      map.fitBounds(place.geometry.viewport);
    } else {
      map.setCenter(place.geometry.location);
      map.setZoom(14);
    }
    clearBtn.style.display = 'flex';
  });

  input.addEventListener('input', function () {
    if (!this.value) clearBtn.style.display = 'none';
  });

  clearBtn.addEventListener('click', function () {
    input.value = '';
    clearBtn.style.display = 'none';
    input.focus();
  });
}

/* ── Map initialisation ──────────────────────────────────────────────────── */
function initMap() {
  var stylers = [{ stylers: [{ hue: '#67c4e6' }, { saturation: -70 }, { lightness: -2 }] }];

  var mapOptions = {
    center:            new google.maps.LatLng(markers[0].lat, markers[0].lng),
    mapTypeId:         google.maps.MapTypeId.ROADMAP,
    styles:            stylers,
    mapTypeControl:    false,
    streetViewControl: false,
  };

  srInfoWindow = new google.maps.InfoWindow({ maxWidth: 280, disableAutoPan: false });
  var infoWindow   = srInfoWindow;
  var map          = new google.maps.Map(document.getElementById('google_map'), mapOptions);
  var latlngbounds = new google.maps.LatLngBounds();

  /* Force-remove Google's internal padding that causes the white gap above image */
  google.maps.event.addListener(infoWindow, 'domready', function () {
    var iwc = document.querySelector('.gm-style-iw-c');
    var iwd = document.querySelector('.gm-style-iw-d');
    if (iwc) { iwc.style.padding = '0'; iwc.style.overflow = 'hidden'; }
    if (iwd) { iwd.style.padding = '0'; iwd.style.overflow = 'hidden'; }
  });

  for (var i = 0; i < markers.length; i++) {
    var data   = markers[i];
    var latlng = new google.maps.LatLng(data.lat, data.lng);
    var marker = new google.maps.Marker({
      position: latlng,
      map:      map,
      icon:     createPriceIcon(data.price, false),
    });
    marker.priceLabel = data.price;

    (function (marker, data) {
      google.maps.event.addListener(marker, 'mouseover', function () {
        marker.setIcon(createPriceIcon(marker.priceLabel, true));
      });
      google.maps.event.addListener(marker, 'mouseout', function () {
        marker.setIcon(createPriceIcon(marker.priceLabel, false));
      });
      google.maps.event.addListener(marker, 'click', function () {
        infoWindow.setContent(createInfoWindowContent(data));
        infoWindow.open(map, marker);
      });
    })(marker, data);

    google.maps.event.addListener(map, 'click', function () { infoWindow.close(); });

    latlngbounds.extend(marker.position);
    gmarkers['property-' + data.id] = marker;
  }

  map.setCenter(latlngbounds.getCenter());
  map.fitBounds(latlngbounds);
  addSearchControl(map);

  /* Scroll-wheel: disabled by default, enabled after 1s hover */
  google.maps.event.addListener(map, 'mousedown', function () { this.setOptions({ scrollwheel: true }); });
  google.maps.event.addListener(map, 'mouseover', function () {
    var self = this;
    timer = setTimeout(function () { self.setOptions({ scrollwheel: true }); }, 1000);
  });
  google.maps.event.addListener(map, 'mouseout', function () {
    this.setOptions({ scrollwheel: false });
    clearTimeout(timer);
  });
}

window.initMap = initMap;

/* ── Card ↔ Marker hover sync ───────────────────────────────────────────── */
jQuery(document).ready(function ($) {
  jQuery('.property-map-link').hover(
    function () {
      var nid = $(this).data('location');
      var m   = gmarkers[nid];
      if (!m) return;
      m.setIcon(createPriceIcon(m.priceLabel, true));
      m.setZIndex(google.maps.Marker.MAX_ZINDEX + 1);
      $(this).css('background-color', '#d6f4ff');
    },
    function () {
      var nid = $(this).data('location');
      var m   = gmarkers[nid];
      if (!m) return;
      m.setIcon(createPriceIcon(m.priceLabel, false));
      m.setZIndex(null);
      $(this).css('background-color', '');
    }
  );
});
