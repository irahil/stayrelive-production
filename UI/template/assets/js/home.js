/* HOME PAGE */
// Home page List Underline animation
const nav = document.querySelector(".sr-type-list");
const navLinks = document.querySelectorAll(".sr-type-list .nav-item");

function createUnderline() {
    const underline = document.createElement("div");
    underline.classList.add("nav-underline");
    nav.appendChild(underline);
    underline.style.width = navLinks[0].offsetWidth + "px";
    navLinks[0].classList.add("active");
    return underline;
}

createUnderline();

navLinks.forEach((link) => {
    link.addEventListener("mouseenter", () => {
        navLinks.forEach((link) => {
            link.classList.remove("active");
        });
        link.classList.add("active");
        const underline = document.querySelector(".nav-underline");
        underline.style.width = link.offsetWidth + "px";
        underline.style.left = link.offsetLeft + "px";
    });
});

// Client Logo Slider
function _intialize_slider() {
    try {
      // Partner Logos
      var slider = tns({
        container: ".partners-logos",
        autoWidth: true,
        loop: true,
        items: 4,
        center: true,
        autoplay: true,
        autoplayHoverPause: false,
        autoplayButtonOutput: false,
        controls: false,
        nav: false,
        speed: 1000,
        autoplayTimeout: 0,
        preventActionWhenRunning: true,
        mouseDrag: true,
      });
    } catch (error) {
      // console.log("Error occured - ", error);
    }
  }


const className = "field--type-entity-reference-revisions";
const selectCityButtonId = "autocomplete_town_city";
const MD_SIZE_IN_PIXEL = 768;
const HOME_SLIDER_FADE_IN_AN_OUT_DELAY_IN_SECS = 2.85;


function showUpdatePriceButtonOnChangingDates() {
    try {
        const dateButton = document.getElementById("flatpickr_date_range");

        dateButton.addEventListener("click", () => {
            const updateButton = document.getElementById("edit-submit");
            updateButton.classList.add("d-block");
        });
    } catch (error) {
        // console.log("Error adding event listener for update price button - ", error)
    }
}


function getQueryParameters() {
    getQueryParameters;
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
            searchComponent.classList.add("sticky-search");
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
        // console.log("Error controling visibility of search navigation : ", error);
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

        // controlNavigationSearchComponentVisibility();
    } catch (error) {
        console.log("Error initializing search bar in navigation - ", error);
    }
}

