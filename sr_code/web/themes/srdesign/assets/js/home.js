/* Replace words on Product Detailed Page */
const config = {
  "&lt;b&gt;": "",
  "&lt;/b&gt;": "",
  Homelike: "StayRelive",
  HomeLike: "StayRelive",
  homelike: "StayRelive",
};
const className = "field--type-entity-reference-revisions";
const selectCityButtonId = "autocomplete_town_city";
const MD_SIZE_IN_PIXEL = 768;
const HOME_SLIDER_FADE_IN_AN_OUT_DELAY_IN_SECS = 2.85;


function replaceWords(config) {
  try {
    let elements = document.getElementsByClassName(className);
    for (let i = 0; i < elements.length; i++) {
      const element = elements[i];
      let content = element.innerHTML;
      for (const word in config) {
        const regex = new RegExp(word, "gi");
        content = content.replace(regex, config[word]);
      }
      document.getElementsByClassName(className)[i].innerHTML = content;
    }
  } catch (error) {
    // console.log(`[replaceWords] Exception occured - ${error}`);
  }
}

function showUpdatePriceButtonOnChangingDates() {
  try {
    const dateButton = document.getElementById("flatpickr_date_range");

    // console.log("DateButton - ", dateButton);

    dateButton.addEventListener("click", () => {
      const updateButton = document.getElementById("edit-submit");

      // console.log("updateButton - ", updateButton);

      updateButton.classList.add("d-block");
    });
  } catch (error) {
    // console.log("Error adding event listener for update price button - ", error)
  }
}

function createCitiesSetup() {
  try {
    let selectCityButton = document.getElementById(selectCityButtonId);

    selectCityButton.addEventListener("click", () => {
      try {
const searchComponent = document.getElementById('searchComponent');

        if(searchComponent){
          if(searchComponent.style.position === 'fixed'){
            // This means city is clicked within navigation
            // do nothing 
            return;
          }
        }
        const isMobile = window.innerWidth <= MD_SIZE_IN_PIXEL;
        if (isMobile) {
          const firstInput = document.querySelector("input");
          if(firstInput){
            firstInput.scrollIntoView({ behavior: "smooth" });
          }
        } else {
          const firstH1 = document.querySelector("h1");
          if(firstH1){
            firstH1.scrollIntoView({ behavior: "smooth" });
          }
        }
      } catch (error) {
        // console.log(error);
      }
    });
  } catch (error) {
    // console.log("Error creating cities setup - ", error)
  }
}

function setup() {
  try {
    replaceWords(config);
    createCitiesSetup();
    showUpdatePriceButtonOnChangingDates();
  } catch (error) {
    // console.log(`Error setting up - ${error}`);
  }
}

var accordionHeaders = document.querySelectorAll(".accordion-header");
var image = document.querySelector(".accordionImg-1");
function getQueryParameters() {
  try {
    const queryParams = new URLSearchParams(window.location.search);
    const params = {};
    queryParams.forEach((value, key) => {
      params[key] = value;
    });
    console.log("params - ", params);

    return params;
  } catch (error) {
    console.log("Error getting uerry params - ", error);
    return {};
  }
}

function toggleVisibility(testElement) {
  try {
    if (window.scrollY > window.innerHeight && window.innerWidth >= 750) {
      testElement.classList.remove("d-none");
    } else {
      testElement.classList.add("d-none");
    }
  } catch (error) {
    console.log(error);
  }

  try {
    const searchComponent = document.getElementById("searchComponent");
    if (window.scrollY > window.innerHeight - 100 && window.innerWidth >= 750) {
      searchComponent.style.position = "fixed";
      searchComponent.style.top = "11px";
    } else {
      searchComponent.style.position = "relative";
    }
  } catch (error) {
    console.log("Error sticking searchcomponent - ", error);
  }
}

