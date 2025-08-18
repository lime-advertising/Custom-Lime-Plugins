$(function () {
  const $wrap = $('.slider-wrap');
  const EFFECT = $wrap.hasClass('effect--reveal') ? 'reveal' : 'classic';
  const $slider = $('.slider');
  const $slides = $slider.find('.slide');
  const count = $slides.length;
  const autoplayDelay = 4500;
  let current = 0;
  let timer = null;
  let isAnimating = false;
  let kenBurnsTween = null;
  // Swipe state
  let touchStartX = 0;
  let touchStartY = 0;
  let touchActive = false;
  const SWIPE_THRESHOLD = 40; // px
  // Pointer/mouse state
  let pointerDown = false;
  let pointerStartX = 0;
  let pointerStartY = 0;

  function updateUI() {
    $slides.removeClass('is-active').css({ pointerEvents: 'none' });
    const $curr = $slides.eq(current);
    $curr.addClass('is-active').css({ pointerEvents: 'auto' });
  }

  // Initial state for slides based on effect
  function primeSlides() {
    $slides.each(function () {
      const $s = $(this);
      if (EFFECT === 'classic') {
        gsap.set($s.find('.content h2'), { y: 30, autoAlpha: 0 });
        gsap.set($s.find('.content p'), { y: 20, autoAlpha: 0 });
        gsap.set($s.find('.content .btn'), { y: 10, autoAlpha: 0 });
        gsap.set($s.find('.slide-bg'), { scale: 1.12, clearProps: 'clipPath' });
      } else {
        gsap.set($s.find('.content'), { x: -40, autoAlpha: 0 });
        gsap.set($s.find('.slide-bg'), { scale: 1.05, clipPath: 'inset(0 100% 0 0)' });
      }
    });
  }

  function startAutoplay() {
    clearTimeout(timer);
    if (autoplayDelay > 0) {
      timer = setTimeout(() => goTo((current + 1) % count, 'next'), autoplayDelay);
    }
  }

  function goTo(target, dir) {
    if (isAnimating || target === current) return;
    isAnimating = true;
    clearTimeout(timer);

    const $prev = $slides.eq(current);
    const $next = $slides.eq(target);

    if (EFFECT === 'classic') {
      // Prepare next
      gsap.set($next, { opacity: 1, zIndex: 2, pointerEvents: 'auto' });
      gsap.set($prev, { opacity: 1, zIndex: 1 });
      gsap.set($next.find('.content h2'), { y: 30, autoAlpha: 0 });
      gsap.set($next.find('.content p'), { y: 20, autoAlpha: 0 });
      gsap.set($next.find('.content .btn'), { y: 10, autoAlpha: 0 });
      gsap.set($next.find('.slide-bg'), { scale: 1.12, clearProps: 'clipPath' });

      const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
      tl.to($next.find('.slide-bg'), { scale: 1, duration: 1.6, ease: 'power2.out' }, 0)
        .to($next.find('.content h2'), { y: 0, autoAlpha: 1, duration: 0.8 }, 0.1)
        .to($next.find('.content p'), { y: 0, autoAlpha: 1, duration: 0.7 }, 0.25)
        .to($next.find('.content .btn'), { y: 0, autoAlpha: 1, duration: 0.6 }, 0.4)
        .to($prev, { opacity: 0, duration: 0.6, ease: 'power1.out' }, 0.2)
        .add(() => {
          // Finalize
          current = target;
          $slides.css({ zIndex: '' });
          $prev.css({ opacity: 0, pointerEvents: 'none' });
          updateUI();
          isAnimating = false;
          startAutoplay();
        });
    } else {
      const isPrev = dir === 'prev';
      const startClip = isPrev ? 'inset(0 0 0 100%)' : 'inset(0 100% 0 0)';

      // Stack prev below, next above
      gsap.set($prev, { opacity: 1, zIndex: 1 });
      gsap.set($next, { opacity: 1, zIndex: 2, pointerEvents: 'auto' });

      // Prepare next slide elements
      gsap.set($next.find('.content'), { x: isPrev ? 40 : -40, autoAlpha: 0 });
      gsap.set($next.find('.slide-bg'), { scale: 1.05, clipPath: startClip });

      // Kill any previous Ken Burns tween and start a fresh one separate from the transition timeline
      if (kenBurnsTween) kenBurnsTween.kill();
      kenBurnsTween = gsap.to($next.find('.slide-bg'), { scale: 1.15, duration: 6, ease: 'none' });

      const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
      // Wipe reveal (short) + content enter; finish interaction quickly so arrows remain responsive
      tl.to($next.find('.slide-bg'), { clipPath: 'inset(0 0% 0 0)', duration: 0.9, ease: 'power2.out' }, 0)
        .to($next.find('.content'), { x: 0, autoAlpha: 1, duration: 0.8 }, 0.15)
        .add(() => {
          // Finalize stacking and re-enable controls
          current = target;
          $slides.css({ zIndex: '' });
          $prev.css({ opacity: 0, pointerEvents: 'none' });
          updateUI();
          isAnimating = false;
          startAutoplay();
        });
    }
  }

  // Navigation
  $slider.find('.nav.next').on('click', () => goTo((current + 1) % count, 'next'));
  $slider.find('.nav.prev').on('click', () => goTo((current - 1 + count) % count, 'prev'));

  // Keyboard arrows when slider is focused or hovered
  document.addEventListener('keydown', (e) => {
    const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
    const interactive = tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable);
    if (interactive) return; // don't hijack typing

    const sliderFocused = document.activeElement === sliderEl;
    const sliderHovered = $slider.is(':hover');
    if (!sliderFocused && !sliderHovered) return;

    if (e.key === 'ArrowRight') {
      e.preventDefault();
      goTo((current + 1) % count, 'next');
    } else if (e.key === 'ArrowLeft') {
      e.preventDefault();
      goTo((current - 1 + count) % count, 'prev');
    }
  });

  // Touch swipe (left/right)
  const sliderEl = $slider.get(0);
  if (sliderEl) {
    sliderEl.addEventListener('touchstart', (e) => {
      if (!e.touches || !e.touches.length) return;
      const t = e.touches[0];
      touchStartX = t.clientX;
      touchStartY = t.clientY;
      touchActive = true;
      clearTimeout(timer); // pause autoplay while interacting
    }, { passive: true });

    sliderEl.addEventListener('touchmove', (e) => {
      // passive; we don't prevent scrolling, only track
    }, { passive: true });

    sliderEl.addEventListener('touchend', (e) => {
      if (!touchActive) return;
      touchActive = false;
      const t = (e.changedTouches && e.changedTouches[0]) || null;
      if (!t) { startAutoplay(); return; }
      const dx = t.clientX - touchStartX;
      const dy = t.clientY - touchStartY;
      if (Math.abs(dx) > SWIPE_THRESHOLD && Math.abs(dx) > Math.abs(dy)) {
        if (dx < 0) {
          goTo((current + 1) % count, 'next');
        } else {
          goTo((current - 1 + count) % count, 'prev');
        }
      } else {
        // resume autoplay if no navigation triggered
        startAutoplay();
      }
    });
  }

  // Pointer/mouse swipe for desktop
  if (window.PointerEvent) {
    sliderEl.addEventListener('pointerdown', (e) => {
      // Ignore secondary buttons
      if (e.button && e.button !== 0) return;
      pointerDown = true;
      pointerStartX = e.clientX;
      pointerStartY = e.clientY;
      $slider.addClass('dragging');
      clearTimeout(timer);
    });
    window.addEventListener('pointerup', (e) => {
      if (!pointerDown) return;
      pointerDown = false;
      $slider.removeClass('dragging');
      const dx = e.clientX - pointerStartX;
      const dy = e.clientY - pointerStartY;
      if (Math.abs(dx) > SWIPE_THRESHOLD && Math.abs(dx) > Math.abs(dy)) {
        if (dx < 0) {
          goTo((current + 1) % count, 'next');
        } else {
          goTo((current - 1 + count) % count, 'prev');
        }
      } else {
        startAutoplay();
      }
    });
    // Prevent image dragging ghost
    sliderEl.addEventListener('dragstart', (e) => e.preventDefault());
  } else {
    // Mouse fallback
    sliderEl.addEventListener('mousedown', (e) => {
      if (e.button && e.button !== 0) return;
      pointerDown = true;
      pointerStartX = e.clientX;
      pointerStartY = e.clientY;
      $slider.addClass('dragging');
      clearTimeout(timer);
    });
    window.addEventListener('mouseup', (e) => {
      if (!pointerDown) return;
      pointerDown = false;
      $slider.removeClass('dragging');
      const dx = e.clientX - pointerStartX;
      const dy = e.clientY - pointerStartY;
      if (Math.abs(dx) > SWIPE_THRESHOLD && Math.abs(dx) > Math.abs(dy)) {
        if (dx < 0) {
          goTo((current + 1) % count, 'next');
        } else {
          goTo((current - 1 + count) % count, 'prev');
        }
      } else {
        startAutoplay();
      }
    });
    sliderEl.addEventListener('dragstart', (e) => e.preventDefault());
  }

  // Init
  primeSlides();
  // Reveal the first slide with an intro animation
  const $first = $slides.eq(current);
  $first.addClass('is-active').css({ opacity: 1, pointerEvents: 'auto' });
  if (EFFECT === 'classic') {
    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    tl.to($first.find('.slide-bg'), { scale: 1, duration: 1.3, ease: 'power2.out' }, 0)
      .to($first.find('.content h2'), { y: 0, autoAlpha: 1, duration: 0.8 }, 0.1)
      .to($first.find('.content p'), { y: 0, autoAlpha: 1, duration: 0.7 }, 0.25)
      .to($first.find('.content .btn'), { y: 0, autoAlpha: 1, duration: 0.6 }, 0.4);
  } else {
    // Start Ken Burns separately so controls stay responsive
    if (kenBurnsTween) kenBurnsTween.kill();
    kenBurnsTween = gsap.to($first.find('.slide-bg'), { scale: 1.15, duration: 6, ease: 'none' });
    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    gsap.set($first.find('.slide-bg'), { clipPath: 'inset(0 100% 0 0)' });
    tl.to($first.find('.slide-bg'), { clipPath: 'inset(0 0% 0 0)', duration: 0.9, ease: 'power2.out' }, 0)
      .to($first.find('.content'), { x: 0, autoAlpha: 1, duration: 0.8 }, 0.15);
  }
  updateUI();
  startAutoplay();
});
