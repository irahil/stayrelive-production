//create slider for cities
var siderContents = [];
citySilderInit();

function citySilderInit() {
    var slides = document.querySelectorAll('.city-carousel .slideContent .sildeItem');
    slides.forEach(function(slide) {
        var sItem = [];
        var sideItems = slide.querySelectorAll('a');
        sideItems.forEach(function(sideItem) {
            sItem.push({
                href: sideItem.href,
                imgSrc: sideItem.querySelector('img').getAttribute('src'),
                title: sideItem.querySelector('h6').innerHTML
            });
        });
        siderContents.push(sItem);
    });

    var carouselLinks = document.querySelector('.continents ul').querySelectorAll('li');

    carouselLinks.forEach(function(link, index) {
        link.querySelector('a').addEventListener('click', function() {
            resetFocusClass(carouselLinks);
            this.classList.add('focus');
            ChangeSliderContent(index);
        });
    })
}

function resetFocusClass(carouselLinks) {
    carouselLinks.forEach((item) => {
        item.querySelector('a').classList.remove('focus');
    });
}

function ChangeSliderContent(index) {
    console.log('index ' + siderContents[index])
    var slider = document.querySelector('.tiny-slider');
    var htmlContent = '<div class="tiny-slider-inner" data-autoplay="true" data-arrow="true" data-edge="2" data-dots="false" data-items-xl="3" data-items-lg="2" data-items-md="1">';
    siderContents[index].forEach(element => {
        htmlContent = htmlContent + `
        <!-- Slider item -->
    <div>
        <div class="card border rounded-3 overflow-hidden">
            <div class="row g-0 align-items-center">
                <!-- Image -->
                <div class="col-sm-12">
                    <a href="` + element.href + `" class="">
                        <img src="` + element.imgSrc + `" class="card-img rounded-0" alt="">
                        <h6 class="card-title px-3">` + element.title + `</h6>
                    </a>
                </div>
            </div>
        </div>
    </div>`;
    });
    htmlContent = htmlContent + '</div>'
    slider.innerHTML = htmlContent;
    e.tinySlider();
}

// Get all accordion headers
var accordionHeaders = document.querySelectorAll('.accordion-header');
var image = document.querySelector('.accordionImg');
var imagePath = ["assets/images/home/slider-business.jpg", "assets/images/home/slider-leisure.jpg", "assets/images/home/slider-medical.jpg"];


// Attach click event listener to each header
accordionHeaders.forEach(function(header, index) {
    header.addEventListener('click', function() {
        var imgSrc = image.getAttribute('src');
        if (imgSrc != imagePath[index]) {
            slideImage(imagePath[index]);
        }
    });
});

function slideImage(imageUrl) {
    image.style.opacity = '0';
    image.style.transition = 'opacity 0.5s';

    setTimeout(function() {
        image.src = imageUrl;
        image.style.opacity = '1';
        image.style.transition = 'opacity 0.5s';
    }, 200); // Adjust the delay (in milliseconds) as needed
}