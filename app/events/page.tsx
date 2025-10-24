import Hero from "@/components/Hero";
import { Calendar, MapPin, Clock } from "lucide-react";

export const metadata = {
  title: "Events | Alive Church Norwich",
  description:
    "Discover upcoming events at Alive Church. Join us for worship services, special events, and community gatherings.",
};

export default function EventsPage() {
  // Placeholder events - will be replaced with CMS data
  const upcomingEvents = [
    {
      title: "Sunday Service",
      date: "Every Sunday",
      time: "11:00 AM",
      location: "Alive House, Nelson Street, Norwich",
      description:
        "Join us for dynamic worship, powerful teaching, and genuine community.",
      category: "Service",
    },
  ];

  return (
    <div>
      <Hero
        title="Events"
        subtitle="What's Happening"
        description="Join us for worship, community events, and special gatherings designed to help you grow in faith and connect with others."
        small
      />

      {/* Events List Section */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="mb-12">
            <h2 className="text-3xl md:text-4xl font-bold mb-4">Upcoming Events</h2>
            <p className="text-lg text-gray-600">
              Mark your calendar and join us for these exciting events!
            </p>
          </div>

          <div className="grid gap-6">
            {upcomingEvents.map((event, index) => (
              <div
                key={index}
                className="bg-gray-50 rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow"
              >
                <div className="md:flex">
                  <div className="md:w-1/4 bg-gradient-to-br from-primary to-primary-dark p-8 text-white flex flex-col justify-center">
                    <div className="text-center">
                      <Calendar className="h-12 w-12 mx-auto mb-4" />
                      <p className="text-2xl font-bold">{event.date}</p>
                      {event.category && (
                        <span className="inline-block mt-2 bg-white/20 px-3 py-1 rounded-full text-sm">
                          {event.category}
                        </span>
                      )}
                    </div>
                  </div>
                  <div className="md:w-3/4 p-8">
                    <h3 className="text-2xl font-bold mb-4">{event.title}</h3>
                    <p className="text-gray-700 mb-6">{event.description}</p>
                    <div className="space-y-2">
                      <div className="flex items-center gap-2 text-gray-600">
                        <Clock className="h-5 w-5 text-primary" />
                        <span>{event.time}</span>
                      </div>
                      <div className="flex items-center gap-2 text-gray-600">
                        <MapPin className="h-5 w-5 text-primary" />
                        <span>{event.location}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* CMS Notice */}
          <div className="mt-12 bg-blue-50 border-l-4 border-blue-500 p-6 rounded">
            <p className="text-gray-700">
              <strong className="font-semibold">Note:</strong> Additional events can be
              added and managed through the CMS. Log in to the{" "}
              <a href="/studio" className="text-primary hover:underline font-semibold">
                CMS admin panel
              </a>{" "}
              to create, edit, and manage events with images, registration links, and more.
            </p>
          </div>
        </div>
      </section>

      {/* Regular Events Section */}
      <section className="py-16 bg-gray-50">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl md:text-4xl font-bold mb-8 text-center">
            Regular Gatherings
          </h2>
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div className="bg-white p-6 rounded-lg shadow-md">
              <h3 className="text-xl font-bold mb-2">Sunday Service</h3>
              <p className="text-sm text-primary font-semibold mb-3">Every Sunday, 11:00 AM</p>
              <p className="text-gray-600">
                Our main weekly gathering featuring worship, teaching, and community.
              </p>
            </div>

            <div className="bg-white p-6 rounded-lg shadow-md">
              <h3 className="text-xl font-bold mb-2">Small Groups</h3>
              <p className="text-sm text-primary font-semibold mb-3">Various Times</p>
              <p className="text-gray-600">
                Connect in a smaller setting for deeper relationships and spiritual growth.
              </p>
            </div>

            <div className="bg-white p-6 rounded-lg shadow-md">
              <h3 className="text-xl font-bold mb-2">Prayer Meetings</h3>
              <p className="text-sm text-primary font-semibold mb-3">Contact for Details</p>
              <p className="text-gray-600">
                Join us as we seek God together through corporate prayer.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Call to Action */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl md:text-4xl font-bold mb-6">
            Questions About an Event?
          </h2>
          <p className="text-lg text-gray-600 mb-8">
            We'd love to help you find the right event or answer any questions you might
            have.
          </p>
          <a
            href="/connect"
            className="inline-flex items-center justify-center bg-primary text-white px-8 py-4 rounded-lg font-semibold hover:bg-primary-dark transition-colors shadow-md hover:shadow-lg"
          >
            Get in Touch
          </a>
        </div>
      </section>
    </div>
  );
}
