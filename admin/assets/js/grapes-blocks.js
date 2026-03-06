/**
 * Custom Church Blocks for GrapesJS
 * Alive Church CMS - Pre-built Components
 */

function initCustomBlocks(editor) {
    const blockManager = editor.BlockManager;

    // 1. Church Hero Block
    blockManager.add('church-hero', {
        label: 'Church Hero',
        category: 'Church',
        content: `
            <section class="hero" style="background: linear-gradient(135deg, #FF1493 0%, #4B2679 100%); padding: 100px 20px; text-align: center; color: white;">
                <div class="container" style="max-width: 1200px; margin: 0 auto;">
                    <h1 style="font-size: 3.5rem; font-weight: 700; margin-bottom: 1rem;" data-gjs-editable="true">Welcome to Alive Church</h1>
                    <p class="eyebrow" style="font-size: 1.25rem; margin-bottom: 2rem; opacity: 0.9;" data-gjs-editable="true">You Belong Here</p>
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="/visit" class="btn btn-primary" style="padding: 12px 32px; background: white; color: #2D1B4E; border-radius: 8px; text-decoration: none; font-weight: 600;">Plan Your Visit</a>
                        <a href="/watch" class="btn btn-outline" style="padding: 12px 32px; border: 2px solid white; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">Watch Online</a>
                    </div>
                </div>
            </section>
        `,
        attributes: {
            title: 'Church Hero - Full-width banner with gradient background'
        }
    });

    // 2. Service Times Block
    blockManager.add('service-times', {
        label: 'Service Times',
        category: 'Church',
        content: `
            <section class="service-times" style="padding: 60px 20px; background: #F9FAFB;">
                <div class="container" style="max-width: 1200px; margin: 0 auto; text-align: center;">
                    <h2 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; color: #2D1B4E;" data-gjs-editable="true">Join Us This Sunday</h2>
                    <p style="font-size: 1.125rem; color: #64748b; margin-bottom: 3rem;" data-gjs-editable="true">Passionate worship, practical teaching, and authentic community</p>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                        <div class="service-card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">⛪</div>
                            <h3 style="font-size: 1.5rem; font-weight: 600; color: #2D1B4E; margin-bottom: 0.5rem;" data-gjs-editable="true">Sunday Gathering</h3>
                            <p style="font-size: 1.125rem; font-weight: 600; color: #FF1493; margin-bottom: 0.5rem;" data-gjs-editable="true">11:00 AM</p>
                            <p style="color: #64748b; font-size: 0.875rem;" data-gjs-editable="true">Coffee & breakfast from 10:15 AM</p>
                        </div>

                        <div class="service-card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">👨‍👩‍👧‍👦</div>
                            <h3 style="font-size: 1.5rem; font-weight: 600; color: #2D1B4E; margin-bottom: 0.5rem;" data-gjs-editable="true">Kids Church</h3>
                            <p style="font-size: 1.125rem; font-weight: 600; color: #FF1493; margin-bottom: 0.5rem;" data-gjs-editable="true">11:00 AM</p>
                            <p style="color: #64748b; font-size: 0.875rem;" data-gjs-editable="true">Ages 0-12, engaging programs</p>
                        </div>

                        <div class="service-card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🎸</div>
                            <h3 style="font-size: 1.5rem; font-weight: 600; color: #2D1B4E; margin-bottom: 0.5rem;" data-gjs-editable="true">Alive Youth</h3>
                            <p style="font-size: 1.125rem; font-weight: 600; color: #FF1493; margin-bottom: 0.5rem;" data-gjs-editable="true">Saturday 4:00 PM</p>
                            <p style="color: #64748b; font-size: 0.875rem;" data-gjs-editable="true">Ages 11-18, games & teaching</p>
                        </div>
                    </div>
                </div>
            </section>
        `,
        attributes: {
            title: 'Service Times - Grid of service cards'
        }
    });

    // 3. Sermon Video Block
    blockManager.add('sermon-video', {
        label: 'Sermon Video',
        category: 'Church',
        content: `
            <section class="sermon-video" style="padding: 60px 20px; background: white;">
                <div class="container" style="max-width: 900px; margin: 0 auto;">
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <p class="eyebrow" style="color: #FF1493; font-weight: 600; margin-bottom: 0.5rem;" data-gjs-editable="true">LATEST MESSAGE</p>
                        <h2 style="font-size: 2.5rem; font-weight: 700; color: #2D1B4E; margin-bottom: 0.5rem;" data-gjs-editable="true">Make Room – Week 3</h2>
                        <p style="color: #64748b;" data-gjs-editable="true">Ps. Philip Thorne • February 2, 2025 • 38 mins</p>
                    </div>

                    <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 12px; box-shadow: 0 8px 16px rgba(0,0,0,0.2);">
                        <iframe data-gjs-editable="true" src="https://www.youtube.com/embed/dQw4w9WgXcQ" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allowfullscreen></iframe>
                    </div>
                </div>
            </section>
        `,
        attributes: {
            title: 'Sermon Video - Embedded video player with metadata'
        }
    });

    // 4. Event Listing Block
    blockManager.add('event-listing', {
        label: 'Event Cards',
        category: 'Church',
        content: `
            <section class="event-listing" style="padding: 60px 20px; background: white;">
                <div class="container" style="max-width: 1200px; margin: 0 auto;">
                    <h2 style="font-size: 2.5rem; font-weight: 700; color: #2D1B4E; margin-bottom: 3rem; text-align: center;" data-gjs-editable="true">Upcoming Events</h2>

                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 2rem;">
                        <div class="event-card" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: transform 0.2s;">
                            <img data-gjs-editable="true" src="/assets/imgs/gallery/alive-church-worship-congregation.jpg" style="width: 100%; height: 200px; object-fit: cover;" alt="Event">
                            <div style="padding: 1.5rem;">
                                <div style="color: #FF1493; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.5rem;" data-gjs-editable="true">SUNDAY, FEB 16</div>
                                <h3 style="font-size: 1.5rem; font-weight: 600; color: #2D1B4E; margin-bottom: 0.75rem;" data-gjs-editable="true">Sunday Gathering</h3>
                                <p style="color: #64748b; margin-bottom: 1rem; line-height: 1.6;" data-gjs-editable="true">Join us for passionate worship, practical teaching, and authentic community.</p>
                                <a href="/events" style="color: #FF1493; font-weight: 600; text-decoration: none;">Learn More →</a>
                            </div>
                        </div>

                        <div class="event-card" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                            <img data-gjs-editable="true" src="/assets/imgs/gallery/alive-church-drummer-worship-team.jpg" style="width: 100%; height: 200px; object-fit: cover;" alt="Event">
                            <div style="padding: 1.5rem;">
                                <div style="color: #FF1493; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.5rem;" data-gjs-editable="true">SATURDAY, FEB 15</div>
                                <h3 style="font-size: 1.5rem; font-weight: 600; color: #2D1B4E; margin-bottom: 0.75rem;" data-gjs-editable="true">Alive Youth</h3>
                                <p style="color: #64748b; margin-bottom: 1rem; line-height: 1.6;" data-gjs-editable="true">Games, worship, and teaching for ages 11-18.</p>
                                <a href="/events" style="color: #FF1493; font-weight: 600; text-decoration: none;">Learn More →</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        `,
        attributes: {
            title: 'Event Cards - Grid of event cards with images'
        }
    });

    // 5. Ministry Cards Block
    blockManager.add('ministry-cards', {
        label: 'Ministry Showcase',
        category: 'Church',
        content: `
            <section class="ministry-cards" style="padding: 60px 20px; background: #F9FAFB;">
                <div class="container" style="max-width: 1200px; margin: 0 auto;">
                    <div style="text-align: center; margin-bottom: 3rem;">
                        <h2 style="font-size: 2.5rem; font-weight: 700; color: #2D1B4E; margin-bottom: 1rem;" data-gjs-editable="true">Our Ministries</h2>
                        <p style="font-size: 1.125rem; color: #64748b;" data-gjs-editable="true">Serving our community and sharing God's love</p>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                        <div style="background: white; padding: 2rem; border-radius: 12px; text-align: center;">
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #FF1493, #4B2679); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem;">☕</div>
                            <h3 style="font-size: 1.5rem; font-weight: 600; color: #2D1B4E; margin-bottom: 0.75rem;" data-gjs-editable="true">Revive Café</h3>
                            <p style="color: #64748b; line-height: 1.6;" data-gjs-editable="true">Our cafe is open throughout the week providing a safe place to connect over coffee.</p>
                        </div>

                        <div style="background: white; padding: 2rem; border-radius: 12px; text-align: center;">
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #FF1493, #4B2679); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem;">🎒</div>
                            <h3 style="font-size: 1.5rem; font-weight: 600; color: #2D1B4E; margin-bottom: 0.75rem;" data-gjs-editable="true">Foodbank</h3>
                            <p style="color: #64748b; line-height: 1.6;" data-gjs-editable="true">Meeting urgent needs for families across Norwich in crisis.</p>
                        </div>

                        <div style="background: white; padding: 2rem; border-radius: 12px; text-align: center;">
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #FF1493, #4B2679); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem;">🙏</div>
                            <h3 style="font-size: 1.5rem; font-weight: 600; color: #2D1B4E; margin-bottom: 0.75rem;" data-gjs-editable="true">Prayer & Care</h3>
                            <p style="color: #64748b; line-height: 1.6;" data-gjs-editable="true">Prayer room, pastoral support, and mentoring for all ages.</p>
                        </div>
                    </div>
                </div>
            </section>
        `,
        attributes: {
            title: 'Ministry Showcase - Icon cards for ministries'
        }
    });

    // 6. Donation Widget Block
    blockManager.add('donation-widget', {
        label: 'Donation Form',
        category: 'Church',
        content: `
            <section class="donation-widget" style="padding: 60px 20px; background: linear-gradient(135deg, #FF1493 0%, #4B2679 100%); color: white; text-align: center;">
                <div class="container" style="max-width: 600px; margin: 0 auto;">
                    <h2 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem;" data-gjs-editable="true">Support Our Mission</h2>
                    <p style="font-size: 1.125rem; margin-bottom: 2rem; opacity: 0.9;" data-gjs-editable="true">Your generosity helps us serve our community and share God's love</p>

                    <div style="background: white; padding: 2rem; border-radius: 12px; color: #2D1B4E;">
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                            <button style="padding: 1rem; border: 2px solid #E2E8F0; border-radius: 8px; background: white; font-size: 1.25rem; font-weight: 600; cursor: pointer;">£25</button>
                            <button style="padding: 1rem; border: 2px solid #FF1493; border-radius: 8px; background: #FFF1F8; font-size: 1.25rem; font-weight: 600; cursor: pointer; color: #FF1493;">£50</button>
                            <button style="padding: 1rem; border: 2px solid #E2E8F0; border-radius: 8px; background: white; font-size: 1.25rem; font-weight: 600; cursor: pointer;">£100</button>
                        </div>
                        <input type="text" placeholder="Other amount" style="width: 100%; padding: 1rem; border: 2px solid #E2E8F0; border-radius: 8px; font-size: 1rem; margin-bottom: 1rem;">
                        <a href="/give" class="btn btn-primary" style="display: block; padding: 1rem; background: linear-gradient(135deg, #FF1493, #4B2679); color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">Give Now</a>
                    </div>
                </div>
            </section>
        `,
        attributes: {
            title: 'Donation Widget - Quick giving form'
        }
    });

    // 7. Contact Card Block
    blockManager.add('contact-card', {
        label: 'Contact Info',
        category: 'Church',
        content: `
            <section class="contact-card" style="padding: 60px 20px; background: white;">
                <div class="container" style="max-width: 1000px; margin: 0 auto;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem;">
                        <div>
                            <h2 style="font-size: 2.5rem; font-weight: 700; color: #2D1B4E; margin-bottom: 2rem;" data-gjs-editable="true">Get In Touch</h2>

                            <div style="margin-bottom: 1.5rem;">
                                <div style="color: #FF1493; font-weight: 600; margin-bottom: 0.5rem;">📍 ADDRESS</div>
                                <p style="color: #2D1B4E; line-height: 1.6;" data-gjs-editable="true">Alive House, Nelson Street<br>Norwich NR2 4DR</p>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <div style="color: #FF1493; font-weight: 600; margin-bottom: 0.5rem;">✉️ EMAIL</div>
                                <p style="color: #2D1B4E;" data-gjs-editable="true">hello@alivechurch.co.uk</p>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <div style="color: #FF1493; font-weight: 600; margin-bottom: 0.5rem;">📞 PHONE</div>
                                <p style="color: #2D1B4E;" data-gjs-editable="true">+44 (0)1603 000000</p>
                            </div>

                            <div>
                                <div style="color: #FF1493; font-weight: 600; margin-bottom: 0.5rem;">⏰ SERVICE TIMES</div>
                                <p style="color: #2D1B4E;" data-gjs-editable="true">Sundays • 11:00 AM<br>Coffee from 10:15 AM</p>
                            </div>
                        </div>

                        <div style="background: #F9FAFB; border-radius: 12px; overflow: hidden; height: 400px;">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2424.1234567890!2d1.3!3d52.6!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNTLCsDM2JzAwLjAiTiAxwrAxOCcwMC4wIkU!5e0!3m2!1sen!2suk!4v1234567890" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                        </div>
                    </div>
                </div>
            </section>
        `,
        attributes: {
            title: 'Contact Card - Address, phone, email, map'
        }
    });

    // 8. Testimony Block
    blockManager.add('testimony-block', {
        label: 'Testimony',
        category: 'Church',
        content: `
            <section class="testimony-block" style="padding: 80px 20px; background: #F9FAFB;">
                <div class="container" style="max-width: 800px; margin: 0 auto; text-align: center;">
                    <div style="font-size: 4rem; color: #FF1493; margin-bottom: 1rem;">"</div>
                    <blockquote style="font-size: 1.5rem; line-height: 1.8; color: #2D1B4E; font-style: italic; margin-bottom: 2rem;" data-gjs-editable="true">
                        Alive Church has become my spiritual home. The genuine love and acceptance I've experienced here has transformed my life and deepened my relationship with God.
                    </blockquote>
                    <div style="margin-top: 2rem;">
                        <div style="font-weight: 600; color: #2D1B4E; font-size: 1.125rem;" data-gjs-editable="true">Sarah Mitchell</div>
                        <div style="color: #64748b; font-size: 0.875rem;" data-gjs-editable="true">Member since 2023</div>
                    </div>
                </div>
            </section>
        `,
        attributes: {
            title: 'Testimony - Quote with attribution'
        }
    });

    // 9. Newsletter Signup Block
    blockManager.add('newsletter-signup', {
        label: 'Newsletter Form',
        category: 'Church',
        content: `
            <section class="newsletter-signup" style="padding: 60px 20px; background: linear-gradient(135deg, #4B2679 0%, #2D1B4E 100%); color: white;">
                <div class="container" style="max-width: 600px; margin: 0 auto; text-align: center;">
                    <h2 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem;" data-gjs-editable="true">Stay Connected</h2>
                    <p style="font-size: 1.125rem; margin-bottom: 2rem; opacity: 0.9;" data-gjs-editable="true">Get weekly updates, sermon highlights, and event invites delivered to your inbox</p>

                    <form action="/api/newsletter-signup.php" method="POST" style="display: flex; gap: 1rem; max-width: 500px; margin: 0 auto;">
                        <input type="email" name="email" placeholder="Your email address" required style="flex: 1; padding: 1rem; border: none; border-radius: 8px; font-size: 1rem;">
                        <button type="submit" style="padding: 1rem 2rem; background: #FF1493; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap;">Subscribe</button>
                    </form>
                </div>
            </section>
        `,
        attributes: {
            title: 'Newsletter Signup - Email capture form'
        }
    });

    // 10. Two Column Text Block
    blockManager.add('two-column-text', {
        label: 'Two Columns',
        category: 'Layout',
        content: `
            <section class="two-column" style="padding: 60px 20px; background: white;">
                <div class="container" style="max-width: 1200px; margin: 0 auto;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem;">
                        <div>
                            <h3 style="font-size: 1.75rem; font-weight: 600; color: #2D1B4E; margin-bottom: 1rem;" data-gjs-editable="true">Our Vision</h3>
                            <p style="color: #64748b; line-height: 1.8;" data-gjs-editable="true">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam.</p>
                        </div>
                        <div>
                            <h3 style="font-size: 1.75rem; font-weight: 600; color: #2D1B4E; margin-bottom: 1rem;" data-gjs-editable="true">Our Mission</h3>
                            <p style="color: #64748b; line-height: 1.8;" data-gjs-editable="true">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam.</p>
                        </div>
                    </div>
                </div>
            </section>
        `,
        attributes: {
            title: 'Two Columns - Responsive text columns'
        }
    });

    // Add Basic Layout Blocks
    blockManager.add('container', {
        label: 'Container',
        category: 'Layout',
        content: '<div class="container" style="max-width: 1200px; margin: 0 auto; padding: 20px;"></div>',
        attributes: {
            title: 'Container - Centered content wrapper'
        }
    });

    blockManager.add('section', {
        label: 'Section',
        category: 'Layout',
        content: '<section style="padding: 60px 20px;"></section>',
        attributes: {
            title: 'Section - Full-width page section'
        }
    });
}

// Export for use in grapes-builder.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { initCustomBlocks };
}
