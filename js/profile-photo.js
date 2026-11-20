/* Photos are saved separately from the customer's contact details. */
(() => {
  const config = window.CAZ_PROFILE_PHOTO;
  if (!config || !window.CAZ_USER_ID) return;
  const endpoint = new URL(config.endpoint, location.href);
  const safePhotoUrl = value => {
    if (!value) return null;
    try { const url = new URL(value, endpoint); return url.origin === endpoint.origin && url.pathname === endpoint.pathname ? url.href : null; }
    catch (_) { return null; }
  };
  let savedUrl = safePhotoUrl(config.photo_url);
  const silhouette = '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="21" r="12"/><path d="M10 58v-5c0-12 10-20 22-20s22 8 22 20v5z"/></svg>';
  function paintAccount() {
    document.querySelectorAll('[data-account-avatar]').forEach(host => {
      host.replaceChildren();
      if (savedUrl) {
        const img = document.createElement('img'); img.className='account-photo'; img.alt=''; img.src=savedUrl;
        img.onerror = () => { host.innerHTML='<i class="fas fa-user" aria-hidden="true"></i>'; };
        host.append(img);
      } else host.innerHTML='<i class="fas fa-user" aria-hidden="true"></i>';
    });
  }
  function init() {
    paintAccount();
    const host = document.querySelector('#profile-panel .profile-avatar');
    if (!host) return; // Messages and offers link to the shared account panel on Home.
    host.innerHTML = `<section class="profile-photo-editor" aria-labelledby="profile-photo-heading"><div class="profile-photo-preview"></div><h3 class="profile-photo-title" id="profile-photo-heading">Profile photo</h3><p class="profile-photo-help" id="profile-photo-help">JPG, PNG or WebP · Up to 2 MB</p><input type="file" id="profile-photo-file" accept="image/jpeg,image/png,image/webp" aria-label="Choose your profile photo" aria-describedby="profile-photo-help" hidden><div class="profile-photo-actions"><button type="button" class="profile-photo-button" data-photo-choose>Upload photo</button><button type="button" class="profile-photo-button profile-photo-button-remove" data-photo-remove hidden>Remove photo</button><button type="button" class="profile-photo-button profile-photo-button-primary" data-photo-save hidden>Save photo</button><button type="button" class="profile-photo-button" data-photo-cancel hidden>Cancel</button></div><p class="profile-photo-status" role="status" aria-live="polite"></p></section>`;
    const editor=host.querySelector('.profile-photo-editor'), preview=host.querySelector('.profile-photo-preview');
    const input=host.querySelector('input'), choose=host.querySelector('[data-photo-choose]'), remove=host.querySelector('[data-photo-remove]');
    const save=host.querySelector('[data-photo-save]'), cancel=host.querySelector('[data-photo-cancel]'), status=host.querySelector('.profile-photo-status');
    let selected=null, objectUrl=null, busy=false;
    const announce=(text,error=false)=>{status.textContent=text;status.dataset.state=error?'error':'success';};
    function clearSelection() { selected=null;input.value='';if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=null; }
    function render() {
      preview.replaceChildren();
      const url=objectUrl||savedUrl;
      if(url){const img=document.createElement('img');img.alt=selected?'Preview of your selected profile photo':'Your profile photo';img.src=url;
        img.onerror=()=>{if(url!==(objectUrl||savedUrl))return;if(selected){clearSelection();render();announce('This image could not be opened. Choose a JPG, PNG or WebP photo.',true);}else preview.innerHTML=silhouette;};preview.append(img);
      } else preview.innerHTML=silhouette;
      choose.textContent=selected?'Choose another':savedUrl?'Change photo':'Upload photo';
      save.hidden=cancel.hidden=!selected;remove.hidden=!savedUrl||!!selected;
      [choose,remove,save,cancel,input].forEach(el=>el.disabled=busy);
      editor.setAttribute('aria-busy',String(busy));
    }
    choose.onclick=()=>input.click();
    async function preparePhoto(file) {
      const source=URL.createObjectURL(file), img=new Image();
      try {
        img.src=source;await img.decode();
        if(!img.naturalWidth||!img.naturalHeight||img.naturalWidth>6000||img.naturalHeight>6000||img.naturalWidth*img.naturalHeight>20000000)throw new Error('Choose a photo no larger than 6,000 pixels on either side and 20 megapixels.');
        const scale=Math.min(1,640/Math.max(img.naturalWidth,img.naturalHeight));
        const canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(img.naturalWidth*scale));canvas.height=Math.max(1,Math.round(img.naturalHeight*scale));
        const context=canvas.getContext('2d');if(!context)throw new Error('Your browser could not prepare this photo. Please try another browser.');
        context.fillStyle='#f4f7f5';context.fillRect(0,0,canvas.width,canvas.height);context.drawImage(img,0,0,canvas.width,canvas.height);
        const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg',0.86));
        if(!blob||blob.size>768*1024)throw new Error('This photo could not be resized. Please choose a smaller image.');
        return new File([blob],'profile-photo.jpg',{type:'image/jpeg'});
      } finally { URL.revokeObjectURL(source); }
    }
    input.onchange=async()=>{
      const file=input.files?.[0];if(!file)return;
      if(!['image/jpeg','image/png','image/webp'].includes(file.type)){input.value='';announce('Choose a JPG, PNG or WebP image.',true);return;}
      if(file.size===0||file.size>2*1024*1024){input.value='';announce('Choose a photo smaller than 2 MB.',true);return;}
      clearSelection();busy=true;render();announce('Preparing your photo…');
      try { selected=await preparePhoto(file);objectUrl=URL.createObjectURL(selected);announce('Preview ready. Select Save photo to use this picture.'); }
      catch(error) { clearSelection();announce(error.message||'This image could not be opened. Please choose another photo.',true); }
      finally { busy=false;render(); }
    };
    cancel.onclick=()=>{clearSelection();render();announce('Selection canceled. Your saved photo is unchanged.');};
    async function submit(removing) {
      if(busy||(!removing&&!selected))return;
      const form=new FormData();if(removing)form.set('action','remove');else form.set('photo',selected);
      busy=true;render();announce(removing?'Removing your photo…':'Saving your photo…');
      try{
        const response=await fetch(endpoint.href,{method:'POST',headers:{'X-CSRF-Token':window.CAZ_CSRF||''},body:form});
        const data=await response.json();
        if(!response.ok||!data.success)throw new Error(data.message||'The photo could not be saved. Please try again.');
        savedUrl=safePhotoUrl(data.photo_url);config.photo_url=data.photo_url;clearSelection();paintAccount();
        window.dispatchEvent(new CustomEvent('caz:profile-photo-changed'));
        // Signal other same-origin tabs without sharing photo URLs or account details.
        try { localStorage.setItem('caz:profile-photo-changed', `${Date.now()}:${Math.random()}`); } catch (_) {}
        announce(removing?'Profile photo removed.':'Profile photo saved. It will be here when you sign in again.');
      }catch(error){announce(error.message||'Connection error. Please try again.',true);}
      finally{busy=false;render();}
    }
    save.onclick=()=>submit(false);remove.onclick=()=>submit(true);render();
    window.addEventListener('pagehide',()=>{if(objectUrl)URL.revokeObjectURL(objectUrl);});
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
