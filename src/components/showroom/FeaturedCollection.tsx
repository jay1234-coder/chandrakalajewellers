'use client';

import React, { useState } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { Heart, ShoppingBag, ArrowRight } from 'lucide-react';

interface FeaturedCollectionProps {
  theme: 'light' | 'dark';
  onWishlistChange?: (count: number) => void;
  onCartChange?: (count: number) => void;
}

interface ProductItem {
  id: number;
  name: string;
  metal: 'Gold' | 'Silver' | '925 Silver';
  weight: number;
  price: number;
  image: string;
  isFavourite: boolean;
  isSoldOut: boolean;
}

export default function FeaturedCollection({
  theme,
  onWishlistChange,
  onCartChange,
}: FeaturedCollectionProps) {
  // Exact 6 items from MySQL database query in index1.php
  const [products] = useState<ProductItem[]>([
    {
      id: 110,
      name: 'Pendent butti set',
      metal: 'Gold',
      weight: 11.64,
      price: 184298,
      image: 'https://res.cloudinary.com/hihrgcxm/image/upload/f_auto,q_auto,w_600,c_limit/chandrakala/uploads/item_68b28d5275f4e9.18137402.jpg',
      isFavourite: true,
      isSoldOut: false,
    },
    {
      id: 109,
      name: 'Gold set with earrings',
      metal: 'Gold',
      weight: 22.21,
      price: 298075,
      image: 'https://res.cloudinary.com/hihrgcxm/image/upload/f_auto,q_auto,w_600,c_limit/chandrakala/uploads/item_6a72ed6a62cde2.58667970.jpg',
      isFavourite: true,
      isSoldOut: false,
    },
    {
      id: 108,
      name: 'Chain with pearl',
      metal: 'Gold',
      weight: 7.00,
      price: 110832,
      image: 'https://res.cloudinary.com/hihrgcxm/image/upload/f_auto,q_auto,w_600,c_limit/chandrakala/uploads/item_6a72ecafaab347.25954770.jpg',
      isFavourite: true,
      isSoldOut: false,
    },
    {
      id: 60114,
      name: 'The Luminescent halo',
      metal: 'Silver',
      weight: 72.46,
      price: 14840,
      image: 'https://res.cloudinary.com/hihrgcxm/image/upload/f_auto,q_auto,w_600,c_limit/chandrakala/uploads/item_6afed5184ec378.12704468.jpg',
      isFavourite: false,
      isSoldOut: false,
    },
    {
      id: 60113,
      name: 'Core Cuff',
      metal: 'Silver',
      weight: 30.00,
      price: 6144,
      image: 'https://res.cloudinary.com/hihrgcxm/image/upload/f_auto,q_auto,w_600,c_limit/chandrakala/uploads/item_6afe95d8332ab7.00224085.jpeg',
      isFavourite: false,
      isSoldOut: false,
    },
    {
      id: 60112,
      name: 'Silver Fern Cuff',
      metal: 'Silver',
      weight: 26.73,
      price: 5474,
      image: 'https://res.cloudinary.com/hihrgcxm/image/upload/f_auto,q_auto,w_600,c_limit/chandrakala/uploads/item_6afe9552b0d978.94345217.jpeg',
      isFavourite: false,
      isSoldOut: false,
    },
  ]);

  const [wishlist, setWishlist] = useState<number[]>([]);
  const [cartCount, setCartCount] = useState<number>(0);
  const [toast, setToast] = useState<string | null>(null);

  const toggleWishlist = (id: number, name: string) => {
    let next: number[];
    if (wishlist.includes(id)) {
      next = wishlist.filter((item) => item !== id);
      showToast(`Removed "${name}" from Wishlist`);
    } else {
      next = [...wishlist, id];
      showToast(`Saved "${name}" to Wishlist!`);
    }
    setWishlist(next);
    if (onWishlistChange) onWishlistChange(next.length);
  };

  const handleAddToCart = (name: string) => {
    const nextCount = cartCount + 1;
    setCartCount(nextCount);
    if (onCartChange) onCartChange(nextCount);
    showToast(`Added "${name}" to Cart!`);
  };

  const showToast = (msg: string) => {
    setToast(msg);
    setTimeout(() => setToast(null), 2500);
  };

  return (
    <section id="shop" className="py-16 sm:py-24 relative border-t border-amber-500/10">
      
      {/* Toast alert */}
      {toast && (
        <div className="fixed bottom-6 right-6 z-50 bg-stone-900/95 text-amber-200 border border-amber-500/40 px-5 py-3 rounded-2xl shadow-2xl text-xs font-semibold backdrop-blur-md">
          {toast}
        </div>
      )}

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Section Heading & Sub (Direct from index1.php #shop) */}
        <div className="text-center max-w-2xl mx-auto mb-12">
          <h2 className="font-serif text-3xl sm:text-4xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
            Hot Favourite Items
          </h2>
          <p className="text-stone-600 dark:text-stone-400 text-sm sm:text-base mt-2">
            Handpicked pieces loved by our customers.
          </p>
        </div>

        {/* 6 Products Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
          {products.map((item) => {
            const isFav = wishlist.includes(item.id);

            return (
              <div
                key={item.id}
                className="group relative rounded-3xl border border-amber-500/25 bg-white/70 dark:bg-stone-900/70 shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col justify-between"
              >
                {/* Product Image Link */}
                <div className="relative h-72 w-full overflow-hidden bg-stone-950">
                  <Image
                    src={item.image}
                    alt={`${item.name} — gold silver jewellery Khedbrahma`}
                    fill
                    sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
                    className="object-cover transition-transform duration-500 group-hover:scale-105"
                  />

                  {/* Metal Badge */}
                  <div className="absolute top-3.5 left-3.5">
                    <span className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider shadow ${
                      item.metal === 'Gold' ? 'bg-amber-600 text-white' : 'bg-stone-700 text-stone-100'
                    }`}>
                      {item.metal}
                    </span>
                  </div>

                  {/* Wishlist Button */}
                  <button
                    type="button"
                    onClick={() => toggleWishlist(item.id, item.name)}
                    aria-label="Add to wishlist"
                    className="absolute top-3.5 right-3.5 p-2 rounded-full bg-black/50 hover:bg-black/70 backdrop-blur-md border border-white/20 text-white transition-all transform hover:scale-110 shadow"
                  >
                    <Heart
                      className={`w-4 h-4 transition-colors ${
                        isFav ? 'fill-red-500 text-red-500' : 'text-white'
                      }`}
                    />
                  </button>
                </div>

                {/* Details */}
                <div className="p-5 flex flex-col flex-grow justify-between">
                  <div>
                    <h3 className="font-serif text-lg font-bold text-stone-900 dark:text-stone-100 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                      {item.name}
                    </h3>
                    <p className="text-xs text-stone-500 dark:text-stone-400 mt-1">
                      {item.metal} &bull; {item.weight.toFixed(2)} g
                    </p>
                  </div>

                  {/* Price Row */}
                  <div className="pt-3 mt-3 border-t border-amber-500/15 flex items-center justify-between">
                    <span className="font-serif text-xl font-bold text-stone-900 dark:text-amber-300">
                      ₹{item.price.toLocaleString('en-IN')}
                    </span>
                  </div>

                  {/* Actions (Add to Cart, Buy Now) */}
                  <div className="grid grid-cols-2 gap-2 mt-4">
                    <button
                      type="button"
                      onClick={() => handleAddToCart(item.name)}
                      className="px-3 py-2 rounded-xl border border-amber-600/30 text-amber-800 dark:text-amber-300 text-xs font-semibold hover:bg-amber-100/40 dark:hover:bg-stone-800 transition-colors"
                    >
                      Add to Cart
                    </button>

                    <a
                      href={`/buynow?id=${item.id}`}
                      className="px-3 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold text-center shadow transition-colors"
                    >
                      Buy Now!
                    </a>
                  </div>
                </div>

              </div>
            );
          })}
        </div>

        {/* View Full Collection Button (Direct from index1.php) */}
        <div className="mt-12 text-center">
          <Link
            href="/gallery"
            className="inline-flex items-center gap-2 px-6 py-3 rounded-full border border-amber-600/30 bg-white/60 dark:bg-stone-900/60 hover:bg-amber-100/40 dark:hover:bg-stone-800 text-stone-800 dark:text-stone-200 text-sm font-semibold transition-all shadow-sm"
          >
            <span>View Full Collection (60+ Designs)</span>
            <ArrowRight className="w-4 h-4" />
          </Link>
        </div>

      </div>
    </section>
  );
}
