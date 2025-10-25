import Hero from "@/components/Hero";
import { Target, Compass, Lightbulb } from "lucide-react";
import { getVisionContent } from "@/sanity/lib/queries";

export const metadata = {
  title: "Our Vision & Mission | Alive Church Norwich",
  description:
    "Our vision is to see community-wide transformation. Our mission is to be restorers of the breach.",
};

export default async function VisionPage() {
  // Fetch content from CMS (falls back to defaults if not available)
  const content = await getVisionContent().catch(() => null);
  return (
    <div>
      <Hero
        title="Vision & Mission"
        subtitle="Our Purpose"
        description="Driven by a passion to see lives transformed and communities restored."
        small
        backgroundImage={content?.heroImage || "/images/worship/worship-1.jpg"}
      />

      {/* Vision Section */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="max-w-3xl mx-auto text-center mb-16">
            <div className="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary/10 mb-6">
              <Target className="h-10 w-10 text-primary" />
            </div>
            <h2 className="text-4xl md:text-5xl font-bold mb-6">Our Vision</h2>
            <p className="text-2xl text-gray-700 font-semibold mb-4">
              {content?.visionStatement || "To see community-wide transformation"}
            </p>
            <p className="text-lg text-gray-600 leading-relaxed">
              We believe God has called us to be agents of change in our city. Our vision
              extends beyond the four walls of our church—we're passionate about seeing
              entire communities transformed by the power of God's love. From individuals
              to families, from neighborhoods to the whole of Norwich, we dream of a city
              where God's kingdom is evident in every sphere of life.
            </p>
          </div>
        </div>
      </section>

      {/* Mission Section */}
      <section className="py-16 bg-gray-50">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="max-w-3xl mx-auto text-center mb-16">
            <div className="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary/10 mb-6">
              <Compass className="h-10 w-10 text-primary" />
            </div>
            <h2 className="text-4xl md:text-5xl font-bold mb-6">Our Mission</h2>
            <p className="text-2xl text-gray-700 font-semibold mb-4">
              {content?.missionStatement || "To be restorers of the breach"}
            </p>
            <div className="text-lg text-gray-600 leading-relaxed space-y-4">
              <p>
                Inspired by Isaiah 58:12, we're committed to rebuilding what has been broken,
                restoring what has been lost, and repairing the foundations for generations
                to come. This isn't just poetic language—it's a practical call to action.
              </p>
              <p>
                We restore lives through prayer, discipleship, and genuine community. We
                rebuild families through biblical teaching and practical support. We repair
                communities through service, compassion, and the demonstration of God's love
                in tangible ways.
              </p>
            </div>
          </div>

          {/* Scripture Reference */}
          <div className="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-md border-l-4 border-primary">
            <p className="text-lg italic text-gray-700 mb-4">
              "Your people will rebuild the ancient ruins and will raise up the age-old
              foundations; you will be called Repairer of Broken Walls, Restorer of Streets
              with Dwellings."
            </p>
            <p className="text-sm font-semibold text-primary">— Isaiah 58:12</p>
          </div>
        </div>
      </section>

      {/* Core Values Section */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <div className="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary/10 mb-6">
              <Lightbulb className="h-10 w-10 text-primary" />
            </div>
            <h2 className="text-3xl md:text-4xl font-bold mb-4">Our Core Values</h2>
            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
              These values shape everything we do and guide us in fulfilling our vision
              and mission.
            </p>
          </div>

          <div className="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
            {content?.coreValues && content.coreValues.length > 0 ? (
              content.coreValues.map((value: any, index: number) => (
                <div key={index} className="bg-gray-50 p-8 rounded-lg">
                  <h3 className="text-2xl font-bold mb-3 text-primary">{value.title}</h3>
                  <p className="text-gray-700">{value.description}</p>
                </div>
              ))
            ) : (
              <>
                <div className="bg-gray-50 p-8 rounded-lg">
                  <h3 className="text-2xl font-bold mb-3 text-primary">Community</h3>
                  <p className="text-gray-700">
                    We believe in the power of authentic, Christ-centered relationships. Church
                    isn't a building—it's a family where everyone belongs and no one walks alone.
                  </p>
                </div>

                <div className="bg-gray-50 p-8 rounded-lg">
                  <h3 className="text-2xl font-bold mb-3 text-primary">Family</h3>
                  <p className="text-gray-700">
                    Family is foundational to who we are. We honor the family unit, support
                    parents, invest in children, and treat every member as part of our church family.
                  </p>
                </div>

                <div className="bg-gray-50 p-8 rounded-lg">
                  <h3 className="text-2xl font-bold mb-3 text-primary">Presence of God</h3>
                  <p className="text-gray-700">
                    As a pentecostal church, we value the presence and power of the Holy Spirit.
                    We create space for encounter, worship, and experiencing God in fresh ways.
                  </p>
                </div>

                <div className="bg-gray-50 p-8 rounded-lg">
                  <h3 className="text-2xl font-bold mb-3 text-primary">Restoration</h3>
                  <p className="text-gray-700">
                    We believe in second chances, new beginnings, and the redemptive power of God.
                    No one is beyond hope, and nothing is impossible with God.
                  </p>
                </div>

                <div className="bg-gray-50 p-8 rounded-lg">
                  <h3 className="text-2xl font-bold mb-3 text-primary">Generosity</h3>
                  <p className="text-gray-700">
                    We're committed to living generous lives—with our time, resources, and gifts.
                    We believe it's more blessed to give than to receive.
                  </p>
                </div>

                <div className="bg-gray-50 p-8 rounded-lg">
                  <h3 className="text-2xl font-bold mb-3 text-primary">Excellence</h3>
                  <p className="text-gray-700">
                    We pursue excellence in all we do, not for show, but to honor God and serve
                    people well. Our best is an offering to the One who gave His best for us.
                  </p>
                </div>
              </>
            )}
          </div>
        </div>
      </section>

      {/* Call to Action */}
      <section className="py-16 bg-primary text-white">
        <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl md:text-4xl font-bold mb-6">Be Part of the Vision</h2>
          <p className="text-xl mb-8">
            We believe God is doing something powerful in Norwich, and we'd love for you to
            be part of it. Whether you're exploring faith for the first time or looking for
            a church home, you have a place here.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <a
              href="/connect"
              className="inline-flex items-center justify-center bg-white text-primary px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition-colors"
            >
              Get Connected
            </a>
            <a
              href="/events"
              className="inline-flex items-center justify-center bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-semibold hover:bg-white hover:text-primary transition-colors"
            >
              View Upcoming Events
            </a>
          </div>
        </div>
      </section>
    </div>
  );
}
