'use client';

import React, { useState } from 'react';
import { ChevronDown } from 'lucide-react';

interface ShowroomFaqProps {
  theme: 'light' | 'dark';
}

export default function ShowroomFaq({ theme }: ShowroomFaqProps) {
  const [openIdx, setOpenIdx] = useState<number | null>(0);

  // Exact FAQs from cj_default_faqs() in includes/seo.php
  const faqs = [
    {
      q: 'Where is ChandraKala Jewellers located in Khedbrahma?',
      a: 'ChandraKala Jewellers (also searched as Chandrakala Jewellers or ચંદ્રકલા જ્વેલર્સ) is located at Opp. Bhoomi Complex, Civil Road, 22JW+HG9, Khedbrahma-383255, Sabarkantha, Gujarat, India (GPS: 24.0314016, 73.0463503). Call or WhatsApp +91 9427080359.',
    },
    {
      q: 'Who is the best jeweller in Khedbrahma and Sabarkantha?',
      a: "ChandraKala Jewellers on Civil Road is Khedbrahma's trusted family-owned jewellery showroom for 916 BIS hallmarked gold, pure silver, and 925 sterling silver jewellery with transparent live weight-based pricing, custom bridal sets, and instant WhatsApp ordering across Sabarkantha, Idar, Vadali, Ambaji, and Himmatnagar.",
    },
    {
      q: "What is today's gold and silver rate in Khedbrahma at ChandraKala Jewellers?",
      a: "Today's gold board rate at ChandraKala Jewellers, Khedbrahma is ₹13,900.00 per gram (₹139,000.00 per 10 grams). Silver rate is ₹160.00 per gram.",
    },
    {
      q: 'What types of gold and silver jewellery can I buy at ChandraKala Jewellers?',
      a: 'We offer 916 (22K), 20K, and 18K gold jewellery including bridal necklace sets, kundan sets, mangalsutra, tanmaniya, dokiya, pendant butti sets, earrings, chains, hathpan, patala bangles, and rings — plus pure silver kada and 925 sterling silver rings & bracelets, alongside 138+ custom made-to-order designs.',
    },
    {
      q: 'Can I order hallmark gold or 925 silver jewellery online on WhatsApp?',
      a: 'Yes. Browse over 60 live-priced jewellery pieces in our online Shop or 138+ photos in our Design Catalogue, compare designs side-by-side, and click Buy Now or WhatsApp Order (+91 9427080359) for instant booking, customization, and showroom pickup or insured delivery guidance.',
    },
    {
      q: 'Do you make custom wedding and bridal gold jewellery in North Gujarat?',
      a: 'Yes. Customers from Khedbrahma, Idar, Himmatnagar, Vadali, Ambaji, Posina, and Vijaynagar visit ChandraKala Jewellers for custom wedding gold jewellery crafted in 22K (916), 20K, or 18K with transparent making charges.',
    },
    {
      q: 'What are ChandraKala Jewellers opening hours?',
      a: 'ChandraKala Jewellers is open Monday to Saturday from 10:00 AM to 8:00 PM IST. Call +91 9427080359 to confirm availability on festivals or Sundays.',
    },
    {
      q: 'Is Chandrakala Jewellers the same as ChandraKala Jewellers?',
      a: 'Yes. ChandraKala Jewellers, Chandrakala Jewellers, and ચંદ્રકલા જ્વેલર્સ all refer to the same family-owned jewellery showroom at Opp. Bhoomi Complex, Civil Road, Khedbrahma, Gujarat.',
    },
  ];

  const toggleFaq = (idx: number) => {
    setOpenIdx((prev) => (prev === idx ? null : idx));
  };

  return (
    <section id="faq" className="py-16 sm:py-24 border-t border-amber-500/10" aria-labelledby="faq-heading">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Header (Direct from index1.php #faq) */}
        <div className="text-center mb-12">
          <h2 id="faq-heading" className="font-serif text-3xl sm:text-4xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
            Frequently Asked Questions
          </h2>
          <p className="text-stone-600 dark:text-stone-400 text-sm sm:text-base mt-2">
            Quick answers about our gold &amp; silver jewellery store in Khedbrahma.
          </p>
        </div>

        {/* Accordion List */}
        <div className="flex flex-col gap-3">
          {faqs.map((faq, idx) => {
            const isOpen = openIdx === idx;

            return (
              <div
                key={idx}
                className="rounded-2xl border border-amber-500/25 bg-white/70 dark:bg-stone-900/70 overflow-hidden shadow-sm transition-all duration-200"
              >
                <button
                  type="button"
                  onClick={() => toggleFaq(idx)}
                  className="w-full px-6 py-4 flex items-center justify-between text-left gap-4 hover:bg-amber-500/5 transition-colors"
                >
                  <span className="font-serif text-base sm:text-lg font-bold text-stone-900 dark:text-stone-100">
                    {faq.q}
                  </span>

                  <div
                    className={`w-7 h-7 rounded-full border border-amber-500/30 flex items-center justify-center shrink-0 transition-transform duration-200 ${
                      isOpen ? 'rotate-180 bg-amber-500 text-stone-950' : 'text-stone-600 dark:text-stone-300'
                    }`}
                  >
                    <ChevronDown className="w-4 h-4" />
                  </div>
                </button>

                {isOpen && (
                  <div className="px-6 pb-5 pt-1 text-xs sm:text-sm text-stone-600 dark:text-stone-400 leading-relaxed border-t border-amber-500/10">
                    {faq.a}
                  </div>
                )}
              </div>
            );
          })}
        </div>

      </div>
    </section>
  );
}