function openSearchDiv() {
    try {
        const navSearchBar = document.getElementById("nav_search_bar");
        const originalSearchComponent = document.getElementById("original_search_component");

        const cityBtn = document.getElementById('cityBtn');
        const dateBtn = document.getElementById('dateBtn');
        const adultsBtn = document.getElementById('adultsBtn');
        const kidsBtn = document.getElementById('kidsBtn');

        document.addEventListener("click", function (event) {
            try {
                const clickedOnSuggestions = ((event?.target?.tagName === 'DIV') && ((JSON.stringify(event?.target?.classList)) === '{}')) ? true : false;

                // console.log(`Clicked on id - ${event?.target?.id}:${event?.target?.tagName}:${JSON.stringify(event?.target?.classList)}:${clickedOnSuggestions}:${(event?.target?.tagName === 'DIV')}:${(!JSON.stringify(event?.target?.classList))}:${typeof(JSON.stringify(event.target.classList))}`);

                const isNavComponentClicked = (cityBtn.contains(event.target)) || (dateBtn.contains(event.target)) || (adultsBtn.contains(event.target)) || (kidsBtn.contains(event.target));

                if (isNavComponentClicked) {
                    if (navSearchBar) {
                        navSearchBar.classList.add("d-none");
                    }
                    if (originalSearchComponent) {
                        originalSearchComponent.classList.remove("d-none");
                    }
                } else if (originalSearchComponent && originalSearchComponent.contains(event.target)) {
                    // do nothing if component is being used
                }
                else if (clickedOnSuggestions) {
                    // do nothing if component is being used
                }
                else {
                    if (originalSearchComponent) {
                        originalSearchComponent.classList.add("d-none");
                    }
                    if (navSearchBar) {
                        navSearchBar.classList.remove("d-none");
                    }
                }
            } catch (error) {
                console.error("Error in document click event:", error);
            }
        });

        cityBtn.addEventListener('click', () => {
            try {
                setTimeout(() => {
                    console.log('clicked ', cityBtn);
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
                setTimeout(() => {
                    const flatpickr_date_range = document.getElementById('flatpickr_date_range');
                    flatpickr_date_range._flatpickr.open();
                }, 100)
            } catch (error) {
                console.log('dateBtn Error = ', error);
            }
        });

        adultsBtn.addEventListener('click', () => {
            try {
                setTimeout(() => {
                    console.log('clicked ', adultsBtn);
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
                setTimeout(() => {
                    console.log('clicked ', kidsBtn);
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

                        // console.log(`Value changed to: ${changedValue}`);
                    });
                } catch (error) {
                    console.log(`error in element - ${error}`);
                }
            });
        } catch (error) {
           // console.log("error creating city input - ", error);
        }

    } catch (error) {
        // console.log('Error = ', error);
    }

}

//preload images after page load
function preloadImages() {
    try {
        const imageUrls = [
            "/new/web/themes/srdesign/assets/images/home/hotels/01.webp",
            "/new/web/themes/srdesign/assets/images/home/hotels/02.webp",
            "/new/web/themes/srdesign/assets/images/home/hotels/03.webp",
            "/new/web/themes/srdesign/assets/images/home/hotels/04.webp",
        ];

        setup();

    _intialize_slider();

        openSearchDiv();
    } catch (error) {
        // console.log("error - ", error)
    }
}

function resetFocusClass(carouselLinks) {
    carouselLinks.forEach((item) => {
        item.querySelector("a").classList.remove("focus");
    });
}

window.addEventListener("load", preloadImages);

function controlPriceComponentStickyBehhavoir(){
  try {
    const price = document.getElementById('block-srdesign-srpropertypriceblockbasic');
    //console.log("Price sticky behavior - ", price);
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


function controlNavigationSearchComponentStickyBehaviour() {
    try {
        const searchComponent = document.getElementById("searchComponent");
        const searchComponent2 = document.getElementById("searchComponent2");

        if (searchComponent && searchComponent2) {

            const isMobile = window.innerWidth <= MD_SIZE_IN_PIXEL;

            if (isMobile) {
                searchComponent2.style.zIndex = "99";
            }
            else {
                searchComponent2.style.zIndex = "999";

                if (window.scrollY > window.innerHeight - 175 && window.innerWidth >= 750) {
                    searchComponent.style.position = "fixed";
                    searchComponent.style.top = "11px";
                    searchComponent.classList.add("sticky-search");
                } else {
                    searchComponent.style.position = "relative";
                }
            }
        }
    } catch (error) {
        console.log("Error sticking searchcomponent - ", error);
    }
}

controlNavigationSearchComponentStickyBehaviour();
window.addEventListener("scroll", controlNavigationSearchComponentStickyBehaviour);


// Hide Status Messages
// Show and Destroy Toast  
function showToast(toastElement, timeoutInMs = 10 * 1000) {
    setTimeout(function () { 
        toastElement.style.transition = "all 0.3s linear"
        toastElement.style.display = "none"
    }, timeoutInMs);
}

window.addEventListener("load", () => {
    const elements = document.getElementsByClassName('messages-list');
    for (const element of elements) { 
        showToast(element);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    // Check if the specific class is present on the body element
    var bodyElement = document.body;
    var targetClass = 'results-region'; // Replace 'yourTargetClassName' with the desired class name

    if (bodyElement.classList.contains(targetClass)) {
        // Add a class to the body if the target class is present
        bodyElement.classList.add('yourNewClassName'); // Replace 'yourNewClassName' with the class you want to add
    }
});

// Replace text in the Apartment Details Page
const config = {
    "&lt;b&gt;": "",
    "&lt;/b&gt;": "",
    Homelike: "StayRelive",
    HomeLike: "StayRelive",
    homelike: "StayRelive",
};

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

function setup() {
    try {
      replaceWords(config);
      showUpdatePriceButtonOnChangingDates();
    } catch (error) {
      // console.log(`Error setting up - ${error}`);
    }
  }