gsap.registerPlugin(ScrollTrigger, ScrollToPlugin);

let timeln = gsap.timeline({
    scrollTrigger: {
        trigger: ".cards",
        pin: true,
        pinSpacing: true,
        start: "top top",
        end: "+=2000",
        scrub: 1
    }
});

timeln.addLabel('card1');
timeln.to('.card-1', {
    yPercent: 0,
    opacity: 1
});

timeln.from('.card-2', {
    yPercent: 75,
    opacity: 0
});
timeln.addLabel("card2");

timeln.to(".card-1", {
    scale: 0.95,
    yPercent: -0.5,
    opacity: 0.5
}, "-=0.3");

timeln.to('.card-2', {
    yPercent: 0,
    opacity: 1
});

timeln.from('.card-3', {
    yPercent: 75,
    opacity: 0
});
timeln.addLabel('card3');

timeln.to(".card-2", {
    scale: 0.98,
    yPercent: -0.4,
    opacity: 0.5
}, "-=0.3");

timeln.to(".card-3", {
    yPercent: 0,
    opacity: 1
});

timeln.to('.card-3', {});
