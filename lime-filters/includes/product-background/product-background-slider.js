(function(){
  function initGallery(container){
    if (typeof window.Swiper === 'undefined') {
      return;
    }

    const mainEl   = container.querySelector('.lf-bg-gallery__main.swiper');
    const thumbsEl = container.querySelector('.lf-bg-gallery__thumbs.swiper');
    if (!mainEl || !thumbsEl) {
      return;
    }

    const prevEl = container.querySelector('.lf-bg-gallery__prev');
    const nextEl = container.querySelector('.lf-bg-gallery__next');

    const desktop = parseInt(container.dataset.columns || '4', 10) || 4;
    const tablet  = parseInt(container.dataset.columnsTablet || '3', 10) || desktop;
    const mobile  = parseInt(container.dataset.columnsMobile || '2', 10) || tablet;

    const thumbsSwiper = new Swiper(thumbsEl, {
      spaceBetween: 14,
      slidesPerView: mobile,
      watchSlidesProgress: true,
      breakpoints: {
        576: {
          slidesPerView: tablet,
        },
        992: {
          slidesPerView: desktop,
        },
      },
    });

    const mainSwiper = new Swiper(mainEl, {
      slidesPerView: 1,
      spaceBetween: 10,
      effect: 'fade',
      fadeEffect: { crossFade: true },
      autoHeight: true,
      navigation: {
        prevEl: prevEl || undefined,
        nextEl: nextEl || undefined,
      },
      thumbs: {
        swiper: thumbsSwiper,
      },
    });

    // Ensure initial measurements
    setTimeout(function(){
      mainSwiper.updateAutoHeight(0);
      mainSwiper.updateSlides();
      mainSwiper.updateSize();
      thumbsSwiper.updateSlides();
      thumbsSwiper.updateSize();
    }, 120);
  }

  function initAll(){
    document.querySelectorAll('.lf-bg-gallery--slider').forEach(function(container){
      if (container.dataset.lfGalleryInit === '1') {
        return;
      }
      container.dataset.lfGalleryInit = '1';
      initGallery(container);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