function controlNavigationSearchComponentVisibility() {
  try {
    const searchComponentId = "nav_search";
    const searchComponent = document.getElementById(searchComponentId);
    const currentPath = window.location.pathname;

    console.log("current path - ", currentPath);

    if (
      currentPath === "/" ||
      currentPath === "/new/web/" ||
      currentPath === "" ||
      currentPath === "/new/web"
    ) {
      window.addEventListener("scroll", () => {
        toggleVisibility(searchComponent);
      });
    } else if (
      currentPath === "/new/web/property/search" ||
      currentPath === "/property/search"
    ) {
      // display mobile component in mobile only
      const nav_search_readonly_strip = document.getElementById(
        "nav_search_readonly_strip"
      );
      const nav_search_mobile_view = document.getElementById(
        "nav_search_mobile_view"
      );

      nav_search_readonly_strip.addEventListener("click", () => {
        nav_search_mobile_view.classList.remove("d-none");
        nav_search_readonly_strip.classList.add("d-none");
      });

      if (window.innerWidth >= 750) {
        // desktop view
        nav_search_mobile_view.classList.add("d-none");
        searchComponent.classList.remove("d-none");
        nav_search_readonly_strip.classList.add("d-none");
      } else {
        // mobile view
        nav_search_mobile_view.classList.add("d-none");
        searchComponent.classList.add("d-none");
        nav_search_readonly_strip.classList.remove("d-none");
      }
    }
  } catch (error) {
    console.log("Error controling visibility of search navigation : ", error);
  }
}

function _intialize_search_component_in_navigation() {
  try {
    console.log("Initializing search component in navigation");

    const form = document.getElementsByTagName("form")[0];
    const submitBtns = document.getElementsByClassName("submit-label");
    const params = getQueryParameters();

    const componentsArray = [
      {
        name: "town_city",
        originalComponent: document.getElementById("autocomplete_town_city"),
        newComponentClassName: "city",
      },
      {
        name: "adult",
        originalComponent: document.getElementById("edit-adult"),
        newComponentClassName: "adults",
      },
      {
        name: "kid",
        originalComponent: document.getElementById("edit-kid"),
        newComponentClassName: "kids",
      },
      {
        name: "date_range",
        originalComponent: document.getElementById("flatpickr_date_range"),
        newComponentClassName: "flatpickr_date_range_nav",
      },
    ];

    componentsArray.forEach((component) => {
      const { name, originalComponent, newComponentClassName } = component;
      try {
        let val = params[`${[name]}`] ? params[`${[name]}`] : "";
        val = val.replace("+", " ");
        val = val.replace("+", " ");

        // Get an array of elements with the specified class name
        const newComponents = document.getElementsByClassName(
          newComponentClassName
        );

        // Set the initial value for each new component
        for (let i = 0; i < newComponents.length; i++) {
          if (val !== "") {
            newComponents[i].value = val;
          }
        }

        if (name === "date_range") {
          const [startDateString, endDateString] = val.split(" to ");
          const startDate = new Date(startDateString);
          const endDate = new Date(endDateString);

          console.log(
            "Intializing date time - ",
            startDate,
            endDate,
            isNaN(startDate),
            isNaN(endDate)
          );

          if (startDate === "Invalid Date" || endDate === "Invalid Date") {
            jQuery(document).ready(function ($) {
              flatpickr(`.${newComponentClassName}`, {
                mode: "range",
                minDate: "today",
              });
            });
          } else {
            jQuery(document).ready(function ($) {
              flatpickr(`.${newComponentClassName}`, {
                mode: "range",
                defaultDate: [startDate, endDate],
                minDate: "today",
              });
            });
          }

          const element = document.getElementById("flatpickr_date_range_nav1");
          if (element) {
            element.value = val;
          }
        }

        console.log("Setting default value of -", val, "- for ", name);

        // Add event listeners for each new component
        for (let i = 0; i < newComponents.length; i++) {
          newComponents[i].addEventListener("change", (event) => {
            console.log(`${name} changed - `, event.target.value);

            // Set the value for all new components within the same category
            for (let j = 0; j < newComponents.length; j++) {
              newComponents[j].value = event.target.value;
            }

            originalComponent.value = event.target.value;

            if (name === "date_range") {
              const element = document.getElementById(
                "flatpickr_date_range_nav1"
              );
              if (element) {
                element.value = event.target.value;
              }
            }
          });
        }

        // Add event listener for the original component (assuming it's still a single element)
        originalComponent.addEventListener("change", (event) => {
          console.log(`Original ${name} changed - `, event.target.value);

          // Set the value for all new components within the same category
          for (let i = 0; i < newComponents.length; i++) {
            newComponents[i].value = event.target.value;
          }
        });
      } catch (error) {
        console.log(
          `Error setting up the - ${name} component for search - ${error}`
        );
      }
    });

    for (const submit of submitBtns) {
      submit.addEventListener("click", (event) => {
        console.log("submitting form - ", event.target.value);

        try {
          const nav_search_readonly_strip = document.getElementById(
            "nav_search_readonly_strip"
          );
          const nav_search_mobile_view = document.getElementById(
            "nav_search_mobile_view"
          );

          nav_search_readonly_strip.classList.remove("d-none");
          nav_search_mobile_view.classList.add("d-none");

          const element = document.getElementById("flatpickr_date_range_nav1");
          if (element) {
            element.value = document.querySelector(
              ".flatpickr_date_range_nav"
            ).value;
          }
        } catch (error) {
          console.log("errror submitting details - ", error);
        } finally {
          form.submit();
        }
      });
    }

  } catch (error) {
    console.log("Error initializing search bar in navigation - ", error);
  }
}

