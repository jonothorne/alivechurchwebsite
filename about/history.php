<?php
/**
 * Our History Page
 */
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/hero-textures.php';

$page_title = 'Our History | ' . $site['name'];
$page_description = 'Discover the story of ' . $site['name'] . ' - from nine people with a vision to a thriving church family transforming Norwich and beyond.';
$hero_texture_class = get_random_texture();

// Initialize CMS
require_once __DIR__ . '/../includes/cms/ContentManager.php';
$cms = new ContentManager('about-history');

include __DIR__ . '/../includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <?= $cms->text('hero_eyebrow', 'Our Story', ['tag' => 'p', 'class' => 'eyebrow']); ?>
        <?= $cms->text('hero_headline', 'Our History', ['tag' => 'h1']); ?>
        <?= $cms->text('hero_subtext', 'From nine people with a God-given vision to a thriving church family – this is the story of how God has moved in Norwich for over four decades.', ['tag' => 'p']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('beginnings_headline', '1985–1986: The Beginning', ['tag' => 'h2']); ?>
        <?= $cms->html('beginnings_content', '<p>Alive Church began as City Church in 1985, when just nine people gathered in Norwich with a simple but powerful vision: <strong>Love God, love one another, and love the world.</strong></p>
        <p>Inspired by Isaiah 58:12 – to become "repairers of the breach" – this small group believed God was calling them to bring restoration and hope to their city. By October 1986, fifteen people had committed to leasing the church\'s first building, stepping out in faith with a £35,000 commitment and furnishing it through a sacrificial £6,000 offering.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('faith_headline', '1988: A Step of Faith', ['tag' => 'h2']); ?>
        <?= $cms->html('faith_content', '<p>In a defining moment for the young church, founder Pastor Philip Thorne resigned from his secure job at the Post Office to lead the church full-time. That same year, City Church sent its first international mission team to Mannheim, Germany, where they strengthened a struggling church through street evangelism and practical support. This heart for mission – both local and global – would become a hallmark of the church\'s identity.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('growth_headline', '1990–1992: Rapid Growth & Regional Impact', ['tag' => 'h2']); ?>
        <?= $cms->html('growth_content', '<p>By 1990, the church had outgrown its first building, Centrepoint on Queens Road. Sunday meetings moved to CNS School Hall and then to the City Suite at Hotel Norwich (now the Mercure). Growth wasn\'t just numerical – the church was gaining influence across the region.</p>
        <p>In 1991, City Church joined Ground Level, connecting with 80 churches across the UK. That year, they hosted <strong>"Together for Jesus"</strong>, a large regional worship gathering that brought over 1,700 Christians from 170 churches together at Norwich Sports Village.</p>
        <p>The following year, Pastor Philip chaired the Norwich March for Jesus, where 2,000 Christians from 70 churches marched through the city centre in worship and prayer. Philip also began preaching internationally, including speaking engagements in Fort Worth, Texas and Missouri.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('outreach_headline', '1993: Heart for the Marginalised', ['tag' => 'h2']); ?>
        <?= $cms->html('outreach_content', '<p>City Church opened the City Gates Centre in Norwich city centre, opposite the Market Place on Gentleman\'s Walk. This became a hub for community outreach, featuring a coffee bar, prayer and counselling rooms, and dedicated outreach to the homeless.</p>
        <p>When the centre eventually closed, the outreach continued through <strong>CityCare</strong>, supporting 30-50 homeless people weekly in the city centre with food, warm clothing, and sleeping bags. The team also walked alongside individuals through court visits, detox programmes, prison visits, and counselling – demonstrating the church\'s commitment to loving the most vulnerable.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('building_headline', '1996–1999: A Permanent Home & Citywide Celebration', ['tag' => 'h2']); ?>
        <?= $cms->html('building_content', '<p>In 1996, after years of meeting in rented spaces, City Church negotiated and purchased its first freehold building – City Christian Centre (now Alive House) – for £85,000. Finally, they had a permanent home.</p>
        <p>As the millennium approached, the church organised a remarkable New Year\'s Eve outdoor gospel event outside Norwich City Hall, featuring a 100-piece choir and band. <strong>Around 50,000 people attended</strong>, making it one of the city\'s largest public celebrations and putting the church\'s faith and creativity on display for the whole city.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('expansion_headline', '2000–2005: Multiplication & Wider Influence', ['tag' => 'h2']); ?>
        <?= $cms->html('expansion_content', '<p>The new millennium brought expansion on multiple fronts. Philip spoke at pastors\' conferences in Peshawar and Abbottabad, Pakistan, while at home the church launched multiple local congregations across Norwich in Bowthorpe, Heigham, Thorpe, and Wymondham.</p>
        <p>In 2002, City Church Wymondham won first prize at the town carnival, and the council later asked the church to organise the annual Wymondham Fun Day, attracting thousands of families. The following year, City Church planted a new congregation in Great Yarmouth after a mission in the town hall – within two years, it had grown to around 40 adults, many of whom had become Christians.</p>
        <p>In 2004, the regional prayer initiative <strong>Prayer in the Park</strong> launched, with the first gathering attracting over 1,200 Christians praying together for the region. By 2005, Philip had joined the leadership team of Ground Level, helping oversee 80+ churches and mission projects across the nation.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('transition_headline', '2005–2011: Seasons of Change & Revival', ['tag' => 'h2']); ?>
        <?= $cms->html('transition_content', '<p>In 2005, a local Brethren church generously gave City Church its building, which was renamed City Gates Centre and became the church office. As the congregation continued to grow, Sunday services moved to larger school halls while City Christian Centre remained a congregational base.</p>
        <p>By 2010, the church had moved into the Mercure Hotel for Sunday services, and the youth group had grown to 30 young people. Then, in October 2010, <strong>something remarkable happened</strong>. A move of the Holy Spirit began that lasted until July 2011, with services taking place not just on Sundays, but on Sunday evenings, Mondays, Tuesdays, and Saturdays to accommodate what God was doing. It was a season of refreshing that left a lasting mark on the church.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('rebirth_headline', '2014–2016: A New Name, A New Season', ['tag' => 'h2']); ?>
        <?= $cms->html('rebirth_content', '<p>In 2014, <strong>City Church became Alive Church</strong> – a name that captured the church\'s heart to help people live fully alive in Jesus. The following year, the church stopped meeting at the Mercure Hotel and returned to City Gates Centre for services, undertaking significant renovations.</p>
        <p>When the five-year lease on City Christian Centre expired in 2016, Alive Church began a complete renovation of the building while continuing to run the Community Café and Foodbank on Fridays. By September 2016, all services had moved into the newly renamed <strong>Alive House</strong> – finally bringing the whole church family together under one roof in their own building.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('pandemic_headline', '2020: Shut Down but Still Alive', ['tag' => 'h2']); ?>
        <?= $cms->html('pandemic_content', '<p>When COVID-19 hit in March 2020, Alive Church refused to stop. A dedicated media team quickly pivoted to livestreaming services, including daily prayer and worship every morning and a chat show on Fridays. Fresh food parcels were delivered to families in need across the city.</p>
        <p>It was also a season of testing. Pastor Phil was hospitalised with septic shock and remained there for eight weeks. During this difficult time, <strong>Pastor Jo stepped into leadership</strong>, guiding the church through one of its most challenging seasons with grace and faith. The church emerged from the pandemic stronger and more united than ever.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('today_headline', 'Today & Beyond', ['tag' => 'h2']); ?>
        <?= $cms->html('today_content', '<p>Today, Alive Church continues to grow as a multi-generational family passionate about helping people live fully alive in Jesus and taking hope to every street. What began with nine people and a God-given vision has become a thriving community impacting Norwich and beyond.</p>
        <p>Our story isn\'t finished. We believe our best days are still ahead as we continue to love God, love one another, and love our world.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('timeline_headline', 'Key Milestones', ['tag' => 'h2']); ?>
        <?= $cms->html('timeline_content', '<ul class="info-list timeline-list">
            <li><strong>1985:</strong> Nine people gather with a vision to become "repairers of the breach"</li>
            <li><strong>1988:</strong> Pastor Philip goes full-time; first international mission to Germany</li>
            <li><strong>1991:</strong> "Together for Jesus" brings 1,700+ Christians from 170 churches together</li>
            <li><strong>1992:</strong> Norwich March for Jesus – 2,000 Christians from 70 churches</li>
            <li><strong>1993:</strong> City Gates Centre opens; CityCare homeless outreach begins</li>
            <li><strong>1996:</strong> Purchase of City Christian Centre (now Alive House)</li>
            <li><strong>1999:</strong> New Year\'s Eve celebration draws 50,000 people to City Hall</li>
            <li><strong>2000:</strong> Church plants launched across Norwich area</li>
            <li><strong>2003:</strong> Great Yarmouth church planted</li>
            <li><strong>2004:</strong> Prayer in the Park launches with 1,200+ Christians</li>
            <li><strong>2010–2011:</strong> Season of revival with extended services</li>
            <li><strong>2014:</strong> Church renamed to Alive Church</li>
            <li><strong>2016:</strong> Move into fully renovated Alive House</li>
            <li><strong>2020:</strong> Navigating COVID with livestreaming and community care</li>
        </ul>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section cta-section alt">
    <div class="container narrow text-center">
        <?= $cms->text('cta_headline', 'Be Part of Our Story', ['tag' => 'h2']); ?>
        <?= $cms->text('cta_text', 'Our history is still being written. We\'d love for you to be part of what God is doing at Alive Church.', ['tag' => 'p']); ?>
        <div class="cta-buttons">
            <a href="/visit" class="btn btn-primary">Plan Your Visit</a>
            <a href="/about/vision" class="btn btn-secondary">See Our Vision</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
