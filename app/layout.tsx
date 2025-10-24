import type { Metadata } from "next";
import "./globals.css";
import Header from "@/components/Header";
import Footer from "@/components/Footer";

export const metadata: Metadata = {
  title: "Alive Church Norwich | Modern Pentecostal Church",
  description: "Alive Church is a modern pentecostal church in Norwich with our core roots in community and family. Join us Sundays at 11am at Alive House, Nelson Street.",
  keywords: ["church", "pentecostal", "Norwich", "Alive Church", "community", "family", "worship"],
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body className="antialiased flex flex-col min-h-screen">
        <Header />
        <main className="flex-grow">{children}</main>
        <Footer />
      </body>
    </html>
  );
}
