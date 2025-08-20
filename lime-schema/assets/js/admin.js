(function(){
  document.addEventListener("DOMContentLoaded", function(){
    // Tabs
    document.querySelectorAll(".ls-tab-nav a").forEach(function(btn){
      btn.addEventListener("click", function(e){
        e.preventDefault();
        var target = this.getAttribute("data-target");
        document.querySelectorAll(".ls-tab-nav a").forEach(function(b){ b.classList.remove("active"); });
        document.querySelectorAll(".ls-tab").forEach(function(t){ t.classList.remove("active"); });
        this.classList.add("active");
        var targetEl = document.querySelector(target);
        if (targetEl) targetEl.classList.add("active");
        history.replaceState(null, "", "#" + target.replace("#", ""));
      });
    });

    // Deep link
    if (location.hash && document.querySelector(location.hash)) {
      var activeNav = document.querySelector(".ls-tab-nav a.active");
      if (activeNav) activeNav.classList.remove("active");
      var activeTab = document.querySelector(".ls-tab.active");
      if (activeTab) activeTab.classList.remove("active");
      var nav = document.querySelector('.ls-tab-nav a[data-target="' + location.hash + '"]');
      if (nav) nav.classList.add("active");
      var tab = document.querySelector(location.hash);
      if (tab) tab.classList.add("active");
    }

    // Repeater: Locations
    var wrap = document.getElementById("ls-locations-wrap");
    if (!wrap) return;
    var addBtn = document.getElementById("ls-add-location");
    var proto  = document.getElementById("ls-location-proto");
    var optionKey = (window.LimeSchemaAdmin && window.LimeSchemaAdmin.optionKey) || 'lime_schema_options';

    function parseLatLngFromUrl(u){
      try {
        var url = new URL(u);
        var atMatch = url.pathname.match(/@(-?\d+(\.\d+)?),(-?\d+(\.\d+)?)/);
        if (atMatch) return { lat: parseFloat(atMatch[1]), lng: parseFloat(atMatch[3]) };
        var bangMatch = url.href.match(/!3d(-?\d+(\.\d+)?)!4d(-?\d+(\.\d+)?)/);
        if (bangMatch) return { lat: parseFloat(bangMatch[1]), lng: parseFloat(bangMatch[3]) };
        var q = url.searchParams.get("q");
        if (q && /-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?/.test(q)){
          var parts = q.split(",");
          return { lat: parseFloat(parts[0].trim()), lng: parseFloat(parts[1].trim()) };
        }
        var ll = url.searchParams.get("ll");
        if (ll && /-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?/.test(ll)){
          var p = ll.split(",");
          return { lat: parseFloat(p[0].trim()), lng: parseFloat(p[1].trim()) };
        }
      } catch(e) {}
      return null;
    }

    function handleHasMapEvent(e){
      var el = e.target;
      if (!el || !el.matches('input[data-name="hasMap"]')) return;
      var val = (el.value || '').trim();
      if (!val) return;
      var coords = parseLatLngFromUrl(val);
      if (!coords) return;
      var block = el.closest('.ls-loc');
      if (!block) return;
      var latInput = block.querySelector('input[data-name="geo[latitude]"]');
      var lngInput = block.querySelector('input[data-name="geo[longitude]"]');
      if (latInput) latInput.value = coords.lat;
      if (lngInput) lngInput.value = coords.lng;
    }

    document.addEventListener('input', handleHasMapEvent, true);
    document.addEventListener('change', handleHasMapEvent, true);
    document.addEventListener('blur', handleHasMapEvent, true);

    function renumber(){
      function bracketize(key){
        if (key.indexOf('[') !== -1){
          return key.replace(/\]/g, '').split('[').map(function(s){ return s.trim(); }).filter(Boolean).map(function(s){ return '['+s+']'; }).join('');
        }
        return '[' + key + ']';
      }
      wrap.querySelectorAll('.ls-loc').forEach(function(block, i){
        block.querySelectorAll('[data-name]').forEach(function(input){
          var key = input.getAttribute('data-name');
          input.name = optionKey + '[locations][' + i + ']' + bracketize(key);
        });
      });
    }

    if (addBtn && proto && proto.content){
      addBtn.addEventListener('click', function(e){
        e.preventDefault();
        var node = document.importNode(proto.content, true);
        wrap.appendChild(node);
        renumber();
      });
    }

    wrap.addEventListener('click', function(e){
      var t = e.target;
      if (t && t.classList && t.classList.contains('ls-remove')){
        e.preventDefault();
        var blk = t.closest('.ls-loc');
        if (blk && blk.parentNode){ blk.parentNode.removeChild(blk); }
        renumber();
      }
    });

    // initial renumber in case of server-rendered entries
    renumber();

    // Repeater: FAQs
    var faqWrap = document.getElementById('ls-faqs-wrap');
    var faqAdd  = document.getElementById('ls-add-faq');
    var faqProto= document.getElementById('ls-faq-proto');

    function renumberFaqs(){
      if (!faqWrap) return;
      faqWrap.querySelectorAll('.ls-loc').forEach(function(block, i){
        block.querySelectorAll('[data-name]').forEach(function(input){
          var key = input.getAttribute('data-name');
          if (!key) return;
          input.name = optionKey + '[faqs][' + i + '][' + key + ']';
        });
      });
    }
    if (faqAdd && faqProto && faqProto.content){
      faqAdd.addEventListener('click', function(e){
        e.preventDefault();
        var node = document.importNode(faqProto.content, true);
        faqWrap.appendChild(node);
        renumberFaqs();
      });
    }
    if (faqWrap){
      faqWrap.addEventListener('click', function(e){
        var t = e.target;
        if (t && t.classList && t.classList.contains('ls-remove')){
          e.preventDefault();
          var blk = t.closest('.ls-loc');
          if (blk && blk.parentNode){ blk.parentNode.removeChild(blk); }
          renumberFaqs();
        }
      });
      renumberFaqs();
    }

    // Preview Refresh
    var refreshBtn = document.getElementById('ls-preview-refresh');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', function(e){
        e.preventDefault();
        var form = refreshBtn.closest('form');
        if (!form) return;
        // Ensure current repeater names are up to date
        if (typeof renumber === 'function') renumber();
        var fd = new FormData(form);
        fd.append('action', 'lime_schema_preview');
        // admin ajax url is available in admin screens
        fetch((typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'), {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        }).then(function(res){ return res.json(); })
          .then(function(json){
            var pre = document.getElementById('ls-preview-output');
            if (!pre) return;
            if (json && json.success && json.data && json.data.payload){
              pre.textContent = JSON.stringify(json.data.payload, null, 2);
              var hints = document.getElementById('ls-preview-hints');
              if (hints){
                var issues = (json.data.issues || []);
                if (issues.length){
                  var html = '<p><strong>Recommendations:</strong></p><ul style="list-style:disc;padding-left:20px">';
                  issues.forEach(function(msg){ html += '<li>' + String(msg).replace(/[<>&]/g, function(c){ return ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]); }) + '</li>'; });
                  html += '</ul>';
                  hints.innerHTML = html;
                } else {
                  hints.innerHTML = '<p>All key fields look good.</p>';
                }
              }
            } else {
              pre.textContent = JSON.stringify(json, null, 2);
            }
          })
          .catch(function(err){
            var pre = document.getElementById('ls-preview-output');
            if (pre) pre.textContent = String(err);
          });
      });
    }

    // Copy to clipboard
    var copyBtn = document.getElementById('ls-preview-copy');
    if (copyBtn){
      copyBtn.addEventListener('click', function(e){
        e.preventDefault();
        var pre = document.getElementById('ls-preview-output');
        if (!pre) return;
        var text = pre.textContent || '';
        if (navigator.clipboard && navigator.clipboard.writeText){
          navigator.clipboard.writeText(text).then(function(){
            var orig = copyBtn.textContent;
            copyBtn.textContent = 'Copied!';
            setTimeout(function(){ copyBtn.textContent = orig; }, 1200);
          }).catch(function(){});
        } else {
          // Fallback
          var ta = document.createElement('textarea');
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          ta.value = text;
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand('copy'); } catch(e) {}
          document.body.removeChild(ta);
          var orig = copyBtn.textContent;
          copyBtn.textContent = 'Copied!';
          setTimeout(function(){ copyBtn.textContent = orig; }, 1200);
        }
      });
    }

    // Validate in Google Rich Results Test
    var validateBtn = document.getElementById('ls-preview-validate');
    if (validateBtn){
      validateBtn.addEventListener('click', function(e){
        e.preventDefault();
        var form = validateBtn.closest('form');
        var base = (window.LimeSchemaAdmin && LimeSchemaAdmin.rrtUrlBase) || 'https://search.google.com/test/rich-results';
        var siteUrlInput = form && form.querySelector('input[name="'+optionKey+'[site_url]"]');
        var siteUrl = siteUrlInput ? (siteUrlInput.value || '').trim() : '';
        var href = base + (siteUrl ? ('?url=' + encodeURIComponent(siteUrl)) : '');
        window.open(href, '_blank');
        // Also copy code for quick paste into Code tab
        var pre = document.getElementById('ls-preview-output');
        if (pre){
          var text = pre.textContent || '';
          if (navigator.clipboard && navigator.clipboard.writeText){
            navigator.clipboard.writeText(text);
          }
        }
      });
    }
  });
})();