function openSearchDiv() {
  try {
    const navSearchBar = document.getElementById("nav_search_bar");
    const originalSearchComponent = document.getElementById("original_search_component");
    const findApartment = document.getElementsByClassName("find-apartment");
  
    const cityBtn = document.getElementById('cityBtn');
    const dateBtn = document.getElementById('dateBtn');
    const adultsBtn = document.getElementById('adultsBtn');
    const kidsBtn = document.getElementById('kidsBtn');

    Array.from(findApartment).forEach((apartmentButton) => {
      apartmentButton.addEventListener("click", function () {
        cityBtn.click();
      });
    });

    document.addEventListener("click", function (event) {
      try {
        const clickedOnSuggestions = ((event?.target?.tagName === 'DIV') && ((JSON.stringify(event?.target?.classList)) === '{}')) ? true : false;
         
        const isNavComponentClicked = 
        (cityBtn && cityBtn.contains(event.target)) || 
        (dateBtn && dateBtn.contains(event.target)) || 
        (adultsBtn && adultsBtn.contains(event.target)) || 
        (kidsBtn && kidsBtn.contains(event.target));
  
        if(navSearchBar){
          navSearchBar.classList.add("d-none");
        }
        if(originalSearchComponent){
          originalSearchComponent.classList.remove("d-none");
        }
        else {
          if(originalSearchComponent){
            originalSearchComponent.classList.add("d-none");
          }
          if(navSearchBar){
            navSearchBar.classList.remove("d-none");
          }
        }
      } catch (error) {
        console.error("Error in document click event:", error);
      }
    });
  
    cityBtn.addEventListener('click', () => {
      try {
        setTimeout(()=>{
          const autocomplete_town_city = document.getElementById('autocomplete_town_city');
          autocomplete_town_city.focus();
          autocomplete_town_city.setSelectionRange(autocomplete_town_city.value.length, autocomplete_town_city.value.length);
        }, 100)
      } catch (error) {
        console.log('cityBtn Error = ', error);
      }
    });
  
    dateBtn.addEventListener('click', () => {
      try {
        setTimeout(()=>{
          const flatpickr_date_range = document.getElementById('flatpickr_date_range');
          flatpickr_date_range._flatpickr.open();
        }, 100)
      } catch (error) {
        console.log('dateBtn Error = ', error);
      }
    });
  
    adultsBtn.addEventListener('click', () => {
      try {
        setTimeout(()=>{
          const editAdults = document.getElementById('edit-adult');
          adultsBtn.selectionStart = kidsBtn.value.length;
          adultsBtn.selectionEnd = kidsBtn.value.length;
          editAdults.focus();
        }, 100)
      } catch (error) {
        console.log('adultsBtn Error = ', error);
      }
    });
  
    kidsBtn.addEventListener('click', () => {
      try {
        setTimeout(()=>{
          const editKid = document.getElementById('edit-kid');
          kidsBtn.selectionStart = kidsBtn.value.length;
          kidsBtn.selectionEnd = kidsBtn.value.length;
          editKid.focus();
        }, 100)
      } catch (error) {
        console.log('kidsBtn Error = ', error);
      }
    });
    
    try {
      const duplicateCityInputTags = document.querySelectorAll(
        '[id="autocomplete_town_city"]'
      );
      duplicateCityInputTags.forEach((element) => {
        try {
          element.addEventListener("change", function (event) {
            const changedValue = event.target.value;
    
            duplicateCityInputTags.forEach((cityInput) => {
              cityInput.value = changedValue;
            });
          });
        } catch (error) {
          console.log(`error in element - ${error}`);
        }
      });
    } catch (error) {
      console.log("error creating city input - ", error);
    }
    
  } catch (error) {
    console.log('Error = ', error);
  }
  
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', openSearchDiv);
} else {
  openSearchDiv();
}

