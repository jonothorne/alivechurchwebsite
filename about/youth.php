<?php
/**
 * Youth Page
 */
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/hero-textures.php';

$page_title = 'Alive Youth | ' . $site['name'];
$page_description = 'Alive Youth is for young people aged 11-18. We meet every Saturday for games, dinner, teaching and community. Join us!';
$hero_texture_class = get_random_texture();

// Initialize CMS
require_once __DIR__ . '/../includes/cms/ContentManager.php';
$cms = new ContentManager('about-youth');

include __DIR__ . '/../includes/header.php';
?>

<section class="page-hero youth-hero">
    <div class="container narrow">
        <?= $cms->text('hero_eyebrow', 'Ages 11-18', ['tag' => 'p', 'class' => 'eyebrow']); ?>
        <?= $cms->image('hero_logo', '/assets/imgs/youth/alive-youth-logo.png', 'Alive Youth', ['class' => 'youth-logo']); ?>
        <h1 class="visually-hidden">Alive Youth</h1>
        <?= $cms->text('hero_tagline', 'Ignite your faith, impact your world.', ['tag' => 'p', 'class' => 'tagline']); ?>
        <?= $cms->text('hero_subtext', 'A place where you can be yourself, make real friends, and discover what it means to follow Jesus. Every Saturday.', ['tag' => 'p']); ?>
        <div class="hero-actions">
            <?= $cms->html('hero_buttons', '<a href="https://youth.alivechur.ch/enrol" class="btn btn-primary" target="_blank" rel="noopener">Enrol Now</a>
            <a href="#what-happens" class="btn btn-secondary">Learn More</a>', ['tag' => 'div']); ?>
        </div>
    </div>
</section>

<section id="what-happens" class="content-section">
    <div class="container split">
        <div>
            <?= $cms->text('about_headline', 'More than just a youth group', ['tag' => 'h2']); ?>
            <?= $cms->html('about_content', '<p class="lead">Alive Youth is a community of young people aged 11-18 who are passionate about life, friendship, and faith.</p>
            <p>Every Saturday, we gather for an action-packed afternoon of games, dinner together, real talk, and tons of fun. Whether you\'re exploring faith for the first time or you\'ve been following Jesus for years, there\'s a place for you here.</p>
            <p>But youth isn\'t just about Saturdays. On Sundays, our young people sit together during the main church service – part of the wider Alive family. Many also serve on teams across the church, using their gifts in worship, tech, kids work, and more.</p>
            <p>We believe that your teenage years are some of the most important of your life – and we want to walk alongside you, helping you navigate the big questions, build lifelong friendships, and discover who God made you to be.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
        </div>
        <div class="card highlight-card">
            <?= $cms->text('meeting_title', 'When We Meet', ['tag' => 'h3']); ?>
            <?= $cms->html('meeting_details', '<div class="meeting-details">
                <div class="meeting-item">
                    <span class="meeting-icon" aria-hidden="true">📅</span>
                    <div>
                        <strong>Every Saturday</strong>
                        <span>Term time</span>
                    </div>
                </div>
                <div class="meeting-item">
                    <span class="meeting-icon" aria-hidden="true">🕓</span>
                    <div>
                        <strong>4:00pm - 7:00pm</strong>
                        <span>Doors open 3:45pm</span>
                    </div>
                </div>
                <div class="meeting-item">
                    <span class="meeting-icon" aria-hidden="true">📍</span>
                    <div>
                        <strong>Alive House</strong>
                        <span>Nelson Street, Norwich</span>
                    </div>
                </div>
                <div class="meeting-item">
                    <span class="meeting-icon" aria-hidden="true">👥</span>
                    <div>
                        <strong>Ages 11-18</strong>
                        <span>School years 7-13</span>
                    </div>
                </div>
            </div>', ['tag' => 'div']); ?>
        </div>
    </div>
</section>

<section class="content-section alt">
    <div class="container">
        <div class="section-heading">
            <?= $cms->text('typical_eyebrow', 'Saturdays', ['tag' => 'p', 'class' => 'eyebrow light']); ?>
            <?= $cms->text('typical_headline', 'What happens at youth?', ['tag' => 'h2']); ?>
        </div>
        <?= $cms->html('features_content', '<div class="card-grid four-col">
            <div class="feature-card">
                <div class="feature-icon" aria-hidden="true">🎮</div>
                <h3>Epic Games</h3>
                <p>We kick off with high-energy games, competitions, and challenges. From team battles to silly relays – it\'s always a blast.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" aria-hidden="true">🍽️</div>
                <h3>Dinner Together</h3>
                <p>We eat together as a community – a proper sit-down meal, completely free. It\'s a great time to chat and connect with friends.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" aria-hidden="true">💬</div>
                <h3>Real Talk</h3>
                <p>Short, relevant teaching that tackles the real stuff – identity, relationships, purpose, faith, and navigating life.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" aria-hidden="true">🎱</div>
                <h3>Hang Time</h3>
                <p>Tuck shop, pool table, chill zones – plenty of time to hang out with friends and make new ones.</p>
            </div>
        </div>', ['tag' => 'div']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="section-heading">
            <?= $cms->text('beyond_eyebrow', 'Beyond Saturdays', ['tag' => 'p', 'class' => 'eyebrow light']); ?>
            <?= $cms->text('beyond_headline', 'There\'s more to youth than Saturday', ['tag' => 'h2']); ?>
        </div>
        <?= $cms->html('beyond_content', '<div class="card-grid three-col">
            <div class="card">
                <h3><span aria-hidden="true">🏕️</span> Youth Weekends Away</h3>
                <p>Throughout the year we head off on epic residential weekends – adventure activities, late night sessions, and memories that last a lifetime.</p>
            </div>
            <div class="card">
                <h3><span aria-hidden="true">🎪</span> Summer Events</h3>
                <p>We take groups to Christian festivals and events – incredible times of community with thousands of other young people.</p>
            </div>
            <div class="card">
                <h3><span aria-hidden="true">🙏</span> Small Groups</h3>
                <p>Midweek small groups give you a chance to go deeper – exploring faith, asking questions, and building friendships in a smaller setting.</p>
            </div>
            <div class="card">
                <h3><span aria-hidden="true">🎭</span> Creative Opportunities</h3>
                <p>Got a talent? Whether it\'s music, drama, tech, or media – there are opportunities to serve, grow your gifts, and be part of something amazing.</p>
            </div>
            <div class="card">
                <h3><span aria-hidden="true">❤️</span> Outreach Projects</h3>
                <p>We believe young people can change the world. Join us for community service projects, mission trips, and making a real difference in Norwich.</p>
            </div>
            <div class="card">
                <h3><span aria-hidden="true">📱</span> Online Community</h3>
                <p>Stay connected throughout the week through our youth social channels, group chats, and online hangouts.</p>
            </div>
        </div>', ['tag' => 'div']); ?>
    </div>
</section>

<section class="content-section testimonial-section">
    <div class="container narrow text-center">
        <?= $cms->html('testimonial', '<blockquote class="youth-quote">
            <p>"You never leave youth without laughing, there is always something great happening."</p>
            <cite>– Alive Youth member</cite>
        </blockquote>', ['tag' => 'div']); ?>
    </div>
</section>

<section class="content-section alt youth-values">
    <div class="container narrow">
        <div class="section-heading">
            <?= $cms->text('values_headline', 'What we\'re about', ['tag' => 'h2']); ?>
        </div>
        <?= $cms->html('values_content', '<div class="values-grid">
            <div class="value-item">
                <h3>Everyone Belongs</h3>
                <p>No cliques, no outsiders. Whether you\'ve been coming for years or it\'s your first time, you\'re welcome here. Come as you are.</p>
            </div>
            <div class="value-item">
                <h3>Real Friendships</h3>
                <p>We\'re not just acquaintances – we\'re a family. The friendships you build here will be some of the best of your life.</p>
            </div>
            <div class="value-item">
                <h3>Faith That\'s Relevant</h3>
                <p>We tackle the real questions. No sugar-coating, no pretending life is easy. Honest faith for the real world.</p>
            </div>
            <div class="value-item">
                <h3>A Safe Space</h3>
                <p>Youth should be a place where you feel safe to be yourself, ask questions, and grow. We take safeguarding seriously.</p>
            </div>
        </div>', ['tag' => 'div']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="section-heading">
            <?= $cms->text('team_eyebrow', 'The Team', ['tag' => 'p', 'class' => 'eyebrow light']); ?>
            <?= $cms->text('team_headline', 'Led by people who care', ['tag' => 'h2']); ?>
            <?= $cms->text('team_subtext', 'Our youth team is made up of passionate leaders who are committed to seeing young people thrive. All our leaders are DBS checked and trained in safeguarding.', ['tag' => 'p']); ?>
        </div>
        <?= $cms->html('team_profiles', '<div class="card-grid profile-grid">
            <article class="profile-card">
                <img src="/assets/imgs/gallery/alive-church-worship-leaders-performance.jpg" alt="Youth leadership" class="profile-card-image">
                <h3>Jono Thorne</h3>
                <p class="role">Youth Pastor</p>
                <p>Jono leads our youth ministry with passion and energy. He\'s all about helping young people discover their purpose and live fully alive.</p>
            </article>
            <article class="profile-card">
                <img src="/assets/imgs/gallery/alive-church-congregation-hands-raised.jpg" alt="Youth team" class="profile-card-image">
                <h3>The Youth Team</h3>
                <p class="role">Volunteers & Leaders</p>
                <p>A brilliant team of volunteers who give their time every week to invest in the next generation. They\'re here to support, encourage, and have fun!</p>
            </article>
        </div>', ['tag' => 'div']); ?>
    </div>
</section>

<section class="faq-section">
    <div class="container narrow">
        <div class="section-heading">
            <?= $cms->text('faq_headline', 'Got questions?', ['tag' => 'h2']); ?>
        </div>
        <?= $cms->html('faq_content', '<details class="faq-item">
            <summary>Do I need to be a Christian to come?</summary>
            <p>Absolutely not! Alive Youth is for everyone – whether you\'re exploring faith, have lots of questions, or have been following Jesus for years. You\'re welcome here.</p>
        </details>
        <details class="faq-item">
            <summary>What if I don\'t know anyone?</summary>
            <p>We\'ve all been there! Our team are great at welcoming new people and helping you feel at home. Most people come on their own the first time – you\'ll make friends quickly.</p>
        </details>
        <details class="faq-item">
            <summary>Do my parents need to stay?</summary>
            <p>Nope! Parents can drop off and pick up. We just need you to be enrolled with an emergency contact and permission form completed.</p>
        </details>
        <details class="faq-item">
            <summary>Is there a cost?</summary>
            <p>No! Saturday sessions including dinner are completely free. We just ask for a small contribution towards trips and special events.</p>
        </details>
        <details class="faq-item">
            <summary>What about safeguarding?</summary>
            <p>The safety of young people is our top priority. All our leaders are DBS checked, trained in safeguarding, and we follow strict policies to ensure youth is a safe environment.</p>
        </details>
        <details class="faq-item">
            <summary>How do I sign up?</summary>
            <p>Just click the "Enrol Now" button to complete our online registration form. You\'ll need a parent/guardian to complete the permission form before your first session.</p>
        </details>', ['tag' => 'div', 'class' => 'faq-list']); ?>
    </div>
</section>

<section class="content-section cta-section alt">
    <div class="container narrow text-center">
        <?= $cms->text('cta_headline', 'Ready to join us?', ['tag' => 'h2']); ?>
        <?= $cms->text('cta_text', 'We\'d love to see you on Saturday! Complete the enrolment form and we\'ll be ready to welcome you.', ['tag' => 'p']); ?>
        <?= $cms->html('cta_buttons', '<div class="cta-buttons">
            <a href="https://youth.alivechur.ch/enrol" class="btn btn-primary btn-large" target="_blank" rel="noopener">Enrol Now</a>
        </div>
        <p class="cta-note">
            <small>Parents: please complete the <a href="https://youth.alivechur.ch/enrol" target="_blank" rel="noopener">permission form</a> before your young person\'s first session.</small>
        </p>', ['tag' => 'div']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow text-center">
        <?= $cms->text('contact_headline', 'Questions?', ['tag' => 'h3']); ?>
        <?= $cms->html('contact_content', '<p>Get in touch with our youth team – we\'re happy to answer any questions you have.</p>
        <p><a href="/contact-us" class="btn btn-secondary">Contact Us</a></p>', ['tag' => 'div']); ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
