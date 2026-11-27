'use client';

import React from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { Play, Eye, ArrowRight } from 'lucide-react';

interface ReelsCinemaProps {
  theme: 'light' | 'dark';
}

export default function ReelsCinema({ theme }: ReelsCinemaProps) {
  // Real reels from database query in index1.php (reels.php)
  const reels = [
    {
      id: 6,
      poster: 'https://res.cloudinary.com/hihrgcxm/video/upload/so_0.5,f_auto,q_auto,w_540/chandrakala/reels/reel_20261118_162836_f11fdb95.jpg',
      views: 15,
      duration: '12s',
    },
    {
      id: 4,
      poster: 'https://res.cloudinary.com/hihrgcxm/video/upload/so_0.5,f_auto,q_auto,w_540/chandrakala/reels/reel_20261118_162822_8ee89832.jpg',
      views: 14,
      duration: '4s',
    },
    {
      id: 1,
      poster: 'https://res.cloudinary.com/hihrgcxm/video/upload/so_0.5,f_auto,q_auto,w_540/chandrakala/reels/reel_20261118_162551_5f293a1b.jpg',
      views: 13,
      duration: '14s',
    },
    {
      id: 2,
      poster: 'https://res.cloudinary.com/hihrgcxm/video/upload/so_0.5,f_auto,q_auto,w_540/chandrakala/reels/reel_20261118_162759_4954e4e2.jpg',
      views: 13,
      duration: '6s',
    },
  ];

  return (
    <section id="reels-showcase" className="py-16 sm:py-24 border-t border-amber-500/10">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Section Header (Direct from index1.php #reels-showcase) */}
        <div className="flex flex-col sm:flex-row sm:items-end justify-between mb-10 gap-4">
          <div>
            <p className="text-[11px] font-bold tracking-[0.14em] uppercase text-amber-700 dark:text-amber-400 mb-1">
              Live Showroom Cinema
            </p>
            <h2 className="font-serif text-3xl sm:text-4xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
              Sparkle in Motion
            </h2>
          </div>

          <Link
            href="/reels"
            className="text-xs sm:text-sm font-bold text-amber-700 dark:text-amber-400 hover:underline flex items-center gap-1"
          >
            <span>Explore All Reels</span>
            <ArrowRight className="w-4 h-4" />
          </Link>
        </div>

        {/* Reels Bento Grid */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
          {reels.map((reel) => (
            <a
              key={reel.id}
              href={`/showcase?v=${reel.id}`}
              className="group relative rounded-3xl overflow-hidden border border-amber-500/30 bg-stone-950 aspect-[9/16] shadow-md hover:shadow-xl transition-all duration-300 block"
            >
              <Image
                src={reel.poster}
                alt="ChandraKala Jewellers Reel"
                fill
                sizes="(max-width: 768px) 50vw, 25vw"
                className="object-cover transition-transform duration-500 group-hover:scale-105"
              />

              <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-black/30 pointer-events-none" />

              {/* View badge */}
              <div className="absolute top-3 left-3">
                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-black/60 backdrop-blur-md text-[10px] font-bold text-white">
                  <Eye className="w-3 h-3 text-amber-400" />
                  <span>{reel.views} views</span>
                </span>
              </div>

              {/* Play icon in center */}
              <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div className="w-12 h-12 rounded-full bg-amber-500/80 group-hover:bg-amber-400 text-stone-950 flex items-center justify-center shadow-lg transition-transform group-hover:scale-110">
                  <Play className="w-5 h-5 fill-stone-950 ml-0.5" />
                </div>
              </div>

              {/* Duration badge */}
              <div className="absolute bottom-3 right-3 text-[10px] font-medium text-stone-300 bg-black/60 px-2 py-0.5 rounded-md">
                {reel.duration}
              </div>
            </a>
          ))}
        </div>

        {/* Watch Full Reels Showcase CTA */}
        <div className="mt-10 text-center">
          <Link
            href="/reels"
            className="inline-flex items-center gap-2 px-6 py-3 rounded-full border border-amber-600/30 bg-white/60 dark:bg-stone-900/60 hover:bg-amber-100/40 dark:hover:bg-stone-800 text-stone-800 dark:text-stone-200 text-sm font-semibold transition-all shadow-sm"
          >
            <span>Watch Full Reels Showcase</span>
            <ArrowRight className="w-4 h-4" />
          </Link>
        </div>

      </div>
    </section>
  );
}
