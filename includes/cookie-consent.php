<?php
/**
 * Cookie Consent Popup Component
 * Include this at the end of the page (before closing body tag)
 */

// Check if consent already given
$has_consent = false;

if (isset($current_user) && $current_user) {
    // Check database for logged-in users
    $stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
    $stmt->execute([$current_user['id']]);
    $row = $stmt->fetch();
    if ($row && $row['preferences']) {
        $preferences = json_decode($row['preferences'], true);
        $has_consent = isset($preferences['cookie_consent']);
    }
}

// Fall back to session check
if (!$has_consent && isset($_SESSION['cookie_consent'])) {
    $has_consent = true;
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

    function saveConsent(action) {
        // Immediately hide to give user feedback
        hidePopup();

        const formData = new FormData();
        formData.append('action', action);

        fetch('/api/cookie-consent.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Cookie consent saved:', data);
        })
        .catch(error => {
            console.error('Cookie consent error:', error);
        });
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
