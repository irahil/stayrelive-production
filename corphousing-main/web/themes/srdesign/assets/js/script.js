document.addEventListener('DOMContentLoaded', function() {
  AOS.init({
      once: false,
      duration: 1000,
      easing: 'ease-out-cubic',
    });
  });


(function ($, Drupal, once) {
  'use strict';

  // ---------------------------
  // 2. Loader on Front Page
  // ---------------------------
  Drupal.behaviors.frontpageLoader = {
    attach: function (context) {
      once('frontpageLoader', 'body.path-frontpage', context).forEach(() => {
        const loader = document.querySelector(".loader");
        const pageContent = document.querySelector(".page-content") || document.querySelector("main");

        if (loader) {
          loader.classList.remove("hidden");
          setTimeout(() => {
            loader.classList.add("hidden");
            if (pageContent) pageContent.classList.add("visible");
            setTimeout(() => loader.remove(), 500);
          }, 800);
        }
      });
    }
  };

  // ---------------------------
  // 3. Sticky Header on Scroll
  // ---------------------------
  Drupal.behaviors.stickyHeader = {
  attach: function (context) {
    once('stickyHeader', 'body', context).forEach(() => {
      const header = document.querySelector('header');
      if (!header) {
        return; 
      }

      const onScroll = () => {
        if (window.scrollY > 20) {
          header.classList.add('sticky');
        } else {
          header.classList.remove('sticky');
        }
      };
      onScroll();
      // use passive:true for better scroll performance
      window.addEventListener('scroll', onScroll, { passive: true });
    });
  }
};

  // ---------------------------
  // 4. Slick Sliders
  // ---------------------------
  Drupal.behaviors.circularCarousel = {
    attach: function (context) {
      once('circularCarousel', '.circular-carousel', context).forEach((carouselElement) => {

        class CircularCarousel {
          constructor(element) {
            this.carousel = element;
            this.track = this.carousel.querySelector('.carousel-track');
            this.slides = Array.from(this.carousel.querySelectorAll('.carousel-slide'));
            this.dotsContainer = this.carousel.closest('.circular-carousel-container').querySelector('.carousel-dots');
            this.currentIndex = 0;
            this.totalSlides = this.slides.length;
            this.isAnimating = false;
            this.autoplayInterval = null;
            this.autoplayDelay = 4000;
            this.isDragging = false;
            this.startX = 0;
            this.currentX = 0;
            this.dragThreshold = 50;
            this.init();
          }

          init() {
            this.createDots();
            this.updateSlidePositions();
            this.attachEvents();
            this.startAutoplay();
          }

          createDots() {
            if (!this.dotsContainer) return;
            this.dotsContainer.innerHTML = '';
            this.slides.forEach((_, index) => {
              const dot = document.createElement('button');
              dot.classList.add('carousel-dot');
              dot.setAttribute('aria-label', `Go to slide ${index + 1}`);
              if (index === this.currentIndex) dot.classList.add('active');
              dot.addEventListener('click', () => this.goToSlide(index));
              this.dotsContainer.appendChild(dot);
            });
          }

          updateDots() {
            const dots = this.dotsContainer?.querySelectorAll('.carousel-dot');
            if (!dots) return;
            dots.forEach((dot, i) => dot.classList.toggle('active', i === this.currentIndex));
          }

          updateSlidePositions() {
            this.slides.forEach((slide, i) => {
              slide.className = 'carousel-slide';
              let pos = i - this.currentIndex;
              if (pos > this.totalSlides / 2) pos -= this.totalSlides;
              if (pos < -this.totalSlides / 2) pos += this.totalSlides;
              slide.classList.add(
                pos === 0 ? 'center' :
                pos === -1 ? 'left' :
                pos === 1 ? 'right' :
                pos === -2 ? 'hidden-left' :
                pos === 2 ? 'hidden-right' : 'hidden'
              );
            });
            this.updateDots();
          }

          next() {
            if (this.isAnimating) return;
            this.isAnimating = true;
            this.currentIndex = (this.currentIndex + 1) % this.totalSlides;
            this.updateSlidePositions();
            setTimeout(() => (this.isAnimating = false), 800);
          }

          prev() {
            if (this.isAnimating) return;
            this.isAnimating = true;
            this.currentIndex = (this.currentIndex - 1 + this.totalSlides) % this.totalSlides;
            this.updateSlidePositions();
            setTimeout(() => (this.isAnimating = false), 800);
          }

          goToSlide(index) {
            if (this.isAnimating || index === this.currentIndex) return;
            this.isAnimating = true;
            this.currentIndex = index;
            this.updateSlidePositions();
            this.resetAutoplay();
            setTimeout(() => (this.isAnimating = false), 800);
          }

          startAutoplay() {
            this.autoplayInterval = setInterval(() => this.next(), this.autoplayDelay);
          }

          stopAutoplay() {
            clearInterval(this.autoplayInterval);
            this.autoplayInterval = null;
          }

          resetAutoplay() {
            this.stopAutoplay();
            this.startAutoplay();
          }

          attachEvents() {
            this.slides.forEach((slide) => {
              slide.addEventListener('click', () => {
                if (this.isDragging) return;
                if (slide.classList.contains('left')) this.prev();
                else if (slide.classList.contains('right')) this.next();
                this.resetAutoplay();
              });
            });

            this.carousel.addEventListener('mouseenter', () => this.stopAutoplay());
            this.carousel.addEventListener('mouseleave', () => this.startAutoplay());
            this.carousel.addEventListener('keydown', (e) => {
              if (e.key === 'ArrowLeft') this.prev();
              else if (e.key === 'ArrowRight') this.next();
              this.resetAutoplay();
            });

            this.track.addEventListener('mousedown', this.dragStart.bind(this));
            this.track.addEventListener('touchstart', this.dragStart.bind(this), { passive: true });
            document.addEventListener('mousemove', this.dragMove.bind(this));
            document.addEventListener('touchmove', this.dragMove.bind(this), { passive: true });
            document.addEventListener('mouseup', this.dragEnd.bind(this));
            document.addEventListener('touchend', this.dragEnd.bind(this));

            this.carousel.setAttribute('tabindex', '0');
          }

          
          dragStart(e) {
            this.isDragging = true;
            this.startX = e.type.includes('mouse') ? e.pageX : e.touches[0].pageX;
            this.currentX = this.startX;
            this.stopAutoplay();
          }

          dragMove(e) {
            if (!this.isDragging) return;
            this.currentX = e.type.includes('mouse') ? e.pageX : e.touches[0].pageX;
          }

          dragEnd() {
            if (!this.isDragging) return;
            this.isDragging = false;
            const diff = this.startX - this.currentX;
            if (Math.abs(diff) > this.dragThreshold) diff > 0 ? this.next() : this.prev();
            this.startAutoplay();
          }
        }

        carouselElement.carouselInstance = new CircularCarousel(carouselElement);
      });

      once('companySlider', '.company-slider', context).forEach((slider) => {
        $(slider).slick({
          slidesToShow: 4,
          slidesToScroll: 1,
          autoplay: true,
          autoplaySpeed: 2000,
          infinite: true,
          arrows: false,
          dots: false,
          centerMode: true,
          variableWidth: true,
          responsive: [
            { breakpoint: 769, settings: { slidesToShow: 2 } },
            { breakpoint: 481, settings: { slidesToShow: 1 } },
          ],
        });
      });
    },
  };

  // ---------------------------
  // 5. Flexslider Initialization
  // ---------------------------
  Drupal.behaviors.flexSlider = {
    attach: function (context) {
      once('flexSlider', '#carousel', context).forEach(() => {
        $('#carousel').flexslider({
          animation: "slide",
          controlNav: false,
          animationLoop: false,
          slideshow: false,
          itemWidth: 210,
          itemMargin: 5,
          asNavFor: '#slider'
        });
        $('#slider').flexslider({
          animation: "slide",
          controlNav: false,
          animationLoop: false,
          slideshow: false,
          sync: "#carousel"
        });
      });

      once('propertyFlexSlider', '#property-slider', context).forEach(() => {
        $('#property-slider').flexslider({
          animation: "slide",
          controlNav: "thumbnails"
        });
      });
    }
  };

  // ---------------------------
  // 8. Filter Toggle
  // ---------------------------
  Drupal.behaviors.filterToggle = {
    attach: function (context) {
      once('filterToggle', '#filter-toggle', context).forEach((btn) => {
        $(btn).on('click', function () {
          $('#search-form-wrapper').toggleClass('d-none');
          $('.filter-bar-wrapper').toggleClass('d-none');
        });
      });
    }
  };

  // ---------------------------
  // 9. Hamburger Menu
  // ---------------------------
  Drupal.behaviors.hamburgerMenu = {
    attach: function (context) {
      once('hamburgerMenu', '.menu-toggle', context).forEach((toggle) => {
        const menuContent = toggle.nextElementSibling;
        toggle.addEventListener('click', function (e) {
          e.stopPropagation();
          toggle.classList.toggle('active');
          menuContent.classList.toggle('open');
        });
        menuContent.addEventListener('click', function (e) {
          e.stopPropagation();
        });
      });

      // One-time global document click
      once('hamburgerMenuDocClick', document).forEach(() => {
        document.addEventListener('click', () => {
          document.querySelectorAll('.menu-toggle.active').forEach(t => t.classList.remove('active'));
          document.querySelectorAll('.menu-content.open').forEach(m => m.classList.remove('open'));
        });
      });
    }
  };

})(jQuery, Drupal, once);
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.propertyPageFeatures = {
    attach: function (context, settings) {

      /* ------------------------------
       * 1️⃣ AMENITY ICONS MAPPING
       * ------------------------------ */
      once('amenityIconsInit', '.amenities, .room-amenities-list', context).forEach((amenitiesList) => {
        const amenityIcons = [
    { terms: ['_4-hour-reception', '24-hour-reception', 'reception','alarm-clock','24-hour-security','24-hour-front-desk'], icon: 'fa-clock' },
    { terms: ['access-to-beach', 'beach-access','beach-facilities','beach-essentials'], icon: 'fa-umbrella-beach' },
    { terms: ['bathing-at-the-sea', 'bathrobe', 'swimming-pool','bathrobes'], icon: 'fa-swimming-pool' },
    { terms: ['access-to-business-centre', 'business-centre'], icon: 'fa-building' },
    { terms: ['accessibility'], icon: 'fa-wheelchair' },
    { terms: ['additional-bed', 'extra-bed', 'total-bedrooms', 'bedroom', '1-bedroom', '2-bedroom', '3-bedroom', '1-bedroom-apartment-in-mumbai', '2-bedroom-apartment-in-mumbai', '3-bedroom-apartment', '3-bedroom-apartment-in-mumbai'], icon: 'fa-bed' },
    { terms: ['bathing-at-the-lake'], icon: 'fa-water' },
    { terms: ['cable-tv','amazon-prime','blu-ray-player','crt-tv','dvd-library','dvd-player','television'], icon: 'fa-tv' },
    { terms: ['bathtub','baby-bath'], icon: 'fa-bath' },
    { terms: ['wifi', 'wi-fi'], icon: 'fa-wifi' },
    { terms: ['cafe', 'cafetiere', 'capsule-coffee-machine','coffee-maker','coffee-tea-for-guests','coffeemaker','tea-&-coffee-maker','tea-coffee-maker','tea-and-coffee-maker'], icon: 'fa-coffee' },
    { terms: ['microwave'], icon: 'fa-microchip' },
    { terms: ['refrigerator','fridge'], icon: 'fa-snowflake' },
    { terms: ['sofa','couch'], icon: 'fa-couch' },
    { terms: ['iron-box','iron','iron-and-table','iron-box-and-table'], icon: 'fa-tshirt' },
    { terms: ['study-table','study-desk','desk'], icon: 'fa-table' },
    { terms: ['kitchenware','kitchenette','kitchenware---kitchenette'], icon: 'fa-utensils' },
    { terms: ['cook-top','cooktop','stove','induction'], icon: 'fa-fire' },
    { terms: ['washing-machine','washer'], icon: 'fa-tshirt' },
    { terms: ['carbon-monoxide-detector'], icon: 'fa-smog' },
    { terms: ['deposit-boxes', 'safety-locker'], icon: 'fa-box' },
    { terms: ['elevator'], icon: 'fa-elevator' },
    { terms: ['design-hotel'], icon: 'fa-paint-brush' },
    { terms: ['desk', 'workplace', '4-hour-front-desk','desk---workplace','front-desk'], icon: 'fa-table' },
    { terms: ['diet-menu-on-request'], icon: 'fa-utensils' },
    { terms: ['early-late-check-out', 'early-check-in', 'available-from', 'min-stay','early---late-check-out-available','early-check-in'], icon: 'fa-calendar' },
    { terms: ['electric-car-charging', 'electricity-adaptors'], icon: 'fa-bolt' },
    { terms: ['ev-charging-facility'], icon: 'fa-charging-station' },
    { terms: ['total-bathrooms'], icon: 'fa-shower' },
    { terms: ['guest'], icon: 'fa-users' },
    { terms: ['area'], icon: 'fa-house' },
    { terms: ['car parking', 'car-parking','accessible-parking','on-site-parking'], icon: 'fa-car' },
    { terms: ['gym','access-to-gym---spa','fitness-centre---gym'], icon: 'fa-dumbbell' },
    { terms: ['_4-hour-security'], icon: 'fa-dumbbell' },
    { terms: ['security'], icon: 'fa-shield' },
    { terms: ['lift'], icon: 'fa-elevator' },
    { terms: ['adults-only'], icon: 'fa-user-secret' },
    { terms: ['air-conditioning', 'air-conditioner'], icon: 'fa-snowflake' },
    { terms: ['alarm-system'], icon: 'fa-bell' },
    { terms: ['all-spaces-non-smoking-public-and-private'], icon: 'fa-smoking' },
    { terms: ['allergy-free-rooms'], icon: 'fa-allergies' },
    { terms: ['atm','bank'], icon: 'fa-bank' },
    { terms: ['baby-changing-mat','baby-monitor'], icon: 'fa-baby' },
    { terms: ['balcony','balcony---patio','balcony---rooftop-terrace'], icon: 'fa-window-maximize' },
    { terms: ['bar'], icon: 'fa-cocktail' },
    { terms: ['barbeque'], icon: 'fa-hotdog' },
    { terms: ['beach-view'], icon: 'fa-sun' },
    { terms: ['beachfront'], icon: 'fa-sun' },
    { terms: ['bed'], icon: 'fa-bed' },
    { terms: ['beach---pool-towels','beach-pool-towels'], icon: 'fa-towel' },
    { terms: ['bicycles','bike-shed'], icon: 'fa-bicycle' },
    { terms: ['bike-shed'], icon: 'fa-bicycle' },
    { terms: ['black-out-blinds'], icon: 'fa-window-maximize' },
    { terms: ['blender'], icon: 'fa-blender' },
    { terms: ['board-games'], icon: 'fa-gamepad' },
    { terms: ['bottled-water','bottled-water-at-extra-charge'], icon: 'fa-water' },
    { terms: ['bread-maker'], icon: 'fa-bread-slice' },
    { terms: ['breakfast','buffet-breakfast','breakfast-in-the-room','breakfast-included'], icon: 'fa-coffee' },
    { terms: ['breakfast-lunch-to-go'], icon: 'fa-utensils' },
    { terms: ['bridal-suite'], icon: 'fa-heart' },
    { terms: ['buzzer---wireless-intercom'], icon: 'fa-phone' },
    { terms: ['cat-of-residence'], icon: 'fa-cat' },
    { terms: ['ceiling-fans'], icon: 'fa-fan' },
    { terms: ['central'], icon: 'fa-location-dot' },
    { terms: ['central-heating'], icon: 'fa-fire' },
    { terms: ['ceran-cooker','classic-cooker','common-kitchen','dishwasher'], icon: 'fa-utensils' },
    { terms: ['changing-table'], icon: 'fa-toilet' },
    { terms: ['chapel'], icon: 'fa-church' },
    { terms: ['child-friendly','childrens-dinnerware'], icon: 'fa-child' },
    { terms: ['cleaning','cleaning-products'], icon: 'fa-broom' },
    { terms: ['clothes-rack','dry-cleaning','drying-rack','dryer'], icon: 'fa-tshirt' },
    { terms: ['childrens-playroom'], icon: 'fa-child' },
    { terms: ['childrens-table'], icon: 'fa-child' },
    { terms: ['computer'], icon: 'fa-laptop' },
    { terms: ['concierge','concierge-services','cooking-essentials','cooking-utensils'], icon: 'fa-user-secret' },
    { terms: ['cot---crib','cots'], icon: 'fa-bed' },
    { terms: ['courtyard'], icon: 'fa-tree' },
    { terms: ['currency-exchange'], icon: 'fa-exchange-alt' },
    { terms: ['deposit-boxes', 'safety-locker'], icon: 'fa-box' },
    { terms: ['doorman'], icon: 'fa-door-open' },
    { terms: ['drying-rack'], icon: 'fa-tshirt' },
    { terms: ['dvd-library'], icon: 'fa-tv' },
    { terms: ['on-site-restaurant'], icon: 'fa-utensils' },
    { terms: ['common-laundry-room','paid-laundry-service'], icon: 'fa-tshirt' },
    { terms: ['power-backup'], icon: 'fa-bolt' }, 
    { terms: ['cctv'], icon: 'fa-video' },
    { terms: ['studio-in-mumbai', 'studio'], icon: 'fa-building' },
    { terms: ['wardrobe', 'closet', 'wardrobe---closet'], icon: 'fa-tshirt' }
  ];

        const amenityItems = once('amenityItems', 'li', amenitiesList);

        amenityItems.forEach(item => {
          const classList = Array.from(item.classList).map(cls => cls.toLowerCase());
          let iconClass = 'fa-check';

          for (const group of amenityIcons) {
            if (group.terms.some(term => classList.includes(term.toLowerCase()))) {
              iconClass = group.icon;
              break;
            }
          }
          let icon = item.querySelector('i');
          if (!icon) {
            icon = document.createElement('i');
            item.insertBefore(icon, item.firstChild);
          }
          icon.className = `fas ${iconClass}`;
        });
      });

      /* ------------------------------
       * 2️⃣ AUTO-ROTATING ACCORDION
       * ------------------------------ */
      once('autoAccordionInit', '#block-srdesign-views-block-components-accordion-block-1', context).forEach(() => {
        const accordionButtons = context.querySelectorAll(
          '#block-srdesign-views-block-components-accordion-block-1 .views-accordion-header'
        );
        const total = accordionButtons.length;

        if (total > 0) {
          const activateAccordion = (index) => {
            accordionButtons.forEach(btn => {
              btn.classList.remove('ui-accordion-header-active', 'ui-state-active');
              btn.setAttribute('aria-selected', 'false');
              btn.setAttribute('aria-expanded', 'false');
            });

            const button = accordionButtons[index];
            if (button) {
              button.classList.add('ui-accordion-header-active', 'ui-state-active');
              button.setAttribute('aria-selected', 'true');
              button.setAttribute('aria-expanded', 'true');
              button.click();
            }
          };

          const startCycle = () => {
            for (let i = 0; i < total; i++) {
              setTimeout(() => activateAccordion(i), 6000 * i);
            }
          };

          startCycle();
          setInterval(startCycle, 6000 * total);
        }
      });

      /* ------------------------------
       * 3️⃣ STICKY TABS + SCROLL LOGIC
       * ------------------------------ */
      once('stickyTabsInit', '#sticky-section-tabs', context).forEach(() => {
        const stickyTabs = document.getElementById('sticky-section-tabs');
        const slider = document.getElementById('property-slider');

        if (stickyTabs && slider) {
          const handleScroll = () => {
            const sliderRect = slider.getBoundingClientRect();
            const sliderHeight = slider.offsetHeight;
            const triggerPoint = sliderHeight * 0.5;

            if (sliderRect.top <= -triggerPoint) {
              stickyTabs.classList.add('show');
            } else {
              stickyTabs.classList.remove('show');
            }
          };

          window.addEventListener('scroll', handleScroll);
        }

        // Tab click and scroll behavior
        const tabs = context.querySelectorAll('#propertyTabs .nav-link');
        tabs.forEach(tab => {
          tab.addEventListener('click', (e) => {
            e.preventDefault();

            const targetId = tab.getAttribute('href').substring(1);
            const targetContent = document.getElementById(targetId);
            const targetSection = targetContent?.closest('.accordion-item');

            if (targetSection) {
              const targetCollapse = targetSection.querySelector('.accordion-collapse');

              // Close other open collapses
              document.querySelectorAll('.collapse.show').forEach(section => {
                if (section !== targetCollapse) {
                  const bsCollapse = bootstrap.Collapse.getOrCreateInstance(section);
                  bsCollapse.hide();
                }
              });

              // Open target collapse
              if (targetCollapse && !targetCollapse.classList.contains('show')) {
                const bsTargetCollapse = bootstrap.Collapse.getOrCreateInstance(targetCollapse);
                bsTargetCollapse.show();
              }

              // Smooth scroll after a small delay
              setTimeout(() => {
                const scrollOffset = 175;
                const targetPosition = targetSection.getBoundingClientRect().top + window.pageYOffset - scrollOffset;
                window.scrollTo({ top: targetPosition, behavior: 'smooth' });

                // Update active tab
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
              }, 400);
            }
          });
        });

        // Scroll-based active tab highlighting (simplified and optimized)
        const accordionSections = context.querySelectorAll('.accordion-item');
        const tabsMap = new Map();
        
        // Build map of tab href to tab element for O(1) lookup
        tabs.forEach(tab => {
          const href = tab.getAttribute('href').substring(1);
          tabsMap.set(href, tab);
        });

        if (accordionSections.length > 0 && tabsMap.size > 0) {
          let activeSectionId = null;
          let scrollTimeout;

          const updateActiveTab = () => {
            // Calculate trigger point - when accordion header reaches this point, section becomes active
            const stickyTabsElement = document.getElementById('sticky-section-tabs');
            const isStickyVisible = stickyTabsElement && stickyTabsElement.classList.contains('show');
            const triggerPoint = isStickyVisible ? 200 : 100; // Point where header should trigger active state

            let activeSection = null;
            let minDistance = Infinity;

            // Find the section whose header has passed the trigger point and is closest
            accordionSections.forEach(section => {
              const header = section.querySelector('.accordion-header');
              if (!header) return;

              const headerRect = header.getBoundingClientRect();
              const sectionRect = section.getBoundingClientRect();
              
              // Check if section is in viewport
              if (sectionRect.bottom > 0 && sectionRect.top < window.innerHeight) {
                // Calculate distance from trigger point
                const distance = headerRect.top - triggerPoint;
                
                // If header has passed or is near the trigger point, this section is a candidate
                if (headerRect.top <= triggerPoint + 50) {
                  // Use the section that's closest to or past the trigger point
                  const absDistance = Math.abs(distance);
                  if (absDistance < minDistance) {
                    minDistance = absDistance;
                    activeSection = section;
                  }
                } else if (!activeSection && headerRect.top <= triggerPoint + 150) {
                  // Fallback: if no section has passed trigger, use closest one approaching it
                  const absDistance = Math.abs(distance);
                  if (absDistance < minDistance) {
                    minDistance = absDistance;
                    activeSection = section;
                  }
                }
              }
            });

            // Update active tab if section found
            if (activeSection) {
              const accordionBody = activeSection.querySelector('.accordion-body');
              if (accordionBody && accordionBody.id) {
                const sectionId = accordionBody.id;
                const activeTab = tabsMap.get(sectionId);
                
                if (activeTab && activeSectionId !== sectionId) {
                  activeSectionId = sectionId;
                  // Remove active class from all tabs
                  tabs.forEach(t => t.classList.remove('active'));
                  // Add active class to current tab
                  activeTab.classList.add('active');
                }
              }
            }
          };

          // Initial check on page load
          updateActiveTab();

          // Handle scroll with debouncing for better performance
          window.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(updateActiveTab, 30);
          }, { passive: true });
        }
      });
    }
  };
})(Drupal, once);

