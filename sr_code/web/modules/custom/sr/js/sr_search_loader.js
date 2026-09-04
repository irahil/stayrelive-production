jQuery(document).ready(function ($) {
  // Property search is a classic full-page-submit flow (HomeSearchForm
  // and PropertySearchForm both reload the page rather than using AJAX),
  // so "loading" here means a page-transition overlay covering the time
  // between submit/pager-click and the new page finishing render.
  var overlay = document.createElement('div');
  overlay.className = 'sr-search-loader-overlay';
  overlay.setAttribute('hidden', 'hidden');
  overlay.innerHTML = '<div class="sr-search-loader-content">'
    + '<div class="sr-search-loader-logo"></div>'
    + '<div class="sr-search-loader-spinner"></div>'
    + '<div class="sr-search-loader-text">Searching properties…</div>'
    + '</div>';
  document.body.appendChild(overlay);

  function showLoader() {
    overlay.classList.remove('sr-search-loader-fade');
    overlay.removeAttribute('hidden');
  }

  // Fades the overlay out (matching corphousing's loader transition) before
  // hiding it, rather than switching it off instantly.
  function hideLoader() {
    overlay.classList.add('sr-search-loader-fade');
    setTimeout(function () {
      overlay.setAttribute('hidden', 'hidden');
    }, 500);
  }

  // Search form submits — covers the main Search button, the sort-by
  // auto-submit (onchange="this.form.submit()"), and the inline
  // price/room/amenities/property-type "Search" buttons, since they're
  // all submit buttons within the same marked form.
  document.addEventListener('submit', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('sr-search-form')) {
      showLoader();
    }
  });

  // Pager links (Drupal core pager markup) — plain <a> navigations, not
  // form submits, so they need their own listener.
  document.addEventListener('click', function (e) {
    var link = e.target.closest ? e.target.closest('.pager a') : null;
    if (link) {
      showLoader();
    }
  });

  // Guard against a stuck overlay if the page is restored from the
  // browser's back/forward cache instead of freshly reloading.
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      hideLoader();
    }
  });
});
