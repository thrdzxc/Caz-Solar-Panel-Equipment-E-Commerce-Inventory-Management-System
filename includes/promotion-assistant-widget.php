<?php
// Public, prepared store answers. The existing Messages page handles staff contact.
$assistantHandoff = $promoCustomer
    ? 'messages.php'
    : '../login.php?role=customer&next=' . rawurlencode('pages/messages.php');
?>
<aside class="caz-assistant" id="caz-assistant" data-api="promotion_assistant_api.php" aria-label="CAZ quick help">
    <button type="button" class="caz-assistant-launcher" id="caz-assistant-launcher" aria-expanded="false" aria-controls="caz-assistant-panel">
        <svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 3v-3H3a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path d="M7 9h10M7 13h6"/></svg>
        <span>CAZ quick help<small>10 questions · No sign-in needed</small></span>
    </button>
    <section class="caz-assistant-panel" id="caz-assistant-panel" role="dialog" aria-modal="false" aria-labelledby="caz-assistant-title" aria-describedby="caz-assistant-intro" hidden>
        <header class="caz-assistant-header">
            <div><span class="caz-assistant-eyebrow">Your store guide</span><h2 id="caz-assistant-title">CAZ quick help</h2></div>
            <button type="button" class="caz-assistant-close" id="caz-assistant-close" aria-label="Close quick help"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        </header>
        <div class="caz-assistant-content" id="caz-assistant-content">
            <p class="caz-assistant-intro" id="caz-assistant-intro">Choose a question to chat with the CAZ store guide. This chatbot answers 10 common questions using CAZ’s store information. You can receive up to 10 answers in this browser session.</p>
            <div class="caz-assistant-history" id="caz-assistant-history" aria-label="Your quick-help conversation"></div>
            <section class="caz-assistant-suggestions" aria-labelledby="caz-assistant-questions-title">
                <h3 id="caz-assistant-questions-title">What would you like to know?</h3>
                <div class="caz-assistant-questions" id="caz-assistant-questions"></div>
            </section>
            <div class="caz-assistant-notice" id="caz-assistant-notice" hidden><p id="caz-assistant-notice-text"></p><button type="button" id="caz-assistant-retry" hidden>Try again</button></div>
            <noscript><p class="caz-assistant-intro">Enable JavaScript to use the chatbot. You can still contact CAZ through the link below.</p></noscript>
        </div>
        <footer class="caz-assistant-footer">
            <p class="caz-assistant-status" id="caz-assistant-status" role="status" aria-live="polite">Loading questions…</p>
            <a class="caz-assistant-handoff" id="caz-assistant-handoff" href="<?= caz_h($assistantHandoff) ?>"><?= $promoCustomer ? 'Message the CAZ admin' : 'Log in to message the admin' ?><span aria-hidden="true"> →</span></a>
            <p class="caz-assistant-handoff-note" id="caz-assistant-handoff-note"><?= $promoCustomer ? 'Need personal help? Continue in Messages. Nothing is sent automatically.' : 'A customer login is required to contact the admin. Quick help stays public.' ?></p>
        </footer>
    </section>
</aside>