(function ($, Drupal, once) {
  /**
   * Sidebar "From ₹…/nightly" and "From ₹…/monthly" match the selected room
   * (same values as data-sidebar-* on #room-type options).
   */
  function updatePropertySidebarFromRoomOption(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt) {
      return;
    }
    const block = document.getElementById('property-sidebar-price-block');
    if (!block) {
      return;
    }
    const ror = opt.getAttribute('data-rate-on-request') === '1';
    const night = (opt.getAttribute('data-sidebar-night') || '').trim();
    const month = (opt.getAttribute('data-sidebar-month') || '').trim();
    if (ror) {
      block.innerHTML = '<div class="property-sidebar-price-inner"><strong>Rate on request</strong></div>';
      return;
    }
    let html = '<div class="property-sidebar-price-inner mb-3">';
    if (night) {
      html += '<div class="mb-1 property-sidebar-line property-sidebar-line--night">From <strong><span id="room-price">₹' + night + '</span></strong>/nightly</div>';
    }
    if (month) {
      html += '<div class="property-sidebar-line property-sidebar-line--month">From <strong><span id="room-price-monthly">₹' + month + '</span></strong>/monthly</div>';
    }
    html += '</div>';
    if (!night && !month) {
      html = '<div class="property-sidebar-price-inner"></div>';
    }
    block.innerHTML = html;
  }

  Drupal.behaviors.roomPriceUpdate = {
    attach: function (context, settings) {
      once('roomPriceChange', '#room-type', context).forEach(function (element) {
        element.addEventListener('change', function () {
          updatePropertySidebarFromRoomOption(element);
        });
      });
    }
  };
})(jQuery, Drupal, once);


