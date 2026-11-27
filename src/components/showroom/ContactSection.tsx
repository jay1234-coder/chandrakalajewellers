'use client';

import React from 'react';
import Link from 'next/link';
import { MapPin, Phone, Mail, ArrowRight } from 'lucide-react';

interface ContactSectionProps {
  theme: 'light' | 'dark';
}

export default function ContactSection({ theme }: ContactSectionProps) {
  return (
    <section id="contact" className="py-16 sm:py-24 border-t border-amber-500/10">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Header (Direct from index1.php #contact) */}
        <div className="text-center max-w-2xl mx-auto mb-12">
          <h2 className="font-serif text-3xl sm:text-4xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
            Contact Us
          </h2>
          <p className="text-stone-600 dark:text-stone-400 text-sm sm:text-base mt-2">
            We would love to help you find the perfect piece.
          </p>
        </div>

        {/* 3 Contact Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8 max-w-5xl mx-auto">
          
          {/* 1. Visit Us */}
          <div className="rounded-3xl border border-amber-500/25 bg-white/70 dark:bg-stone-900/70 p-6 shadow-md text-center flex flex-col items-center">
            <div className="w-12 h-12 rounded-2xl bg-amber-500/15 text-amber-700 dark:text-amber-300 flex items-center justify-center mb-4">
              <MapPin className="w-6 h-6" />
            </div>
            <h3 className="font-serif text-lg font-bold text-stone-900 dark:text-stone-100 mb-2">
              Visit Us
            </h3>
            <p className="text-xs sm:text-sm text-stone-600 dark:text-stone-400 leading-relaxed">
              Opp. Bhoomi Complex, Civil Road, Khedbrahma-383255
            </p>
          </div>

          {/* 2. Call */}
          <div className="rounded-3xl border border-amber-500/25 bg-white/70 dark:bg-stone-900/70 p-6 shadow-md text-center flex flex-col items-center">
            <div className="w-12 h-12 rounded-2xl bg-amber-500/15 text-amber-700 dark:text-amber-300 flex items-center justify-center mb-4">
              <Phone className="w-6 h-6" />
            </div>
            <h3 className="font-serif text-lg font-bold text-stone-900 dark:text-stone-100 mb-2">
              Call
            </h3>
            <p className="text-xs sm:text-sm text-stone-600 dark:text-stone-400 leading-relaxed">
              <a href="tel:+919427080359" className="hover:underline font-semibold text-amber-700 dark:text-amber-400">
                +91 9427080359
              </a>
            </p>
          </div>

          {/* 3. Email */}
          <div className="rounded-3xl border border-amber-500/25 bg-white/70 dark:bg-stone-900/70 p-6 shadow-md text-center flex flex-col items-center">
            <div className="w-12 h-12 rounded-2xl bg-amber-500/15 text-amber-700 dark:text-amber-300 flex items-center justify-center mb-4">
              <Mail className="w-6 h-6" />
            </div>
            <h3 className="font-serif text-lg font-bold text-stone-900 dark:text-stone-100 mb-2">
              Email
            </h3>
            <p className="text-xs sm:text-sm text-stone-600 dark:text-stone-400 leading-relaxed break-all">
              <a href="mailto:chandrakalajewellers849@gmail.com" className="hover:underline font-medium text-amber-700 dark:text-amber-400">
                chandrakalajewellers849@gmail.com
              </a>
            </p>
          </div>

        </div>

        {/* Get in Touch Button */}
        <div className="mt-10 text-center">
          <Link
            href="/contact"
            className="inline-flex items-center gap-2 px-7 py-3 rounded-full bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm shadow-md transition-all"
          >
            <span>Get in Touch</span>
            <ArrowRight className="w-4 h-4" />
          </Link>
        </div>

      </div>
    </section>
  );
}
