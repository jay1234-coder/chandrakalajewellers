import type { Metadata, Viewport } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'ChandraKala Jewellers | 916 BIS Gold, Silver & Live Rates Khedbrahma',
  description: 'ChandraKala Jewellers — Trusted 916 BIS Hallmarked Gold, Pure Silver & 925 Sterling Silver Jewellery Showroom in Khedbrahma, Gujarat.',
  icons: {
    icon: '/favicon.ico',
    apple: '/apple-touch-icon.png',
  },
  manifest: '/manifest.webmanifest',
};

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  maximumScale: 5,
  themeColor: '#B8972C',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en-IN">
      <body>{children}</body>
    </html>
  );
}
