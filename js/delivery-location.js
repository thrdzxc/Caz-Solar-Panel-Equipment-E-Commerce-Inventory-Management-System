/* Customer-selected order coordinates; map tiles are OpenStreetMap, directions open Google Maps. */
(() => {
  const libraryBase = new URL('../assets/vendor/leaflet/', document.currentScript.src).href;
  const startingCenter = [16.6965416, 121.5240213]; // Existing store-area map; never saved as a customer's pin.
  let libraryPromise, sequence = 0;
  const valid = pin => pin && Number.isFinite(pin.latitude) && Number.isFinite(pin.longitude) && Math.abs(pin.latitude) <= 90 && Math.abs(pin.longitude) <= 180;
  const copyPin = pin => valid(pin) ? {latitude: Number(pin.latitude.toFixed(7)), longitude: Number(pin.longitude.toFixed(7))} : null;
  function loadLibrary() {
    if (window.L) return Promise.resolve(window.L);
    if (!libraryPromise) libraryPromise = new Promise((resolve, reject) => {
      if (!document.querySelector('[data-caz-leaflet-css]')) {
        const css = document.createElement('link'); css.rel = 'stylesheet'; css.href = libraryBase + 'leaflet.css'; css.dataset.cazLeafletCss = 'true'; document.head.append(css);
      }
      const script = document.createElement('script'); script.src = libraryBase + 'leaflet.js';
      script.onload = () => window.L ? resolve(window.L) : reject(new Error('The map could not load.'));
      script.onerror = () => { script.remove(); libraryPromise = null; reject(new Error('The map could not load. Enter coordinates below or try again.')); };
      document.head.append(script);
    });
    return libraryPromise;
  }
  const pinIcon = L => L.divIcon({className:'delivery-marker',html:'<span></span>',iconSize:[32,42],iconAnchor:[16,40]});
  function addTiles(L, map, onError) {
    const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom:19, attribution:'&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>'
    }).addTo(map);
    tiles.on('tileerror', () => onError?.('Map images are unavailable. Check your internet connection, or enter coordinates below.'));
    return tiles;
  }
  function mountPicker(host, options = {}) {
    const id = 'delivery-pin-' + (++sequence);
    let pin = copyPin(options.initial), map, marker, destroyed = false, dirty = false, opening, geoRequest = 0;
    host.classList.add('delivery-picker');
    host.innerHTML = `<h3 class="delivery-picker-heading">Pin your delivery location</h3><p class="delivery-location-help">Choose your entrance or delivery spot. Our admin can open directions to your selected pin.</p><div class="delivery-location-actions"><button type="button" class="delivery-location-link" data-map-open>Choose location on map</button><button type="button" class="delivery-location-link" data-map-gps>Use my location</button></div><div class="delivery-picker-map-area" hidden><div class="delivery-map-canvas" role="region" aria-label="Delivery location map: tap to place a pin, then drag to adjust" tabindex="0"></div><div class="delivery-location-actions"><button type="button" class="delivery-location-link" data-map-center>Place pin at map center</button><button type="button" class="delivery-location-link" data-map-clear>Clear pin</button></div></div><p class="delivery-pin-summary" aria-live="polite"></p><p class="delivery-map-status" role="status"></p><details class="delivery-coordinate-fields"><summary>Enter coordinates instead</summary><p class="delivery-location-help">You can copy latitude and longitude from a dropped pin in Google Maps.</p><div class="delivery-coordinate-grid"><label for="${id}-lat">Latitude<input id="${id}-lat" type="number" inputmode="decimal" min="-90" max="90" step="any" placeholder="e.g. 16.696542"></label><label for="${id}-lng">Longitude<input id="${id}-lng" type="number" inputmode="decimal" min="-180" max="180" step="any" placeholder="e.g. 121.524021"></label></div><div class="delivery-location-actions"><button type="button" class="delivery-location-link" data-map-apply>Apply coordinates</button><button type="button" class="delivery-location-link" data-map-clear>Clear pin</button></div></details>`;
    const canvas = host.querySelector('.delivery-map-canvas'), area = host.querySelector('.delivery-picker-map-area');
    const status = host.querySelector('.delivery-map-status'), summary = host.querySelector('.delivery-pin-summary');
    const latitude = host.querySelector('#' + id + '-lat'), longitude = host.querySelector('#' + id + '-lng');
    function announce(text) { if (!destroyed) status.textContent = text; }
    function refresh() {
      if (!dirty) { latitude.value = pin ? pin.latitude.toFixed(7) : ''; longitude.value = pin ? pin.longitude.toFixed(7) : ''; }
      summary.textContent = pin ? 'Selected pin: ' + pin.latitude.toFixed(7) + ', ' + pin.longitude.toFixed(7) : 'No delivery pin selected yet. Your written address is kept separately.';
      if (map && window.L) {
        if (!pin) { marker?.remove(); marker = null; }
        else if (marker) marker.setLatLng([pin.latitude, pin.longitude]);
        else {
          marker = window.L.marker([pin.latitude, pin.longitude], {draggable:true,icon:pinIcon(window.L),title:'Delivery pin — drag to your exact delivery spot',alt:'Delivery pin'}).addTo(map);
          marker.on('dragstart', () => { geoRequest++; });
          marker.on('dragend', () => { const point = marker.getLatLng().wrap(); setValue({latitude:point.lat,longitude:point.lng}); });
        }
      }
    }
    function setValue(value, fromGeolocation = false) {
      if (destroyed) return;
      // A later GPS response must not replace the customer's newer manual choice.
      if (!fromGeolocation) geoRequest++;
      pin = copyPin(value); dirty = false; refresh(); announce(pin ? 'Pin selected. Drag it to adjust the delivery spot.' : 'Pin cleared. Choose a location for this address.');
      options.onChange?.(pin ? {...pin} : null);
    }
    async function open() {
      if (destroyed) return;
      area.hidden = false;
      if (map) { map.invalidateSize(); return; }
      if (opening) return opening;
      announce('Loading the map…');
      opening = (async () => {
        try {
          const L = await loadLibrary(); if (destroyed || !host.isConnected) return;
          map = L.map(canvas, {scrollWheelZoom:false,worldCopyJump:true}).setView(pin ? [pin.latitude,pin.longitude] : startingCenter, pin ? 17 : 14);
          addTiles(L, map, announce);
          map.on('click', event => { const point = event.latlng.wrap(); setValue({latitude:point.lat,longitude:point.lng}); });
          refresh(); map.invalidateSize(); announce('Tap the map to place a pin, or pan and use “Place pin at map center”. You can drag the pin afterward.');
        } catch (error) { announce(error.message); } finally { opening = null; }
      })();
      return opening;
    }
    host.querySelector('[data-map-open]').onclick = open;
    host.querySelector('[data-map-center]').onclick = () => { if (!map) { announce('Wait for the map to load, or enter coordinates.'); return; } const point=map.getCenter().wrap(); setValue({latitude:point.lat,longitude:point.lng}); };
    host.querySelectorAll('[data-map-clear]').forEach(button => button.onclick = () => setValue(null));
    latitude.oninput = longitude.oninput = () => { geoRequest++; dirty = true; announce('Press Apply coordinates to confirm this location.'); };
    host.querySelector('[data-map-apply]').onclick = () => {
      const value = {latitude:Number(latitude.value),longitude:Number(longitude.value)};
      if (!latitude.value.trim() || !longitude.value.trim() || !valid(value)) { announce('Enter a latitude from −90 to 90 and longitude from −180 to 180.'); return; }
      setValue(value); if (map) map.setView([pin.latitude,pin.longitude],17);
    };
    host.querySelector('[data-map-gps]').onclick = () => {
      if (!navigator.geolocation) { announce('Location is unavailable in this browser. Choose a pin on the map or enter coordinates.'); return; }
      const request = ++geoRequest; announce('Waiting for location permission…');
      navigator.geolocation.getCurrentPosition(async position => {
        if (destroyed || request !== geoRequest) return;
        setValue({latitude:position.coords.latitude,longitude:position.coords.longitude}, true);
        await open(); if (destroyed || request !== geoRequest) return;
        if (map && pin) map.setView([pin.latitude,pin.longitude],17);
        announce('Location found (approximately ' + Math.round(position.coords.accuracy) + ' m accuracy). Check the pin and drag it to your entrance.');
      }, () => { if (!destroyed && request === geoRequest) announce('Location could not be accessed. Tap the map or enter coordinates instead.'); }, {enableHighAccuracy:true,timeout:15000,maximumAge:0});
    };
    refresh();
    return {
      getValue: () => pin ? {...pin} : null,
      validate: () => { if (dirty) { announce('Apply your coordinate changes before continuing, or clear the pin.'); return false; } return true; },
      clear: () => setValue(null), open,
      destroy: () => { destroyed = true; geoRequest++; map?.remove(); map = marker = null; }
    };
  }
  async function mountPreview(element, value) {
    const pin = copyPin(value); if (!pin) return;
    try {
      const L = await loadLibrary(); if (!element.isConnected) return;
      element.replaceChildren();
      const map = L.map(element, {scrollWheelZoom:false}).setView([pin.latitude,pin.longitude],17);
      addTiles(L, map, () => { element.setAttribute('aria-label','Map images unavailable. Use the Google Maps directions link below.'); });
      L.marker([pin.latitude,pin.longitude], {icon:pinIcon(L),title:'Customer-selected delivery location',alt:'Customer delivery pin'}).addTo(map);
      return map;
    } catch (_) { element.textContent = 'Map preview unavailable. The saved coordinates and Google Maps directions below are still available.'; }
  }
  window.CAZDeliveryMap = {mountPicker, mountPreview};
})();
