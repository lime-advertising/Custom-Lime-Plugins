/* global google */
(function () {
  function $(id) { return document.getElementById(id); }

  function bindAutocomplete(config) {
    var input = $(config.inputId);
    if (!input) return;
    if (typeof google === 'undefined' || !google.maps || !google.maps.places) return;
    try {
      var ac = new google.maps.places.Autocomplete(input, {
        fields: ['formatted_address', 'geometry', 'name', 'place_id'],
        types: ['geocode']
      });
      ac.addListener('place_changed', function () {
        var place = ac.getPlace();
        var pid = $(config.placeIdId);
        var lat = $(config.latId);
        var lng = $(config.lngId);
        if (place) {
          if (place.formatted_address) {
            input.value = place.formatted_address;
          }
          if (pid) { pid.value = place.place_id || ''; }
          if (place.geometry && place.geometry.location) {
            if (lat) { lat.value = String(place.geometry.location.lat()); }
            if (lng) { lng.value = String(place.geometry.location.lng()); }
          }
        }
      });
      // If user edits manually, clear stored place metadata to avoid stale coords
      input.addEventListener('input', function(){
        var pid = $(config.placeIdId);
        var lat = $(config.latId);
        var lng = $(config.lngId);
        if (pid) pid.value=''; if (lat) lat.value=''; if (lng) lng.value='';
      });
    } catch (e) {
      // Fail silently; input remains a plain text field
    }
  }

  function bindPickOnMap(cfg) {
    var btn = $(cfg.buttonId);
    var modal = $(cfg.modalId);
    var mapEl = $(cfg.mapId);
    var useBtn = $(cfg.useId);
    var cancelEls = (cfg.cancelIds || []).map(function(id){ return $(id); });
    if (!btn || !modal || !mapEl || !useBtn) return;
    if (typeof google === 'undefined' || !google.maps) return;

    var map, marker, geocoder;
    function openModal() {
      modal.style.display = 'flex';
      setTimeout(function(){
        // Initialize or refresh map
        if (!map) {
          var lat = parseFloat(($(cfg.latId) && $(cfg.latId).value) || '') || -25.2744; // AU default
          var lng = parseFloat(($(cfg.lngId) && $(cfg.lngId).value) || '') || 133.7751;
          var center = { lat: lat, lng: lng };
          map = new google.maps.Map(mapEl, { center: center, zoom: 5, mapTypeControl: false });
          geocoder = new google.maps.Geocoder();
          marker = new google.maps.Marker({ position: center, map: map, draggable: true });
          map.addListener('click', function(e){ marker.setPosition(e.latLng); });
        } else {
          google.maps.event.trigger(map, 'resize');
        }
      }, 10);
    }
    function closeModal() { modal.style.display = 'none'; }

    function useLocation() {
      var latLng = marker.getPosition();
      if (!latLng) { closeModal(); return; }
      var lat = latLng.lat();
      var lng = latLng.lng();
      var latInput = $(cfg.latId);
      var lngInput = $(cfg.lngId);
      var pidInput = $(cfg.placeIdId);
      var addrInput = $(cfg.inputId);
      if (latInput) latInput.value = String(lat);
      if (lngInput) lngInput.value = String(lng);
      // Reverse geocode to fill address and possibly place_id
      if (geocoder && addrInput) {
        geocoder.geocode({ location: { lat: lat, lng: lng } }, function(res, status){
          if (status === 'OK' && res && res[0]) {
            addrInput.value = res[0].formatted_address || addrInput.value;
            if (pidInput) pidInput.value = (res[0].place_id || '');
          } else {
            if (pidInput) pidInput.value = '';
          }
          closeModal();
        });
      } else {
        if (pidInput) pidInput.value = '';
        closeModal();
      }
    }

    btn.addEventListener('click', openModal);
    useBtn.addEventListener('click', useLocation);
    cancelEls.forEach(function(el){ if (el) el.addEventListener('click', closeModal); });
  }

  function init() {
    bindAutocomplete({
      inputId: 'careernest_applicant_location',
      placeIdId: 'careernest_applicant_place_id',
      latId: 'careernest_applicant_lat',
      lngId: 'careernest_applicant_lng'
    });
    bindAutocomplete({
      inputId: 'careernest_location',
      placeIdId: 'careernest_employer_place_id',
      latId: 'careernest_employer_lat',
      lngId: 'careernest_employer_lng'
    });
    bindAutocomplete({
      inputId: 'careernest_job_location',
      placeIdId: 'careernest_job_place_id',
      latId: 'careernest_job_lat',
      lngId: 'careernest_job_lng'
    });

    // Bind Pick-on-Map modals
    bindPickOnMap({
      buttonId: 'careernest_applicant_pick_map',
      modalId: 'careernest_applicant_map_modal',
      mapId: 'careernest_applicant_map_canvas',
      useId: 'careernest_applicant_map_use',
      cancelIds: ['careernest_applicant_map_cancel', 'careernest_applicant_map_cancel_2'],
      inputId: 'careernest_applicant_location',
      placeIdId: 'careernest_applicant_place_id',
      latId: 'careernest_applicant_lat',
      lngId: 'careernest_applicant_lng'
    });
    bindPickOnMap({
      buttonId: 'careernest_employer_pick_map',
      modalId: 'careernest_employer_map_modal',
      mapId: 'careernest_employer_map_canvas',
      useId: 'careernest_employer_map_use',
      cancelIds: ['careernest_employer_map_cancel', 'careernest_employer_map_cancel_2'],
      inputId: 'careernest_location',
      placeIdId: 'careernest_employer_place_id',
      latId: 'careernest_employer_lat',
      lngId: 'careernest_employer_lng'
    });
    bindPickOnMap({
      buttonId: 'careernest_job_pick_map',
      modalId: 'careernest_job_map_modal',
      mapId: 'careernest_job_map_canvas',
      useId: 'careernest_job_map_use',
      cancelIds: ['careernest_job_map_cancel', 'careernest_job_map_cancel_2'],
      inputId: 'careernest_job_location',
      placeIdId: 'careernest_job_place_id',
      latId: 'careernest_job_lat',
      lngId: 'careernest_job_lng'
    });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
