'use client';

import React from 'react';
import { Star, Sparkles, Quote, MapPin } from 'lucide-react';

interface CustomerLoveProps {
  theme: 'light' | 'dark';
}

export default function CustomerLove({ theme }: CustomerLoveProps) {
  const reviews = [
    {
      name: 'Rameshbhai & Kokilaben Patel',
      location: 'Khedbrahma',
      purchase: 'Complete Bridal Wedding Set',
      quote:
        'મારી દીકરીના લગ્ન માટે અમે ચંદ્રકલા જ્વેલર્સમાંથી આખું સેટ બનાવડાવ્યું. ડિઝાઇન, હોલમાર્કિંગ અને વજનની ચોકસાઈ એટલી સુંદર હતી કે આખા પરિવારે વખાણ કર્યા. ખેડબ્રહ્મામાં આનાથી સારો વિશ્વાસ ક્યાંય નથી.',
      stars: 5,
      date: 'Wedding Season 2024',
    },
    {
      name: 'Geetaben Soni',
      location: 'Idar',
      purchase: '22K 916 Antique Patla Bangles',
      quote:
        'The karigari on the antique patla bangles is simply breathtaking. Every single gram was weighed in front of us, with full HUID certificate. Very honest and polite family service!',
      stars: 5,
      date: 'Diwali Festive Purchase',
    },
    {
      name: 'Dr. Hitesh Shah',
      location: 'Himatnagar / Khedbrahma',
      purchase: 'Gold Coins & Dokiya Chain',
      quote:
        'What I truly respect about ChandraKala is their rate transparency. Zero hidden making charges, accurate live MCX gold rate, and lifetime exchange guarantee. Truly a 5-star experience.',
      stars: 5,
      date: 'Akshaya Tritiya',
    },
  ];

  return (
    <section id="reviews" className="py-16 sm:py-24 border-t border-amber-500/10 bg-amber-500/5 dark:bg-stone-900/40">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Section Header */}
        <div className="text-center max-w-2xl mx-auto mb-14">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300 text-xs font-semibold tracking-widest uppercase mb-3">
            <Sparkles className="w-3.5 h-3.5 text-amber-600 dark:text-yellow-400" />
            <span>Customer Love</span>
          </div>
          <h2 className="font-serif text-3xl sm:text-4xl font-bold tracking-tight text-stone-900 dark:text-stone-100 mb-2">
            Loved by Families Across Gujarat
          </h2>
          <p className="text-stone-600 dark:text-stone-400 text-sm sm:text-base">
            ખેડબ્રહ્મા, ઈડર અને સાબરકાંઠાના પરિવારોનો અતૂટ સ્નેહ અને વિશ્વાસ.
          </p>
        </div>

        {/* 3 Review Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
          {reviews.map((rev, idx) => (
            <div
              key={idx}
              className="p-7 rounded-3xl border border-amber-500/25 bg-white/70 dark:bg-stone-900/70 shadow-lg hover:shadow-xl transition-all duration-300 flex flex-col justify-between"
            >
              <div>
                {/* 5 Stars */}
                <div className="flex items-center gap-1 mb-4 text-amber-500">
                  {[...Array(rev.stars)].map((_, i) => (
                    <Star key={i} className="w-4 h-4 fill-amber-400 text-amber-400" />
                  ))}
                </div>

                {/* Quote */}
                <p className="text-stone-700 dark:text-stone-300 text-sm leading-relaxed italic mb-6">
                  "{rev.quote}"
                </p>
              </div>

              {/* Reviewer Details */}
              <div className="pt-4 border-t border-amber-500/20 flex items-center justify-between">
                <div>
                  <h4 className="font-serif text-sm font-bold text-stone-900 dark:text-stone-100">
                    {rev.name}
                  </h4>
                  <div className="flex items-center gap-1 text-[11px] text-amber-700 dark:text-amber-400 mt-0.5">
                    <MapPin className="w-3 h-3" />
                    <span>{rev.location} · {rev.purchase}</span>
                  </div>
                </div>

                <Quote className="w-6 h-6 text-amber-400/30 shrink-0" />
              </div>
            </div>
          ))}
        </div>

      </div>
    </section>
  );
}
