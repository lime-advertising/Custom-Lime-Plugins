// Placeholder for future enhancements
console.log("Home Depot Reviews JS loaded.");

document.addEventListener("DOMContentLoaded", function () {
  if (typeof Swiper !== "undefined") {
    new Swiper(".hd-review-swiper", {
      slidesPerView: 1,
      spaceBetween: 20,
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
      navigation: {
        nextEl: ".hd-button-next",
        prevEl: ".hd-button-prev",
      },
      breakpoints: {
        768: {
          slidesPerView: 2,
        },
      },
    });
  }
});
