(function($){
  function nextIndex($table){
    var max = -1;
    $table.find('tbody tr').each(function(){
      var $row = $(this);
      var name = $row.find('input').first().attr('name') || '';
      var m = name.match(/feeds_list\]\[(\d+)\]\[label\]/);
      if(m){
        var idx = parseInt(m[1], 10);
        if(!isNaN(idx) && idx > max) max = idx;
      }
    });
    return max + 1;
  }

  $(document).on('click', '#uca-ics-row-add', function(e){
    e.preventDefault();
    var $table = $('#uca-ics-feeds-table');
    var idx = nextIndex($table);
    var tmpl = $('#tmpl-uca-ics-row').html();
    if(!tmpl) return;
    tmpl = tmpl.replace(/__index__/g, String(idx));
    $table.find('tbody').append(tmpl);
  });

  $(document).on('click', '.uca-ics-row-remove', function(e){
    e.preventDefault();
    var $row = $(this).closest('tr');
    var $tbody = $row.closest('tbody');
    $row.remove();
    // Ensure at least one empty row exists
    if($tbody.find('tr').length === 0){
      $('#uca-ics-row-add').trigger('click');
    }
  });

  // Styling live preview
  function updatePreviewFromInputs(){
    var $preview = $('#uca-ics-style-preview');
    if(!$preview.length) return;
    var form = document.getElementById('uca-ics-style-form');
    if(!form) return;
    var data = new FormData(form);
    var vars = {};
    function setVar(name, val){ if(val) vars[name] = val; }
    setVar('--uca-ics-link', data.get('uca_ics_settings[style_accent_color]'));
    setVar('--uca-ics-badge-bg', data.get('uca_ics_settings[style_badge_bg]'));
    setVar('--uca-ics-badge-border', data.get('uca_ics_settings[style_badge_border]'));
    setVar('--uca-ics-border', data.get('uca_ics_settings[style_border_color]'));
    setVar('--uca-ics-bg', data.get('uca_ics_settings[style_bg_color]'));
    setVar('--uca-ics-item-border', data.get('uca_ics_settings[style_item_border_color]'));
    setVar('--uca-ics-title', data.get('uca_ics_settings[style_title_color]'));
    setVar('--uca-ics-item-bg', data.get('uca_ics_settings[style_item_bg]'));
    setVar('--uca-ics-when-color', data.get('uca_ics_settings[style_when_color]'));
    setVar('--uca-ics-when-weight', data.get('uca_ics_settings[style_when_weight]'));
    setVar('--uca-ics-when-size', data.get('uca_ics_settings[style_when_size]'));
    setVar('--uca-ics-title-weight', data.get('uca_ics_settings[style_title_weight]'));
    setVar('--uca-ics-title-size', data.get('uca_ics_settings[style_title_size]'));
    setVar('--uca-ics-badge-color', data.get('uca_ics_settings[style_badge_color]'));
    setVar('--uca-ics-badge-size', data.get('uca_ics_settings[style_badge_size]'));
    setVar('--uca-ics-desc', data.get('uca_ics_settings[style_desc_color]'));
    setVar('--uca-ics-desc-size', data.get('uca_ics_settings[style_desc_size]'));
    setVar('--uca-ics-location', data.get('uca_ics_settings[style_location_color]'));
    setVar('--uca-ics-location-size', data.get('uca_ics_settings[style_location_size]'));
    setVar('--uca-ics-link-weight', data.get('uca_ics_settings[style_link_weight]'));
    setVar('--uca-ics-link-decoration', data.get('uca_ics_settings[style_link_decoration]'));
    setVar('--uca-ics-card-padding', data.get('uca_ics_settings[style_card_padding]'));
    setVar('--uca-ics-card-margin', data.get('uca_ics_settings[style_card_margin]'));
    setVar('--uca-ics-list-gap', data.get('uca_ics_settings[style_list_gap]'));
    setVar('--uca-ics-item-padding', data.get('uca_ics_settings[style_item_padding]'));
    setVar('--uca-ics-item-margin', data.get('uca_ics_settings[style_item_margin]'));
    setVar('--uca-ics-when-margin', data.get('uca_ics_settings[style_when_margin]'));
    setVar('--uca-ics-when-padding', data.get('uca_ics_settings[style_when_padding]'));
    setVar('--uca-ics-title-margin', data.get('uca_ics_settings[style_title_margin]'));
    setVar('--uca-ics-title-padding', data.get('uca_ics_settings[style_title_padding]'));
    setVar('--uca-ics-badge-padding', data.get('uca_ics_settings[style_badge_padding]'));
    setVar('--uca-ics-badge-margin', data.get('uca_ics_settings[style_badge_margin]'));
    setVar('--uca-ics-desc-margin', data.get('uca_ics_settings[style_desc_margin]'));
    setVar('--uca-ics-desc-padding', data.get('uca_ics_settings[style_desc_padding]'));
    setVar('--uca-ics-location-margin', data.get('uca_ics_settings[style_location_margin]'));
    setVar('--uca-ics-location-padding', data.get('uca_ics_settings[style_location_padding]'));

    var view = data.get('uca_ics_settings[style_view]') || 'list';
    // Columns per breakpoint
    var cDesktop = parseInt(data.get('uca_ics_settings[style_cols_desktop]') || '1', 10);
    var cTablet  = parseInt(data.get('uca_ics_settings[style_cols_tablet]') || String(cDesktop), 10);
    var cMobile  = parseInt(data.get('uca_ics_settings[style_cols_mobile]') || '1', 10);
    if (isNaN(cDesktop) || cDesktop < 1) cDesktop = 1;
    if (isNaN(cTablet)  || cTablet  < 1) cTablet  = 1;
    if (isNaN(cMobile)  || cMobile  < 1) cMobile  = 1;
    vars['--uca-ics-cols'] = (view === 'grid') ? String(cDesktop) : '1';
    vars['--uca-ics-cols-tablet'] = (view === 'grid') ? String(cTablet) : '1';
    vars['--uca-ics-cols-mobile'] = (view === 'grid') ? String(cMobile) : '1';

    // Scale factors
    var sTab = parseFloat(data.get('uca_ics_settings[style_ar_tablet]') || '1');
    var sMob = parseFloat(data.get('uca_ics_settings[style_ar_mobile]') || '1');
    if (isNaN(sTab)) sTab = 1; if (isNaN(sMob)) sMob = 1;
    vars['--uca-ics-scale-tablet'] = String(sTab);
    vars['--uca-ics-scale-mobile'] = String(sMob);

    var styleStr = Object.keys(vars).map(function(k){ return k + ':' + vars[k] + ';'; }).join('');
    $preview.attr('style', styleStr);

    var compact = data.get('uca_ics_settings[style_compact]') === '1';
    $preview.toggleClass('uca-ics--compact', !!compact);
    $preview.toggleClass('uca-ics--grid', view === 'grid');
  }

  $(document).on('input change', '#uca-ics-style-form input, #uca-ics-style-form textarea, #uca-ics-style-form select', updatePreviewFromInputs);
  $(updatePreviewFromInputs);

  // Enable/disable Grid columns fields based on View selection
  function updateGridControls(){
    var $form = $('#uca-ics-style-form');
    var $view = $form.find('select[name="uca_ics_settings[style_view]"]');
    var $colsD = $form.find('input[name="uca_ics_settings[style_cols_desktop]"]');
    var $colsT = $form.find('input[name="uca_ics_settings[style_cols_tablet]"]');
    var $colsM = $form.find('input[name="uca_ics_settings[style_cols_mobile]"]');
    var $arT = $form.find('input[name="uca_ics_settings[style_ar_tablet]"]');
    var $arM = $form.find('input[name="uca_ics_settings[style_ar_mobile]"]');
    if(!$view.length) return;
    var v = $view.val() || 'list';
    var isGrid = v === 'grid';
    $colsD.prop('disabled', !isGrid);
    $colsT.prop('disabled', !isGrid);
    $colsM.prop('disabled', !isGrid);
    $arT.prop('disabled', false);
    $arM.prop('disabled', false);
  }

  $(document).on('change', '#uca-ics-style-form select[name="uca_ics_settings[style_view]"]', function(){
    updateGridControls();
    updatePreviewFromInputs();
  });
  $(updateGridControls);

  // Enhance color pickers with WP Color Picker and palettes
  function hexToRgb(hex){
    hex = String(hex || '').trim();
    var m = hex.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if(!m) return null;
    var h = m[1];
    if(h.length === 3){ h = h.split('').map(function(c){ return c + c; }).join(''); }
    var num = parseInt(h, 16);
    return { r: (num >> 16) & 255, g: (num >> 8) & 255, b: num & 255 };
  }
  function parseColor(val){
    val = String(val || '').trim();
    var m = val.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(0|0?\.\d+|1(?:\.0+)?))?\s*\)$/i);
    if(m){
      return { r: parseInt(m[1],10), g: parseInt(m[2],10), b: parseInt(m[3],10), a: m[4] != null ? parseFloat(m[4]) : 1 };
    }
    var rgb = hexToRgb(val);
    if(rgb) return { r: rgb.r, g: rgb.g, b: rgb.b, a: 1 };
    return null;
  }

  function initColorPickers(){
    if (!$.fn.wpColorPicker) return;
    var palette = ['#111827','#374151','#6B7280','#9CA3AF','#D1D5DB','#2563EB','#059669','#DC2626','#D97706','#7C3AED','#EC4899','#10B981','#F59E0B','#0EA5E9'];
    $('.uca-ics-color').each(function(){
      var $input = $(this);
      if ($input.data('wpColorPicker')) return; // already initialized
      // Normalize rgba() to hex, since transparency is no longer supported
      (function(){
        var val = String($input.val() || '').trim();
        var m = val.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i);
        if(m){
          var r = Math.max(0, Math.min(255, parseInt(m[1],10)||0));
          var g = Math.max(0, Math.min(255, parseInt(m[2],10)||0));
          var b = Math.max(0, Math.min(255, parseInt(m[3],10)||0));
          var hx = '#' + [r,g,b].map(function(x){ var s=x.toString(16); return s.length===1?'0'+s:s; }).join('');
          $input.val(hx);
        }
      })();
      $input.wpColorPicker({
        palettes: palette,
        change: function(event, ui){
          var hex = ui.color.toString();
          $input.val(hex);
          updatePreviewFromInputs();
        },
        clear: function(){
          updatePreviewFromInputs();
        }
      });
      // No alpha control
    });
  }

  // Ensure picker opens on input focus/click and closes on outside click
  $(document).on('focus click', '.uca-ics-color', function(){
    var $holder = $(this).closest('.wp-picker-container');
    $holder.addClass('wp-picker-active');
    $holder.find('.wp-picker-holder').show();
  });
  $(document).on('mousedown', function(e){
    var $t = $(e.target);
    if ($t.closest('.wp-picker-container').length === 0) {
      $('.wp-picker-container').removeClass('wp-picker-active');
      $('.wp-picker-holder').hide();
    }
  });
  $(initColorPickers);
  $(document).on('focus', '.uca-ics-color', initColorPickers);

  // Elements drag and drop ordering
  function initElementsSortable(){
    var $list = $('#uca-ics-elements-list');
    if(!$list.length || $list.data('sortable-init')) return;
    $list.sortable({
      handle: '.uca-ics-el-handle',
      update: function(){
        var keys = [];
        $list.find('.uca-ics-el').each(function(){ keys.push($(this).data('key')); });
        $('#uca-ics-elements-order').val(keys.join(','));
      }
    });
    $list.data('sortable-init', true);
  }
  $(initElementsSortable);

  // Only one accordion open at a time (use summary click for reliable behavior)
  $(document).on('click', '#uca-ics-style-form details > summary', function(){
    var details = this.parentNode;
    var willOpen = !details.open; // state before the browser toggles it
    if (willOpen) {
      $('#uca-ics-style-form details').not(details).prop('open', false);
    }
    // let the default toggle proceed
  });
})(jQuery);
