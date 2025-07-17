jQuery(document).ready(function ($) {
  let compareList = JSON.parse(localStorage.getItem("compareList")) || [];

  function saveCompareList() {
    localStorage.setItem("compareList", JSON.stringify(compareList));
    updateCompareButtons();
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
    localStorage.removeItem("wcp_open_modal");
  }

  $(document).on("click", ".wcp-compare-button", function () {
    const productId = $(this).data("product-id").toString();

    if (compareList.includes(productId)) {
      localStorage.setItem("wcp_open_modal", "yes");
      updateModal();
      return;
    }

    // if (compareList.length >= 4) {
    //   alert("You can compare up to 4 products.");
    //   return;
    // }

    compareList.push(productId);
    saveCompareList();
    localStorage.setItem("wcp_open_modal", "yes");
    updateModal();
  });

  $(document).on("click", ".wcp-close-compare", closeModal);

  $(document).on("click", ".wcp-remove-item", function () {
    const idToRemove = $(this).data("remove-id").toString();
    compareList = compareList.filter((id) => id !== idToRemove);
    saveCompareList();
    updateModal();
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
  if (localStorage.getItem("wcp_open_modal") === "yes") {
    updateModal();
    localStorage.removeItem("wcp_open_modal");
  }
});