// Hide Status Messages
function showToast(toastElement, timeoutInMs = 10000) {
    toastElement.classList.remove('d-none', 'hidden');
    
    // Add close button to each message if it doesn't exist
    const messages = toastElement.querySelectorAll('.messages');
    messages.forEach(messageDiv => {
        if (!messageDiv.querySelector('.messages__close')) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'messages__close';
            closeBtn.innerHTML = '×';
            closeBtn.setAttribute('aria-label', 'Close message');
            closeBtn.onclick = function() {
                closeMessage(messageDiv, toastElement);
            };
            messageDiv.appendChild(closeBtn);
        }
        
        // Set auto-close timeout for each message
        const timeoutId = setTimeout(function () {
            closeMessage(messageDiv, toastElement);
        }, timeoutInMs);
        
        messageDiv.dataset.timeoutId = timeoutId;
    });
}

function closeMessage(messageDiv, toastElement) {
    // Cancel auto-close timeout if exists
    if (messageDiv.dataset.timeoutId) {
        clearTimeout(messageDiv.dataset.timeoutId);
    }
    
    // Add fade out animation
    messageDiv.style.opacity = '0';
    messageDiv.style.transform = 'translateY(-20px)';
    messageDiv.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    
    setTimeout(() => {
        messageDiv.remove();
        
        // If no messages left, hide the entire container
        const remainingMessages = toastElement.querySelectorAll('.messages');
        if (remainingMessages.length === 0) {
            toastElement.classList.add('hidden');
            setTimeout(() => {
                toastElement.classList.add('d-none');
            }, 500);
        }
    }, 300);
}

