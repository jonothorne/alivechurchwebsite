<?php
/**
 * Post-Visit Email - Day 1 after visit
 * "How was your first visit?"
 *
 * Sent the day after their expected visit (Monday)
 * Design: Warm follow-up with clear next steps
 */

$baseUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl .= '://' . ($_SERVER['HTTP_HOST'] ?? 'alivechur.ch');
$imageBaseUrl = 'https://alivechur.ch';

$preheader = "We hope you had an amazing time! Here's what's next on your journey...";
$hero_title = "Hope You Had a Great Time!";
$hero_subtitle = "We loved having you with us";
$hero_emoji = "🎉";
$cta_text = "Watch Sunday's Message";
$cta_url = $baseUrl . '/sermons';

ob_start();
?>
<!-- Personal Greeting -->
<h1 style="margin: 0 0 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 28px; font-weight: 800; color: #1e1a2b; line-height: 1.3;">
    Hey <?= htmlspecialchars($firstName); ?>! 🎉
</h1>

<p style="margin: 0 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    <strong style="color: #1e1a2b;">Hope you had an amazing time at Alive Church!</strong> It was so great having you with us yesterday.
</p>

<p style="margin: 0 0 32px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    We'd genuinely love to know how it went. Got questions? Want to share feedback? We're all ears!
</p>

<!-- Feedback Box -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fdf2f8 0%, #f5f3ff 100%); border-radius: 20px; margin-bottom: 36px;">
    <tr>
        <td style="padding: 32px; text-align: center;">
            <p style="margin: 0 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 18px; font-weight: 700; color: #1e1a2b;">
                How was your experience?
            </p>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                <tr>
                    <td style="padding-right: 12px;">
                        <a href="mailto:<?= htmlspecialchars($site['email'] ?? 'office@alive.me.uk'); ?>?subject=My%20Visit%20to%20Alive%20Church" style="display: inline-block; background-color: #ffffff; color: #4b2679; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; font-weight: 700; text-decoration: none; padding: 14px 28px; border-radius: 50px; border: 2px solid #4b2679; box-shadow: 0 2px 8px rgba(75, 38, 121, 0.15);">
                            💬 Email Us
                        </a>
                    </td>
                    <td>
                        <a href="<?= $baseUrl; ?>/contact-us" style="display: inline-block; background-color: #ffffff; color: #eb008b; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; font-weight: 700; text-decoration: none; padding: 14px 28px; border-radius: 50px; border: 2px solid #eb008b; box-shadow: 0 2px 8px rgba(235, 0, 139, 0.15);">
                            📝 Contact Form
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Section Header -->
<p style="margin: 0 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 12px; font-weight: 700; color: #eb008b; text-transform: uppercase; letter-spacing: 2px;">
    Stay Connected This Week
</p>

<!-- Next Steps Cards -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <!-- Card 1: Watch Sermons -->
    <tr>
        <td style="padding-bottom: 14px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 16px;">
                <tr>
                    <td style="padding: 22px 24px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td width="50" valign="middle">
                                    <div style="font-size: 32px; line-height: 1;">🙏</div>
                                </td>
                                <td style="padding-left: 16px;" valign="middle">
                                    <a href="<?= $baseUrl; ?>/sermons" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #4b2679; text-decoration: none;">
                                        Watch Sermons Online →
                                    </a>
                                    <p style="margin: 4px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #6b7280; line-height: 1.5;">
                                        Rewatch Sunday's message or catch up on past series
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Card 2: Bible Study -->
    <tr>
        <td style="padding-bottom: 14px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-radius: 16px;">
                <tr>
                    <td style="padding: 22px 24px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td width="50" valign="middle">
                                    <div style="font-size: 32px; line-height: 1;">📖</div>
                                </td>
                                <td style="padding-left: 16px;" valign="middle">
                                    <a href="<?= $baseUrl; ?>/bible-study" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #059669; text-decoration: none;">
                                        Start a Reading Plan →
                                    </a>
                                    <p style="margin: 4px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #6b7280; line-height: 1.5;">
                                        Dive deeper into the Bible with our study guides
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Card 3: Instagram -->
    <tr>
        <td>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%); border-radius: 16px;">
                <tr>
                    <td style="padding: 22px 24px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td width="50" valign="middle">
                                    <div style="font-size: 32px; line-height: 1;">📱</div>
                                </td>
                                <td style="padding-left: 16px;" valign="middle">
                                    <a href="https://instagram.com/alivechurchnorwich" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #eb008b; text-decoration: none;">
                                        Follow Us on Instagram →
                                    </a>
                                    <p style="margin: 4px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #6b7280; line-height: 1.5;">
                                        Stay connected and see what's happening mid-week
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Closing message -->
<p style="margin: 36px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    We hope to see you again soon. Remember — <strong style="color: #eb008b;">you belong here</strong>. 💜
</p>

<!-- Sign off -->
<p style="margin: 28px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; color: #374151;">
    With love,<br>
    <strong style="color: #1e1a2b;">The Alive Church Team</strong>
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base.php';
