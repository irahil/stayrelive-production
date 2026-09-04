jQuery(document).ready(function ($) {
  // City suggestions come from sr.town_city_autocomplete (SrController::
  // townCityAutocomplete()) as the user types, rather than a full local
  // array — the town_city vocabulary has ~71k terms, too many to embed
  // in the page or scan client-side on every keystroke.
  function autocomplete(inp) {
    var currentFocus;
    var debounceTimer;
    var activeRequest;

    function escapeHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function renderList(matches, val) {
      closeAllLists();
      if (!matches.length) {
        return;
      }
      var a = document.createElement('DIV');
      a.setAttribute('id', inp.id + 'autocomplete-list');
      a.setAttribute('class', 'autocomplete-items');
      inp.parentNode.appendChild(a);

      matches.forEach(function (name) {
        var b = document.createElement('DIV');
        var idx = name.toLowerCase().indexOf(val.toLowerCase());
        if (idx === -1) {
          b.innerHTML = escapeHtml(name);
        }
        else {
          b.innerHTML = escapeHtml(name.substr(0, idx))
            + '<strong>' + escapeHtml(name.substr(idx, val.length)) + '</strong>'
            + escapeHtml(name.substr(idx + val.length));
        }
        b.innerHTML += "<input type='hidden' value='" + escapeHtml(name) + "'>";
        b.addEventListener('click', function () {
          inp.value = this.getElementsByTagName('input')[0].value;
          closeAllLists();
        });
        a.appendChild(b);
      });
    }

    inp.addEventListener('input', function () {
      var val = this.value;
      closeAllLists();
      clearTimeout(debounceTimer);
      if (activeRequest) {
        activeRequest.abort();
      }
      currentFocus = -1;
      if (!val || val.length < 2) {
        return;
      }

      debounceTimer = setTimeout(function () {
        activeRequest = $.getJSON(Drupal.url('sr/town-city/autocomplete'), { q: val })
          .done(function (matches) {
            renderList(matches || [], val);
          });
      }, 250);
    });

    inp.addEventListener('keydown', function (e) {
      var x = document.getElementById(this.id + 'autocomplete-list');
      if (x) x = x.getElementsByTagName('div');
      if (e.keyCode == 40) {
        currentFocus++;
        addActive(x);
      } else if (e.keyCode == 38) {
        currentFocus--;
        addActive(x);
      } else if (e.keyCode == 13) {
        e.preventDefault();
        if (currentFocus > -1) {
          if (x) x[currentFocus].click();
        }
      }
    });
    function addActive(x) {
      if (!x) return false;
      removeActive(x);
      if (currentFocus >= x.length) currentFocus = 0;
      if (currentFocus < 0) currentFocus = (x.length - 1);
      x[currentFocus].classList.add('autocomplete-active');
    }
    function removeActive(x) {
      for (var i = 0; i < x.length; i++) {
        x[i].classList.remove('autocomplete-active');
      }
    }
    function closeAllLists(elmnt) {
      var x = document.getElementsByClassName('autocomplete-items');
      for (var i = 0; i < x.length; i++) {
        if (elmnt != x[i] && elmnt != inp) {
          x[i].parentNode.removeChild(x[i]);
        }
      }
    }
    document.addEventListener('click', function (e) {
      closeAllLists(e.target);
    });
  }

  var townCityInput = document.getElementById('autocomplete_town_city');
  if (townCityInput) {
    autocomplete(townCityInput);
  }
});
