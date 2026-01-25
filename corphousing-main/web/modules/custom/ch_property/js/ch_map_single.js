function initMap() {
  const mapElement = document.getElementById("google_map");
  const lat = parseFloat(mapElement.dataset.lat);
  const lng = parseFloat(mapElement.dataset.lng);
  const propertyName = mapElement.dataset.propertyName || "Property Location";
  const location = { lat, lng };

  const map = new google.maps.Map(document.getElementById("google_map"), {
    center: location,
    zoom: 15,
    styles: [{
      "stylers": [
        { "hue": "#67c4e6" },
        { "saturation": -70 },
        { "lightness": -2 }
      ]
    }],
    streetViewControl: true
  });

  // Create info window for property marker
  const infoWindow = new google.maps.InfoWindow({
    content: `<div style="padding: 10px;">
                <strong>${propertyName}</strong><br>
                <small>Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}</small>
              </div>`
  });

  // Main marker
  const propertyMarker = new google.maps.Marker({
    position: location,
    map,
    title: propertyName,
    icon: {
      url: 'https://maps.gstatic.com/mapfiles/api-3/images/spotlight-poi2.png',
      scaledSize: new google.maps.Size(30, 40)
    }
  });

  // Add click event to show property name
  propertyMarker.addListener('click', function() {
    infoWindow.open(map, propertyMarker);
  });

  const service = new google.maps.places.PlacesService(map);

  const request = {
    location,
    radius: 1000, // 1km radius
    type: ['restaurant', 'school', 'gym', 'hospital']
  };

  service.nearbySearch(request, function(results, status) {
    console.log('PlacesService status:', status);
    console.log('Nearby results:', results);

    if (status === google.maps.places.PlacesServiceStatus.OK) {

      results.forEach(place => {
        new google.maps.Marker({
          map,
          position: place.geometry.location,
          title: place.name,
          icon: {
            url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png'
          }
        });
      });
    } else {
        alert("Nearby search failed: " + status)
    }
  });
}

window.initMap = initMap;
