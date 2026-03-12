<?php
/**
 * Cookie Consent Popup Component
 * Include this at the end of the page (before closing body tag)
 */

// Check if consent already given
$has_consent = false;

// Check cookie first (works for all users)
if (isset($_COOKIE['cookie_consent'])) {
    $has_consent = true;
}

// For logged-in users, also check database
if (!$has_consent && isset($current_user) && $current_user) {
    $stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
    $stmt->execute([$current_user['id']]);
    $row = $stmt->fetch();
    if ($row && $row['preferences']) {
        $preferences = json_decode($row['preferences'], true);
        $has_consent = isset($preferences['cookie_consent']);
    }
}

// Don't show popup if consent already given
if ($has_consent) {
    return;
}
?>
<div class="cookie-consent" id="cookie-consent">
    <div class="cookie-consent-content">
        <div class="cookie-consent-text">
            <h4>We use cookies</h4>
            <p>We use cookies to enhance your browsing experience and analyze site traffic. By clicking "Accept All", you consent to our use of cookies. <a href="/cookie-policy">Learn more</a></p>
        </div>
        <div class="cookie-consent-actions">
            <button type="button" class="btn btn-outline cookie-decline-btn" id="cookie-decline">Decline</button>
            <button type="button" class="btn btn-primary cookie-accept-btn" id="cookie-accept">Accept All</button>
        </div>
    </div>
</div>

<script>
(function() {
    const popup = document.getElementById('cookie-consent');
    const acceptBtn = document.getElementById('cookie-accept');
    const declineBtn = document.getElementById('cookie-decline');

    if (!popup || !acceptBtn || !declineBtn) {
        console.error('Cookie consent elements not found');
        return;
    }

    function hidePopup() {
        popup.classList.add('hiding');
        setTimeout(() => {
            popup.style.display = 'none';
        }, 300);
    }

    function setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax';
    }

    function saveConsent(action) {
        const consent = {
            accepted: action === 'accept',
            timestamp: new Date().toISOString(),
            necessary: true,
            analytics: action === 'accept',
            marketing: false
        };

        // Set cookie directly in browser (365 days)
        setCookie('cookie_consent', JSON.stringify(consent), 365);

        // Hide popup
        hidePopup();

        // Also notify server (fire and forget)
        const formData = new FormData();
        formData.append('action', action);
        fetch('/api/cookie-consent.php', {
            method: 'POST',
            body: formData
        }).catch(() => {});
    }

    acceptBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        saveConsent('accept');
    });

    declineBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        saveConsent('decline');
    });

    // Show popup with animation
    setTimeout(() => {
        popup.classList.add('visible');
    }, 1000);
})();
</script>