window.addEventListener("load", () => {
    const elements = document.getElementsByClassName('messages-list');
    for (const element of elements) {
        showToast(element);
    }
});

//  Mobile Filter _ JS 

(function (Drupal, once) {
  Drupal.behaviors.filterButtonTrigger = {
    attach: function (context) {
      once('filter-btn-mobile', '#filter-btn-mobile', context).forEach((mobileButton) => {
        const desktopFilter = context.querySelector('#filter-toggle');
        if (desktopFilter) {
          mobileButton.addEventListener('click', function () {
            desktopFilter.click();
          });
        }
      });

      once('search-backdrop', '#search-form-wrapper', context).forEach((wrapper) => {
        wrapper.addEventListener('click', function (e) {
          if (e.target === wrapper) {
            const searchWrapper = context.querySelector('#search-form-wrapper');
            const desktopFilter = context.querySelector('#filter-toggle');

            if (searchWrapper) {
              searchWrapper.style.display = 'none';
            }
            if (desktopFilter) {
              desktopFilter.click();
            }
          }
        });
      });
    },
  };
})(Drupal, once);


(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.storePropertyFormInCookies = {
    attach: function (context, settings) {

      // Helper function to set cookie
      function setCookie(name, value, days = 1) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + "=" + encodeURIComponent(value) + ";expires=" + expires.toUTCString() + ";path=/";
      }

      // Helper function to convert date format from YYYY-MM-DD → DD-MM-YYYY
      function formatDateToDMY(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-'); // ["2025", "11", "13"]
        if (parts.length !== 3) return dateStr; // fallback
        return `${parts[2]}-${parts[1]}-${parts[0]}`; // "13-11-2025"
      }

      once('store-form-values', '.request-book-button', context).forEach(function (button) {
        button.addEventListener('click', function () {

          const propertyNid = this.getAttribute('data-property-nid');
          const propertyTitle = this.getAttribute('data-property-title');

          const checkIn = document.getElementById('check-in');
          const checkOut = document.getElementById('check-out');
          const guests = document.getElementById('guests');
          const roomType = document.getElementById('room-type');
          const roomPrice = document.getElementById('room-price');

          if (checkIn && checkOut && guests && roomType && roomPrice) {

            // ✅ Get formatted dates
            const formattedCheckIn = formatDateToDMY(checkIn.value);
            const formattedCheckOut = formatDateToDMY(checkOut.value);

            // ✅ Get selected option details
            const selectedOption = roomType.options[roomType.selectedIndex];
            const roomTypeTitle = selectedOption.textContent.trim();
            const roomPriceValue = selectedOption.getAttribute('data-price');

            // ✅ Store in cookies (now with formatted dates)
            setCookie('check_in', formattedCheckIn);
            setCookie('check_out', formattedCheckOut);
            setCookie('guests', guests.value);
            setCookie('room_type', roomTypeTitle);
            setCookie('room_price', roomPriceValue);

            if (propertyNid) {
              setCookie('property_nid', propertyNid);
              setCookie('property_name', propertyTitle);
              console.log('🏠 property_nid stored:', propertyNid);
            } else {
              console.warn('⚠️ No data-property-nid found on button.');
            }

            console.log(`✅ Dates stored: ${formattedCheckIn} → ${formattedCheckOut}`);
          } else {
            console.warn('⚠️ One or more booking fields not found — cookies not set.');
          }
        });
      });

    }
  };

})(Drupal, once);

