'use client';

import React, { useState, useEffect } from 'react';
import ShowroomHeader from '@/components/showroom/ShowroomHeader';
import LiveRatesHero from '@/components/showroom/LiveRatesHero';
import FeaturedCollection from '@/components/showroom/FeaturedCollection';
import ReelsCinema from '@/components/showroom/ReelsCinema';
import HeritageTrust from '@/components/showroom/HeritageTrust';
import ContactSection from '@/components/showroom/ContactSection';
import BlogSection from '@/components/showroom/BlogSection';
import ShowroomFaq from '@/components/showroom/ShowroomFaq';
import ShowroomFooter from '@/components/showroom/ShowroomFooter';

export default function ShowroomPage() {
  // Theme state: 'light' (primary matching theme) and 'dark'
  const [theme, setTheme] = useState<'light' | 'dark'>('light');
  const [wishlistCount, setWishlistCount] = useState<number>(0);
  const [cartCount, setCartCount] = useState<number>(0);

  // Sync theme with html class
  useEffect(() => {
    const root = document.documentElement;
    if (theme === 'dark') {
      root.classList.add('dark');
      root.classList.remove('light');
    } else {
      root.classList.add('light');
      root.classList.remove('dark');
    }
  }, [theme]);

  const toggleTheme = () => {
    setTheme((prev) => (prev === 'light' ? 'dark' : 'light'));
  };

  return (
    <div className={`min-h-screen transition-colors duration-500 font-sans ${theme === 'light' ? 'bg-[#FAF8F5] text-stone-900' : 'bg-[#090B0E] text-stone-100'}`}>
      
      {/* 1. Header with Ticker Bar & Theme Switch */}
      <ShowroomHeader
        theme={theme}
        onToggleTheme={toggleTheme}
        wishlistCount={wishlistCount}
        cartCount={cartCount}
      />

      <main className="relative">
        {/* 2. Welcome Greeting & Today's Rates Hero */}
        <LiveRatesHero theme={theme} />

        {/* 3. Hot Favourite Items (Exact 6 pieces from database) */}
        <FeaturedCollection
          theme={theme}
          onWishlistChange={(count) => setWishlistCount(count)}
          onCartChange={(count) => setCartCount(count)}
        />

        {/* 4. Live Showroom Cinema (Sparkle in Motion) */}
        <ReelsCinema theme={theme} />

        {/* 5. About ChandraKala Jewellers, Khedbrahma (Story & 4 Stats) */}
        <HeritageTrust theme={theme} />

        {/* 6. Contact Us (Visit Us, Call, Email) */}
        <ContactSection theme={theme} />

        {/* 7. From Our Blog (Care Tips, Buying Guide, Occasions) */}
        <BlogSection theme={theme} />

        {/* 8. Frequently Asked Questions (8 Exact FAQs) */}
        <ShowroomFaq theme={theme} />
      </main>

      {/* 9. Site Footer */}
      <ShowroomFooter theme={theme} />
    </div>
  );
}
