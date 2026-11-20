(() => {
  'use strict';
  const phoneMessage = 'Enter exactly 11 digits, starting with 09.';
  document.querySelectorAll('[data-account-phone]').forEach(input => {
    const validate = () => {
      input.value = input.value.replace(/[^0-9]/g, '').slice(0, 11);
      input.setCustomValidity(input.value && !/^09[0-9]{9}$/.test(input.value) ? phoneMessage : '');
    };
    input.addEventListener('input', validate);
    input.addEventListener('blur', validate);
  });
  document.querySelectorAll('input[data-account-name]').forEach(input => {
    input.addEventListener('input', () => {
      input.value = input.value.replace(/[^\p{L}\p{M} ]/gu, '');
      input.setCustomValidity('');
    });
    input.addEventListener('blur', () => {
      input.value = input.value.trim();
      input.setCustomValidity(input.value && !/^\p{L}[\p{L}\p{M}]*(?: +\p{L}[\p{L}\p{M}]*)*$/u.test(input.value)
        ? 'Use letters and spaces only for your username / full name.' : '');
    });
  });
  document.querySelectorAll('[data-account-password]').forEach(input => {
    input.addEventListener('input', () => {
      let message = '';
      if (input.value && Array.from(input.value).length < 8) message = 'Use at least 8 characters.';
      else if (input.value && !/[\p{P}\p{S}]/u.test(input.value)) message = 'Include a special character such as !, @ or #. Spaces do not count.';
      else if (new TextEncoder().encode(input.value).length > 72) message = 'Keep your password within 72 bytes (up to 72 plain-text characters).';
      input.setCustomValidity(message);
    });
  });
})();
