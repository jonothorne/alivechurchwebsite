<?php
/**
 * Base Email Template - Premium Design
 * Beautiful, high-conversion email template for Alive Church
 *
 * Variables available:
 * - $firstName: Visitor's first name
 * - $name: Visitor's full name
 * - $email: Visitor's email
 * - $site: Site configuration array
 * - $content: Main email content (HTML)
 * - $preheader: Email preheader text (shows in inbox preview)
 * - $cta_text: Call-to-action button text
 * - $cta_url: Call-to-action button URL
 * - $hero_image: Hero image URL (optional)
 * - $hero_title: Hero title text (optional, for gradient headers)
 * - $hero_subtitle: Hero subtitle text (optional)
 * - $hero_emoji: Emoji for gradient header (optional)
 */

$baseUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl .= '://' . ($_SERVER['HTTP_HOST'] ?? 'alivechur.ch');

// Use production URL for images in emails (local dev won't work in email clients)
$imageBaseUrl = 'https://alivechur.ch';

// Default hero settings
$hero_title = $hero_title ?? '';
$hero_subtitle = $hero_subtitle ?? '';
$hero_emoji = $hero_emoji ?? '💜';
?>
<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title><?= htmlspecialchars($site['name'] ?? 'Alive Church'); ?></title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <style>
        table { border-collapse: collapse; }
        td, th { mso-line-height-rule: exactly; }
        .button-td { padding: 15px 30px !important; }
        .button-a { padding: 0 !important; }
    </style>
    <![endif]-->
    <style>
        /* Reset */
        body, table, td, p, a, li { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }

        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: #f0eff5;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
        }

        /* Links */
        a { color: #eb008b; text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* Button hover effect */
        .button-a:hover {
            background: linear-gradient(135deg, #d4007d 0%, #b3006d 100%) !important;
        }

        /* Responsive */
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .fluid { max-width: 100% !important; height: auto !important; }
            .stack-column { display: block !important; width: 100% !important; max-width: 100% !important; }
            .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
            .mobile-padding-hero { padding: 40px 24px !important; }
            .mobile-center { text-align: center !important; }
            .mobile-br { display: block !important; }
            .mobile-hide { display: none !important; }
            .mobile-full-width { width: 100% !important; }
            h1 { font-size: 24px !important; }
            .hero-title { font-size: 32px !important; }
            .hero-subtitle { font-size: 16px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f0eff5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">

    <!-- Preheader (shows in inbox preview) -->
    <div style="display: none; max-height: 0; overflow: hidden; font-size: 1px; line-height: 1px; color: #f0eff5;">
        <?= htmlspecialchars($preheader ?? ''); ?>
        &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
    </div>

    <center style="width: 100%; background-color: #f0eff5;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f0eff5;">
            <tr>
                <td style="padding: 30px 16px 50px;">

                    <!--[if mso]>
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center">
                    <tr>
                    <td>
                    <![endif]-->

                    <div class="email-container" style="max-width: 600px; margin: 0 auto;">

                        <!-- Logo Header -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td style="padding: 0 0 24px; text-align: center;">
                                    <a href="<?= $baseUrl; ?>" style="display: inline-block;">
                                        <img src="<?= $imageBaseUrl; ?>/assets/imgs/logo.png" width="120" alt="Alive Church" style="display: block; height: auto;">
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <!-- Main Email Card -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">

                            <?php if (!empty($hero_image)): ?>
                            <!-- Hero Image -->
                            <tr>
                                <td style="padding: 0; line-height: 0;">
                                    <img src="<?= htmlspecialchars($hero_image); ?>" width="600" alt="Alive Church welcome photo" style="width: 100%; max-width: 600px; height: auto; display: block;">
                                </td>
                            </tr>
                            <?php elseif (!empty($hero_title)): ?>
                            <!-- Gradient Hero Banner -->
                            <tr>
                                <td class="mobile-padding-hero" style="background: linear-gradient(135deg, #eb008b 0%, #9333ea 50%, #4b2679 100%); padding: 56px 48px; text-align: center;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="text-align: center;">
                                                <?php if (!empty($hero_emoji)): ?>
                                                <div style="font-size: 56px; line-height: 1; margin-bottom: 16px;"><?= $hero_emoji; ?></div>
                                                <?php endif; ?>
                                                <h1 class="hero-title" style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 36px; font-weight: 800; color: #ffffff; line-height: 1.2; letter-spacing: -0.5px;">
                                                    <?= htmlspecialchars($hero_title); ?>
                                                </h1>
                                                <?php if (!empty($hero_subtitle)): ?>
                                                <p class="hero-subtitle" style="margin: 12px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 18px; color: rgba(255, 255, 255, 0.9); line-height: 1.5;">
                                                    <?= htmlspecialchars($hero_subtitle); ?>
                                                </p>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- Main Content -->
                            <tr>
                                <td class="mobile-padding" style="padding: 48px 48px 40px;">
                                    <?= $content ?? ''; ?>

                                    <?php if (!empty($cta_url) && !empty($cta_text)): ?>
                                    <!-- Primary CTA Button -->
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 36px;">
                                        <tr>
                                            <td style="text-align: center;">
                                                <!--[if mso]>
                                                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?= htmlspecialchars($cta_url); ?>" style="height:56px;v-text-anchor:middle;width:260px;" arcsize="50%" strokecolor="#eb008b" fillcolor="#eb008b">
                                                <w:anchorlock/>
                                                <center style="color:#ffffff;font-family:sans-serif;font-size:16px;font-weight:bold;"><?= htmlspecialchars($cta_text); ?></center>
                                                </v:roundrect>
                                                <![endif]-->
                                                <!--[if !mso]><!-->
                                                <a href="<?= htmlspecialchars($cta_url); ?>" class="button-a" style="display: inline-block; background: linear-gradient(135deg, #eb008b 0%, #c4007a 100%); color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 700; text-decoration: none; padding: 18px 48px; border-radius: 50px; box-shadow: 0 4px 14px rgba(235, 0, 139, 0.4); transition: all 0.2s ease;">
                                                    <?= htmlspecialchars($cta_text); ?>
                                                </a>
                                                <!--<![endif]-->
                                            </td>
                                        </tr>
                                    </table>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Service Info Banner -->
                            <tr>
                                <td style="padding: 0 32px 32px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fdf2f8 0%, #f5f3ff 100%); border-radius: 16px;">
                                        <tr>
                                            <td style="padding: 28px 24px; text-align: center;">
                                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                    <tr>
                                                        <td width="60" valign="middle" style="text-align: center;">
                                                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #eb008b 0%, #4b2679 100%); border-radius: 12px; line-height: 48px; text-align: center;">
                                                                <span style="font-size: 24px; line-height: 48px;">⛪</span>
                                                            </div>
                                                        </td>
                                                        <td style="padding-left: 16px; text-align: left;">
                                                            <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 11px; font-weight: 700; color: #eb008b; text-transform: uppercase; letter-spacing: 1.5px;">
                                                                Join Us Sunday
                                                            </p>
                                                            <p style="margin: 4px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 18px; font-weight: 700; color: #1e1a2b;">
                                                                11:00 AM
                                                            </p>
                                                            <p style="margin: 2px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #6b7280;">
                                                                Alive House, Nelson Street, Norwich
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

                        <!-- Footer -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td style="padding: 32px 20px 0; text-align: center;">

                                    <!-- Social Links -->
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                        <tr>
                                            <td style="padding: 0 8px;">
                                                <a href="https://facebook.com/alivechurchnorwich" style="display: inline-block; width: 36px; height: 36px; background-color: #ffffff; border-radius: 50%; text-align: center; line-height: 36px; text-decoration: none; box-shadow: 0 2px 4px rgba(0,0,0,0.06);">
                                                    <img src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6/svgs/brands/facebook-f.svg" width="14" height="14" alt="Facebook" style="display: inline-block; vertical-align: middle; opacity: 0.6;">
                                                </a>
                                            </td>
                                            <td style="padding: 0 8px;">
                                                <a href="https://instagram.com/alivechurchnorwich" style="display: inline-block; width: 36px; height: 36px; background-color: #ffffff; border-radius: 50%; text-align: center; line-height: 36px; text-decoration: none; box-shadow: 0 2px 4px rgba(0,0,0,0.06);">
                                                    <img src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6/svgs/brands/instagram.svg" width="14" height="14" alt="Instagram" style="display: inline-block; vertical-align: middle; opacity: 0.6;">
                                                </a>
                                            </td>
                                            <td style="padding: 0 8px;">
                                                <a href="https://youtube.com/@alivechurch" style="display: inline-block; width: 36px; height: 36px; background-color: #ffffff; border-radius: 50%; text-align: center; line-height: 36px; text-decoration: none; box-shadow: 0 2px 4px rgba(0,0,0,0.06);">
                                                    <img src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6/svgs/brands/youtube.svg" width="14" height="14" alt="YouTube" style="display: inline-block; vertical-align: middle; opacity: 0.6;">
                                                </a>
                                            </td>
                                        </tr>
                                    </table>

                                    <p style="margin: 24px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #6b7280; line-height: 1.6;">
                                        <strong style="color: #374151;">Alive Church</strong><br>
                                        Alive House, Nelson Street, Norwich NR2 4DR
                                    </p>

                                    <p style="margin: 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 12px; color: #9ca3af;">
                                        <a href="<?= $baseUrl; ?>" style="color: #eb008b; text-decoration: none; font-weight: 500;">Visit Website</a>
                                        <span style="color: #d1d5db; padding: 0 8px;">•</span>
                                        <a href="<?= $baseUrl; ?>/unsubscribe?token={{unsubscribe_token}}" style="color: #9ca3af; text-decoration: underline;">Unsubscribe</a>
                                    </p>

                                    <p style="margin: 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 11px; color: #d1d5db;">
                                        You're receiving this because you registered to visit Alive Church.
                                    </p>

                                </td>
                            </tr>
                        </table>

                    </div>

                    <!--[if mso]>
                    </td>
                    </tr>
                    </table>
                    <![endif]-->

                </td>
            </tr>
        </table>
    </center>
</body>
</html>
