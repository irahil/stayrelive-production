var map;
var gmarkers = [];

function initMap() {
  var stylers = [{
     "stylers": [{
           "hue": "#67c4e6"
     }, {
           "saturation": -70
     }, {
           "lightness": -2
     }]
  }]
  var mapOptions = {
        center: new google.maps.LatLng(markers[0].lat, markers[0].lng),
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        styles: stylers,
        mapTypeControl: false,
        streetViewControl: false
  };
  var infoWindow = new google.maps.InfoWindow();
  var map = new google.maps.Map(document.getElementById("google_map"), mapOptions);

  //Create LatLngBounds object.
  var latlngbounds = new google.maps.LatLngBounds();

  for (var i = 0; i < markers.length; i++) {
     var data = markers[i]
     var myLatlng = new google.maps.LatLng(data.lat, data.lng);
     var marker = new google.maps.Marker({
        position: myLatlng,
        map: map,
        icon: {
          url: 'data:image/svg+xml;charset=utf-8,' +
            encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24"><path fill="#0d0e1a" d="M12 0c-4.198 0-8 3.403-8 7.602 0 4.198 3.469 9.21 8 16.398 4.531-7.188 8-12.2 8-16.398 0-4.199-3.801-7.602-8-7.602zm0 11c-1.657 0-3-1.343-3-3s1.343-3 3-3 3 1.343 3 3-1.343 3-3 3z"/></svg>'),
          scaledSize: new google.maps.Size(44, 44),
          origin: new google.maps.Point(0, 0),
          anchor: new google.maps.Point(44, 44),
          labelOrigin: new google.maps.Point(22, 18),
        },
     });
     marker.setOpacity(.75);
     (function (marker, data) {
        google.maps.event.addListener(marker, "click", function (e) {
          infoWindow.setContent("<div style = 'width:200px;min-height:40px'><b><a href='" + data.link + "'>" + data.title + "</a></b></div>");
          infoWindow.open(map, marker);
        });
        google.maps.event.addListener(map, 'click', function() {
          infoWindow.close();
        });
     })(marker, data);

     //Extend each marker's position in LatLngBounds object.
     latlngbounds.extend(marker.position);
     //alert('in function ' + data.title);
     gmarkers['property-'+data.id] = marker;
  }

  //Get the boundaries of the Map.
  var bounds = new google.maps.LatLngBounds();
  //Center map and adjust Zoom based on the position of all markers.
  map.setCenter(latlngbounds.getCenter());
  map.fitBounds(latlngbounds);

  google.maps.event.addListener(map, 'mousedown', function(event){
    this.setOptions({scrollwheel:true});
  });
  google.maps.event.addListener(map, 'mouseover', function(event){
    self = this;
    timer = setTimeout(function() {
      self.setOptions({scrollwheel:true});
    }, 1000);
  });
  google.maps.event.addListener(map, 'mouseout', function(event){
    this.setOptions({scrollwheel:false});
    clearTimeout(timer);
  });

}
window.initMap = initMap;
jQuery(document).ready(function($) {
      jQuery('.property-map-link').hover( function(){
            var $this = $(this),
            link_nid = $this.data('location');
            gmarkers[link_nid].setOpacity(1.0);
            gmarkers[link_nid].setIcon({
              url: 'data:image/svg+xml;charset=utf-8,' +
                encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24"><path fill="red" d="M12 0c-4.198 0-8 3.403-8 7.602 0 4.198 3.469 9.21 8 16.398 4.531-7.188 8-12.2 8-16.398 0-4.199-3.801-7.602-8-7.602zm0 11c-1.657 0-3-1.343-3-3s1.343-3 3-3 3 1.343 3 3-1.343 3-3 3z"/></svg>'),
              scaledSize: new google.maps.Size(44, 44),
              origin: new google.maps.Point(0, 0),
              anchor: new google.maps.Point(44, 44),
              labelOrigin: new google.maps.Point(22, 18),
            })
            jQuery(this).css('background-color', '#d6f4ff');
      },
      function(){
            var $this = $(this),
            link_nid = $this.data('location');
            gmarkers[link_nid].setOpacity(.75);
            gmarkers[link_nid].setIcon({
                  url: 'data:image/svg+xml;charset=utf-8,' +
                  encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24"><path fill="#0d0e1a" d="M12 0c-4.198 0-8 3.403-8 7.602 0 4.198 3.469 9.21 8 16.398 4.531-7.188 8-12.2 8-16.398 0-4.199-3.801-7.602-8-7.602zm0 11c-1.657 0-3-1.343-3-3s1.343-3 3-3 3 1.343 3 3-1.343 3-3 3z"/></svg>'),
                  scaledSize: new google.maps.Size(44, 44),
                  origin: new google.maps.Point(0, 0),
                  anchor: new google.maps.Point(44, 44),
                  labelOrigin: new google.maps.Point(22, 18),
            });
            jQuery(this).css('background-color', '#ffffff');
      });
});

/*
$('a').hover(
    function() {
      var $this = $(this),
        loc = $this.data('location');
      gmarkers[loc].setOpacity(1.0);
      gmarkers[loc].setIcon({
        url: 'data:image/svg+xml;charset=utf-8,' +
          encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24"><path fill="red" d="M12 0c-4.198 0-8 3.403-8 7.602 0 4.198 3.469 9.21 8 16.398 4.531-7.188 8-12.2 8-16.398 0-4.199-3.801-7.602-8-7.602zm0 11c-1.657 0-3-1.343-3-3s1.343-3 3-3 3 1.343 3 3-1.343 3-3 3z"/></svg>'),
        scaledSize: new google.maps.Size(44, 44),
        origin: new google.maps.Point(0, 0),
        anchor: new google.maps.Point(44, 44),
        labelOrigin: new google.maps.Point(22, 18),
      })
    },
    function() {
      var $this = $(this),
        loc = $this.data('location');
      gmarkers[loc].setOpacity(.75);
      gmarkers[loc].setIcon({
        url: 'data:image/svg+xml;charset=utf-8,' +
          encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24"><path d="M12 0c-4.198 0-8 3.403-8 7.602 0 4.198 3.469 9.21 8 16.398 4.531-7.188 8-12.2 8-16.398 0-4.199-3.801-7.602-8-7.602zm0 11c-1.657 0-3-1.343-3-3s1.343-3 3-3 3 1.343 3 3-1.343 3-3 3z"/></svg>'),
        scaledSize: new google.maps.Size(44, 44),
        origin: new google.maps.Point(0, 0),
        anchor: new google.maps.Point(44, 44),
        labelOrigin: new google.maps.Point(22, 18),
      });
    }
  );
  */