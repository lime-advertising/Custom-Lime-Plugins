jQuery(document).ready(function ($) {
  let compareList = JSON.parse(localStorage.getItem("compareList")) || [];

  sessionStorage.removeItem("wcp_open_modal");

  function saveCompareList() {
    localStorage.setItem("compareList", JSON.stringify(compareList));
    updateCompareButtons();
    updateCompareShortcodeIcon();
  }

  function updateCompareButtons() {
    $(".wcp-compare-button").each(function () {
      const id = $(this).data("product-id").toString();
      if (compareList.includes(id)) {
        $(this).text("View Comparison");
      } else {
        $(this).text("Compare");
      }
    });
  }

  function updateCompareShortcodeIcon() {
    const el = document.querySelector(".wcp-shortcode-icon");
    if (!el) return;

    if (compareList.length > 0) {
      el.style.display = "flex";
      const countEl = el.querySelector(".wcp-count");
      if (countEl) {
        countEl.textContent = `${compareList.length}`;
      }

      el.onclick = function () {
        updateModal();
      };
    } else {
      el.style.display = "none";
    }
  }

  function updateModal() {
    if (compareList.length === 0) {
      closeModal();
      return;
    }

    $.ajax({
      url: wcp_ajax.ajax_url,
      method: "POST",
      data: {
        action: "get_compare_data",
        product_ids: compareList,
      },
      success: function (response) {
        if (response.success) {
          $("#wcp-compare-table-container").html(response.data);
          $("#wcp-compare-modal").show();

          // JS Event Tracking for Compare Actions
          window.dataLayer.push({
            event: "compare_modal_open",
            product_ids: compareList,
          });


          // Delay scroll detection until DOM is fully updated
          setTimeout(function () {
            const scrollContainer = document.querySelector(".wcp-table-scroll");
            const swipeHint = document.querySelector(".wcp-swipe-hint");

            if (scrollContainer && swipeHint) {
              const hasHorizontalScroll =
                scrollContainer.scrollWidth > scrollContainer.clientWidth;
              swipeHint.style.display = hasHorizontalScroll ? "block" : "none";
            }
          }, 50);
        } else {
          alert("Error loading compare data.");
        }
      },
    });
  }

  function closeModal() {
    $("#wcp-compare-modal").hide();
    sessionStorage.removeItem("wcp_open_modal");
  }

  $(document).on("click", ".wcp-compare-button", function () {
    const productId = $(this).data("product-id").toString();

    if (compareList.includes(productId)) {
      sessionStorage.setItem("wcp_open_modal", "yes");
      updateModal();
      return;
    }

    compareList.push(productId);
    saveCompareList();
    sessionStorage.setItem("wcp_open_modal", "yes");
    updateModal();

    // JS Event Tracking for Compare Actions
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: "compare_click",
      product_id: productId,
      compare_count: compareList.length + 1,
    });
  });

  $(document).on("click", ".wcp-close-compare", closeModal);

  $(document).on("click", ".wcp-remove-item", function () {
    const idToRemove = $(this).data("remove-id").toString();
    compareList = compareList.filter((id) => id !== idToRemove);

    saveCompareList();
    updateModal();

    // JS Event Tracking for Compare Actions
    window.dataLayer.push({
      event: "compare_remove",
      product_id: idToRemove,
      compare_count: compareList.length,
    });
  });

  $(document).on("click", "#wcp-clear-all", function () {
    compareList = [];
    saveCompareList();
    $("#wcp-compare-modal").hide();
    closeModal();
  });

  // Close modal on Escape key press
  $(document).on("keydown", function (e) {
    if (e.key === "Escape") {
      closeModal();
    }
  });

  // Close modal on overlay click
  $(document).on("click", ".wcp-overlay", function () {
    closeModal();
  });

  // Initialize buttons and modal on load
  updateCompareButtons();
  updateCompareShortcodeIcon();
  if (sessionStorage.getItem("wcp_open_modal") === "yes") {
    updateModal();
  }
});
