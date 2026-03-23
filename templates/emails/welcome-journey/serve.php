<?php
/**
 * Serve Email - Week 4
 * "Ready to make a difference?"
 *
 * Sent 4 weeks after visit to encourage serving
 * Design: Inspiring, purpose-driven with clear opportunities
 */

$baseUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl .= '://' . ($_SERVER['HTTP_HOST'] ?? 'alivechur.ch');
$imageBaseUrl = 'https://alivechur.ch';

$preheader = "You have unique gifts. Ready to use them to make a difference?";
$hero_title = "Ready to Make a Difference?";
$hero_subtitle = "Your gifts can change lives";
$hero_emoji = "🙌";
$cta_text = "Explore Serve Opportunities";
$cta_url = $baseUrl . '/serve';

ob_start();
?>
<!-- Personal Greeting -->
<h1 style="margin: 0 0 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 28px; font-weight: 800; color: #1e1a2b; line-height: 1.3;">
    Hey <?= htmlspecialchars($firstName); ?>! 🙌
</h1>

<p style="margin: 0 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    It's been a few weeks since you visited Alive Church, and <strong style="color: #1e1a2b;">we hope you're starting to feel at home here!</strong>
</p>

<p style="margin: 0 0 32px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    We wanted to share something with you: <strong style="color: #eb008b;">some of the most fulfilled people in our church are those who serve.</strong> There's something amazing that happens when you use your gifts to help others.
</p>

<!-- Scripture Quote Box -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fdf2f8 0%, #f5f3ff 100%); border-left: 5px solid #eb008b; border-radius: 0 20px 20px 0; margin-bottom: 32px;">
    <tr>
        <td style="padding: 32px;">
            <p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-size: 20px; font-style: italic; color: #4b2679; line-height: 1.7;">
                "Each of you should use whatever gift you have received to serve others, as faithful stewards of God's grace."
            </p>
            <p style="margin: 16px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; font-weight: 600; color: #9ca3af; letter-spacing: 0.5px;">
                — 1 PETER 4:10
            </p>
        </td>
    </tr>
</table>

<p style="margin: 0 0 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    <strong style="color: #1e1a2b;">You have unique gifts.</strong> Whether you're creative, organized, great with kids, or love chatting with new people — there's a place for you to make a difference.
</p>

<!-- Section Header -->
<p style="margin: 0 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 12px; font-weight: 700; color: #eb008b; text-transform: uppercase; letter-spacing: 2px;">
    Ways You Can Serve
</p>

<!-- Serve Areas List -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <!-- Welcome Team -->
    <tr>
        <td style="padding: 16px 0; border-bottom: 1px solid #e5e7eb;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td width="56" valign="top">
                        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #eb008b 0%, #db2777 100%); border-radius: 14px; text-align: center; line-height: 48px;">
                            <span style="font-size: 22px;">👋</span>
                        </div>
                    </td>
                    <td style="padding-left: 18px;" valign="middle">
                        <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #1e1a2b;">Welcome Team</p>
                        <p style="margin: 4px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #6b7280;">Be the first smile someone sees on Sunday</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Worship & Production -->
    <tr>
        <td style="padding: 16px 0; border-bottom: 1px solid #e5e7eb;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td width="56" valign="top">
                        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); border-radius: 14px; text-align: center; line-height: 48px;">
                            <span style="font-size: 22px;">🎵</span>
                        </div>
                    </td>
                    <td style="padding-left: 18px;" valign="middle">
                        <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #1e1a2b;">Worship & Production</p>
                        <p style="margin: 4px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #6b7280;">Music, sound, lights, or visuals</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Kids Ministry -->
    <tr>
        <td style="padding: 16px 0; border-bottom: 1px solid #e5e7eb;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td width="56" valign="top">
                        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 14px; text-align: center; line-height: 48px;">
                            <span style="font-size: 22px;">👶</span>
                        </div>
                    </td>
                    <td style="padding-left: 18px;" valign="middle">
                        <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #1e1a2b;">Kids Ministry</p>
                        <p style="margin: 4px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #6b7280;">Help the next generation know Jesus</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Café Team -->
    <tr>
        <td style="padding: 16px 0; border-bottom: 1px solid #e5e7eb;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td width="56" valign="top">
                        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 14px; text-align: center; line-height: 48px;">
                            <span style="font-size: 22px;">☕</span>
                        </div>
                    </td>
                    <td style="padding-left: 18px;" valign="middle">
                        <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #1e1a2b;">Café Team</p>
                        <p style="margin: 4px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #6b7280;">Serve coffee and create community</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Media & Creative -->
    <tr>
        <td style="padding: 16px 0;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td width="56" valign="top">
                        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border-radius: 14px; text-align: center; line-height: 48px;">
                            <span style="font-size: 22px;">🎬</span>
                        </div>
                    </td>
                    <td style="padding-left: 18px;" valign="middle">
                        <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #1e1a2b;">Media & Creative</p>
                        <p style="margin: 4px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #6b7280;">Photography, video, design, and social media</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- No experience needed -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-radius: 16px; margin-top: 28px;">
    <tr>
        <td style="padding: 24px; text-align: center;">
            <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; color: #065f46;">
                <strong style="color: #047857;">No experience needed!</strong> We'll train you and match you with a team that fits your gifts and schedule.
            </p>
        </td>
    </tr>
</table>

<!-- Closing message -->
<p style="margin: 36px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; line-height: 1.7; color: #374151;">
    Ready to make a difference? Click below to explore opportunities and sign up!
</p>

<!-- Sign off -->
<p style="margin: 28px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 17px; color: #374151;">
    With gratitude,<br>
    <strong style="color: #1e1a2b;">The Alive Church Team</strong>
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base.php';
