'use client';

import React from 'react';
import Link from 'next/link';

interface ShowroomFooterProps {
  theme: 'light' | 'dark';
}

export default function ShowroomFooter({ theme }: ShowroomFooterProps) {
  // Exact content from includes/footer.php
  return (
    <footer className="border-t border-amber-500/20 bg-stone-100/90 dark:bg-[#090B0E] text-stone-700 dark:text-stone-300 transition-colors duration-300">
      
      {/* 4 Footer Columns */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
          
          {/* Section 1: About */}
          <div>
            <h3 className="font-serif text-lg font-bold text-stone-900 dark:text-stone-100 mb-3">
              ChandraKala Jewellers
            </h3>
            <p className="text-xs sm:text-sm text-stone-600 dark:text-stone-400 leading-relaxed">
              Family-owned gold &amp; silver jewellers in Khedbrahma, Sabarkantha. 916 gold, silver, custom designs &amp; WhatsApp orders.
            </p>
          </div>

          {/* Section 2: Quick Links */}
          <div>
            <h3 className="font-serif text-base font-bold text-stone-900 dark:text-stone-100 mb-3">
              Quick Links
            </h3>
            <ul className="text-xs sm:text-sm space-y-2 text-stone-600 dark:text-stone-400">
              <li><Link href="/showroom" className="hover:text-amber-600 dark:hover:text-amber-400">Home</Link></li>
              <li><Link href="/gallery" className="hover:text-amber-600 dark:hover:text-amber-400">Shop</Link></li>
              <li><Link href="/Catalogue" className="hover:text-amber-600 dark:hover:text-amber-400">Design Catalogue</Link></li>
              <li><Link href="/reels" className="hover:text-amber-600 dark:hover:text-amber-400">Jewellery Reels</Link></li>
              <li><Link href="/blog" className="hover:text-amber-600 dark:hover:text-amber-400">Blog</Link></li>
              <li><Link href="/jewellers-in-khedbrahma" className="hover:text-amber-600 dark:hover:text-amber-400">Jewellers in Khedbrahma</Link></li>
              <li><Link href="/gold-rate-khedbrahma" className="hover:text-amber-600 dark:hover:text-amber-400">Gold Rate Khedbrahma</Link></li>
              <li><Link href="/follow-us" className="hover:text-amber-600 dark:hover:text-amber-400">Follow Us &amp; Reviews</Link></li>
            </ul>
          </div>

          {/* Section 3: Information */}
          <div>
            <h3 className="font-serif text-base font-bold text-stone-900 dark:text-stone-100 mb-3">
              Information
            </h3>
            <ul className="text-xs sm:text-sm space-y-2 text-stone-600 dark:text-stone-400">
              <li><Link href="/about" className="hover:text-amber-600 dark:hover:text-amber-400">About Us</Link></li>
              <li><Link href="/contact" className="hover:text-amber-600 dark:hover:text-amber-400">Contact Us</Link></li>
              <li><Link href="/follow-us" className="hover:text-amber-600 dark:hover:text-amber-400">Instagram &amp; Google</Link></li>
              <li><Link href="/blog" className="hover:text-amber-600 dark:hover:text-amber-400">Blog &amp; Care Tips</Link></li>
              <li><Link href="/gallery?metal_type=gold" className="hover:text-amber-600 dark:hover:text-amber-400">916 Gold Jewellery</Link></li>
              <li><Link href="/gallery?metal_type=silver" className="hover:text-amber-600 dark:hover:text-amber-400">Silver Jewellery</Link></li>
            </ul>
          </div>

          {/* Section 4: Visit Us */}
          <div>
            <h3 className="font-serif text-base font-bold text-stone-900 dark:text-stone-100 mb-3">
              Visit Us
            </h3>
            <p className="text-xs sm:text-sm text-stone-600 dark:text-stone-400 leading-relaxed mb-3">
              Opp. Bhoomi Complex, Civil Road,<br />
              22JW+HG9, Khedbrahma-383255, Gujarat
            </p>

            <p className="text-xs sm:text-sm mb-3">
              <a href="tel:+919427080359" className="font-semibold text-amber-700 dark:text-amber-400 hover:underline">
                +91 9427080359
              </a>
            </p>

            <p className="text-xs mb-2">
              <a
                href="https://www.google.com/maps/dir/?api=1&destination=Chandrakala+jewellers,+22JW%2BHG9,+Khedbrahma,+Gujarat+383255&destination_place_id=0x395d0b1a27d5a3df:0x216db437426f9e6a"
                target="_blank"
                rel="noopener noreferrer"
                className="hover:underline text-stone-700 dark:text-stone-300 font-medium"
              >
                Google Maps Directions
              </a>
            </p>

            <p className="text-xs space-x-2 text-stone-600 dark:text-stone-400">
              <a
                href="https://www.instagram.com/chandra.kalajewellers?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                target="_blank"
                rel="noopener noreferrer"
                className="hover:underline text-amber-700 dark:text-amber-400 font-medium"
              >
                Instagram
              </a>
              <span>·</span>
              <a
                href="https://g.page/r/CWqeb0I3tG0hEAI/review"
                target="_blank"
                rel="noopener noreferrer"
                className="hover:underline text-amber-700 dark:text-amber-400 font-medium"
              >
                Google reviews
              </a>
            </p>
          </div>

        </div>
      </div>

      {/* Footer Bottom */}
      <div className="py-5 border-t border-amber-500/15 bg-stone-200/50 dark:bg-black/50 text-stone-500 dark:text-stone-400 text-xs text-center sm:flex sm:justify-between sm:text-left max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p>&copy; {new Date().getFullYear()} ChandraKala Jewellers · <a href="/showroom" className="hover:underline">chandrakalajewellers.in</a></p>
        <p className="mt-1 sm:mt-0">Designed with elegance &amp; passion.</p>
      </div>

    </footer>
  );
}
