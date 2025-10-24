import Hero from "@/components/Hero";
import { Heart, Smartphone, Building, Users } from "lucide-react";

export const metadata = {
  title: "Give | Alive Church Norwich",
  description:
    "Support the mission and ministry of Alive Church through generous giving.",
};

export default function GivePage() {
  return (
    <div>
      <Hero
        title="Give"
        subtitle="Generous Living"
        description="Your generosity enables us to reach our community, support those in need, and advance God's kingdom."
        small
      />

      {/* Why We Give Section */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl md:text-4xl font-bold mb-8 text-center">
            Why We Give
          </h2>
          <div className="prose prose-lg max-w-none">
            <p className="text-lg text-gray-700 leading-relaxed mb-6">
              Giving is an act of worship and a response to God's incredible generosity toward
              us. When we give, we're participating in God's work of transformation in our
              community and beyond. Your financial gifts enable us to:
            </p>
            <div className="grid md:grid-cols-2 gap-6 mt-8">
              <div className="bg-gray-50 p-6 rounded-lg">
                <Church className="h-8 w-8 text-primary mb-3" />
                <h3 className="text-xl font-bold mb-2">Advance the Gospel</h3>
                <p className="text-gray-700">
                  Support outreach, missions, and evangelism efforts locally and globally.
                </p>
              </div>
              <div className="bg-gray-50 p-6 rounded-lg">
                <Users className="h-8 w-8 text-primary mb-3" />
                <h3 className="text-xl font-bold mb-2">Build Community</h3>
                <p className="text-gray-700">
                  Create environments where people can connect, grow, and experience God.
                </p>
              </div>
              <div className="bg-gray-50 p-6 rounded-lg">
                <Heart className="h-8 w-8 text-primary mb-3" />
                <h3 className="text-xl font-bold mb-2">Serve Others</h3>
                <p className="text-gray-700">
                  Meet practical needs in our community and support those facing hardship.
                </p>
              </div>
              <div className="bg-gray-50 p-6 rounded-lg">
                <Building className="h-8 w-8 text-primary mb-3" />
                <h3 className="text-xl font-bold mb-2">Sustain Ministry</h3>
                <p className="text-gray-700">
                  Maintain our facilities and resources to serve our church family well.
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Ways to Give Section */}
      <section className="py-16 bg-gray-50">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl md:text-4xl font-bold mb-12 text-center">
            Ways to Give
          </h2>
          <div className="grid md:grid-cols-3 gap-8">
            <div className="bg-white p-8 rounded-lg shadow-md text-center">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                <Smartphone className="h-8 w-8 text-primary" />
              </div>
              <h3 className="text-xl font-bold mb-3">Online Giving</h3>
              <p className="text-gray-600 mb-6">
                Give securely online using your debit or credit card. Fast, simple, and secure.
              </p>
              <div className="bg-primary/10 text-primary px-4 py-3 rounded text-sm font-semibold">
                Integration Coming Soon
              </div>
            </div>

            <div className="bg-white p-8 rounded-lg shadow-md text-center">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                <Building className="h-8 w-8 text-primary" />
              </div>
              <h3 className="text-xl font-bold mb-3">Bank Transfer</h3>
              <p className="text-gray-600 mb-6">
                Set up a standing order or make a one-time transfer directly to our bank account.
              </p>
              <p className="text-sm text-gray-500">
                Contact us for bank details
              </p>
            </div>

            <div className="bg-white p-8 rounded-lg shadow-md text-center">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                <Heart className="h-8 w-8 text-primary" />
              </div>
              <h3 className="text-xl font-bold mb-3">In Person</h3>
              <p className="text-gray-600 mb-6">
                Give during our Sunday service using the offering baskets or giving station.
              </p>
              <p className="text-sm text-gray-500">
                Sundays at 11:00 AM
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Biblical Teaching Section */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl md:text-4xl font-bold mb-8 text-center">
            God's Heart for Generosity
          </h2>
          <div className="space-y-6">
            <div className="bg-gray-50 p-6 rounded-lg border-l-4 border-primary">
              <p className="text-lg italic text-gray-700 mb-2">
                "Each of you should give what you have decided in your heart to give, not
                reluctantly or under compulsion, for God loves a cheerful giver."
              </p>
              <p className="text-sm font-semibold text-primary">— 2 Corinthians 9:7</p>
            </div>

            <div className="bg-gray-50 p-6 rounded-lg border-l-4 border-primary">
              <p className="text-lg italic text-gray-700 mb-2">
                "Honor the LORD with your wealth, with the firstfruits of all your crops."
              </p>
              <p className="text-sm font-semibold text-primary">— Proverbs 3:9</p>
            </div>

            <div className="bg-gray-50 p-6 rounded-lg border-l-4 border-primary">
              <p className="text-lg italic text-gray-700 mb-2">
                "Give, and it will be given to you. A good measure, pressed down, shaken
                together and running over, will be poured into your lap."
              </p>
              <p className="text-sm font-semibold text-primary">— Luke 6:38</p>
            </div>
          </div>
        </div>
      </section>

      {/* Call to Action */}
      <section className="py-16 bg-primary text-white">
        <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl md:text-4xl font-bold mb-6">
            Questions About Giving?
          </h2>
          <p className="text-xl mb-8">
            We're happy to answer any questions you have about financial giving or stewardship.
          </p>
          <a
            href="/connect"
            className="inline-flex items-center justify-center bg-white text-primary px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition-colors shadow-md"
          >
            Contact Us
          </a>
        </div>
      </section>
    </div>
  );
}
