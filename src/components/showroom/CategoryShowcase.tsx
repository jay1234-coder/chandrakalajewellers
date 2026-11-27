'use client';

import React from 'react';
import Image from 'next/image';
import { ArrowUpRight, Sparkles } from 'lucide-react';

interface CategoryShowcaseProps {
  theme: 'light' | 'dark';
}

interface CategoryItem {
  id: string;
  title: string;
  gujarati: string;
  tagline: string;
  image: string;
  itemCount: string;
  tag: string;
}

export default function CategoryShowcase({ theme }: CategoryShowcaseProps) {
  const categories: CategoryItem[] = [
    {
      id: 'bridal',
      title: 'Bridal & Dulhan Sets',
      gujarati: 'દુલ્હન સેટ અને રાણી હાર',
      tagline: 'Royal antique choker masterpieces for Gujarati weddings',
      image: '/images/necklace.jpg',
      itemCount: '48+ Designs',
      tag: 'Grand Wedding',
    },
    {
      id: 'bangles',
      title: 'Royal Bangles & Kadas',
      gujarati: 'સોનાના પાટલા અને કડા',
      tagline: 'Hand-carved 916 gold patla, gokhru, and daily wear bangles',
      image: '/images/jewelry2.jpg',
      itemCount: '65+ Designs',
      tag: 'Bestseller',
    },
    {
      id: 'mangalsutra',
      title: 'Mangalsutra & Dokiya',
      gujarati: 'મંગળસૂત્ર અને ડોકીયા',
      tagline: 'Auspicious black bead designs with 22K hallmarked motifs',
      image: '/images/jewelry4.jpg',
      itemCount: '36+ Designs',
      tag: 'Everyday Sacred',
    },
    {
      id: 'earrings',
      title: 'Jhumkas & Chandbalis',
      gujarati: 'ઝુમ્મર, બુટ્ટી અને વાળી',
      tagline: 'Cascading bridal jhumkis, temple studs, and lightweight hoops',
      image: '/images/jewelry3.jpg',
      itemCount: '72+ Designs',
      tag: 'Festive Favorite',
    },
    {
      id: 'rings',
      title: 'Rings & Solitaires',
      gujarati: 'વીંટી અને સગાઈ કલેક્શન',
      tagline: 'Engagement bands, royal floral motifs, and gents luxury rings',
      image: '/images/jewelry1.jpg',
      itemCount: '54+ Designs',
      tag: 'Modern Classic',
    },
    {
      id: 'silver',
      title: '925 Sterling Silver',
      gujarati: 'ચાંદીના આભૂષણ અને પૂજા સામગ્રી',
      tagline: 'Silver payal, baby gifts, auspicious coins, and mandir articles',
      image: '/images/jewelry5.jpg',
      itemCount: '90+ Designs',
      tag: 'Pure & Divine',
    },
  ];

  return (
    <section id="categories" className="py-16 sm:py-24 border-t border-amber-500/10">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Section Header */}
        <div className="text-center max-w-2xl mx-auto mb-14">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300 text-xs font-semibold tracking-widest uppercase mb-3">
            <Sparkles className="w-3.5 h-3.5 text-amber-600 dark:text-yellow-400" />
            <span>Curated Silhouettes</span>
          </div>
          <h2 className="font-serif text-3xl sm:text-4xl font-bold tracking-tight text-stone-900 dark:text-stone-100 mb-3">
            Explore Showroom Collections
          </h2>
          <p className="text-stone-600 dark:text-stone-400 text-sm sm:text-base leading-relaxed">
            Every creation is sculpted to celebrate your most cherished moments, hallmarked with the utmost precision.
          </p>
        </div>

        {/* Category Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
          {categories.map((cat) => (
            <div
              key={cat.id}
              className="group relative rounded-3xl overflow-hidden border border-amber-500/25 bg-white/60 dark:bg-stone-900/60 shadow-lg hover:shadow-2xl transition-all duration-500 flex flex-col justify-between"
            >
              {/* Category Image with Arch Frame & Hover Zoom */}
              <div className="relative h-64 w-full overflow-hidden bg-stone-950">
                <Image
                  src={cat.image}
                  alt={cat.title}
                  fill
                  sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
                  className="object-cover transition-transform duration-700 ease-out group-hover:scale-110 filter brightness-95 group-hover:brightness-105"
                />
                
                {/* Gradient vignette */}
                <div className="absolute inset-0 bg-gradient-to-t from-stone-950 via-stone-950/20 to-transparent opacity-80" />

                {/* Top Badge */}
                <div className="absolute top-4 left-4">
                  <span className="px-3 py-1 rounded-full bg-black/60 backdrop-blur-md border border-amber-500/30 text-[11px] font-bold text-amber-300 uppercase tracking-wider">
                    {cat.tag}
                  </span>
                </div>

                {/* Item Count Pill */}
                <div className="absolute top-4 right-4">
                  <span className="px-2.5 py-1 rounded-full bg-amber-500/90 text-stone-950 text-[11px] font-extrabold tracking-wide">
                    {cat.itemCount}
                  </span>
                </div>

                {/* Gujarati Title on Image */}
                <div className="absolute bottom-4 left-4 right-4">
                  <span className="text-xs font-semibold text-amber-300 font-sans tracking-wide">
                    {cat.gujarati}
                  </span>
                  <h3 className="font-serif text-xl sm:text-2xl font-bold text-white tracking-wide mt-0.5">
                    {cat.title}
                  </h3>
                </div>
              </div>

              {/* Bottom Details & Link */}
              <div className="p-5 flex items-center justify-between gap-4 bg-gradient-to-b from-transparent to-amber-500/5">
                <p className="text-xs text-stone-600 dark:text-stone-400 line-clamp-2 leading-relaxed">
                  {cat.tagline}
                </p>

                <a
                  href={`https://wa.me/919427080359?text=Hello%20ChandraKala%20Jewellers,%20I%20would%20like%20to%20view%20designs%20for%20${encodeURIComponent(cat.title)}.`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="shrink-0 w-10 h-10 rounded-full border border-amber-500/30 bg-amber-500/10 hover:bg-amber-500 text-amber-800 dark:text-amber-300 hover:text-stone-950 flex items-center justify-center transition-all duration-300 group-hover:scale-105 shadow-sm"
                  aria-label={`View ${cat.title} on WhatsApp`}
                >
                  <ArrowUpRight className="w-5 h-5 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                </a>
              </div>
            </div>
          ))}
        </div>

      </div>
    </section>
  );
}
