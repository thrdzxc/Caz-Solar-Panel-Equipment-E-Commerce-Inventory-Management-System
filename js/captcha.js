(() => {
    'use strict';
    document.querySelectorAll('.caz-captcha').forEach(box => {
        const button = box.querySelector('.captcha-refresh');
        const image = box.querySelector('.captcha-image');
        const id = box.querySelector('[name="captcha_id"]');
        const answer = box.querySelector('[name="captcha_answer"]');
        const status = box.querySelector('.captcha-status');
        const showStatus = (message, error = false) => {
            status.textContent = message;
            status.classList.toggle('is-error', error);
        };
        image.addEventListener('error', () => showStatus('The image could not load. Choose New code, or reload the page.', true));
        button.addEventListener('click', async () => {
            button.disabled = true;
            showStatus('Getting a new code…');
            try {
                const response = await fetch('captcha.php', {
                    method: 'POST', credentials: 'same-origin', cache: 'no-store',
                    body: new URLSearchParams({token: box.dataset.refreshToken, purpose: box.dataset.purpose, previous_id: id.value})
                });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.message || 'Unable to refresh. Please reload this page.');
                id.value = data.id;
                image.src = data.image;
                answer.value = '';
                answer.focus();
                showStatus('New code ready. Enter the six characters in the image.');
            } catch (error) {
                showStatus(error.message || 'Unable to refresh. Please reload this page.', true);
            } finally { button.disabled = false; }
        });
    });
})();
