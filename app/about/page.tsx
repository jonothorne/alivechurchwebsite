import Hero from "@/components/Hero";
import Button from "@/components/Button";
import { Church, Heart, Users2 } from "lucide-react";
import Link from "next/link";

export const metadata = {
  title: "About Us | Alive Church Norwich",
  description:
    "Learn about Alive Church - a modern pentecostal church in Norwich with 40 years of history, rooted in community and family.",
};

export default function AboutPage() {
  return (
    <div>
      <Hero
        title="About Alive Church"
        subtitle="Our Story"
        description="For 40 years, we've been a community of faith, hope, and love in the heart of Norwich."
        small
      />

      {/* Our Story Section */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="max-w-3xl mx-auto">
            <h2 className="text-3xl md:text-4xl font-bold mb-6">Our Story</h2>
            <div className="prose prose-lg max-w-none">
              <p className="text-lg text-gray-700 leading-relaxed mb-4">
                Alive Church was founded 40 years ago by Senior Pastor Phil Thorne with a
                simple but powerful vision: to see lives transformed by the love of God. What
                began as a small gathering has grown into a vibrant, multi-generational
                community of believers committed to making a difference in Norwich and beyond.
              </p>
              <p className="text-lg text-gray-700 leading-relaxed mb-4">
                Together with Pastor Jo, Phil continues to lead our church family with passion,
                wisdom, and a heart for both people and God. Over four decades, we've witnessed
                countless lives changed, families restored, and communities impacted through
                the power of the Gospel.
              </p>
              <p className="text-lg text-gray-700 leading-relaxed">
                Today, Alive Church is a modern pentecostal church with our core roots firmly
                planted in community and family. We believe church isn't just a Sunday morning
                event—it's a way of life, a family you belong to, and a movement you're part of.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Core Values Section */}
      <section className="py-16 bg-gray-50">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl md:text-4xl font-bold text-center mb-12">
            What We Believe In
          </h2>
          <div className="grid md:grid-cols-3 gap-8">
            <div className="bg-white p-8 rounded-lg shadow-md">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                <Church className="h-8 w-8 text-primary" />
              </div>
              <h3 className="text-xl font-bold mb-3">Community</h3>
              <p className="text-gray-600">
                We believe in the power of authentic community. Church is family, and
                everyone has a place at the table. We grow stronger together.
              </p>
            </div>

            <div className="bg-white p-8 rounded-lg shadow-md">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                <Heart className="h-8 w-8 text-primary" />
              </div>
              <h3 className="text-xl font-bold mb-3">Family</h3>
              <p className="text-gray-600">
                Family is at the heart of everything we do. We support, encourage, and
                journey through life together as the family of God.
              </p>
            </div>

            <div className="bg-white p-8 rounded-lg shadow-md">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                <Users2 className="h-8 w-8 text-primary" />
              </div>
              <h3 className="text-xl font-bold mb-3">Transformation</h3>
              <p className="text-gray-600">
                We're committed to seeing lives transformed and communities restored
                through the power of God's Word and Spirit.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* What to Expect Section */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="max-w-3xl mx-auto">
            <h2 className="text-3xl md:text-4xl font-bold mb-8">
              What to Expect When You Visit
            </h2>
            <div className="space-y-6">
              <div>
                <h3 className="text-xl font-bold mb-2">Warm Welcome</h3>
                <p className="text-gray-700">
                  From the moment you arrive, you'll be greeted by our friendly team.
                  We want you to feel at home, not like a visitor.
                </p>
              </div>
              <div>
                <h3 className="text-xl font-bold mb-2">Dynamic Worship</h3>
                <p className="text-gray-700">
                  Our worship is passionate and contemporary, creating an atmosphere
                  where you can encounter God and express your faith freely.
                </p>
              </div>
              <div>
                <h3 className="text-xl font-bold mb-2">Practical Teaching</h3>
                <p className="text-gray-700">
                  Expect messages that are relevant, biblical, and applicable to your
                  everyday life. We believe God's Word has power to transform.
                </p>
              </div>
              <div>
                <h3 className="text-xl font-bold mb-2">Genuine Community</h3>
                <p className="text-gray-700">
                  After the service, grab a coffee and connect with others. Church
                  doesn't end when the music stops—it's just getting started.
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Quick Links Section */}
      <section className="py-16 bg-primary text-white">
        <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl md:text-4xl font-bold mb-8">Learn More</h2>
          <div className="grid md:grid-cols-2 gap-6">
            <Link
              href="/about/vision"
              className="bg-white/10 hover:bg-white/20 transition-colors p-8 rounded-lg backdrop-blur-sm"
            >
              <h3 className="text-2xl font-bold mb-3">Our Vision & Mission</h3>
              <p className="text-white/90">
                Discover our heart for community transformation and restoration.
              </p>
            </Link>
            <Link
              href="/about/leadership"
              className="bg-white/10 hover:bg-white/20 transition-colors p-8 rounded-lg backdrop-blur-sm"
            >
              <h3 className="text-2xl font-bold mb-3">Leadership Team</h3>
              <p className="text-white/90">
                Meet the pastors and leaders who serve our church family.
              </p>
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
}
