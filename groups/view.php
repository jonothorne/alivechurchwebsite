<?php
/**
 * Public Group Detail Page
 */

require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/services/GroupsService.php';

$pdo = getDbConnection();
$groupsService = new GroupsService($pdo);

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    header('Location: /groups');
    exit;
}

$group = $groupsService->getGroupBySlug($slug);
if (!$group || $group['visibility'] === 'private' || $group['status'] !== 'active') {
    http_response_code(404);
    include __DIR__ . '/../404.php';
    exit;
}

$page_title = $group['name'] . ' | Groups | ' . $site['name'];
$page_description = $group['description'] ? substr(strip_tags($group['description']), 0, 160) : 'Join ' . $group['name'] . ' at Alive Church Norwich.';

$days = ['sunday'=>'Sunday','monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday'];

// Handle signup
$signupSuccess = false;
$signupError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup']) && isset($_SESSION['user_id'])) {
    $result = $groupsService->requestSignup($group['id'], $_SESSION['user_id'], trim($_POST['message'] ?? ''));
    if ($result['success']) {
        $signupSuccess = true;
    } else {
        $signupError = $result['error'];
    }
}

$isLoggedIn = isset($_SESSION['user_id']);
$isMember = $isLoggedIn ? $groupsService->isMember($group['id'], $_SESSION['user_id']) : false;

include __DIR__ . '/../includes/header.php';
?>

