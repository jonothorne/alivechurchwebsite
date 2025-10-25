import Hero from "@/components/Hero";
import Card from "@/components/Card";
import Button from "@/components/Button";
import { Calendar, Users, Heart, ArrowRight } from "lucide-react";
import { getHomepageContent, getSiteSettings } from "@/sanity/lib/queries";

export default async function Home() {
  // Fetch content from CMS (falls back to defaults if not available)
  const content = await getHomepageContent().catch(() => null);
  const settings = await getSiteSettings().catch(() => null);

  return (
    <div>
      {/* Hero Section */}
      <Hero
        title={content?.heroTitle || "Welcome to Alive Church"}
        subtitle={content?.heroSubtitle || "Norwich"}
        description={
          content?.heroDescription ||
          "A modern pentecostal church rooted in community and family. Join us as we pursue transformation and restoration together."
        }
        ctaText={content?.heroCtaText || "Plan Your Visit"}
        ctaLink={content?.heroCtaLink || "/about"}
        backgroundImage={content?.heroImage || "/images/hero/hero-1.jpg"}
      />

      {/* Service Info Section */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="bg-gradient-to-br from-primary/10 to-primary/5 rounded-2xl p-8 md:p-12">
            <div className="grid md:grid-cols-2 gap-8 items-center">
              <div>
                <h2 className="text-3xl md:text-4xl font-bold mb-4">
                  Join Us This Sunday
                </h2>
                <p className="text-lg text-gray-700 mb-6">
                  Experience dynamic worship, powerful teaching, and genuine community.
                  Everyone is welcome!
                </p>
                <div className="space-y-3">
                  <div>
                    <p className="text-sm font-semibold text-primary uppercase">When</p>
                    <p className="text-xl font-bold">
                      {settings?.serviceTime || "Sundays at 11:00 AM"}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-primary uppercase">Where</p>
                    <p className="text-lg">
                      {settings?.address?.street || "Alive House, Nelson Street"}<br />
                      {settings?.address?.city || "Norwich"} {settings?.address?.postcode || "NR2 4DR"}
                    </p>
                  </div>
                </div>
              </div>
              <div className="flex justify-center">
                <Button href="/about" variant="primary" size="lg" icon={ArrowRight}>
                  What to Expect
                </Button>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Quick Links Section */}
      <section className="py-16 bg-gray-50">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl md:text-4xl font-bold text-center mb-12">
            Get Connected
          </h2>
          <div className="grid md:grid-cols-3 gap-8">
            <div className="bg-white p-8 rounded-lg shadow-md text-center hover:shadow-xl transition-shadow">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                <Calendar className="h-8 w-8 text-primary" />
              </div>
              <h3 className="text-xl font-bold mb-3">Upcoming Events</h3>
              <p className="text-gray-600 mb-6">
                Discover what's happening at Alive Church and join us for special events.
              </p>
              <Button href="/events" variant="outline">
                View Events
              </Button>
            </div>

            <div className="bg-white p-8 rounded-lg shadow-md text-center hover:shadow-xl transition-shadow">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                <Users className="h-8 w-8 text-primary" />
              </div>
              <h3 className="text-xl font-bold mb-3">Small Groups</h3>
              <p className="text-gray-600 mb-6">
                Connect with others in a smaller setting for deeper relationships.
              </p>
              <Button href="/connect" variant="outline">
                Find a Group
              </Button>
            </div>

            <div className="bg-white p-8 rounded-lg shadow-md text-center hover:shadow-xl transition-shadow">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                <Heart className="h-8 w-8 text-primary" />
              </div>
              <h3 className="text-xl font-bold mb-3">Give Online</h3>
              <p className="text-gray-600 mb-6">
                Support the mission and ministry of Alive Church through online giving.
              </p>
              <Button href="/give" variant="outline">
                Give Now
              </Button>
            </div>
          </div>
        </div>
      </section>

      {/* Vision & Mission Section */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="grid md:grid-cols-2 gap-12 items-center">
            <div>
              <h2 className="text-3xl md:text-4xl font-bold mb-6">
                40 Years of Faith & Family
              </h2>
              <p className="text-lg text-gray-700 mb-6">
                Founded 40 years ago by our Senior Pastor Phil Thorne, Alive Church
                has been a beacon of hope and transformation in Norwich. Together
                with Pastor Jo, they continue to lead our community in faith,
                worship, and service.
              </p>
              <div className="space-y-4">
                <div>
                  <h3 className="text-xl font-bold text-primary mb-2">Our Vision</h3>
                  <p className="text-gray-700">
                    {content?.visionText ||
                      "To see community-wide transformation through the power of God's love."}
                  </p>
                </div>
                <div>
                  <h3 className="text-xl font-bold text-primary mb-2">Our Mission</h3>
                  <p className="text-gray-700">
                    {content?.missionText ||
                      "To be restorers of the breach, rebuilding lives and communities."}
                  </p>
                </div>
              </div>
              <div className="mt-8">
                <Button href="/about/vision" variant="primary">
                  Learn More About Us
                </Button>
              </div>
            </div>
            <div className="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-12 text-white">
              <blockquote className="text-2xl font-semibold italic mb-4">
                "Alive Church is more than a place—it's a family where everyone belongs."
              </blockquote>
              <p className="text-lg">
                - Phil & Jo Thorne, Senior Pastors
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Community Gallery Section */}
      <section className="py-16 bg-gray-50">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl md:text-4xl font-bold mb-4">
              {content?.communityGalleryTitle || "Our Church Family"}
            </h2>
            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
              {content?.communityGalleryDescription ||
                "See what it's like to be part of Alive Church - a vibrant community worshipping together, growing together, and making a difference."}
            </p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {content?.communityImages && content.communityImages.length > 0 ? (
              content.communityImages.map((item: any, index: number) => (
                <div key={index} className="relative h-80 rounded-2xl overflow-hidden shadow-lg group">
                  <img
                    src={item.image}
                    alt={item.caption || "Church Community"}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                  {item.caption && (
                    <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent flex items-end p-6">
                      <p className="text-white text-xl font-semibold">{item.caption}</p>
                    </div>
                  )}
                </div>
              ))
            ) : (
              <>
                <div className="relative h-80 rounded-2xl overflow-hidden shadow-lg group">
                  <img
                    src="/images/community/community-1.jpg"
                    alt="Alive Church Community"
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent flex items-end p-6">
                    <p className="text-white text-xl font-semibold">Worshipping Together</p>
                  </div>
                </div>
                <div className="relative h-80 rounded-2xl overflow-hidden shadow-lg group">
                  <img
                    src="/images/community/community-2.jpg"
                    alt="Alive Church Worship"
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent flex items-end p-6">
                    <p className="text-white text-xl font-semibold">Growing in Faith</p>
                  </div>
                </div>
              </>
            )}
          </div>
        </div>
      </section>

      {/* Call to Action */}
      <section className="py-16 bg-primary text-white">
        <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl md:text-4xl font-bold mb-6">
            Ready to Take Your Next Step?
          </h2>
          <p className="text-xl mb-8">
            We'd love to connect with you and help you find your place at Alive Church.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Button href="/connect" variant="secondary" size="lg">
              Get Connected
            </Button>
            <Button href="/about" variant="outline" size="lg">
              Learn More
            </Button>
          </div>
        </div>
      </section>
    </div>
  );
}
