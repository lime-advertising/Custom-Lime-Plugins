/*!
 * MM Master Slider (consumer) – v1.0.3
 * - Single GSAP timeline per transition (fixes reverse animations)
 * - True initial autoplay delay (no first-jump)
 * - url2 effect variable fix
 * - Safer Splitting/TouchSwipe guards
 * - FIX: Don't pre-hide text when eff === "none"
 */
(function ($, window, document, undefined) {
  "use strict";

  var MasterSlider = function (elm, opts) {
    this.elm = elm;
    this.$elm = $(elm);
    this.opts = opts || {};
    this.config = this.$elm.data("config") || {};
  };

  MasterSlider.prototype = {
    defaults: {
      autoplay: "no",
      autoplaySpeed: 9000, // ms
      kenburns: "no",
      kenburnsZoom: 1.1,
      kenburnsDuration: 5000, // ms

      subEffIn: { eff: "none", prop: {} },
      subEffOut: { eff: "none", prop: {} },
      titleEffIn: { eff: "none", prop: {} },
      titleEffOut: { eff: "none", prop: {} },
      descEffIn: { eff: "none", prop: {} },
      descEffOut: { eff: "none", prop: {} },
      url1EffIn: { eff: "none", prop: {} },
      url1EffOut: { eff: "none", prop: {} },
      url2EffIn: { eff: "none", prop: {} },
      url2EffOut: { eff: "none", prop: {} },
      wrapEffIn: { eff: "none", prop: {} },
      wrapEffOut: { eff: "none", prop: {} },
      bgEffIn: { eff: "none", prop: {} },
      bgEffOut: { eff: "none", prop: {} },
    },

    init: function () {
      this.args = $.extend(true, {}, this.defaults, this.opts, this.config);
      this.args.autoplaySpeed = parseInt(this.args.autoplaySpeed, 10) || 9000;
      this.build();
      this.bind();
      return this;
    },

    build: function () {
      var t = this;

      // Shorthand (guards)
      var a = (t.args.subEffIn || {}).eff || "none",
        b = (t.args.subEffOut || {}).eff || "none",
        c = (t.args.titleEffIn || {}).eff || "none",
        d = (t.args.titleEffOut || {}).eff || "none",
        e = (t.args.descEffIn || {}).eff || "none",
        f = (t.args.descEffOut || {}).eff || "none",
        g = (t.args.url1EffIn || {}).eff || "none",
        h = (t.args.url1EffOut || {}).eff || "none",
        i = (t.args.url2EffIn || {}).eff || "none", // FIXED
        j = (t.args.url2EffOut || {}).eff || "none", // FIXED
        k = (t.args.wrapEffIn || {}).eff || "none",
        m = (t.args.bgEffIn || {}).eff || "none";

      var slides = t.$elm.find(".content-wrap .slide");
      var images = t.$elm.find(".bg-wrap .bg");
      var dotsWrap = t.$elm.find(".nav-dots");

      // Dots
      slides.each(function () {
        $('<span class="dot"></span>').appendTo(dotsWrap);
      });

      // Ensure one active
      var existingActive = slides.filter(".active").first();
      if (!existingActive.length) {
        images.eq(0).addClass("active");
        slides.eq(0).addClass("active").css("z-index", 2);
        dotsWrap.find(".dot").eq(0).addClass("active");
      } else {
        var idx = existingActive.index();
        slides.eq(idx).css("z-index", 2);
        dotsWrap.find(".dot").eq(idx).addClass("active");
      }

      // Decorators
      if (k === "zoomOut") t.$elm.find(".slide").addClass("cb-zoom");
      if (m === "zoomOut") t.$elm.find(".bg-wrap").addClass("cb-zoom");
      if (m === "vslide") t.$elm.find(".bg-wrap").addClass("cb-vslide2");

      // Effect tables
      t.params = {
        images: {
          none: { out: {}, set: { next: {}, prev: {} }, in: {} },
          fade: {
            out: {
              next: { opacity: 0, duration: 1 },
              prev: { opacity: 0, duration: 1 },
            },
            set: { next: { opacity: 0 }, prev: { opacity: 0 } },
            in: { opacity: 1, duration: 1 },
          },
          fadeScale: {
            out: {
              next: { opacity: 0, duration: 0.3, delay: 0.5 },
              prev: { opacity: 0, duration: 0.3, delay: 0.5 },
            },
            set: {
              next: { scale: 0.9, opacity: 0 },
              prev: { scale: 0.9, opacity: 0 },
            },
            in: { opacity: 1, scale: 1, duration: 0.6 },
          },
          reveal: {
            out: { next: {}, prev: {} },
            set: {
              next: {
                clipPath: "polygon(0% 0%, 0% 0%, 0% 100%, 0% 100%)",
                scale: 1.3,
              },
              prev: {
                clipPath: "polygon(100% 0%, 100% 0%, 100% 100%, 100% 100%)",
                scale: 1.3,
              },
            },
            in: {
              clipPath: "polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%)",
              scale: 1,
              ease: "power2.out",
              duration: 1.2,
            },
          },
          reveal2: {
            out: { next: {}, prev: {} },
            set: {
              prev: {
                clipPath: "polygon(0% 0%, 0% 0%, 0% 100%, 0% 100%)",
                scale: 1.3,
              },
              next: {
                clipPath: "polygon(100% 0%, 100% 0%, 100% 100%, 100% 100%)",
                scale: 1.3,
              },
            },
            in: {
              clipPath: "polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%)",
              scale: 1,
              ease: "power2.out",
              duration: 1.2,
            },
          },
          slide: {
            out: {
              next: { x: "-100%", duration: 0.5, ease: "power2.inOut" },
              prev: { x: "100%", duration: 0.5, ease: "power2.inOut" },
            },
            set: { next: { x: "100%" }, prev: { x: "-100%" } },
            in: { x: 0, duration: 0.5, ease: "power2.inOut" },
          },
          vslide: {
            out: {
              next: { y: "-100%", duration: 1.2 },
              prev: { y: "100%", duration: 1.2 },
            },
            set: { next: { y: "100%" }, prev: { y: "-100%" } },
            in: { y: 0, duration: 1.2 },
          },
          fadeRight: {
            out: {
              next: { x: 50, opacity: 0, duration: 0.3 },
              prev: { x: -50, opacity: 0, duration: 0.3 },
            },
            set: { next: { x: -50, opacity: 0 }, prev: { x: 50, opacity: 0 } },
            in: { x: 0, opacity: 1, duration: 0.3, delay: 0.5 },
          },
          fadeLeft: {
            out: {
              next: { x: -50, opacity: 0, duration: 0.3 },
              prev: { x: 50, opacity: 0, duration: 0.3 },
            },
            set: { next: { x: -50, opacity: 0 }, prev: { x: 50, opacity: 0 } },
            in: { x: 0, opacity: 1, duration: 0.3, delay: 0.5 },
          },
          slideScale: {
            out: {
              next: { x: "-100%", duration: 0.3, delay: 0.3 },
              prev: { x: "100%", duration: 0.3, delay: 0.3 },
            },
            set: {
              next: { x: "100%", scale: 2 },
              prev: { x: "-100%", scale: 2 },
            },
            in: { x: 0, scale: 1, duration: 0.6 },
          },
          zoomOut: {
            out: {
              next: { y: -50, scale: 1.2, opacity: 0, duration: 1.5 },
              prev: { y: -50, scale: 1.2, opacity: 0, duration: 1.5 },
            },
            set: {
              next: { y: 50, scale: 0.95, opacity: 0, zIndex: 2 },
              prev: { y: 50, scale: 0.95, opacity: 0, zIndex: 2 },
            },
            in: { y: 0, scale: 1, opacity: 1, duration: 1 },
          },
        },
        text: {
          none: { out: {}, set: { next: {}, prev: {} }, in: {} },
          slide: {
            out: {
              next: { x: "-80vw", opacity: 0, duration: 0.3 },
              prev: { x: "80vw", opacity: 0, duration: 0.3 },
            },
            set: { next: { x: "80vw" }, prev: { x: "-80vw" } },
            in: { x: 0, opacity: 1, duration: 0.3, delay: 0.3 },
          },
          fade: {
            out: {
              next: { opacity: 0, duration: 0.3 },
              prev: { opacity: 0, duration: 0.3 },
            },
            set: { next: {}, prev: {} },
            in: { x: 0, opacity: 1, duration: 0.3, delay: 0.3 },
          },
          fadeUp: {
            out: {
              next: { y: -20, opacity: 0, duration: 0.3 },
              prev: { y: 20, opacity: 0, duration: 0.3 },
            },
            set: { next: { y: 20, opacity: 0 }, prev: { y: -20, opacity: 0 } },
            in: { y: 0, opacity: 1, duration: 0.3, delay: 0.5 },
          },
          fadeDown: {
            out: {
              next: { y: 20, opacity: 0, duration: 0.3 },
              prev: { y: -20, opacity: 0, duration: 0.3 },
            },
            set: { next: { y: -20, opacity: 0 }, prev: { y: 20, opacity: 0 } },
            in: { y: 0, opacity: 1, duration: 0.3, delay: 0.5 },
          },
          textSlide: {
            out: {
              next: { y: "-120%", duration: 0.3 },
              prev: { y: "120%", duration: 0.3 },
            },
            set: { next: { y: "120%" }, prev: { y: "-120%" } },
            in: { y: 0, duration: 0.3, delay: 0.5 },
          },
          fadeRight: {
            out: {
              next: { opacity: 0, x: 100, duration: 0.3 },
              prev: { opacity: 0, x: 100, duration: 0.3 },
            },
            set: {
              next: { opacity: 0, x: -100 },
              prev: { opacity: 0, x: -100 },
            },
            in: { opacity: 1, x: 0, duration: 0.3 },
          },
          zoomOut: {
            out: {
              next: { y: -50, scale: 1.2, opacity: 0, duration: 1.5 },
              prev: { y: -50, scale: 1.2, opacity: 0, duration: 1.5 },
            },
            set: {
              next: { y: 50, scale: 0.9, opacity: 0 },
              prev: { y: 50, scale: 0.9, opacity: 0 },
            },
            in: { y: 0, scale: 1, opacity: 1, duration: 1.5, delay: 1.5 },
          },
        },
        url: {
          none: { out: {}, set: { next: {}, prev: {} }, in: {} },
          slide: {
            out: {
              next: { x: "-80vw", opacity: 0, duration: 0.3 },
              prev: { x: "80vw", opacity: 0, duration: 0.3 },
            },
            set: { next: { x: "80vw" }, prev: { x: "-80vw" } },
            in: { x: 0, opacity: 1, duration: 0.3, delay: 0.3 },
          },
          fade: {
            out: {
              next: { opacity: 0, duration: 0.3 },
              prev: { opacity: 0, duration: 0.3 },
            },
            set: { next: {}, prev: {} },
            in: { x: 0, opacity: 1, duration: 0.3, delay: 0.3 },
          },
          fadeUp: {
            out: {
              next: { y: -20, opacity: 0, duration: 0.3 },
              prev: { y: 20, opacity: 0, duration: 0.3 },
            },
            set: { next: { y: 20, opacity: 0 }, prev: { y: -20, opacity: 0 } },
            in: { y: 0, opacity: 1, duration: 0.3, delay: 0.5 },
          },
          fadeDown: {
            out: {
              next: { y: 20, opacity: 0, duration: 0.3 },
              prev: { y: -20, opacity: 0, duration: 0.3 },
            },
            set: { next: { y: -20, opacity: 0 }, prev: { y: 20, opacity: 0 } },
            in: { y: 0, opacity: 1, duration: 0.3, delay: 0.5 },
          },
          slideUp: {
            out: {
              next: { y: "-120%", duration: 0.3 },
              prev: { y: "120%", duration: 0.3 },
            },
            set: { next: { y: "120%" }, prev: { y: "-120%" } },
            in: { y: 0, duration: 0.3, delay: 0.5 },
          },
          zoomOut: {
            out: {
              next: { y: -50, scale: 1.2, opacity: 0, duration: 1.5 },
              prev: { y: -50, scale: 1.2, opacity: 0, duration: 1.5 },
            },
            set: {
              next: { y: 50, scale: 0.9, opacity: 0 },
              prev: { y: 50, scale: 0.9, opacity: 0 },
            },
            in: { y: 0, scale: 1, opacity: 1, duration: 1.5, delay: 1.5 },
          },
        },
      };

      // Splitting guards
      var canSplit = typeof window.Splitting === "function";
      var slidesAll = t.$elm.find(".content-wrap .slide");

      if (canSplit && (a === "textSlide" || b === "textSlide")) {
        window.Splitting({
          target: slidesAll.find(".sub-title").get(),
          by: "lines",
        });
        slidesAll
          .find(".sub-title")
          .find("> span")
          .wrap('<span class="text-wrap"></span>');
      }
      if (canSplit && (c === "textSlide" || d === "textSlide")) {
        window.Splitting({
          target: slidesAll.find(".title").get(),
          by: "lines",
        });
        slidesAll
          .find(".title")
          .find("> span")
          .wrap('<span class="text-wrap"></span>');
      }
      if (canSplit && (e === "textSlide" || f === "textSlide")) {
        if (!slidesAll.find(".desc").children().length) {
          window.Splitting({
            target: slidesAll.find(".desc").get(),
            by: "lines",
          });
          slidesAll
            .find(".desc")
            .find("> span")
            .wrap('<span class="text-wrap"></span>');
        } else {
          window.Splitting({
            target: slidesAll.find(".desc").children().get(),
            by: "lines",
          });
          slidesAll
            .find(".desc")
            .children()
            .find("> span, > i")
            .wrap('<span class="text-wrap"></span>');
        }
      }
      if (g === "slideUp" || h === "slideUp") {
        slidesAll
          .find(".url1")
          .find("> *")
          .wrap(
            '<span class="text-wrap"><span class="url-wrap"></span></span>'
          );
      }
      if (i === "slideUp" || j === "slideUp") {
        slidesAll
          .find(".url2")
          .find("> *")
          .wrap(
            '<span class="text-wrap"><span class="url-wrap"></span></span>'
          );
      }
    },

    bind: function () {
      var t = this,
        slides = t.$elm.find(".content-wrap .slide"),
        images = t.$elm.find(".bg-wrap .bg"),
        nextArrow = t.$elm.find(".control-wrap .arrow-next"),
        prevArrow = t.$elm.find(".control-wrap .arrow-prev"),
        dots = t.$elm.find(".control-wrap .nav-dots .dot");

      var current = slides.filter(".active").first().index();
      if (current < 0) current = 0;

      var direction = "next";
      var playing = false;
      var tl = null; // per-transition timeline

      // Kenburns
      var kenburns = t.args.kenburns === "yes";
      var kbDuration = (parseFloat(t.args.kenburnsDuration) || 5000) / 1000;
      var kbZoom = parseFloat(t.args.kenburnsZoom) || 1.1;

      if (kenburns && images[current]) {
        gsap.to(images[current], {
          duration: kbDuration,
          scale: kbZoom,
          ease: "power1.out",
          delay: 0.3,
        });
      }

      // Handlers
      nextArrow.on("click", function () {
        navigate("next");
      });
      prevArrow.on("click", function () {
        navigate("prev");
      });
      dots.each(function (idx, el) {
        $(el).on("click", function () {
          navigateTo(idx);
        });
      });

      // Swipe
      if ($.fn.swipe) {
        t.$elm.swipe({
          swipeLeft: function () {
            navigate("next");
          },
          swipeRight: function () {
            navigate("prev");
          },
        });
      }

      // Autoplay (true initial delay)
      var autoplay = t.args.autoplay === "yes";
      var autoplaySpeed = t.args.autoplaySpeed;
      var autoplayTimer = null;
      function startAutoplay() {
        if (!autoplay) return;
        stopAutoplay();
        autoplayTimer = setTimeout(function tick() {
          navigate("next");
          autoplayTimer = setInterval(function () {
            navigate("next");
          }, autoplaySpeed);
        }, autoplaySpeed);
      }
      function stopAutoplay() {
        if (autoplayTimer) {
          clearTimeout(autoplayTimer);
          clearInterval(autoplayTimer);
          autoplayTimer = null;
        }
      }
      t.$elm.on("mouseenter", stopAutoplay).on("mouseleave", startAutoplay);
      startAutoplay();

      /* ---------------- core navigation with timeline ---------------- */
      function navigate(way) {
        if (playing) return;
        playing = true;
        direction = way === "prev" ? "prev" : "next";
        var newIndex =
          direction === "next"
            ? current === slides.length - 1
              ? 0
              : current + 1
            : current === 0
            ? slides.length - 1
            : current - 1;
        transitionTo(newIndex);
      }
      function navigateTo(index) {
        if (playing || index === current) return;
        playing = true;
        direction = index > current ? "next" : "prev";
        transitionTo(index);
      }

      function transitionTo(newIndex) {
        if (tl) {
          tl.kill();
          tl = null;
        }
        gsap.killTweensOf([slides.get(), images.get()]);

        // --- PREP ---
        if (kenburns) {
          gsap.set(images[newIndex], { scale: 1 });
          gsap.killTweensOf(images[current]);
        }

        dots.eq(current).removeClass("active");
        dots.eq(newIndex).addClass("active");

        slides.eq(newIndex).addClass("animating");
        gsap.set(images[current], { zIndex: 1, visibility: "visible" });
        gsap.set(images[newIndex], { zIndex: 2, visibility: "visible" });
        gsap.set(slides[current], { zIndex: 1, visibility: "visible" });
        gsap.set(slides[newIndex], {
          zIndex: 2,
          visibility: "visible",
          opacity: 1,
        });

        // Prep text opacity:
        // Only pre-hide when the incoming effect is NOT "none".
        var ns = slides.eq(newIndex);
        var prep = function (sel, effKey) {
          var eff = ((t.args[effKey] || {}).eff || "none").toLowerCase();
          if (!ns.find(sel).length) return;
          var $target = ns.find(sel);
          if ($target.find(".text-wrap").length) {
            gsap.set($target, { opacity: 1 });
          } else {
            gsap.set($target, { opacity: eff === "none" ? 1 : 0 });
          }
        };
        prep(".sub-title", "subEffIn");
        prep(".title", "titleEffIn");
        prep(".desc", "descEffIn");
        prep(".url1", "url1EffIn");
        prep(".url2", "url2EffIn");

        // --- TIMELINE ---
        tl = gsap.timeline({
          onComplete: function () {
            slides.eq(current).removeClass("active");
            images.eq(current).removeClass("active");
            slides.eq(newIndex).removeClass("animating").addClass("active");
            images.eq(newIndex).addClass("active");

            gsap.set(slides[current], {
              visibility: "hidden",
              zIndex: 0,
              clearProps: "transform,opacity,clipPath",
            });
            gsap.set(images[current], {
              visibility: "hidden",
              zIndex: 0,
              clearProps: "transform,opacity,clipPath",
            });

            current = newIndex;
            playing = false;

            if (kenburns) {
              gsap.to(images[newIndex], {
                duration: kbDuration,
                scale: kbZoom,
                ease: "power1.out",
              });
            }
          },
        });

        // lookup helpers
        var P = {
          imgOut: function (key) {
            var eff = (t.args[key] || {}).eff || "none";
            var table = t.params.images[eff] || t.params.images.none;
            return $.extend({}, table.out[direction], (t.args[key] || {}).prop);
          },
          imgSet: function (key) {
            var eff = (t.args[key] || {}).eff || "none";
            var table = t.params.images[eff] || t.params.images.none;
            return table.set[direction] || {};
          },
          imgIn: function (key) {
            var eff = (t.args[key] || {}).eff || "none";
            var table = t.params.images[eff] || t.params.images.none;
            return $.extend({}, table.in, (t.args[key] || {}).prop);
          },
          txtOut: function (key) {
            var eff = (t.args[key] || {}).eff || "none";
            var table = t.params.text[eff] || t.params.text.none;
            return $.extend({}, table.out[direction], (t.args[key] || {}).prop);
          },
          txtSet: function (key) {
            var eff = (t.args[key] || {}).eff || "none";
            var table = t.params.text[eff] || t.params.text.none;
            return table.set && table.set[direction]
              ? table.set[direction]
              : {};
          },
          txtIn: function (key) {
            var eff = (t.args[key] || {}).eff || "none";
            var table = t.params.text[eff] || t.params.text.none;
            return $.extend({}, table.in, (t.args[key] || {}).prop);
          },
          urlOut: function (key) {
            var eff = (t.args[key] || {}).eff || "none";
            var table = t.params.url[eff] || t.params.url.none;
            return $.extend({}, table.out[direction], (t.args[key] || {}).prop);
          },
          urlSet: function (key) {
            var eff = (t.args[key] || {}).eff || "none";
            var table = t.params.url[eff] || t.params.url.none;
            return table.set && table.set[direction]
              ? table.set[direction]
              : {};
          },
          urlIn: function (key) {
            var eff = (t.args[key] || {}).eff || "none";
            var table = t.params.url[eff] || t.params.url.none;
            return $.extend({}, table.in, (t.args[key] || {}).prop);
          },
        };

        // Set initial states for incoming targets
        var bgSet = P.imgSet("bgEffIn");
        if (Object.keys(bgSet).length) gsap.set(images[newIndex], bgSet);
        var wrapSet = P.imgSet("wrapEffIn");
        if (Object.keys(wrapSet).length) gsap.set(slides[newIndex], wrapSet);

        var nsSub = ns.find(".sub-title");
        if (nsSub.length) {
          var nsSubT = nsSub.find(".text-wrap").length
            ? nsSub.find(".text-wrap > *")
            : nsSub;
          var s1 = P.txtSet("subEffIn");
          if (Object.keys(s1).length) gsap.set(nsSubT, s1);
        }
        var nsTit = ns.find(".title");
        if (nsTit.length) {
          var nsTitT = nsTit.find(".text-wrap").length
            ? nsTit.find(".text-wrap > *")
            : nsTit;
          var s2 = P.txtSet("titleEffIn");
          if (Object.keys(s2).length) gsap.set(nsTitT, s2);
        }
        var nsDesc = ns.find(".desc");
        if (nsDesc.length) {
          var nsDescT = nsDesc.find(".text-wrap").length
            ? nsDesc.find(".text-wrap > *")
            : nsDesc;
          var s3 = P.txtSet("descEffIn");
          if (Object.keys(s3).length) gsap.set(nsDescT, s3);
        }
        var nsU1 = ns.find(".url1");
        if (nsU1.length) {
          var nsU1T = nsU1.find(".text-wrap").length
            ? nsU1.find(".text-wrap > *")
            : nsU1;
          var s4 = P.urlSet("url1EffIn");
          if (Object.keys(s4).length) gsap.set(nsU1T, s4);
        }
        var nsU2 = ns.find(".url2");
        if (nsU2.length) {
          var nsU2T = nsU2.find(".text-wrap").length
            ? nsU2.find(".text-wrap > *")
            : nsU2;
          var s5 = P.urlSet("url2EffIn");
          if (Object.keys(s5).length) gsap.set(nsU2T, s5);
        }

        // Timeline tweens (time 0 => symmetric both ways)
        var bgOut = P.imgOut("bgEffOut");
        if (Object.keys(bgOut).length) tl.to(images[current], bgOut, 0);
        var bgIn = P.imgIn("bgEffIn");
        if (Object.keys(bgIn).length) tl.to(images[newIndex], bgIn, 0);

        var wOut = P.imgOut("wrapEffOut");
        if (Object.keys(wOut).length) tl.to(slides[current], wOut, 0);
        var wIn = P.imgIn("wrapEffIn");
        if (Object.keys(wIn).length) tl.to(slides[newIndex], wIn, 0);

        var cur = slides.eq(current),
          tgt;
        tgt = cur.find(".sub-title");
        if (tgt.length)
          tl.to(
            tgt.find(".text-wrap").length ? tgt.find(".text-wrap > *") : tgt,
            P.txtOut("subEffOut"),
            0
          );
        tgt = ns.find(".sub-title");
        if (tgt.length)
          tl.to(
            tgt.find(".text-wrap").length ? tgt.find(".text-wrap > *") : tgt,
            P.txtIn("subEffIn"),
            0
          );

        tgt = cur.find(".title");
        if (tgt.length)
          tl.to(
            tgt.find(".text-wrap").length ? tgt.find(".text-wrap > *") : tgt,
            P.txtOut("titleEffOut"),
            0
          );
        tgt = ns.find(".title");
        if (tgt.length)
          tl.to(
            tgt.find(".text-wrap").length ? tgt.find(".text-wrap > *") : tgt,
            P.txtIn("titleEffIn"),
            0
          );

        tgt = cur.find(".desc");
        if (tgt.length)
          tl.to(
            tgt.find(".text-wrap").length ? tgt.find(".text-wrap > *") : tgt,
            P.txtOut("descEffOut"),
            0
          );
        tgt = ns.find(".desc");
        if (tgt.length)
          tl.to(
            tgt.find(".text-wrap").length ? tgt.find(".text-wrap > *") : tgt,
            P.txtIn("descEffIn"),
            0
          );

        tgt = cur.find(".url1");
        if (tgt.length)
          tl.to(
            tgt.find(".text-wrap").length ? tgt.find(".text-wrap > *") : tgt,
            P.urlOut("url1EffOut"),
            0
          );
        tgt = ns.find(".url1");
        if (tgt.length)
          tl.to(
            tgt.find(".text-wrap").length ? tgt.find(".text-wrap > *") : tgt,
            P.urlIn("url1EffIn"),
            0
          );

        tgt = cur.find(".url2");
        if (tgt.length)
          tl.to(
            tgt.find(".text-wrap").length ? tgt.find(".text-wrap > *") : tgt,
            P.urlOut("url2EffOut"),
            0
          );
        tgt = ns.find(".url2");
        if (tgt.length)
          tl.to(
            tgt.find(".text-wrap").length ? tgt.find(".text-wrap > *") : tgt,
            P.urlIn("url2EffIn"),
            0
          );
      }
    },
  };

  MasterSlider.defaults = MasterSlider.prototype.defaults;

  $.fn.masterSlider = function (opts) {
    return this.each(function () {
      new MasterSlider(this, opts).init();
    });
  };
})(jQuery, window, document);
