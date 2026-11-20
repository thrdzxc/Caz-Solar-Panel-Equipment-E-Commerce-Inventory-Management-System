(() => {
  'use strict';
  const input=document.getElementById('identity-file'), preview=document.getElementById('identity-preview');
  if (!input || !preview) return;
  let currentUrl=null;
  input.addEventListener('change', () => {
    input.setCustomValidity(''); preview.hidden=true;
    if (currentUrl) URL.revokeObjectURL(currentUrl);
    const file=input.files[0]; if (!file) return;
    if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size>8388608) {
      input.setCustomValidity('Choose a JPG, PNG or WebP photo up to 8 MB.'); input.reportValidity(); return;
    }
    const url=URL.createObjectURL(file); currentUrl=url;
    const photo=preview.querySelector('img');
    photo.onload=() => {
      if (currentUrl!==url) return;
      if (Math.min(photo.naturalWidth,photo.naturalHeight)<500 || Math.max(photo.naturalWidth,photo.naturalHeight)<800) {
        input.setCustomValidity('Use a clearer photo at least 800 × 500 pixels, in either orientation.'); input.reportValidity();
      }
      preview.hidden=false;
    };
    photo.onerror=() => { if (currentUrl===url) { input.setCustomValidity('This image cannot be opened. Choose another photo.'); input.reportValidity(); } };
    photo.src=url;
  });
})();