<article class="group-detail">
    <!-- Hero -->
    <section class="group-hero" style="<?= $group['image_url'] ? "background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.7)), url('" . htmlspecialchars($group['image_url']) . "');" : "background: " . htmlspecialchars($group['type_color'] ?? '#1f2937') . ";"; ?>">
        <div class="container narrow">
            <a href="/groups" class="back-link">&larr; All Groups</a>
            <span class="group-type-label"><?= htmlspecialchars($group['type_name']); ?></span>
            <h1><?= htmlspecialchars($group['name']); ?></h1>
            <?php if ($group['meeting_day']): ?>
                <p class="group-schedule">
                    <?= $days[$group['meeting_day']] ?? ucfirst($group['meeting_day']); ?>s
                    <?= $group['meeting_time'] ? ' at ' . date('g:i A', strtotime($group['meeting_time'])) : ''; ?>
                    <?php if ($group['meeting_frequency'] !== 'weekly'): ?>
                        (<?= ucfirst(str_replace('-', ' ', $group['meeting_frequency'])); ?>)
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <div class="container">
        <div class="group-layout">
            <!-- Main Content -->
            <div class="group-main">
                <?php if ($group['description']): ?>
                    <div class="group-description">
                        <?= nl2br(htmlspecialchars($group['description'])); ?>
                    </div>
                <?php endif; ?>

                <!-- Leaders -->
                <?php if ($group['leaders']): ?>
                    <div class="group-leaders">
                        <h3>Leaders</h3>
                        <div class="leaders-list">
                            <?php foreach ($group['leaders'] as $l): ?>
                                <div class="leader-card">
                                    <?php if ($l['profile_photo']): ?>
                                        <img src="<?= htmlspecialchars($l['profile_photo']); ?>" alt="" class="leader-photo">
                                    <?php else: ?>
                                        <div class="leader-photo leader-initials"><?= strtoupper(substr($l['first_name'], 0, 1)); ?></div>
                                    <?php endif; ?>
                                    <div class="leader-info">
                                        <span class="leader-name"><?= htmlspecialchars(trim($l['first_name'] . ' ' . $l['last_name'])); ?></span>
                                        <span class="leader-role"><?= ucfirst(str_replace('-', ' ', $l['role'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="group-sidebar">
                <!-- Join Card -->
                <div class="sidebar-card join-card">
                    <?php if ($signupSuccess): ?>
                        <div class="success-message">
                            <h3>Request Submitted!</h3>
                            <p>A leader will be in touch soon.</p>
                        </div>
                    <?php elseif ($isMember): ?>
                        <div class="member-badge">
                            <span>You're a member</span>
                        </div>
                    <?php elseif ($group['allow_signups']): ?>
                        <?php if ($group['max_members'] && $group['member_count'] >= $group['max_members']): ?>
                            <p class="full-notice">This group is currently full.</p>
                        <?php elseif ($isLoggedIn): ?>
                            <form method="post">
                                <input type="hidden" name="signup" value="1">
                                <?php if ($group['requires_approval']): ?>
                                    <div class="form-group">
                                        <label>Message to leaders (optional)</label>
                                        <textarea name="message" rows="3" placeholder="Tell us a bit about yourself..."></textarea>
                                    </div>
                                <?php endif; ?>
                                <?php if ($signupError): ?>
                                    <p class="error-message"><?= htmlspecialchars($signupError); ?></p>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <?= $group['requires_approval'] ? 'Request to Join' : 'Join This Group'; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <p>Sign in to join this group.</p>
                            <a href="/login?redirect=/groups/<?= htmlspecialchars($group['slug']); ?>" class="btn btn-primary btn-block">Sign In to Join</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>This group is not accepting online signups. Contact us for more info.</p>
                    <?php endif; ?>
                </div>

                <!-- Details Card -->
                <div class="sidebar-card">
                    <h3>Details</h3>
                    <dl class="details-list">
                        <?php if ($group['meeting_day']): ?>
                            <dt>When</dt>
                            <dd><?= $days[$group['meeting_day']] ?? ucfirst($group['meeting_day']); ?>s<?= $group['meeting_time'] ? ' at ' . date('g:i A', strtotime($group['meeting_time'])) : ''; ?></dd>
                        <?php endif; ?>

                        <?php if ($group['location_type'] === 'online'): ?>
                            <dt>Where</dt>
                            <dd>Online</dd>
                        <?php elseif ($group['location_name'] || $group['location_city']): ?>
                            <dt>Where</dt>
                            <dd>
                                <?= htmlspecialchars($group['location_name'] ?? ''); ?>
                                <?= $group['location_city'] ? '<br>' . htmlspecialchars($group['location_city']) : ''; ?>
                            </dd>
                        <?php endif; ?>

                        <?php if ($group['childcare_available']): ?>
                            <dt>Childcare</dt>
                            <dd>Available</dd>
                        <?php endif; ?>

                        <dt>Members</dt>
                        <dd><?= $group['member_count']; ?><?= $group['max_members'] ? ' / ' . $group['max_members'] : ''; ?></dd>
                    </dl>
                </div>

                <!-- Contact -->
                <?php if ($group['contact_email']): ?>
                    <div class="sidebar-card">
                        <h3>Questions?</h3>
                        <a href="mailto:<?= htmlspecialchars($group['contact_email']); ?>" class="btn btn-outline btn-block">Email the Leaders</a>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</article>

<style>
.group-hero { padding: 4rem 0; color: white; background-size: cover; background-position: center; text-align: center; }
.back-link { display: inline-block; color: rgba(255,255,255,0.8); text-decoration: none; margin-bottom: 1rem; }
.back-link:hover { color: white; }
.group-type-label { display: inline-block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.9; margin-bottom: 0.5rem; }
.group-hero h1 { font-size: 2.5rem; margin: 0 0 0.5rem; }
.group-schedule { font-size: 1.125rem; opacity: 0.9; }
.group-layout { display: grid; grid-template-columns: 1fr 350px; gap: 3rem; padding: 3rem 0; }
.group-description { font-size: 1.0625rem; line-height: 1.7; }
.group-leaders { margin-top: 2rem; }
.group-leaders h3 { margin-bottom: 1rem; }
.leaders-list { display: flex; flex-wrap: wrap; gap: 1rem; }
.leader-card { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; background: var(--color-surface-hover, #f9fafb); border-radius: 8px; }
.leader-photo { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; }
.leader-initials { display: flex; align-items: center; justify-content: center; background: var(--color-primary); color: white; font-weight: 600; }
.leader-info { display: flex; flex-direction: column; }
.leader-name { font-weight: 600; }
.leader-role { font-size: 0.75rem; color: var(--color-text-muted); }
.sidebar-card { background: var(--color-surface, #fff); border: 1px solid var(--color-border, #e5e7eb); border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; }
.sidebar-card h3 { margin: 0 0 1rem; font-size: 1rem; }
.join-card { background: var(--color-primary); color: white; }
.join-card h3 { color: white; }
.join-card .btn-primary { background: white; color: var(--color-primary); }
.join-card textarea { background: rgba(255,255,255,0.9); color: #1f2937; }
.success-message { text-align: center; }
.member-badge { text-align: center; padding: 1rem; background: rgba(255,255,255,0.2); border-radius: 8px; font-weight: 600; }
.full-notice { text-align: center; opacity: 0.9; }
.error-message { background: rgba(239,68,68,0.2); padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem; }
.details-list { margin: 0; }
.details-list dt { font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; margin-top: 0.75rem; }
.details-list dt:first-child { margin-top: 0; }
.details-list dd { margin: 0.25rem 0 0; font-weight: 500; }
.btn-block { width: 100%; }
@media (max-width: 900px) { .group-layout { grid-template-columns: 1fr; } .group-sidebar { order: -1; } }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
