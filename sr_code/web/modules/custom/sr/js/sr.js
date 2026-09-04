jQuery(document).ready(function ($) {

  jQuery(".view-hero-slider video").attr('playsinline', 'playsinline');
  

  // Function to set the showMonths option based on screen size
  function setFlatpickrOptions() {
      const windowWidth = $(window).width();
      const isMdScreen = windowWidth >= 768;

      const flatpickrOptions = {
        altInput: true,
        mode: "range",
        minDate: "today",
        altFormat: "J M y",
        dateFormat: "Y-m-d"
      };

      if (isMdScreen) {
          flatpickrOptions.showMonths = 2;
      } else {
          flatpickrOptions.showMonths = 1;
      }

      flatpickr('#flatpickr_date_range', flatpickrOptions);

      const datepickerElements = document.querySelectorAll(".flatpickr_date_range_nav");
      datepickerElements.forEach((element) => {
        const flatpickrInstance = flatpickr(element, flatpickrOptions);
        flatpickrInstance.set("showMonths", isMdScreen ? 2 : 1);
      });
  }

  setFlatpickrOptions();

  $(window).on('resize', function () {
      setFlatpickrOptions();
  });
});

function customReset() {
  document.getElementById('edit-price-min').value = '';
  document.getElementById('edit-price-max').value = '';

  var uncheck=document.getElementsByTagName('input');
  for(var i=0;i<uncheck.length;i++) {
   if(uncheck[i].type=='checkbox' && uncheck[i].name.startsWith("amenities")) {
    uncheck[i].checked=false;
   }
  }
  return false;
}

// function initFlatpickr() {
//   const isMobile = window.innerWidth <= 758;

//   const datepickerElements = document.querySelectorAll(".flatpickr_date_range_nav");
//   const flatpickr_date_range = document.getElementById('flatpickr_date_range');


//   datepickerElements.forEach((element) => {
//     const flatpickrInstance = flatpickr(element, {
//       mode:"range",
//       minDate: "today"
//     });

//     flatpickrInstance.set("showMonths", isMobile ? 1 : 2);
//   });


//   const flatpickrInstance2 = flatpickr(flatpickr_date_range, {
//     mode:"range",
//     minDate: "today"
//   });

//   flatpickrInstance2.set("showMonths", isMobile ? 1 : 2);
// }

document.addEventListener('DOMContentLoaded', function () {

  if(window.location.pathname === '/property/search') {

  let sliderOne = document.getElementById("slider-1");
  let sliderTwo = document.getElementById("slider-2");
  let minGap = 500;
  let sliderTrack = document.querySelector(".slider-track");
  let sliderMaxValue = sliderOne.max;

  // Define slideOne function
  function slideOne() {
    if (parseInt(sliderTwo.value) - parseInt(sliderOne.value) <= minGap) {
      sliderOne.value = parseInt(sliderTwo.value) - minGap;
    }
    fillColor();
  }

  // Define slideTwo function
  function slideTwo() {
    if (parseInt(sliderTwo.value) - parseInt(sliderOne.value) <= minGap) {
      sliderTwo.value = parseInt(sliderOne.value) + minGap;
    }
    fillColor();
  }

  // Function to fill color between sliders
  function fillColor() {
    let percent1 = (sliderOne.value / sliderMaxValue) * 100;
    let percent2 = (sliderTwo.value / sliderMaxValue) * 100;
    
    // Fallback for browsers like Safari/Firefox
    if (sliderTrack) {
      sliderTrack.style.background = `linear-gradient(to right, #dadae5 ${percent1}% , #0B8D9F ${percent1}% , #0B8D9F ${percent2}%, #dadae5 ${percent2}%)`;
    }
  }

  // Event listeners for sliders using both 'input' and 'change' events for cross-browser compatibility
  sliderOne.addEventListener('input', function () {
    slideOne();
    updateMinMaxInputs();
  });

  sliderOne.addEventListener('change', function () {
    slideOne();
    updateMinMaxInputs();
  });

  sliderTwo.addEventListener('input', function () {
    slideTwo();
    updateMinMaxInputs();
  });

  sliderTwo.addEventListener('change', function () {
    slideTwo();
    updateMinMaxInputs();
  });

  // Update min and max price inputs based on sliders
  function updateMinMaxInputs() {
    jQuery("#edit-price-min").val(sliderOne.value);
    jQuery("#edit-price-max").val(sliderTwo.value);

    // Store values in localStorage
    localStorage.setItem("minValue", sliderOne.value);
    localStorage.setItem("maxValue", sliderTwo.value);
  }

  // Load saved values from localStorage if available
  var storedMaxValue = localStorage.getItem('maxValue');
  var storedMinValue = localStorage.getItem('minValue');

  if (storedMaxValue) {
    sliderTwo.value = storedMaxValue;
  }

  if (storedMinValue) {
    sliderOne.value = storedMinValue;
  }

  // Initial color fill and input update
  updateMinMaxInputs();
  fillColor(); // Fill the color when page loads
  }
});

jQuery(document).ready(function () {
  function hideMessages(){
    var isLoginMessage = jQuery('.messages-list');
      if (isLoginMessage.length) {
        setTimeout(function () {
          isLoginMessage.fadeOut('slow');
      }, 3000);
    }
  }
  // On page load
  hideMessages();

  // On ajax request
  jQuery(document).ajaxComplete(function () {
    hideMessages();
  });
});

jQuery(document).ready(function () {
  function toggleClearButton(inputField, checkDefaultValue = false) {
      let clearButton = jQuery(inputField).siblings(".clear-search");
      let value = jQuery(inputField).val().trim();

      // Apply default value check only for .search-city-default-check
      if (checkDefaultValue && (value === "" || jQuery(inputField).is(":hidden"))) {
          clearButton.hide();
      } else if (!checkDefaultValue && value === "") {
          clearButton.hide();
      } else {
          clearButton.show();
      }
  }

  // Add clear button dynamically where necessary (City & Date fields)
  jQuery(".search-city input, .search-date input").each(function () {
      if (jQuery(this).siblings(".clear-search").length === 0) {
          jQuery(this).after('<button type="button" class="clear-search">✖</button>');
      }
      // Check for default value only if it's inside .search-city-default-check
      let checkDefaultValue = jQuery(this).closest(".search-city-default-check").length > 0;
      toggleClearButton(this, checkDefaultValue);
  });

  // Show/hide clear button when typing (City & Date fields)
  jQuery(".search-city input, .search-date input").on("input", function () {
      let checkDefaultValue = jQuery(this).closest(".search-city-default-check").length > 0;
      toggleClearButton(this, checkDefaultValue);
  });

  // Clear field when clicking the button (City & Date fields)
  jQuery(".search-city, .search-date").on("click", ".clear-search", function () {
      let inputField = jQuery(this).siblings("input");
      inputField.val("").trigger("input");
      jQuery(this).hide(); // Hide clear button after clearing field
  });
});

(function (Drupal, once) {
  Drupal.behaviors.autoSubmitPriceForm = {
    attach: function (context) {
      once('auto-submit-price', '#flatpickr_date_range', context).forEach(function (element) {

        element.addEventListener('change', function () {
          if (element.value.includes(' to ')) {
            var form = element.closest('form');
            // requestSubmit() (unlike submit()) fires the form's 'submit'
            // event, which sr_search_loader.js listens for to show the
            // loading overlay.
            if (form.requestSubmit) {
              form.requestSubmit();
            } else {
              form.submit();
            }
          }
        });

      });
    }
  };
})(Drupal, once);