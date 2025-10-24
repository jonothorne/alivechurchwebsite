import Link from "next/link";
import Image from "next/image";
import { ArrowRight } from "lucide-react";

interface CardProps {
  title: string;
  description?: string;
  image?: string;
  href: string;
  date?: string;
  category?: string;
}

export default function Card({
  title,
  description,
  image,
  href,
  date,
  category,
}: CardProps) {
  return (
    <Link
      href={href}
      className="group bg-white rounded-lg shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col h-full"
    >
      {image && (
        <div className="relative h-48 w-full overflow-hidden">
          <Image
            src={image}
            alt={title}
            fill
            className="object-cover group-hover:scale-105 transition-transform duration-300"
          />
          {category && (
            <div className="absolute top-4 left-4">
              <span className="bg-primary text-white px-3 py-1 rounded-full text-xs font-semibold">
                {category}
              </span>
            </div>
          )}
        </div>
      )}

      <div className="p-6 flex flex-col flex-grow">
        {date && <p className="text-sm text-primary font-semibold mb-2">{date}</p>}

        <h3 className="text-xl font-bold mb-2 group-hover:text-primary transition-colors">
          {title}
        </h3>

        {description && (
          <p className="text-gray-600 mb-4 flex-grow line-clamp-3">{description}</p>
        )}

        <div className="flex items-center text-primary font-semibold mt-auto">
          <span className="text-sm">Learn more</span>
          <ArrowRight className="h-4 w-4 ml-2 group-hover:translate-x-1 transition-transform" />
        </div>
      </div>
    </Link>
  );
}