(function ($, Drupal) {
  'use strict';

  // =====================================================
  // TAB SWITCHING BEHAVIOR FOR USER BOOKINGS
  // =====================================================
  Drupal.behaviors.userBookingsTabs = {
    attach: function (context, settings) {
      // Use "once" to prevent multiple attachments
      once('user-bookings-tabs', '.user-bookings', context).forEach(function(bookingWrapper) {
        const tabs = bookingWrapper.querySelectorAll('.tab');
        const contents = bookingWrapper.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
          tab.addEventListener('click', function() {
            const key = this.getAttribute('data-tab');

            // Remove active from all tabs and content sections within this wrapper only
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));

            // Add active to clicked tab and matching content within this wrapper
            this.classList.add('active');
            const activeContent = bookingWrapper.querySelector('#tab-' + key);
            if (activeContent) {
              activeContent.classList.add('active');
            }
          });
        });
      });
    }
  };

  // simple cookie getter (no dependency)
  function getCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i].trim();
      if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length));
    }
    return null;
  }

  // fill hidden inputs in a given formElement (DOM element or jQuery)
  function fillHiddenFields(formElement) {
    try {
      var $form = (formElement instanceof jQuery) ? formElement : $(formElement);
      if (!$form.length) return false;

      // get cookie values
      var checkIn = getCookie('check_in') || '';
      var checkOut = getCookie('check_out') || '';
      var guestCount = getCookie('guest_count') || getCookie('guests') || '';
      var roomType = getCookie('room_type') || '';
      var roomPrice = getCookie('room_price') || '';
      var propertyNid = getCookie('property_nid') || '';
      var propertyTitle = getCookie('property_name') || '';

      // list of mappings: form field name => value
      var map = {
        'check_in': checkIn,
        'check_out': checkOut,
        'guest_count': guestCount,
        'room_type': roomType,
        'room_price': roomPrice,
        'property_nid': propertyNid,
        'property_name': propertyTitle
      };

      Object.keys(map).forEach(function (name) {
        var val = map[name];
        // try input[name="..."], fallback to inputs with data-drupal-selector if needed
        var $input = $form.find('input[name="' + name + '"]');
        if (!$input.length) {
          // sometimes webform names differ; try data-drupal-selector contains
          $input = $form.find('input[data-drupal-selector*="' + name.replace(/_/g, '-') + '"]');
        }
        if ($input.length) {
          $input.each(function () {
            // set both DOM value and jQuery val
            this.value = val;
            try { $(this).val(val); } catch (e) { /* ignore */ }
          });
        } else {
          // debug: missing field
          console.log('⚠️ Hidden field not found in form:', name, ' — skipping');
        }
      });

      console.log('✅ Hidden fields populated before submit:', map);
      return true;
    } catch (err) {
      console.error('Error in fillHiddenFields:', err);
      return false;
    }
  }

  Drupal.behaviors.prefillBookingForm = {
    attach: function (context, settings) {
      // Debug attach to confirm JS loaded and behavior attached
      // Removed verbose console log

      // Delegated submit handler: runs for forms injected later too
      $(document).off('submit.prefillBooking').on('submit.prefillBooking', 'form.webform-submission-request-booking-add-form, form[id^="webform-submission-request-booking-add-form"]', function (e) {
        if (window.console) console.log('submit event captured for booking form');
        fillHiddenFields(this);
        // allow form continue to submit
      });

      // Delegated click handler on submit button as a secondary measure
      $(document).off('click.prefillBooking').on('click.prefillBooking', '.webform-submission-request-booking-add-form .webform-button--submit, form.webform-submission-request-booking-add-form .webform-button--submit, .ui-dialog-content form .webform-button--submit', function (e) {
        // find the related form
        var $btn = $(this);
        var $form = $btn.closest('form');
        if ($form.length === 0) {
          // fallback: try to find visible booking form
          $form = $('form.webform-submission-request-booking-add-form:visible').first();
        }
        if ($form.length) {
          if (window.console) console.log('submit button click detected — filling hidden fields');
          fillHiddenFields($form);
          // allow click to proceed
        } else {
          if (window.console) console.log('submit button click detected but booking form not found');
        }
      });

      // Optional: also observe DOM additions (if your site blocks submit event or uses custom submit)
      if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
          mutations.forEach(function (m) {
            // if a booking form was added, attach a one-time fill for debug
            var added = $(m.addedNodes).find('form.webform-submission-request-booking-add-form');
            if (added.length) {
              console.log('MutationObserver detected booking form added; pre-filling now.');
              added.each(function () { fillHiddenFields(this); });
            }
          });
        });
        observer.observe(document.body, { childList: true, subtree: true });
      }
    }
  };

  // Pre-fill check-in and check-out dates from URL parameters on property detail page
  Drupal.behaviors.prefillPropertyDates = {
    attach: function (context, settings) {
      once('prefill-property-dates', 'body', context).forEach(function() {
        const checkInInput = document.getElementById('check-in');
        const checkOutInput = document.getElementById('check-out');
        
        if (!checkInInput || !checkOutInput) {
          return; // Date inputs not found on this page
        }

        // Get dates from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        let checkIn = urlParams.get('check_in') || '';
        let checkOut = urlParams.get('check_out') || '';

        // Set the values if we have them
        if (checkIn) {
          checkInInput.value = checkIn;
          // Trigger input event to update check-out min date
          checkInInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        if (checkOut) {
          checkOutInput.value = checkOut;
        }

        // Also pre-fill guests if available
        const guestsSelect = document.getElementById('guests');
        if (guestsSelect) {
          const guests = urlParams.get('guests') || urlParams.get('rooms') || '';
          if (guests && guestsSelect.querySelector('option[value="' + guests + '"]')) {
            guestsSelect.value = guests;
          }
        }
      });
    }
  };

})(jQuery, Drupal);
// services accordion js 
document.addEventListener("DOMContentLoaded", function () {
  const accordionButtons = document.querySelectorAll('#accordion-component .custom-accordion-header');
  const total = accordionButtons.length;

  if (total > 0) {
    function activateAccordion(index) {
      accordionButtons.forEach(btn => {
        btn.classList.remove('ui-accordion-header-active', 'ui-state-active');
        btn.setAttribute('aria-selected', 'false');
        btn.setAttribute('aria-expanded', 'false');

        const contentId = btn.getAttribute('aria-controls');
        const contentPanel = document.getElementById(contentId);
        if (contentPanel) {
          contentPanel.setAttribute('aria-hidden', 'true');
          contentPanel.classList.remove('active');
        }
      });

      const button = accordionButtons[index];
      if (button) {
        button.classList.add('ui-accordion-header-active', 'ui-state-active');
        button.setAttribute('aria-selected', 'true');
        button.setAttribute('aria-expanded', 'true');

        const contentId = button.getAttribute('aria-controls');
        const contentPanel = document.getElementById(contentId);
        if (contentPanel) {
          contentPanel.setAttribute('aria-hidden', 'false');
          contentPanel.classList.add('active');
        }
      }
    }

    accordionButtons.forEach((button, index) => {
      button.addEventListener("click", () => {
        const isActive = button.classList.contains("ui-accordion-header-active");
        if (isActive) {
          button.classList.remove("ui-accordion-header-active", "ui-state-active");
          button.setAttribute("aria-expanded", "false");
          const contentId = button.getAttribute("aria-controls");
          const contentPanel = document.getElementById(contentId);
          if (contentPanel) {
            contentPanel.classList.remove("active");
            contentPanel.setAttribute("aria-hidden", "true");
          }
        } else {
          activateAccordion(index);
        }
      });
    });

    function startCycle() {
      for (let i = 0; i < total; i++) {
        setTimeout(() => {
          activateAccordion(i);
        }, 3000 * i);
      }
    }

    startCycle();
    setInterval(startCycle, 3000 * total);
  }

  const stickyTabs = document.getElementById("sticky-section-tabs");
  const slider = document.getElementById("property-slider");

  if (stickyTabs && slider) {
    function handleScroll() {
      const sliderRect = slider.getBoundingClientRect();
      const sliderHeight = slider.offsetHeight;
      const triggerPoint = sliderHeight * 0.5;
      if (sliderRect.top <= -triggerPoint) {
        stickyTabs.classList.add("show");
      } else {
        stickyTabs.classList.remove("show");
      }
    }
    window.addEventListener("scroll", handleScroll);
  }

  document.querySelectorAll("#propertyTabs .nav-link").forEach((tab) => {
    tab.addEventListener("click", function (e) {
      e.preventDefault();
      const targetId = this.getAttribute("href").substring(1);
      const targetContent = document.getElementById(targetId);
      const targetSection = targetContent?.closest(".accordion-item");

      if (targetSection) {
        const targetCollapse = targetSection.querySelector(".accordion-collapse");

        document.querySelectorAll(".collapse.show").forEach((section) => {
          if (section !== targetCollapse) {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(section);
            bsCollapse.hide();
          }
        });

        if (targetCollapse && !targetCollapse.classList.contains("show")) {
          const bsTargetCollapse = bootstrap.Collapse.getOrCreateInstance(targetCollapse);
          bsTargetCollapse.show();
        }

        setTimeout(() => {
          const scrollOffset = 175;
          const targetPosition = targetSection.getBoundingClientRect().top + window.pageYOffset - scrollOffset;
          window.scrollTo({
            top: targetPosition,
            behavior: "smooth"
          });

          document.querySelectorAll("#propertyTabs .nav-link").forEach((t) => {
            t.classList.remove("active");
          });
          tab.classList.add("active");
        }, 400);
      }
    });
  });

});
// Adding container class for team section

