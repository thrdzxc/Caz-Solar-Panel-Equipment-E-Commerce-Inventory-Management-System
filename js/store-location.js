/* Admin owns the destination pin; Google Maps supplies routes from the visitor's origin. */
document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-store-config]');
  if (!root) return;
  let config;
  try { config = JSON.parse(root.dataset.storeConfig); } catch (_) { return; }
  const canvas = root.querySelector('#store-map');
  const status = root.querySelector('.store-map-status');
  const form = root.querySelector('#store-location-form');
  const lat = form?.querySelector('[name=latitude]'), lng = form?.querySelector('[name=longitude]');
  const badge = root.querySelector('.store-pin-state');
  const valid = (a,b) => Number.isFinite(a) && Number.isFinite(b) && Math.abs(a)<=90 && Math.abs(b)<=180;
  let pin = config.latitude !== null && config.longitude !== null && valid(config.latitude,config.longitude) ? {latitude:config.latitude,longitude:config.longitude} : null;
  let map, marker, geoSequence = 0;
  const announce = text => { if (status) status.textContent = text; };
  function drawPin() {
    if (!map || !pin) { if (marker) { marker.remove(); marker=null; } return; }
    if (marker) marker.setLatLng([pin.latitude,pin.longitude]);
    else {
      marker = L.marker([pin.latitude,pin.longitude],{draggable:!!config.admin,title:'CAZ store entrance',alt:'CAZ store entrance',icon:L.divIcon({className:'store-marker',html:'<span aria-hidden="true"></span>',iconSize:[30,40],iconAnchor:[15,38]})}).addTo(map);
      if (config.admin) {
        marker.on('dragstart',()=>{geoSequence++;});
        marker.on('dragend',()=>{ const p=marker.getLatLng().wrap(); choose(p.lat,p.lng); });
      } else {
        const label=document.createElement('div');
        const title=document.createElement('strong'); title.textContent='Caz Solar & CCTV';
        const address=document.createElement('p'); address.textContent=config.address;
        const route=document.createElement('a'); route.textContent='Get directions'; route.href=config.directions; route.target='_blank'; route.rel='noopener noreferrer';
        label.append(title,address,route); marker.bindPopup(label);
      }
    }
  }
  function choose(a,b) {
    if (!valid(a,b)) return;
    geoSequence++;
    pin={latitude:Number(a.toFixed(7)),longitude:Number(b.toFixed(7))};
    lat.value=pin.latitude.toFixed(7); lng.value=pin.longitude.toFixed(7);
    drawPin(); badge.textContent='Unsaved pin';
    announce('Pin selected. Check the entrance, then choose Save store location.');
  }
  if (window.L) {
    canvas.replaceChildren();
    map=L.map(canvas,{scrollWheelZoom:false,worldCopyJump:true}).setView(pin?[pin.latitude,pin.longitude]:[16.6965416,121.5240213],pin?18:14);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>'}).on('tileerror',()=>announce('Map images could not load. Check your connection; the address and Google Maps link are still available.')).addTo(map);
    drawPin();
    if (config.admin) map.on('click',event=>{const p=event.latlng.wrap();choose(p.lat,p.lng);});
  } else {
    canvas.textContent=config.admin?'The map could not load. Enter coordinates below to save the store location.':'Map preview unavailable. Use Get directions to open Google Maps.';
  }
  if (!config.admin || !form) return;
  form.querySelector('[data-pin-center]').addEventListener('click',()=>{
    if (!map) {announce('Map unavailable. Enter coordinates instead.');return;}
    const p=map.getCenter().wrap(); choose(p.lat,p.lng);
  });
  for (const input of [lat,lng]) input.addEventListener('input',()=>{
    geoSequence++; badge.textContent='Unsaved changes';
    const a=Number(lat.value),b=Number(lng.value);
    pin=lat.value.trim() && lng.value.trim() && valid(a,b)?{latitude:a,longitude:b}:null;
    drawPin();
  });
  form.querySelector('[name=address]').addEventListener('input',()=>{badge.textContent='Unsaved changes';});
  form.querySelector('[data-pin-gps]').addEventListener('click',()=>{
    if(!navigator.geolocation){announce('Location is unavailable. Place a pin manually.');return;}
    const request=++geoSequence; announce('Waiting for location permission…');
    navigator.geolocation.getCurrentPosition(position=>{
      if(request!==geoSequence)return;
      choose(position.coords.latitude,position.coords.longitude);
      if(map)map.setView([pin.latitude,pin.longitude],18);
      announce('Location found (approximately '+Math.round(position.coords.accuracy)+' m accuracy). Adjust to the store entrance before saving.');
    },()=>{if(request===geoSequence)announce('Location access was unavailable. You can click the map or enter coordinates.');},{enableHighAccuracy:true,timeout:12000,maximumAge:0});
  });
  form.addEventListener('submit',async event=>{
    event.preventDefault();
    if(!form.reportValidity())return;
    const button=form.querySelector('[type=submit]'), message=form.querySelector('.store-save-status');
    button.disabled=true; message.textContent='Saving…';
    const body=new URLSearchParams(new FormData(form));
    try {
      const response=await fetch(config.endpoint,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body});
      const result=await response.json();
      if(!response.ok || !result.success)throw new Error(result.message || 'Unable to save. Please try again.');
      message.textContent=result.message;
      // Only call a pin published if no newer unsaved edit was made during the request.
      if(body.toString()===new URLSearchParams(new FormData(form)).toString()) {
        badge.textContent='Published pin';
        announce('The saved entrance pin is now visible on the customer Contact page.');
      }
      const link=form.querySelector('[data-store-directions]'); link.href=result.directions; link.textContent='Preview published directions';
    } catch(error) { message.textContent=error.message || 'Unable to save. Please try again.'; }
    finally {button.disabled=false;}
  });
});
