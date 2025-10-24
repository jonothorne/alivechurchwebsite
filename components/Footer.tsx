import Link from "next/link";
import Image from "next/image";
import { MapPin, Mail, Facebook, Instagram } from "lucide-react";

export default function Footer() {
  const currentYear = new Date().getFullYear();

  return (
    <footer className="bg-gray-900 text-gray-300">
      <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          {/* Logo and Description */}
          <div className="md:col-span-1">
            <Link href="/" className="flex items-center gap-2 mb-4">
              <Image
                src="/logo.png"
                alt="Alive Church Logo"
                width={40}
                height={40}
                className="h-10 w-auto"
              />
              <span className="text-lg font-bold text-white">
                Alive <span className="text-primary">Church</span>
              </span>
            </Link>
            <p className="text-sm">
              A modern pentecostal church rooted in community and family.
            </p>
          </div>

          {/* Quick Links */}
          <div>
            <h3 className="text-white font-semibold mb-4">Quick Links</h3>
            <ul className="space-y-2 text-sm">
              <li>
                <Link href="/about" className="hover:text-primary transition-colors">
                  About Us
                </Link>
              </li>
              <li>
                <Link href="/events" className="hover:text-primary transition-colors">
                  Events
                </Link>
              </li>
              <li>
                <Link href="/news" className="hover:text-primary transition-colors">
                  News
                </Link>
              </li>
              <li>
                <Link href="/connect" className="hover:text-primary transition-colors">
                  Connect
                </Link>
              </li>
              <li>
                <Link href="/give" className="hover:text-primary transition-colors">
                  Give
                </Link>
              </li>
            </ul>
          </div>

          {/* Service Times */}
          <div>
            <h3 className="text-white font-semibold mb-4">Service Times</h3>
            <p className="text-sm mb-4">
              <strong className="text-white">Sunday Service</strong>
              <br />
              11:00 AM
            </p>
            <p className="text-sm">
              Join us every Sunday for worship, teaching, and fellowship.
            </p>
          </div>

          {/* Contact */}
          <div>
            <h3 className="text-white font-semibold mb-4">Get in Touch</h3>
            <ul className="space-y-3 text-sm">
              <li className="flex items-start gap-2">
                <MapPin className="h-5 w-5 flex-shrink-0 text-primary mt-0.5" />
                <span>
                  Alive House, Nelson Street
                  <br />
                  Norwich NR2 4DR
                </span>
              </li>
              <li className="flex items-center gap-2">
                <Mail className="h-5 w-5 flex-shrink-0 text-primary" />
                <a
                  href="mailto:office@alive.me.uk"
                  className="hover:text-primary transition-colors"
                >
                  office@alive.me.uk
                </a>
              </li>
            </ul>
            {/* Social Media */}
            <div className="flex gap-4 mt-4">
              <a
                href="#"
                className="hover:text-primary transition-colors"
                aria-label="Facebook"
              >
                <Facebook className="h-5 w-5" />
              </a>
              <a
                href="#"
                className="hover:text-primary transition-colors"
                aria-label="Instagram"
              >
                <Instagram className="h-5 w-5" />
              </a>
            </div>
          </div>
        </div>

        {/* Bottom Bar */}
        <div className="mt-12 pt-8 border-t border-gray-800 text-sm text-center">
          <p>
            &copy; {currentYear} Alive Church Norwich. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  );
}