document.addEventListener("DOMContentLoaded", function () {
  const blocks = [
    document.querySelector("#block-srdesign-views-block-about-block-4"),
    document.querySelector("#block-srdesign-views-block-about-block-5"),
  ];

  blocks.forEach(block => {
    if (block && !block.classList.contains("container")) {
      block.classList.add("container");
    }
  });
});

//Request to Book: js
(function (Drupal, once, drupalSettings) {
  /**
   * Behavior 1: Cancel booking button
   */
  Drupal.behaviors.cancelBooking = {
    attach: function (context, settings) {
      once('cancelBooking', '.cancel-booking-btn', context).forEach(function (el) {
        el.addEventListener('click', function (e) {
          e.preventDefault();
          if (!confirm('Are you sure you want to cancel this booking?')) return;
          const submissionId = el.getAttribute('data-submission-id');
          fetch('/cancel-booking/' + submissionId, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Content-Type': 'application/json'
            },
            credentials: 'same-origin'
          })
          .then(response => response.json())
          .then(response => {
            if (response.status === 'success') {
              alert(response.message);
              window.location.reload();
            } else {
              alert(response.message || 'Failed to cancel booking');
            }
          })
          .catch(() => {
            alert('Failed to cancel booking. Please try again.');
          });
        });
      });
    }
  };

  // ---------------------------
  // Remove favourite card when trash icon is clicked
  // ---------------------------
  // Store clicked card globally (outside behavior) so it persists across AJAX re-attachments
  let clickedCardData = null;
  
  // Attach mousedown handlers to unflag links on favourites page
  Drupal.behaviors.favouriteCardAutoRemove = {
    attach: function (context, settings) {
      // Only apply to favourites page
      const favouritesView = context.querySelector('.view-flag-bookmark') || document.querySelector('.view-flag-bookmark');
      const isFavouritesPage = window.location.pathname.indexOf('/favourite') !== -1 || document.body.classList.contains('path-favourite');
      
      if (!favouritesView && !isFavouritesPage) {
        return;
      }

      // Find all unflag links
      const unflagLinks = $(document).find('.flag.action-unflag a, a[href*="flag/unflag"].use-ajax');
      
      // Attach mousedown handler to capture card BEFORE AJAX intercepts click
      unflagLinks.each(function() {
        const link = this;
        $(link).off('mousedown.favouriteCardAutoRemove');
        $(link).on('mousedown.favouriteCardAutoRemove', function(e) {
          // Store card data on mousedown (before AJAX happens)
          const card = $(link).closest('.card, .property-card, .views-row')[0];
          const flagWrapper = $(link).closest('.flag')[0];
          
          if (card && flagWrapper) {
            clickedCardData = {
              card: card,
              flagWrapper: flagWrapper,
              flagId: $(link).attr('href').match(/\/flag\/unflag\/favourite\/(\d+)/) ? $(link).attr('href').match(/\/flag\/unflag\/favourite\/(\d+)/)[1] : null
            };
          }
        });
      });

      // Check if we have stored card data and flag changed - remove card if needed
      if (clickedCardData && clickedCardData.card) {
        const card = clickedCardData.card;
        const flagWrapper = clickedCardData.flagWrapper;
        
        // Check if flag wrapper now has action-flag (means it was unflagged)
        if (flagWrapper && document.contains(flagWrapper)) {
          const hasActionFlag = flagWrapper.classList.contains('action-flag');
          
          if (hasActionFlag) {
            card.classList.add('favourite-card--removing');
            setTimeout(function() {
              if (card.parentNode) {
                card.remove();
                clickedCardData = null;
              }
            }, 150);
            return;
          }
        }
        
        // Also check by looking for action-flag in the card
        const newFlagWrapper = $(card).find('.flag.action-flag')[0];
        if (newFlagWrapper) {
          card.classList.add('favourite-card--removing');
          setTimeout(function() {
            if (card.parentNode) {
              card.remove();
              clickedCardData = null;
            }
          }, 150);
        }
      }
    }
  };

  /* ------------------------------
   * 4️⃣ PROPERTY FORM PRE-FILL
   * ------------------------------ */
  Drupal.behaviors.propertyFormPrefill = {
    attach: function (context, settings) {
      once('propertyFormPrefill', 'body', context).forEach(() => {
        // Pre-fill guests field from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const guestsParam = urlParams.get('guests');
        
        console.log('Guests param from URL:', guestsParam); // Debug log
        
        if (guestsParam) {
          const guestsSelect = document.getElementById('guests');
          console.log('Guests select element:', guestsSelect); // Debug log
          
          if (guestsSelect) {
            console.log('Available options:', Array.from(guestsSelect.options).map(opt => ({ text: opt.text, value: opt.value }))); // Debug log
              
            // Find matching option in guests dropdown by text content
            for (let i = 0; i < guestsSelect.options.length; i++) {
              if (guestsSelect.options[i].text === guestsParam) {
                guestsSelect.selectedIndex = i;
                console.log('Matched option at index:', i); // Debug log
                break;
              }
            }
          }
        }
      });
    }
  };

})(Drupal, once, drupalSettings);