// Attach click event listener to each header
accordionHeaders.forEach(function (header, index) {
  header.addEventListener("click", function () {
    var imgSrc = image.getAttribute("src");
    if (imgSrc != imagePath[index]) {
      slideImage(imagePath[index]);
    }
  });
});

function slideImage(imageUrl) {
  image.style.opacity = "0";
  image.style.transition = "opacity 0.5s";

  setTimeout(function () {
    image.src = imageUrl;
    image.style.opacity = "1";
    image.style.transition = "opacity 0.5s";
  }, 100); // Adjust the delay (in milliseconds) as needed
}

//create slider for cities
var siderContents = [];

function citySilderInit() {
  var slides = document.querySelectorAll(
    ".city-carousel .slideContent .sildeItem"
  );
  slides.forEach(function (slide) {
    var sItem = [];
    var sideItems = slide.querySelectorAll("a");
    sideItems.forEach(function (sideItem) {
      sItem.push({
        href: sideItem.href,
        imgSrc: sideItem.querySelector("img").getAttribute("src"),
        title: sideItem.querySelector("h6").innerHTML,
      });
    });
    if (sItem.length) {
      siderContents.push(sItem);
    }
  });

  // console.log("siderContents : \n", JSON.stringify(siderContents));

  if (document.querySelector(".continents ul") != null) {
    var carouselLinks = document
      .querySelector(".continents ul")
      .querySelectorAll("li");
    carouselLinks.forEach(function (link, index) {
      link.querySelector("a").addEventListener("click", function () {
        resetFocusClass(carouselLinks);
        this.classList.add("focus");
        ChangeSliderContent(index);
      });
    });
    document.getElementById("city-focus").click();
  }
}

function resetFocusClass(carouselLinks) {
  carouselLinks.forEach((item) => {
    item.querySelector("a").classList.remove("focus");
  });
}

function ChangeSliderContent(index) {
  // console.log("index " + siderContents[index]);
  var slider = document.querySelector(".tiny-slider");
  var htmlContent =
    '<div class="tiny-slider-inner" data-aos-once="true" data-aos="fade-up" data-autoplay="true" data-arrow="true" data-edge="2" data-dots="false" data-items-xl="3" data-items-lg="2" data-items-md="1">';
  siderContents[index].forEach((element) => {
    htmlContent =
      htmlContent +
      `
        <!-- Slider item -->
    <div>
        <div class="card  rounded-0 overflow-hidden">
            <div class="row g-0 align-items-center">
                <!-- Image -->
                <div class="col-sm-12">
                    <a href="#!" class="abc">
                        <img src="` +
      element.imgSrc +
      `" class="card-img rounded-3" alt="">
                        <h6 class="card-title px-3">` +
      element.title +
      `</h6>
                    </a>
                </div>
            </div>
        </div>
    </div>`;
  });
  htmlContent = htmlContent + "</div>";
  slider.innerHTML = htmlContent;
  e.tinySlider();
}

  // Select all anchor elements within the '.tbl-booking' table
  const bookingLinks = document.querySelectorAll('.tbl-booking a');

  bookingLinks.forEach(function(link) {
    const statusText = link.textContent.trim();

    if (statusText === 'Pending Booking') {
      link.classList.add('pending');
    } 
    else if (statusText === 'Confirmed Booking') {
      link.classList.add('confirmed');
    } 
    else if (statusText === 'Canceled Booking') {
      link.classList.add('cancelled');
    }
  });

  // Modify form structure
  jQuery('#contact-message-contact-us-form > div:nth-child(3)').removeClass('row').addClass('container');

  // Reload page after unflag action
  jQuery('.path-viewfavouritepage-1 .action-unflag a').on('click', function() {
    window.location.reload();
  });

  // Home page partner slider
  jQuery(document).ready(function($) {
    $('.partners-logos').slick({
      slidesToShow: 4,
      slidesToScroll: 1,
      infinite: true,
      centerMode: true,
      autoplay: true,
      autoplaySpeed: 300,
      speed: 800,
      arrows: false,
      dots: false,
      pauseOnHover: false,
      pauseOnFocus: false,
      pauseOnClick: false,
      draggable: false,
      variableWidth: true,
      cssEase: 'linear',
      responsive: [
        {
          breakpoint: 1024,
          settings: {
            slidesToShow: 3
          }
        },
        {
          breakpoint: 768,
          settings: {
            slidesToShow: 2
          }
        },
        {
          breakpoint: 480,
          settings: {
            slidesToShow: 1
          }
        }
      ]
    });
  });

