'use client';

import React from 'react';
import Link from 'next/link';
import { ArrowRight } from 'lucide-react';

interface HeritageTrustProps {
  theme: 'light' | 'dark';
}

export default function HeritageTrust({ theme }: HeritageTrustProps) {
  // Exact 4 stats from index1.php #about
  const stats = [
    { title: 'Family', subtitle: 'Owned & Run' },
    { title: '916', subtitle: 'Gold Hallmark' },
    { title: 'Care', subtitle: 'Custom Designs' },
    { title: 'Local', subtitle: 'Khedbrahma' },
  ];

  return (
    <section id="about" className="py-16 sm:py-24 border-t border-amber-500/10">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
          
          {/* About Content (Direct from index1.php) */}
          <div className="lg:col-span-7">
            <h2 className="font-serif text-3xl sm:text-4xl font-bold tracking-tight text-stone-900 dark:text-stone-100 mb-6 leading-tight">
              About ChandraKala Jewellers, Khedbrahma
            </h2>

            <p className="text-sm sm:text-base text-stone-600 dark:text-stone-400 leading-relaxed mb-4">
              We are a family-owned gold and silver jewellery store on Civil Road, Khedbrahma (Sabarkantha, Gujarat), dedicated to high-quality, handcrafted 916 gold and silver jewellery. Our passion for craftsmanship and attention to detail sets us apart.
            </p>

            <p className="text-sm sm:text-base text-stone-600 dark:text-stone-400 leading-relaxed mb-8">
              From everyday elegance to celebration pieces, every design is chosen with care — so customers across Khedbrahma and nearby areas receive jewellery that feels personal, lasting, and true to tradition.
            </p>

            <Link
              href="/contact"
              className="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm shadow-md transition-all"
            >
              <span>Visit Our Store</span>
              <ArrowRight className="w-4 h-4" />
            </Link>
          </div>

          {/* 4 Stats Grid (Direct from index1.php .about-stats) */}
          <div className="lg:col-span-5 grid grid-cols-2 gap-4">
            {stats.map((stat, idx) => (
              <div
                key={idx}
                className="p-6 rounded-2xl border border-amber-500/25 bg-white/70 dark:bg-stone-900/70 shadow-sm flex flex-col items-center justify-center text-center"
              >
                <strong className="font-serif text-3xl font-bold text-amber-700 dark:text-amber-300 block mb-1">
                  {stat.title}
                </strong>
                <span className="text-xs font-medium text-stone-600 dark:text-stone-400">
                  {stat.subtitle}
                </span>
              </div>
            ))}
          </div>

        </div>

      </div>
    </section>
  );
}
