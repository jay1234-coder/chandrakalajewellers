'use client';

import React from 'react';
import Link from 'next/link';
import { ArrowRight, Sparkles, Clock, ShieldCheck } from 'lucide-react';

interface LiveRatesHeroProps {
  theme: 'light' | 'dark';
}

export default function LiveRatesHero({ theme }: LiveRatesHeroProps) {
  // Exact rates from index1.php / database
  const rates = {
    gold: '13,900.00',
    silver: '160.00',
    silver925: '600.00',
    copper: '1,800.00',
    updatedAt: '2026-11-19 14:17:17 IST',
  };

  return (
    <section id="hero" className="relative overflow-hidden pt-10 pb-16 sm:py-20">
      
      {/* Background ambient gold tint */}
      <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-tr from-amber-400/10 via-yellow-300/5 to-transparent rounded-full blur-3xl pointer-events-none" />

      <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        
        {/* ========================================================
            App Greeting (Direct from index1.php #app-greeting)
            ======================================================== */}
        <div className="mb-8 inline-flex flex-col items-center">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300 text-xs font-semibold tracking-widest uppercase mb-2">
            <Sparkles className="w-3.5 h-3.5 text-amber-600 dark:text-yellow-400" />
            <span>Welcome</span>
          </div>
          <h2 className="font-serif text-3xl sm:text-4xl font-bold tracking-tight text-amber-900 dark:text-amber-200">
            Namaste
          </h2>
          <p className="text-xs sm:text-sm text-stone-600 dark:text-stone-400 mt-1">
            Explore gold &amp; silver jewellery crafted in Khedbrahma.
          </p>
        </div>

        {/* ========================================================
            Main Hero Headline & Subtitle (Direct from index1.php #hero)
            ======================================================== */}
        <h1 className="font-serif text-3xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-stone-900 dark:text-stone-100 leading-tight mb-4 max-w-4xl mx-auto">
          Gold &amp; Silver Jewellery Store in Khedbrahma
        </h1>

        <p className="text-base sm:text-lg text-stone-600 dark:text-stone-400 max-w-2xl mx-auto mb-10 leading-relaxed font-sans">
          ChandraKala Jewellers — timeless 916 gold &amp; silver pieces crafted with passion on Civil Road.
        </p>

        {/* ========================================================
            OUR TODAY'S RATES BOX (Direct from index1.php .rate-box)
            ======================================================== */}
        <div className="max-w-2xl mx-auto rounded-3xl p-6 sm:p-8 border border-amber-500/30 bg-gradient-to-b from-white/95 to-amber-50/70 dark:from-stone-900/95 dark:to-stone-950/90 shadow-xl backdrop-blur-md mb-8">
          
          <div className="flex items-center justify-between pb-4 border-b border-amber-500/20 mb-6">
            <div className="flex items-center gap-2">
              <span className="relative flex h-2.5 w-2.5">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
              </span>
              <h3 className="font-serif text-base sm:text-lg font-bold tracking-wider uppercase text-amber-900 dark:text-amber-200">
                OUR TODAY'S RATES
              </h3>
            </div>

            <div className="flex items-center gap-1 text-[11px] text-stone-500 dark:text-stone-400 font-medium">
              <Clock className="w-3.5 h-3.5 text-amber-600" />
              <span>Updated: {rates.updatedAt}</span>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-left">
            {/* Gold */}
            <div className="p-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 dark:bg-amber-500/15">
              <span className="text-[11px] font-bold tracking-wider text-amber-800 dark:text-amber-300 uppercase block">
                GOLD
              </span>
              <span className="text-2xl font-bold font-serif text-stone-900 dark:text-amber-200 mt-1 block">
                ₹{rates.gold}
              </span>
              <span className="text-[10px] text-stone-500 dark:text-stone-400 mt-1 block">
                per gram*
              </span>
            </div>

            {/* Silver */}
            <div className="p-4 rounded-2xl border border-stone-300 dark:border-stone-800 bg-stone-100/50 dark:bg-stone-900/50">
              <span className="text-[11px] font-bold tracking-wider text-stone-600 dark:text-stone-300 uppercase block">
                SILVER
              </span>
              <span className="text-2xl font-bold font-serif text-stone-900 dark:text-stone-100 mt-1 block">
                ₹{rates.silver}
              </span>
              <span className="text-[10px] text-stone-500 dark:text-stone-400 mt-1 block">
                per gram*
              </span>
            </div>

            {/* 925 Silver */}
            <div className="p-4 rounded-2xl border border-stone-300 dark:border-stone-800 bg-stone-100/50 dark:bg-stone-900/50">
              <span className="text-[11px] font-bold tracking-wider text-stone-600 dark:text-stone-300 uppercase block">
                925 SILVER
              </span>
              <span className="text-2xl font-bold font-serif text-stone-900 dark:text-stone-100 mt-1 block">
                ₹{rates.silver925}
              </span>
              <span className="text-[10px] text-stone-500 dark:text-stone-400 mt-1 block">
                per gram*
              </span>
            </div>

            {/* Copper 999 Fine */}
            <div className="p-4 rounded-2xl border border-amber-700/20 bg-amber-800/5 dark:bg-amber-900/20">
              <span className="text-[11px] font-bold tracking-wider text-amber-800 dark:text-amber-400 uppercase block">
                COPPER 999 FINE
              </span>
              <span className="text-2xl font-bold font-serif text-stone-900 dark:text-stone-100 mt-1 block">
                ₹{rates.copper}
              </span>
              <span className="text-[10px] text-stone-500 dark:text-stone-400 mt-1 block font-medium">
                /KG · Copper delivered in 4 or 4+ working days
              </span>
            </div>
          </div>

        </div>

        {/* Hero Disclaimer */}
        <p className="text-xs text-stone-500 dark:text-stone-400 mb-8 italic">
          The jewellery product shown may vary from the actual product*
        </p>

        {/* Hero CTAs */}
        <div className="flex flex-wrap items-center justify-center gap-4">
          <Link
            href="/gallery"
            className="inline-flex items-center gap-2 px-7 py-3 rounded-full bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white font-semibold text-sm shadow-md hover:shadow-lg transition-all"
          >
            <span>Shop Now</span>
            <ArrowRight className="w-4 h-4" />
          </Link>

          <Link
            href="/Catalogue"
            className="inline-flex items-center gap-2 px-7 py-3 rounded-full border border-amber-600/40 bg-white/50 dark:bg-stone-900/50 hover:bg-amber-100/40 dark:hover:bg-stone-800 text-stone-900 dark:text-amber-300 font-semibold text-sm transition-all"
          >
            <span>Catalogue</span>
          </Link>
        </div>

      </div>
    </section>
  );
}