function controlPriceComponentStickyBehhavoir(){
  try {
    const price = document.getElementById('block-srdesign-srpropertypriceblockbasic');
    // console.log("Price sticky behavior - ", price);
    if(price){
      const isDesktop = window.innerWidth > MD_SIZE_IN_PIXEL;
        if (isDesktop) {
          price.classList.add('sticky-top');
          price.style.top = '100px';
          price.style.zIndex = 99;
          price.style.transition = 'all linear 0.2s';
        }
        else{
          price.classList.remove('sticky-top');
        }
    }
  } catch (error) {
    console.log('error while controlPriceComponentStickyBehhavoir - ', error)
  }
}


controlPriceComponentStickyBehhavoir();
window.addEventListener("load", controlPriceComponentStickyBehhavoir);
window.addEventListener("resize", controlPriceComponentStickyBehhavoir);


function controlNavigationSearchComponentStickyBehaviour(){
  try {
    const searchComponent = document.getElementById("searchComponent");
    const searchComponent2 = document.getElementById("searchComponent2");

    if(searchComponent && searchComponent2){

      const isMobile = window.innerWidth <= MD_SIZE_IN_PIXEL;

      if(isMobile){
        searchComponent2.style.zIndex = "99";
      }
      else{
        searchComponent2.style.zIndex = "999";
        
        const mediaQuery = window.matchMedia('(max-width: 1460px) and (min-width: 768px)');

        if (window.scrollY > window.innerHeight - 175 && window.innerWidth >= 750) {
          searchComponent.style.position = "fixed";
          searchComponent.style.top = "11px";
          
          if(mediaQuery.matches){
            searchComponent.style.transform = "scale(0.9)";
            searchComponent.style.transition = "all linear 0.2s";
            searchComponent.style.top = "20px"
          } else {
            searchComponent.style.transform = "none";
          }
        } else {
          searchComponent.style.position = "relative";
          searchComponent.style.transform = "none";
        }
      }
    }
  } catch (error) {
    console.log("Error sticking searchcomponent - ", error);
  }
}

controlNavigationSearchComponentStickyBehaviour();
window.addEventListener("scroll", controlNavigationSearchComponentStickyBehaviour);

