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

    // Applicant resume media picker
    var $resumeSelect = $('#careernest_resume_select');
    var $resumeClear = $('#careernest_resume_clear');
    var $resumeInput = $('#careernest_resume_id');
    var $resumePreview = $('#careernest_resume_preview');
    var frame;
    function updatePreview(id, url, title) {
      if (!id || !url) {
        $resumePreview.html('<em>No file selected</em>');
        $resumeInput.val('');
        return;
      }
      var name = title || url.split('/').pop();
      $resumePreview.html('<a href="' + url + '" target="_blank" rel="noreferrer noopener">' + $('<div>').text(name).html() + '</a>');
      $resumeInput.val(id);
    }
    if ($resumeSelect.length) {
      $resumeSelect.on('click', function (e) {
        e.preventDefault();
        if (frame) { frame.open(); return; }
        frame = wp.media({
          title: 'Select Resume',
          button: { text: 'Use this file' },
          multiple: false,
          library: { type: ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'] }
        });
        frame.on('select', function () {
          var attachment = frame.state().get('selection').first().toJSON();
          updatePreview(attachment.id, attachment.url, attachment.title);
        });
        frame.open();
      });
    }
    if ($resumeClear.length) {
      $resumeClear.on('click', function (e) {
        e.preventDefault();
        updatePreview('', '', '');
      });
    }

    // Application resume media picker
    (function(){
      var $sel = $('#careernest_app_resume_select');
      var $clr = $('#careernest_app_resume_clear');
      var $inp = $('#careernest_app_resume_id');
      var $prev = $('#careernest_app_resume_preview');
      var frame2;
      function upd(id,url,title){ if(!id||!url){ $prev.html('<em>No file selected</em>'); $inp.val(''); return; } var name=title||url.split('/').pop(); $prev.html('<a href="'+url+'" target="_blank" rel="noreferrer noopener">'+$('<div>').text(name).html()+'</a>'); $inp.val(id); }
      if ($sel.length) {
        $sel.on('click', function(e){ e.preventDefault(); if(frame2){ frame2.open(); return; } frame2 = wp.media({ title:'Select Resume', button:{text:'Use this file'}, multiple:false, library:{ type:['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'] } }); frame2.on('select', function(){ var att = frame2.state().get('selection').first().toJSON(); upd(att.id, att.url, att.title); }); frame2.open(); });
      }
      if ($clr.length) { $clr.on('click', function(e){ e.preventDefault(); upd('','',''); }); }
    })();

    // Job salary mode toggle
    (function(){
      var $mode = $('input[name="careernest_salary_mode"]');
      var $rowRange = $('.cn-salary-range-row');
      var $rowNum = $('.cn-salary-numeric-row');
      if (!$mode.length) return;
      function update() {
        var val = $mode.filter(':checked').val();
        if (val === 'numeric') {
          $rowRange.hide();
          $rowNum.show();
        } else {
          $rowNum.hide();
          $rowRange.show();
        }
      }
      $mode.on('change', update);
      update();
    })();

    // Applicant: autofill post title from selected linked user
    var $appUser = $('#careernest_applicant_user');
    var $title = $('#title');
    function maybeAutofillTitle() {
      if (!$appUser.length || !$title.length) return;
      var selected = $appUser.find('option:selected');
      var name = selected.data('name');
      if (!name) return;
      if (!$title.val()) {
        $title.val(name);
      }
    }
    if ($appUser.length && $title.length) {
      $appUser.on('change', function () {
        maybeAutofillTitle();
      });
      // On load, if empty title and user preselected, fill once
      maybeAutofillTitle();
    }

    // Jobs: dynamically populate "Job Posted By" based on selected Employer
    (function(){
      var $emp = $('#careernest_employer_id');
      var $sel = $('#careernest_posted_by');
      if (!$emp.length || !$sel.length || typeof careernestAdmin === 'undefined') return;
      function buildOptions(items){
        $sel.empty();
        if (!items || !items.length) {
          $sel.append($('<option/>',{value:''}).text(careernestAdmin.i18n.selectEmployerFirst));
          return;
        }
        $sel.append($('<option/>',{value:''}).text(careernestAdmin.i18n.selectUser));
        items.forEach(function(it){ $sel.append($('<option/>',{value:String(it.id)}).text(it.label)); });
      }
      $emp.on('change', function(){
        var id = $emp.val();
        if (!id) { buildOptions([]); return; }
        $.ajax({
          url: careernestAdmin.ajaxUrl,
          method: 'POST',
          dataType: 'json',
          data: { action: 'careernest_get_employer_team', employer_id: id, _wpnonce: careernestAdmin.nonce },
        }).done(function(resp){ if (resp && resp.success) { buildOptions(resp.data.items||[]); } else { buildOptions([]); } })
          .fail(function(){ buildOptions([]); });
      });
    })();
  });
})(jQuery);

    // Applicant Links repeater
    (function($){
      var $list = $('#careernest-link-list');
      var $add = $('#careernest-link-add');
      if (!$list.length || !$add.length) return;
      function newItem() {
        var html = ''+
          '<div class="cn-link-item">'+
          ' <div class="cn-link-handle"><span class="dashicons dashicons-move"></span> Drag to reorder</div>'+
          ' <div class="cn-link-grid">'+
          '  <p><label>Label</label><br /><input type="text" name="careernest_link_label[]" class="regular-text" placeholder="e.g., Portfolio, GitHub" /></p>'+
          '  <p><label>URL</label><br /><input type="url" name="careernest_link_url[]" class="regular-text" placeholder="https://" /></p>'+
          '  <p class="cn-span-2"><label>Notes</label><br /><textarea name="careernest_link_notes[]" rows="2" class="large-text"></textarea></p>'+
          ' </div>'+
          ' <p><button type="button" class="button-link-delete cn-link-remove">Remove</button></p>'+
          ' <hr />'+
          '</div>';
        return $(html);
      }
      $add.on('click', function(e){ e.preventDefault(); $list.append(newItem()); });
      $list.on('click', '.cn-link-remove', function(e){ e.preventDefault(); $(this).closest('.cn-link-item').remove(); });
      if ($.fn.sortable) {
        $list.sortable({ items: '.cn-link-item', handle: '.cn-link-handle', placeholder: 'cn-link-sort-placeholder' });
      }
    })(jQuery);
    // Applicant skills pills
    (function($){
      var $input = $('#careernest_skill_input');
      var $pills = $('#careernest_skill_pills');
      if (!$input.length || !$pills.length) return;
      function normalize(text){ return (text || '').replace(/[,]+/g,' ').trim(); }
      function exists(val){ var key = (val||'').toLowerCase(); return $pills.find('.cn-skill-pill[data-skill="'+CSS.escape(key)+'"]').length > 0; }
      function addSkill(text){ var v = normalize(text); if(!v) return; if (exists(v)) { $input.val(''); return; }
        var $pill = $('<div class="cn-skill-pill"/>').attr('data-skill', v.toLowerCase());
        $pill.append($('<span/>').text(v));
        $pill.append('<button type="button" class="cn-skill-remove" aria-label="Remove">×</button>');
        $pill.append($('<input type="hidden" name="careernest_skills[]" />').val(v));
        $pills.append($pill);
        $input.val('');
      }
      $input.on('keydown', function(e){
        if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addSkill($input.val()); }
      });
      $pills.on('click', '.cn-skill-remove', function(){ $(this).closest('.cn-skill-pill').remove(); });
    })(jQuery);
    // Applicant Licences & Certifications repeater
    (function($){
      var $list = $('#careernest-lic-list');
      var $add = $('#careernest-lic-add');
      if (!$list.length || !$add.length) return;
      function newItem() {
        var html = ''+
          '<div class="cn-lic-item">'+
          ' <div class="cn-lic-handle"><span class="dashicons dashicons-move"></span> Drag to reorder</div>'+
          ' <div class="cn-lic-grid">'+
          '  <p><label>Name</label><br /><input type="text" name="careernest_lic_name[]" class="regular-text" /></p>'+
          '  <p><label>Issuing Company</label><br /><input type="text" name="careernest_lic_issuer[]" class="regular-text" /></p>'+
          '  <p><label>Expiry Date</label><br /><input type="date" name="careernest_lic_expiry[]" /></p>'+
          '  <p class="cn-span-2"><label>Notes</label><br /><textarea name="careernest_lic_notes[]" rows="3" class="large-text"></textarea></p>'+
          ' </div>'+
          ' <p><button type="button" class="button-link-delete cn-lic-remove">Remove</button></p>'+
          ' <hr />'+
          '</div>';
        return $(html);
      }
      $add.on('click', function(e){ e.preventDefault(); $list.append(newItem()); });
      $list.on('click', '.cn-lic-remove', function(e){ e.preventDefault(); $(this).closest('.cn-lic-item').remove(); });
      if ($.fn.sortable) {
        $list.sortable({ items: '.cn-lic-item', handle: '.cn-lic-handle', placeholder: 'cn-lic-sort-placeholder' });
      }
    })(jQuery);
    // Applicant Work Experience repeater
    (function($){
      var $list = $('#careernest-exp-list');
      var $add = $('#careernest-exp-add');
      if (!$list.length || !$add.length) return;
      function newItem() {
        var html = ''+
          '<div class="cn-exp-item">'+
          ' <div class="cn-exp-handle"><span class="dashicons dashicons-move"></span> Drag to reorder</div>'+
          ' <div class="cn-exp-grid">'+
          '  <p><label>Company</label><br /><input type="text" name="careernest_exp_company[]" class="regular-text" /></p>'+
          '  <p><label>Job Title</label><br /><input type="text" name="careernest_exp_title[]" class="regular-text" /></p>'+
          '  <p><label>Start Date</label><br /><input type="date" name="careernest_exp_start[]" /></p>'+
          '  <p><label>End Date</label><br /><input type="date" class="cn-exp-end" name="careernest_exp_end[]" /></p>'+
          '  <p class="cn-span-2"><label>Notes</label><br /><textarea name="careernest_exp_notes[]" rows="3" class="large-text"></textarea></p>'+
          '  <p><input type="hidden" name="careernest_exp_current_row[]" value="0" />'+
          '     <label><input type="checkbox" class="cn-exp-current" /> Current Role</label></p>'+
          ' </div>'+
          ' <p><button type="button" class="button-link-delete cn-exp-remove">Remove</button></p>'+
          ' <hr />'+
          '</div>';
        return $(html);
      }
      $add.on('click', function(e){ e.preventDefault(); $list.append(newItem()); });
      $list.on('click', '.cn-exp-remove', function(e){ e.preventDefault(); $(this).closest('.cn-exp-item').remove(); });
      if ($.fn.sortable) {
        $list.sortable({ items: '.cn-exp-item', handle: '.cn-exp-handle', placeholder: 'cn-exp-sort-placeholder' });
      }
      // toggle end date disabled and hidden current value
      $list.on('change', '.cn-exp-current', function(){
        var $p = $(this).closest('p');
        var $hidden = $p.find('input[type="hidden"][name="careernest_exp_current_row[]"]');
        var $end = $(this).closest('.cn-exp-grid').find('.cn-exp-end').first();
        var checked = this.checked;
        if ($hidden.length) { $hidden.val(checked ? '1' : '0'); }
        if ($end.length) { $end.prop('disabled', checked); if (checked) { $end.val(''); } }
      });
    })(jQuery);
    // Applicant Education repeater
    (function($){
      var $list = $('#careernest-edu-list');
      var $add = $('#careernest-edu-add');
      if (!$list.length || !$add.length) return;
      function newItem() {
        var idx = $list.find('.cn-edu-item').length;
        var html = ''+
          '<div class="cn-edu-item">'+
          ' <div class="cn-edu-handle"><span class="dashicons dashicons-move"></span> Drag to reorder</div>'+
          ' <div class="cn-edu-grid">'+
          '  <p><label>Institution</label><br /><input type="text" name="careernest_edu_institution[]" class="regular-text" /></p>'+
          '  <p><label>Certification</label><br /><input type="text" name="careernest_edu_certification[]" class="regular-text" /></p>'+
          '  <p><label>Start Date</label><br /><input type="date" name="careernest_edu_start[]" /></p>'+
          '  <p><label>End Date</label><br /><input type="date" name="careernest_edu_end[]" /></p>'+
          '  <p class="cn-span-2"><label>Notes</label><br /><textarea name="careernest_edu_notes[]" rows="3" class="large-text"></textarea></p>'+
          '  <p><input type="hidden" name="careernest_edu_complete_row[]" value="0" />'+
          '     <label><input type="checkbox" class="cn-edu-complete" /> Qualification Complete</label></p>'+
          ' </div>'+
          ' <p><button type="button" class="button-link-delete cn-edu-remove">Remove</button></p>'+
          ' <hr />'+
          '</div>';
        return $(html);
      }
      $add.on('click', function(e){ e.preventDefault(); $list.append(newItem()); });
      $list.on('click', '.cn-edu-remove', function(e){ e.preventDefault(); $(this).closest('.cn-edu-item').remove(); });
      // toggle hidden complete value
      $list.on('change', '.cn-edu-complete', function(){
        var $hidden = $(this).closest('p').find('input[type="hidden"][name="careernest_edu_complete_row[]"]');
        if ($hidden.length) { $hidden.val(this.checked ? '1' : '0'); }
      });
      // Enable drag & drop
      if ($.fn.sortable) {
        $list.sortable({ items: '.cn-edu-item', handle: '.cn-edu-handle', placeholder: 'cn-edu-sort-placeholder' });
      }
    })(jQuery);
