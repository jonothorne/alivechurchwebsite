import Hero from "@/components/Hero";
import { Mail } from "lucide-react";
import Image from "next/image";

export const metadata = {
  title: "Leadership Team | Alive Church Norwich",
  description:
    "Meet the leadership team at Alive Church, led by Senior Pastors Phil & Jo Thorne.",
};

export default function LeadershipPage() {
  return (
    <div>
      <Hero
        title="Our Leadership"
        subtitle="Meet the Team"
        description="Passionate leaders committed to serving our church family and community."
        small
      />

      {/* Senior Pastors Section */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl md:text-4xl font-bold mb-4">Senior Pastors</h2>
            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
              For 40 years, Phil and Jo Thorne have faithfully led Alive Church with vision,
              wisdom, and a heart for God and people.
            </p>
          </div>

          <div className="grid md:grid-cols-2 gap-12 max-w-5xl mx-auto">
            {/* Phil Thorne */}
            <div className="bg-gray-50 rounded-lg overflow-hidden shadow-md">
              <div className="aspect-square bg-gradient-to-br from-primary/20 to-primary/10 flex items-center justify-center">
                <div className="w-40 h-40 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 text-5xl font-bold">
                  PT
                </div>
              </div>
              <div className="p-8">
                <h3 className="text-2xl font-bold mb-2">Phil Thorne</h3>
                <p className="text-primary font-semibold mb-4">Senior Pastor & Founder</p>
                <p className="text-gray-700 mb-6">
                  Phil founded Alive Church 40 years ago with a vision to see lives
                  transformed and communities restored. His passion for the local church and
                  heart for people continues to inspire and shape our church family today.
                  Phil is known for his practical Bible teaching, pastoral wisdom, and
                  unwavering faith in God's power to change lives.
                </p>
                <div className="flex items-center gap-2 text-gray-600">
                  <Mail className="h-5 w-5" />
                  <a
                    href="mailto:office@alive.me.uk"
                    className="hover:text-primary transition-colors"
                  >
                    office@alive.me.uk
                  </a>
                </div>
              </div>
            </div>

            {/* Jo Thorne */}
            <div className="bg-gray-50 rounded-lg overflow-hidden shadow-md">
              <div className="aspect-square bg-gradient-to-br from-primary/20 to-primary/10 flex items-center justify-center">
                <div className="w-40 h-40 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 text-5xl font-bold">
                  JT
                </div>
              </div>
              <div className="p-8">
                <h3 className="text-2xl font-bold mb-2">Jo Thorne</h3>
                <p className="text-primary font-semibold mb-4">Senior Pastor</p>
                <p className="text-gray-700 mb-6">
                  Pastor Jo serves alongside Phil with grace, compassion, and a genuine love
                  for people. Her heart for families, women's ministry, and discipleship has
                  been instrumental in building the strong community we have today. Jo brings
                  warmth, encouragement, and practical faith to everything she does.
                </p>
                <div className="flex items-center gap-2 text-gray-600">
                  <Mail className="h-5 w-5" />
                  <a
                    href="mailto:office@alive.me.uk"
                    className="hover:text-primary transition-colors"
                  >
                    office@alive.me.uk
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Additional Leadership */}
      <section className="py-16 bg-gray-50">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl md:text-4xl font-bold mb-4">
              Additional Leadership Team
            </h2>
            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
              Our church is blessed with a team of dedicated leaders who serve in various
              ministries and areas of church life. Additional team members can be added
              through our CMS.
            </p>
          </div>

          <div className="bg-white rounded-lg shadow-md p-8 max-w-2xl mx-auto text-center">
            <p className="text-gray-600 mb-4">
              To add more team members, log in to the CMS admin panel and create new team
              member entries with photos, bios, and contact information.
            </p>
            <a
              href="/studio"
              className="inline-flex items-center justify-center bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-dark transition-colors"
            >
              Go to CMS
            </a>
          </div>
        </div>
      </section>

      {/* Call to Action */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl md:text-4xl font-bold mb-6">Get in Touch</h2>
          <p className="text-lg text-gray-600 mb-8">
            Have a question or want to connect with our leadership team? We'd love to hear
            from you.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <a
              href="/connect"
              className="inline-flex items-center justify-center bg-primary text-white px-8 py-4 rounded-lg font-semibold hover:bg-primary-dark transition-colors shadow-md hover:shadow-lg"
            >
              Contact Us
            </a>
            <a
              href="mailto:office@alive.me.uk"
              className="inline-flex items-center justify-center gap-2 bg-transparent border-2 border-primary text-primary px-8 py-4 rounded-lg font-semibold hover:bg-primary hover:text-white transition-colors"
            >
              <Mail className="h-5 w-5" />
              Email Us
            </a>
          </div>
        </div>
      </section>
    </div>
  );
}
