<?php
/**
 * Welcome Email - Day 0
 * "We can't wait to meet you!"
 *
 * Sent immediately when someone registers to visit
 * Design: Warm, personal, high-energy welcome with practical info
 */

$baseUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl .= '://' . ($_SERVER['HTTP_HOST'] ?? 'alivechur.ch');
$imageBaseUrl = 'https://alivechur.ch';

$preheader = "We can't wait to meet you this Sunday! Here's everything you need to know.";
$hero_image = $imageBaseUrl . '/assets/imgs/gallery/alive-church-worship-congregation.jpg';
$cta_text = "Get Directions";
$cta_url = $site['maps_url'] ?? 'https://maps.google.com/?q=Alive+House+Nelson+Street+Norwich+NR2+4DR';

ob_start();
?>
<!-- Personal Greeting -->
<h1 style="margin: 0 0 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 28px; font-weight: 800; color: #1e1a2b; line-height: 1.3;">
    Hey <?= htmlspecialchars($firstName); ?>! 👋
</h1>

<p style="margin: 0 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    <strong style="color: #1e1a2b;">We're genuinely excited you're coming to visit!</strong> Seriously, you just made our day.
</p>

<p style="margin: 0 0 32px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    We know visiting a new church can feel a bit nerve-wracking, so we want to make sure you feel completely at home from the moment you walk through our doors.
</p>

<!-- Section Header -->
<p style="margin: 0 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 12px; font-weight: 700; color: #eb008b; text-transform: uppercase; letter-spacing: 2px;">
    What to Expect
</p>

<!-- Info Cards -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <!-- Card 1: Coffee -->
    <tr>
        <td style="padding-bottom: 16px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%); border-radius: 16px;">
                <tr>
                    <td style="padding: 24px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td width="56" valign="top">
                                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #eb008b 0%, #db2777 100%); border-radius: 14px; text-align: center; line-height: 48px;">
                                        <span style="font-size: 24px;">☕</span>
                                    </div>
                                </td>
                                <td style="padding-left: 18px;" valign="top">
                                    <p style="margin: 0 0 6px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #1e1a2b;">
                                        Arrive from 10:30 AM
                                    </p>
                                    <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; color: #6b7280; line-height: 1.6;">
                                        Grab a <strong style="color: #374151;">free coffee & pastry</strong>. Our welcome team will greet you and help you find a seat.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Card 2: Worship -->
    <tr>
        <td style="padding-bottom: 16px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 16px;">
                <tr>
                    <td style="padding: 24px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td width="56" valign="top">
                                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); border-radius: 14px; text-align: center; line-height: 48px;">
                                        <span style="font-size: 24px;">🎵</span>
                                    </div>
                                </td>
                                <td style="padding-left: 18px;" valign="top">
                                    <p style="margin: 0 0 6px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #1e1a2b;">
                                        Service at 11:00 AM
                                    </p>
                                    <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; color: #6b7280; line-height: 1.6;">
                                        <strong style="color: #374151;">Live worship, a relevant message</strong>, and genuine community. About 75 minutes total.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Card 3: Kids -->
    <tr>
        <td style="padding-bottom: 16px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-radius: 16px;">
                <tr>
                    <td style="padding: 24px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td width="56" valign="top">
                                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 14px; text-align: center; line-height: 48px;">
                                        <span style="font-size: 24px;">👶</span>
                                    </div>
                                </td>
                                <td style="padding-left: 18px;" valign="top">
                                    <p style="margin: 0 0 6px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #1e1a2b;">
                                        Got kids? We've got you!
                                    </p>
                                    <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; color: #6b7280; line-height: 1.6;">
                                        Kids join us for worship then head to their <strong style="color: #374151;">fun, age-appropriate programme</strong>. Safe & secure.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Card 4: Come as you are -->
    <tr>
        <td>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 16px;">
                <tr>
                    <td style="padding: 24px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td width="56" valign="top">
                                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 14px; text-align: center; line-height: 48px;">
                                        <span style="font-size: 24px;">👟</span>
                                    </div>
                                </td>
                                <td style="padding-left: 18px;" valign="top">
                                    <p style="margin: 0 0 6px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #1e1a2b;">
                                        Come as you are
                                    </p>
                                    <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; color: #6b7280; line-height: 1.6;">
                                        Jeans? Great. Suit? Also great. <strong style="color: #374151;">We care way more about you</strong> than what you're wearing.
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

<!-- Questions -->
<p style="margin: 36px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; line-height: 1.7; color: #374151;">
    Questions before you come? Just <a href="mailto:<?= htmlspecialchars($site['email'] ?? 'office@alive.me.uk'); ?>" style="color: #eb008b; font-weight: 600; text-decoration: none;">reply to this email</a> — we'd love to help!
</p>

<!-- Sign off -->
<p style="margin: 28px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; color: #374151;">
    See you Sunday! 💜<br>
    <strong style="color: #1e1a2b;">The Alive Church Team</strong>
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base.php';