/* Amenity icons */
document.addEventListener('DOMContentLoaded', function () {
  const amenityIcons = [
    { terms: ['_4-hour-reception','self-check-in---check-out', '24-hour-reception', 'reception', 'alarm-clock', '4-hour-security', '_4-hour-front-desk', 'reception-desk'], icon: 'fa-clock' },
    { terms: ['access-to-beach', 'beach-access', 'beach-facilities', 'beach-essentials', 'next-to-the-beach', 'private-beach', 'beach-view', 'beachfront','umbrellas'], icon: 'fa-umbrella-beach' },
    { terms: ['bathing-at-the-sea', 'bathrobe', 'swimming-pool', 'bathrobes', 'heated-swimming-pool', 'indoor-pool', 'outdoor-pool', 'pool-facilities', 'pool-house', 'poolside-bar', 'rooftop-pool', 'seasonal-indoor-swimming-pool', 'seasonal-outdoor-swimming-pool', 'shared-pool', 'fenced-pool', 'heated-pool','private-pool'], icon: 'fa-swimming-pool' },
    { terms: ['access-to-business-centre', 'business-centre', 'meeting---conference-rooms', 'concierge-services'], icon: 'fa-building' },
    { terms: ['accessibility', 'wheelchair-access', 'step-free-access', 'upper-floors-accessible-by-elevator'], icon: 'fa-wheelchair' },
    { terms: ['additional-bed', 'extra-bed', 'total-bedrooms', 'cot---crib', 'cots', 'extra-pillows-and-blankets', 'family-room', 'interconnecting-rooms-available', 'luxury-bedding','towels-and-bed-linens'], icon: 'fa-bed' },
    { terms: ['bathing-at-the-lake', 'lake', 'mountains-lakes'], icon: 'fa-water' },
    { terms: ['cable-tv', 'amazon-prime', 'blu-ray-player', 'crt-tv', 'dvd-library', 'dvd-player', 'dvdplayer', 'flat-screen-tv', 'flatscreen-tv', 'home-cinema-setup', 'netflix', 'projector', 'satellite---cable-tv', 'smart-tv', 'streaming-services', 'television-in-lobby', 'tv'], icon: 'fa-tv' },
    { terms: ['bathtub', 'baby-bath', 'outdoor-bathtub', 'shower-bathtub'], icon: 'fa-bath' },
    { terms: ['wifi', 'wi-fi', 'free-wi-fi', 'free-internet', 'in-room-internet', 'internet', 'internet-access', 'wlan', 'wi-fi-in-the-hotel'], icon: 'fa-wifi' },
    { terms: ['cafe', 'cafetiere', 'capsule-coffee-machine', 'coffee-maker', 'coffee-tea-for-guests', 'coffeemaker', 'espresso-machine', 'filter-coffee-machine', 'pod-coffee-machine'], icon: 'fa-coffee' },
    { terms: ['carbon-monoxide-detector', 'smoke-alarm', 'smoke-detector'], icon: 'fa-smog' },
    { terms: ['deposit-boxes', 'safe-deposit-box', 'locker', 'laptop-safe', 'safe', 'safe-in-room'], icon: 'fa-box' },
    { terms: ['elevator', 'elevator-lift', 'lift-access', 'no-elevators'], icon: 'fa-elevator' },
    { terms: ['design-hotel', 'resort-access'], icon: 'fa-paint-brush' },
    { terms: ['desk', 'workplace', '4-hour-front-desk', 'desk---workplace', 'laptop-friendly-workspace', 'working-desk'], icon: 'fa-table' },
    { terms: ['diet-menu-on-request', 'breakfast-lunch-to-go', 'packed-lunches', 'restaurant', 'restaurant-buffet-style', 'snack-bar','kettle'], icon: 'fa-utensils' },
    { terms: ['early-late-check-out', 'early-check-in', 'available-from', 'min-stay', 'early---late-check-out-available', 'early-check-in', 'late-check-out', 'express-check-in', 'express-check-out', 'express-check-in-check-out', 'private-check-in-check-out'], icon: 'fa-calendar' },
    { terms: ['electric-car-charging', 'electricity-adaptors', 'ev-charger'], icon: 'fa-bolt' },
    { terms: ['total-bathrooms', 'shower', 'rain-shower', 'outdoor-shower'], icon: 'fa-shower' },
    { terms: ['guest'], icon: 'fa-users' },
    { terms: ['area'], icon: 'fa-house' },
    { terms: ['car-parking', 'accessible-parking', 'free-parking', 'free-parking-on-premises', 'free-parking-on-street', 'guaranteed-parking', 'paid-parking', 'paid-parking-on-premises', 'paid-parking-on-street', 'parking', 'parking-available', 'parking-on-premises', 'parking-on-street', 'parking-private-free', 'private-parking', 'secured-parking', 'valet-parking'], icon: 'fa-car' },
    { terms: ['gym', 'access-to-gym---spa', 'fitness', 'exercise-equipment', 'gym-access'], icon: 'fa-dumbbell' },
    { terms: ['_4-hour-security', 'security-guard', 'security-system', 'security-cameras', 'alarm-system'], icon: 'fa-shield-alt' },
    { terms: ['adults-only', 'concierge'], icon: 'fa-user-secret' },
    { terms: ['air-conditioning', 'temperature-control', 'ceiling-fans', 'fan', 'heating', 'underfloor-heating'], icon: 'fa-snowflake' },
    { terms: ['all-spaces-non-smoking-public-and-private', 'non-smoking-rooms', 'nonsmoking', 'smoke-free-property', 'smoking-allowed', 'smoking-allowed-in-bedrooms', 'smoking-areas'], icon: 'fa-smoking-ban' },
    { terms: ['allergy-free-rooms'], icon: 'fa-allergies' },
    { terms: ['atm', 'bank', 'currency-exchange'], icon: 'fa-money-bill-wave' },
    { terms: ['baby-changing-mat', 'baby-monitor', 'high-chair', 'safety-gates', 'stair-gates', 'infant-friendly'], icon: 'fa-baby' },
    { terms: ['balcony', 'balcony---patio', 'balcony---rooftop-terrace', 'patio', 'patio---terrace', 'terrace', 'rooftop-terrace'], icon: 'fa-window-maximize' },
    { terms: ['bar', 'poolside-bar'], icon: 'fa-glass-martini-alt' },
    { terms: ['barbeque', 'outdoor-kitchen'], icon: 'fa-utensils' },
    { terms: ['bicycles', 'bike-shed'], icon: 'fa-bicycle' },
    { terms: ['black-out-blinds'], icon: 'fa-window-close' },
    { terms: ['blender', 'food-steamer', 'rice-maker', 'toaster', 'juicer'], icon: 'fa-blender' },
    { terms: ['board-games', 'games-console', 'games-room', 'nintendo-wii', 'playstation', 'ps3', 'ps4', 'xbox', 'foosball---table-football', 'ping-pong-table', 'pool---snooker-table'], icon: 'fa-gamepad' },
    { terms: ['bottled-water', 'bottled-water-at-extra-charge', 'ice-machine'], icon: 'fa-tint' },
    { terms: ['bread-maker'], icon: 'fa-bread-slice' },
    { terms: ['cooking-essentials','cooking-utensils','breakfast', 'buffet-breakfast', 'breakfast-in-the-room', 'breakfast-included', 'breakfast-lunch-to-go', 'room-service'], icon: 'fa-utensils' },
    { terms: ['bridal-suite', 'vip-room-amenities'], icon: 'fa-heart' },
    { terms: ['buzzer---wireless-intercom', 'video-intercom'], icon: 'fa-phone' },
    { terms: ['cat-of-residence', 'pet-basket', 'pet-bowls', 'pets-allowed', 'no-pets-allowed'], icon: 'fa-paw' },
    { terms: ['central', 'central-heating'], icon: 'fa-location-arrow' },
    { terms: ['ceran-cooker', 'classic-cooker', 'common-kitchen', 'dishwasher', 'gas-cooker', 'induction-cooker', 'kitchen', 'kitchen-utensils', 'kitchenette', 'microwave', 'microwave-oven', 'oven', 'stove'], icon: 'fa-utensils' },
    { terms: ['changing-table'], icon: 'fa-baby' },
    { terms: ['chapel'], icon: 'fa-church' },
    { terms: ['child-friendly', 'childrens-dinnerware', 'playground'], icon: 'fa-child' },
    { terms: ['cleaning', 'cleaning-products', 'laundry', 'laundry-supplies', 'dry-cleaning', 'ironing', 'ironing-accessories-by-request', 'ironing-board', 'iron-and-board', 'iron'], icon: 'fa-shirt' },
    { terms: ['clothes-rack', 'drying-rack', 'wardrobe-closet', 'hangers'], icon: 'fa-tshirt' },
    { terms: ['computer', 'printer'], icon: 'fa-laptop' },
    { terms: ['concierge-services', 'ticket-assistance', 'wake-up-service', 'shoe-shine'], icon: 'fa-concierge-bell' },
    { terms: ['courtyard', 'garden', 'garden---courtyard', 'herb-garden', 'outdoor-seating', 'outdoor-furniture'], icon: 'fa-tree' },
    { terms: ['doorman', 'private-entrance'], icon: 'fa-door-open' },
    { terms: ['dryer', 'private-dryer', 'shared-dryer', 'washing-machine', 'private-washing-machine', 'shared-washing-machine', 'washingmachine'], icon: 'fa-tshirt' },
    { terms: ['fire-extinguisher', 'first-aid-kit'], icon: 'fa-first-aid' },
    { terms: ['fireplace', 'indoor-fireplace', 'outdoor-fireplace---firepit'], icon: 'fa-fire' },
    { terms: ['garden-games', 'water-fun', 'water-sport-facilities', 'water-park', 'golfing', 'hiking', 'hiking-mountains', 'ski-in-and-ski-out', 'ski-storage---boot-room', 'tennis-court'], icon: 'fa-running' },
    { terms: ['gated-community', 'fenced-garden', 'fenced-property'], icon: 'fa-shield-alt' },
    { terms: ['gift-shop', 'shopping-on-site', 'pharmacy', 'vending-machine'], icon: 'fa-shopping-bag' },
    { terms: ['hair-dryer', 'hairdryer', 'hairdryer-on-request'], icon: 'fa-wind' },
    { terms: ['hammock', 'sun-loungers---beach-chairs', 'sun-umbrellas'], icon: 'fa-umbrella-beach' },
    { terms: ['helipad'], icon: 'fa-helicopter' },
    { terms: ['hot-tub', 'jacuzzi', 'spa', 'spa-tub', 'sauna', 'sauna---steam-room', 'steam-room'], icon: 'fa-hot-tub' },
    { terms: ['linens', 'throws'], icon: 'fa-bed' },
    { terms: ['luggage-storage'], icon: 'fa-suitcase' },
    { terms: ['mini-bar', 'minibar', 'mini-fridge', 'wine-cellar', 'wine-cooler', 'wine-cooler---wine-cellar'], icon: 'fa-wine-bottle' },
    { terms: ['mosquito-net'], icon: 'fa-bug' },
    { terms: ['music-system', 'record-player', 'sonos', 'sound-system', 'outdoor-audio'], icon: 'fa-music' },
    { terms: ['newspapers', 'library-of-books'], icon: 'fa-newspaper' },
    { terms: ['residence'], icon: 'fa-home' },
    { terms: ['rest-and-relax-summer'], icon: 'fa-sun' },
    { terms: ['slippers', 'luxury-toiletries', 'toiletries'], icon: 'fa-socks' },
    { terms: ['smart-home-system'], icon: 'fa-home' },
    { terms: ['telefone', 'telephone'], icon: 'fa-phone' },
    { terms: ['vacuum-cleaner'], icon: 'fa-broom' },
    { terms: ['welcome-hamper'], icon: 'fa-gift' },
    { terms: ['freezer','fridge'], icon: 'fa-ice-cream' },
    { terms: ['heated-towel-rail'], icon: 'fa-snowflake' },
    { terms: ['multiple-sets-of-keys'], icon: 'fa-key' },
    { terms: ['piano'], icon: 'fa-keyboard' },
    { terms: ['radio'], icon: 'fa-radio' },
    { terms: ['single-level-home'], icon: 'fa-home' },
    { terms: ['soundproof-rooms'], icon: 'fa-volume-mute' },
    { terms: ['beach---pool-towels', 'beach-pool-towels', 'towel--linen-service'], icon: 'fa-vest' },
    { terms: ['trash-compactor'], icon: 'fa-trash' },
    { terms: ['trouser-press'], icon: 'fa-tshirt' },
  ];

  const amenityItems = document.querySelectorAll('.field--name-field-amenities .field__item', '.field--name-field-amenities-home .field__item');

  amenityItems.forEach(item => {
    const classList = Array.from(item.classList).map(cls => cls.toLowerCase());
    let iconClass = 'fa-check';

    for (const group of amenityIcons) {
      if (group.terms.some(term => classList.includes(term.toLowerCase()))) {
        iconClass = group.icon;
        break;
      }
    }

    const icon = item.querySelector('i') || document.createElement('i');
    icon.className = `fas ${iconClass}`;
    if (!item.querySelector('i')) {
      item.insertBefore(icon, item.firstChild);
    }
  });
});

var viewAllBtn = document.querySelector('.apartment-gallery .icon-gallery');
var firstGallery = document.querySelector('.apartment-gallery .card-overlay-hover');

if(viewAllBtn && firstGallery) {
  
  let spanTag = document.createElement('span');
  spanTag.innerHTML = viewAllBtn.innerHTML;
  spanTag.className = viewAllBtn.className;
  
  viewAllBtn.replaceWith(spanTag);
  
  spanTag.addEventListener('click', () => {
    firstGallery.click();
  });
}