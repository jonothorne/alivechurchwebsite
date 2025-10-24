import Link from "next/link";
import { ArrowRight } from "lucide-react";

interface HeroProps {
  title: string;
  subtitle?: string;
  description?: string;
  ctaText?: string;
  ctaLink?: string;
  backgroundImage?: string;
  small?: boolean;
}

export default function Hero({
  title,
  subtitle,
  description,
  ctaText,
  ctaLink,
  backgroundImage,
  small = false,
}: HeroProps) {
  return (
    <div
      className={`relative bg-gradient-to-br from-primary/10 via-white to-primary/5 ${
        small ? "py-16" : "py-24 md:py-32"
      }`}
      style={
        backgroundImage
          ? {
              backgroundImage: `url(${backgroundImage})`,
              backgroundSize: "cover",
              backgroundPosition: "center",
            }
          : undefined
      }
    >
      {backgroundImage && (
        <div className="absolute inset-0 bg-gradient-to-r from-black/60 to-black/40" />
      )}

      <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className={`max-w-3xl ${backgroundImage ? "text-white" : ""}`}>
          {subtitle && (
            <p className={`text-sm font-semibold uppercase tracking-wide mb-4 ${
              backgroundImage ? "text-primary-light" : "text-primary"
            }`}>
              {subtitle}
            </p>
          )}

          <h1
            className={`font-bold mb-6 ${
              small ? "text-4xl md:text-5xl" : "text-5xl md:text-6xl lg:text-7xl"
            }`}
          >
            {title}
          </h1>

          {description && (
            <p
              className={`text-lg md:text-xl mb-8 ${
                backgroundImage ? "text-gray-100" : "text-gray-600"
              }`}
            >
              {description}
            </p>
          )}

          {ctaText && ctaLink && (
            <Link
              href={ctaLink}
              className="inline-flex items-center gap-2 bg-primary text-white px-8 py-4 rounded-lg font-semibold hover:bg-primary-dark transition-colors shadow-lg hover:shadow-xl"
            >
              {ctaText}
              <ArrowRight className="h-5 w-5" />
            </Link>
          )}
        </div>
      </div>
    </div>
  );
}
