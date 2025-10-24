import Hero from "@/components/Hero";
import Card from "@/components/Card";

export const metadata = {
  title: "News & Updates | Alive Church Norwich",
  description:
    "Stay updated with the latest news, announcements, and stories from Alive Church.",
};

export default function NewsPage() {
  // Placeholder news items - will be replaced with CMS data
  const newsItems = [
    {
      title: "Welcome to Alive Church Website",
      excerpt:
        "We're excited to launch our new website! Stay tuned for updates, news, and resources.",
      date: "October 2024",
      category: "Announcement",
    },
  ];

  return (
    <div>
      <Hero
        title="News & Updates"
        subtitle="Stay Connected"
        description="Keep up with the latest news, announcements, and stories from our church family."
        small
      />

      {/* News Grid Section */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="mb-12">
            <h2 className="text-3xl md:text-4xl font-bold mb-4">Latest News</h2>
            <p className="text-lg text-gray-600">
              Stay informed about what's happening at Alive Church.
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            {newsItems.map((item, index) => (
              <div
                key={index}
                className="bg-gray-50 rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow"
              >
                <div className="aspect-video bg-gradient-to-br from-primary/20 to-primary/10 flex items-center justify-center">
                  <p className="text-gray-500 text-sm">Featured Image</p>
                </div>
                <div className="p-6">
                  {item.category && (
                    <span className="inline-block bg-primary text-white px-3 py-1 rounded-full text-xs font-semibold mb-3">
                      {item.category}
                    </span>
                  )}
                  {item.date && (
                    <p className="text-sm text-gray-500 mb-2">{item.date}</p>
                  )}
                  <h3 className="text-xl font-bold mb-3">{item.title}</h3>
                  <p className="text-gray-600 mb-4">{item.excerpt}</p>
                  <a
                    href="#"
                    className="text-primary font-semibold hover:underline inline-flex items-center gap-1"
                  >
                    Read more →
                  </a>
                </div>
              </div>
            ))}
          </div>

          {/* CMS Notice */}
          <div className="mt-12 bg-blue-50 border-l-4 border-blue-500 p-6 rounded">
            <p className="text-gray-700">
              <strong className="font-semibold">Note:</strong> Blog posts and news articles
              can be added and managed through the CMS. Log in to the{" "}
              <a href="/studio" className="text-primary hover:underline font-semibold">
                CMS admin panel
              </a>{" "}
              to create rich blog posts with images, categories, and formatted content.
            </p>
          </div>
        </div>
      </section>

      {/* Categories Section */}
      <section className="py-16 bg-gray-50">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl md:text-4xl font-bold mb-8 text-center">
            Browse by Category
          </h2>
          <div className="grid md:grid-cols-3 lg:grid-cols-5 gap-4">
            {["Sermons", "News", "Announcements", "Testimonies", "Event Recaps"].map(
              (category) => (
                <a
                  key={category}
                  href="#"
                  className="bg-white p-4 rounded-lg shadow-md text-center hover:shadow-lg hover:bg-primary hover:text-white transition-all"
                >
                  <span className="font-semibold">{category}</span>
                </a>
              )
            )}
          </div>
        </div>
      </section>

      {/* Newsletter Signup */}
      <section className="py-16 bg-white">
        <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl md:text-4xl font-bold mb-6">
            Stay in the Loop
          </h2>
          <p className="text-lg text-gray-600 mb-8">
            Subscribe to receive updates, news, and encouragement delivered to your inbox.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 max-w-md mx-auto">
            <input
              type="email"
              placeholder="Your email address"
              className="flex-grow px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
            />
            <button className="bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-dark transition-colors whitespace-nowrap">
              Subscribe
            </button>
          </div>
          <p className="text-sm text-gray-500 mt-4">
            We respect your privacy. Unsubscribe at any time.
          </p>
        </div>
      </section>
    </div>
  );
}
