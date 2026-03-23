<?php
/**
 * Join a Group Email - Week 2
 * "Life is better together"
 *
 * Sent 2 weeks after visit to encourage small group connection
 * Design: Inviting, community-focused with clear value proposition
 */

$baseUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl .= '://' . ($_SERVER['HTTP_HOST'] ?? 'alivechur.ch');
$imageBaseUrl = 'https://alivechur.ch';

$preheader = "Ready to go deeper? Join a group and find your people.";
$hero_title = "Life Is Better Together";
$hero_subtitle = "Find your community";
$hero_emoji = "👥";
$cta_text = "Find Your Group";
$cta_url = $baseUrl . '/groups';

ob_start();
?>
<!-- Personal Greeting -->
<h1 style="margin: 0 0 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 28px; font-weight: 800; color: #1e1a2b; line-height: 1.3;">
    Hey <?= htmlspecialchars($firstName); ?>! 👋
</h1>

<p style="margin: 0 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    It's been a couple of weeks since you visited Alive Church, and <strong style="color: #1e1a2b;">we've been thinking about you!</strong>
</p>

<p style="margin: 0 0 32px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    Here's something we've learned: <strong style="color: #eb008b;">life is better together.</strong> Sunday services are amazing, but real life change happens in community — in small groups where you can be known, supported, and grow.
</p>

<!-- Why Join Box -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #4b2679 0%, #7c3aed 100%); border-radius: 20px; margin-bottom: 32px;">
    <tr>
        <td style="padding: 36px;">
            <p style="margin: 0 0 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 20px; font-weight: 800; color: #ffffff;">
                Why Join a Group?
            </p>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td style="padding: 10px 0;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td width="36" valign="top" style="font-size: 20px; line-height: 1.4;">✨</td>
                                <td style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 15px; color: #ffffff; line-height: 1.6;">
                                    <strong>Make real friends</strong> who genuinely care about your life
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px 0;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td width="36" valign="top" style="font-size: 20px; line-height: 1.4;">📖</td>
                                <td style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 15px; color: #ffffff; line-height: 1.6;">
                                    <strong>Grow in faith</strong> through discussion and accountability
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px 0;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td width="36" valign="top" style="font-size: 20px; line-height: 1.4;">🤝</td>
                                <td style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 15px; color: #ffffff; line-height: 1.6;">
                                    <strong>Get support</strong> when life gets tough (and celebrate the wins!)
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px 0;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td width="36" valign="top" style="font-size: 20px; line-height: 1.4;">🏠</td>
                                <td style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 15px; color: #ffffff; line-height: 1.6;">
                                    <strong>Feel at home</strong> in a smaller, more intimate setting
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin: 0 0 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    We have groups for all stages of life — young adults, families, men's groups, women's groups, and more. Most meet weekly in homes around Norwich.
</p>

<!-- Section Header -->
<p style="margin: 0 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 12px; font-weight: 700; color: #eb008b; text-transform: uppercase; letter-spacing: 2px;">
    Groups For Everyone
</p>

<!-- Group Types Grid - 2x2 -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td width="48%" style="padding-right: 2%; padding-bottom: 12px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%); border-radius: 16px;">
                <tr>
                    <td style="padding: 24px; text-align: center;">
                        <div style="font-size: 40px; margin-bottom: 10px; line-height: 1;">👨‍👩‍👧‍👦</div>
                        <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; font-weight: 700; color: #1e1a2b;">Family Groups</p>
                    </td>
                </tr>
            </table>
        </td>
        <td width="48%" style="padding-left: 2%; padding-bottom: 12px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 16px;">
                <tr>
                    <td style="padding: 24px; text-align: center;">
                        <div style="font-size: 40px; margin-bottom: 10px; line-height: 1;">👫</div>
                        <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; font-weight: 700; color: #1e1a2b;">Young Adults</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td width="48%" style="padding-right: 2%;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-radius: 16px;">
                <tr>
                    <td style="padding: 24px; text-align: center;">
                        <div style="font-size: 40px; margin-bottom: 10px; line-height: 1;">👨‍👨‍👦</div>
                        <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; font-weight: 700; color: #1e1a2b;">Men's Groups</p>
                    </td>
                </tr>
            </table>
        </td>
        <td width="48%" style="padding-left: 2%;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 16px;">
                <tr>
                    <td style="padding: 24px; text-align: center;">
                        <div style="font-size: 40px; margin-bottom: 10px; line-height: 1;">👩‍👩‍👧</div>
                        <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; font-weight: 700; color: #1e1a2b;">Women's Groups</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Closing message -->
<p style="margin: 36px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    Ready to find your people? Click below to browse our groups and sign up!
</p>

<!-- Sign off -->
<p style="margin: 28px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; color: #374151;">
    Cheering you on,<br>
    <strong style="color: #1e1a2b;">The Alive Church Team</strong>
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base.php';
