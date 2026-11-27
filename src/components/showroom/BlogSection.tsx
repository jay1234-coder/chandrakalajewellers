'use client';

import React from 'react';
import Link from 'next/link';
import { ArrowRight } from 'lucide-react';

interface BlogSectionProps {
  theme: 'light' | 'dark';
}

export default function BlogSection({ theme }: BlogSectionProps) {
  // Exact 3 posts from index1.php #blog
  const posts = [
    {
      tag: 'Care Tips',
      title: 'Jewellery Care Tips',
      excerpt: 'Learn how to keep your jewellery sparkling with our expert care tips for gold and silver pieces.',
      slug: 'jewellery-care-tips',
    },
    {
      tag: 'Buying Guide',
      title: 'Choosing the Right Gold',
      excerpt: 'Understand hallmarking, purity, and making charges so you shop with confidence.',
      slug: 'choosing-the-right-gold',
    },
    {
      tag: 'Style',
      title: 'Jewellery for Every Occasion',
      excerpt: 'From daily wear to wedding celebrations — find pieces that match every moment.',
      slug: 'jewellery-for-every-occasion',
    },
  ];

  return (
    <section id="blog" className="py-16 sm:py-24 border-t border-amber-500/10">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Header (Direct from index1.php) */}
        <div className="text-center max-w-2xl mx-auto mb-12">
          <h2 className="font-serif text-3xl sm:text-4xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
            From Our Blog
          </h2>
          <p className="text-stone-600 dark:text-stone-400 text-sm sm:text-base mt-2">
            Care tips and inspiration to keep your jewellery sparkling.
          </p>
        </div>

        {/* 3 Blog Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
          {posts.map((post) => (
            <article
              key={post.slug}
              className="rounded-3xl border border-amber-500/25 bg-white/70 dark:bg-stone-900/70 p-6 shadow-md hover:shadow-xl transition-all duration-300 flex flex-col justify-between"
            >
              <div>
                <span className="inline-block px-3 py-1 rounded-full bg-amber-500/15 text-amber-800 dark:text-amber-300 text-xs font-semibold uppercase tracking-wider mb-3">
                  {post.tag}
                </span>

                <h3 className="font-serif text-xl font-bold text-stone-900 dark:text-stone-100 mb-2">
                  {post.title}
                </h3>

                <p className="text-xs sm:text-sm text-stone-600 dark:text-stone-400 leading-relaxed mb-6">
                  {post.excerpt}
                </p>
              </div>

              <div>
                <Link
                  href={`/blog-post?slug=${post.slug}`}
                  className="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 dark:text-amber-400 hover:underline"
                >
                  <span>Read More</span>
                  <ArrowRight className="w-3.5 h-3.5" />
                </Link>
              </div>
            </article>
          ))}
        </div>

        {/* View All Posts Button */}
        <div className="mt-10 text-center">
          <Link
            href="/blog"
            className="inline-flex items-center gap-2 px-6 py-3 rounded-full border border-amber-600/30 bg-white/60 dark:bg-stone-900/60 hover:bg-amber-100/40 dark:hover:bg-stone-800 text-stone-800 dark:text-stone-200 text-sm font-semibold transition-all shadow-sm"
          >
            <span>View All Posts</span>
            <ArrowRight className="w-4 h-4" />
          </Link>
        </div>

      </div>
    </section>
  );
}
