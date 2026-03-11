<?php
require __DIR__ . '/config.php';
$page_title = 'Safeguarding Policy | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow">Policy</p>
        <h1>Safeguarding Policy</h1>
        <p>Our commitment to protecting children, young people, and vulnerable adults.</p>
    </div>
</section>

<section class="legal-content">
    <div class="container narrow">
        <p class="legal-updated">Last updated: <?= date('F j, Y'); ?></p>

        <h2>Our Commitment</h2>
        <p><?= htmlspecialchars($site['name']); ?> is committed to the safeguarding of children, young people, and vulnerable adults. We recognise that everyone has different levels of vulnerability and that each of us may be regarded as vulnerable at some time in our lives.</p>
        <p>As members of this church, we commit to respectful pastoral care for all adults and to the safeguarding of children and young people in our church community.</p>

        <h2>Policy Statement</h2>
        <p>We recognise that:</p>
        <ul>
            <li>The welfare of the child, young person, or vulnerable adult is paramount</li>
            <li>All children, young people, and vulnerable adults have the right to equal protection from all types of harm or abuse</li>
            <li>Working in partnership with children, young people, vulnerable adults, their parents, carers, and other agencies is essential in promoting their welfare</li>
        </ul>

        <h2>Our Safeguarding Principles</h2>
        <div class="rights-grid">
            <div class="right-card">
                <h3>Safe Environment</h3>
                <p>We will create a safe and caring environment for children, young people, and vulnerable adults.</p>
            </div>
            <div class="right-card">
                <h3>Safe Recruitment</h3>
                <p>We will safely recruit and support all those with responsibility for children and vulnerable adults.</p>
            </div>
            <div class="right-card">
                <h3>Respond Appropriately</h3>
                <p>We will respond promptly and appropriately to every safeguarding concern or allegation.</p>
            </div>
            <div class="right-card">
                <h3>Pastoral Care</h3>
                <p>We will care pastorally for victims/survivors of abuse and other affected persons.</p>
            </div>
            <div class="right-card">
                <h3>Support Offenders</h3>
                <p>We will care pastorally for those who are the subject of concerns or allegations while managing risk appropriately.</p>
            </div>
            <div class="right-card">
                <h3>Training</h3>
                <p>We will ensure all workers are trained and supported in safeguarding matters.</p>
            </div>
        </div>

        <h2>Recruitment and Training</h2>
        <p>All those who work with children, young people, or vulnerable adults on behalf of <?= htmlspecialchars($site['name']); ?> will:</p>
        <ul>
            <li>Complete an application form</li>
            <li>Provide references which will be followed up</li>
            <li>Undergo a DBS (Disclosure and Barring Service) check at the appropriate level</li>
            <li>Receive appropriate safeguarding training</li>
            <li>Be supervised and supported in their role</li>
        </ul>

        <h2>Safe Working Practices</h2>
        <p>We are committed to safe working practices, including:</p>
        <ul>
            <li>Maintaining appropriate adult-to-child ratios</li>
            <li>Ensuring activities are properly planned and risk-assessed</li>
            <li>Maintaining appropriate boundaries in all relationships</li>
            <li>Ensuring that all communication with children and young people is appropriate</li>
            <li>Having clear procedures for transporting children and young people</li>
            <li>Maintaining accurate records</li>
        </ul>

        <h2>Responding to Concerns</h2>
        <p>If anyone has concerns about the welfare of a child, young person, or vulnerable adult, or about the behaviour of someone working with these groups, they should:</p>
        <ol>
            <li>Make a record of their concerns as soon as possible</li>
            <li>Report their concerns to the Safeguarding Coordinator (details below)</li>
            <li>In an emergency or if there is immediate risk, contact the police (999) or social services directly</li>
        </ol>
        <p>All concerns will be taken seriously and dealt with appropriately and confidentially.</p>

        <h2>Allegations Against Workers</h2>
        <p>Any allegation against a church worker (paid or volunteer) will be taken seriously. We will:</p>
        <ul>
            <li>Follow statutory procedures and cooperate fully with any investigation</li>
            <li>Immediately remove the worker from duties while the investigation takes place</li>
            <li>Provide appropriate support to all those involved</li>
            <li>Maintain confidentiality as far as possible</li>
        </ul>

        <h2>Online Safety</h2>
        <p>We recognise the importance of online safety. We will:</p>
        <ul>
            <li>Ensure appropriate consent is obtained before publishing photos or videos of children</li>
            <li>Monitor and moderate any online platforms used by the church</li>
            <li>Provide guidance on safe online practices</li>
            <li>Ensure all online communication with children is transparent and appropriate</li>
        </ul>

        <h2>Review</h2>
        <p>This policy will be reviewed annually by the church leadership to ensure it remains current and effective.</p>

        <h2>Safeguarding Contacts</h2>
        <div class="contact-details">
            <p><strong>Safeguarding Coordinator</strong></p>
            <p>For all safeguarding concerns, please contact our Safeguarding Coordinator:</p>
            <ul>
                <li><strong>Email:</strong> <?= htmlspecialchars($site['email']); ?></li>
                <li><strong>Address:</strong> <?= htmlspecialchars($site['location']); ?></li>
            </ul>
        </div>

        <h3>External Contacts</h3>
        <ul class="contact-details">
            <li><strong>Thirtyone:eight (formerly CCPAS):</strong> 0303 003 1111</li>
            <li><strong>Norfolk County Council - Children's Services:</strong> 0344 800 8020</li>
            <li><strong>Norfolk County Council - Adult Social Services:</strong> 0344 800 8020</li>
            <li><strong>Police (non-emergency):</strong> 101</li>
            <li><strong>Police (emergency):</strong> 999</li>
        </ul>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