document.addEventListener('click', function (e) {
  const text = e.target.closest('.favorite-text');
  if (!text) return;

  const wrapper = text.closest('.favorite-wrapper');
  const link = wrapper.querySelector('a');

  if (link) {
    link.click();
  }
});

(function($, Drupal) {
    'use strict';

    // =====================================================
    // TAB SWITCHING BEHAVIOR FOR GCC ROLE SECTION
    // =====================================================
    Drupal.behaviors.gccRoleSectionTabs = {
        attach: function(context, settings) {
            once('gcc-role-tabs', '.role-section', context).forEach(function(roleSection) {
                const tabs = roleSection.querySelectorAll('.tab-link');
                const contents = roleSection.querySelectorAll('.tab-content');

                tabs.forEach(tab => {
                    tab.addEventListener('click', function(e) {
                        e.preventDefault();
                        const key = this.getAttribute('data-tab');

                        // Remove active from all tabs and content sections
                        tabs.forEach(t => t.classList.remove('active'));
                        contents.forEach(c => c.classList.remove('active'));

                        // Add active to clicked tab and matching content
                        this.classList.add('active');
                        const activeContent = roleSection.querySelector('#tab-' + key);
                        if (activeContent) {
                            activeContent.classList.add('active');
                        }
                    });
                });
            });
        }
    };

    // =====================================================
// PRICE / AMENITIES TOGGLE FOR COMPARISON SECTION
// =====================================================
Drupal.behaviors.priceAmenitiesToggle = {
  attach: function (context, settings) {
    once('price-amenities-toggle', '.comparison-section', context).forEach(function (section) {
      const toggleBtns = section.querySelectorAll('.toggle-btn');
      const views = section.querySelectorAll('.comparison-view');
      const cityTabs = section.querySelectorAll('.city-tab');

      // Toggle between price & amenities
      toggleBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          const view = this.getAttribute('data-view');

          toggleBtns.forEach(function (b) {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
          });
          this.classList.add('active');
          this.setAttribute('aria-selected', 'true');

          views.forEach(function (v) {
            v.classList.toggle('active', v.getAttribute('data-view') === view);
          });
        });
      });

      var DURATIONS = [7, 15, 30, 90, 365];
      var DURATION_IDS = ['7n', '15n', '1m', '3m', '12m'];

      function formatINR(amount) {
        return '₹' + amount.toLocaleString('en-IN');
      }
      

      function updateCityPrices(saRate, hotelRate) {
        var saPrice = document.getElementById('gcc-sa-price');
        var hotelPrice = document.getElementById('gcc-hotel-price');
        if (saPrice) saPrice.textContent = formatINR(saRate);
        if (hotelPrice) hotelPrice.textContent = formatINR(hotelRate);
        DURATION_IDS.forEach(function (id, i) {
          var saCell = document.getElementById('gcc-sa-' + id);
          var hotelCell = document.getElementById('gcc-hotel-' + id);
          if (saCell) saCell.textContent = formatINR(saRate * DURATIONS[i]);
          if (hotelCell) hotelCell.textContent = formatINR(hotelRate * DURATIONS[i]);
        });
      }

      // City tab click: update active state and recalculate prices
      cityTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          cityTabs.forEach(function (t) { t.classList.remove('active'); });
          this.classList.add('active');
          var saRate = parseInt(this.getAttribute('data-sa-rate'), 10);
          var hotelRate = parseInt(this.getAttribute('data-hotel-rate'), 10);
          if (saRate && hotelRate) {
            updateCityPrices(saRate, hotelRate);
          }
        });
      });
    });
  }
};

})(jQuery, Drupal);