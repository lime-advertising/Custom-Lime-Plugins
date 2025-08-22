/* global jQuery */
(function ($) {
  $(function () {
    // Toggle external application field
    var $toggle = $('#careernest_apply_externally');
    var $container = $('#careernest_external_container');
    if ($toggle.length && $container.length) {
      $toggle.on('change', function () {
        if (this.checked) {
          $container.slideDown(120);
        } else {
          $container.slideUp(120);
        }
      });
    }
  });
})(jQuery);

