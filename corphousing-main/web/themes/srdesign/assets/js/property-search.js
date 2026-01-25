((Drupal, once) => {
  Drupal.behaviors.srdesignPropertyPriceSort = {
    attach(context) {
      once('srdesignPropertyPriceSort', '#property-sort-select', context).forEach((select) => {
        select.addEventListener('change', (event) => {
          const sortValue = event.target.value;

          const url = new URL(window.location.href);

          if (sortValue) {
            url.searchParams.set('sort_by', sortValue);
          } else {
            url.searchParams.delete('sort_by');
          }

          window.location.href = url.toString();
        });
      });
    },
  };
})(Drupal, once);

