jQuery(document).ready(function ($) {
  const $wrapper = $(".lime-wp-search-wrapper");
  if (!$wrapper.length) return; // Shortcode not on this page

  $(document).on("submit", ".lime-wp-search-form", function (e) {
    var q = $("#lime-wp-search-input").val().trim();
    if (!q) {
      e.preventDefault();
    }
  });

  const $input = $("#lime-wp-search-input");
  const $results = $("#lime-wp-search-results");
  let timeout;

  // ----- SETTINGS (declare BEFORE use) -----
  const clickToShow = !!(
    window.lws_ajax && Number(lws_ajax.click_to_show) === 1
  );
  const triggerSelector =
    window.lws_ajax && lws_ajax.trigger_selector
      ? String(lws_ajax.trigger_selector).trim()
      : "";

  // ====== MODAL MODE ONLY IF SETTINGS SAY SO ======
  const useModal = clickToShow && triggerSelector;
  let $overlay, $dialog, $closeBtn;
  let lastFocusedEl = null;

  function buildModal() {
    if ($overlay) return;

    $overlay = $(
      '<div class="lws-modal" aria-hidden="true" tabindex="-1"></div>'
    );
    $dialog = $(
      '<div class="lws-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="lws-modal-title"></div>'
    );
    $closeBtn = $(
      '<button type="button" class="lws-modal__close" aria-label="Close search">&times;</button>'
    );

    const $title = $(
      '<h2 id="lws-modal-title" style="position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden;">Site Search</h2>'
    );

    $dialog.append($title, $closeBtn);
    // Move the existing wrapper into the modal dialog so markup is reused
    $wrapper.appendTo($dialog).show();
    $overlay.append($dialog);
    $("body").append($overlay);

    $closeBtn.on("click", closeModal);

    $overlay.on("mousedown", function (e) {
      if ($(e.target).is($overlay)) closeModal();
    });

    $(document).on("keydown.lws-modal", function (e) {
      if (e.key === "Escape" && isOpen()) {
        e.preventDefault();
        closeModal();
      }
    });

    // Simple focus trap
    $(document).on("keydown.lws-trap", function (e) {
      if (!isOpen() || e.key !== "Tab") return;
      const focusable = $dialog
        .find(
          'a, button, input, textarea, select, details, [tabindex]:not([tabindex="-1"])'
        )
        .filter(":visible");
      if (!focusable.length) return;
      const first = focusable[0],
        last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });
  }

  function isOpen() {
    return $overlay && $overlay.attr("aria-hidden") === "false";
  }

  function openModal() {
    if (!$overlay) buildModal();
    lastFocusedEl = document.activeElement;
    $overlay.css("display", "flex").attr("aria-hidden", "false");
    $("body").addClass("lws-no-scroll");
    $results.empty().hide();
    setTimeout(() => $input.trigger("focus"), 0);
  }

  function closeModal() {
    if (!$overlay) return;
    $overlay.attr("aria-hidden", "true").hide();
    $("body").removeClass("lws-no-scroll");
    $results.hide();
    if (lastFocusedEl && typeof lastFocusedEl.focus === "function")
      lastFocusedEl.focus();
  }

  // Toggle behaviour
  if (useModal) {
    // Hide the inline wrapper; open via trigger
    $wrapper.hide();
    $(document).on("click", triggerSelector, function (e) {
      e.preventDefault();
      openModal();
    });
  } else {
    // Inline (no modal) — ensure it’s visible
    $wrapper.show();
  }

  // ====== LIVE SEARCH (unchanged) ======
  $input.on("input", function () {
    clearTimeout(timeout);
    const query = $(this).val().trim();

    if (query.length < 2) {
      $results.hide().empty();
      return;
    }

    timeout = setTimeout(function () {
      $.ajax({
        url: window.lws_ajax && lws_ajax.ajax_url ? lws_ajax.ajax_url : "",
        type: "POST",
        data: {
          action: "lime_wp_live_search",
          keyword: query,
          _ajax_nonce: lws_ajax.nonce,
        },
        success: function (response) {
          $results.html(response).fadeIn();
        },
        error: function () {
          $results.html("<p>Error fetching results</p>").fadeIn();
        },
      });
    }, 300);
  });

  // Hide results when clicking outside (and not inside modal dialog)
  $(document).on("click", function (e) {
    const inWrapper = $(e.target).closest(".lime-wp-search-wrapper").length > 0;
    const inDialog = $(e.target).closest(".lws-modal__dialog").length > 0;
    if (!inWrapper && !inDialog) $results.fadeOut(100);
  });
});
