'use client';

import React, { useState } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { Sun, Moon, Phone, ShoppingBag, Heart, Menu, X } from 'lucide-react';

interface ShowroomHeaderProps {
  theme: 'light' | 'dark';
  onToggleTheme: () => void;
  wishlistCount?: number;
  cartCount?: number;
}

export default function ShowroomHeader({
  theme,
  onToggleTheme,
  wishlistCount = 0,
  cartCount = 0,
}: ShowroomHeaderProps) {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const navLinks = [
    { label: 'Home', href: '/showroom' },
    { label: 'Shop', href: '/gallery' },
    { label: 'Catalogue', href: '/Catalogue' },
    { label: 'Reels', href: '/reels' },
    { label: 'About', href: '#about' },
    { label: 'Contact', href: '#contact' },
    { label: 'Blog', href: '#blog' },
  ];

  return (
    <header className="sticky top-0 z-50 w-full transition-colors duration-300 border-b border-amber-500/20 backdrop-blur-xl bg-[#FAF8F5]/90 dark:bg-[#090B0E]/90 text-stone-900 dark:text-stone-100 shadow-sm">
      
      {/* ========================================================
          Today's Rate Announcement Ticker Bar (Exact rates from index1.php)
          ======================================================== */}
      <div className="bg-gradient-to-r from-amber-700 via-amber-600 to-yellow-600 text-white text-[11px] sm:text-xs font-medium py-1.5 px-4 tracking-wide">
        <div className="max-w-7xl mx-auto flex items-center justify-between">
          <div className="flex items-center gap-2.5 overflow-hidden">
            <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-black/25 text-amber-200 text-[10px] font-bold uppercase tracking-wider">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping" />
              Today's Rates
            </span>
            <span className="truncate">
              GOLD: <strong>₹13,900.00/g*</strong> &nbsp;|&nbsp; 
              SILVER: <strong>₹160.00/g*</strong> &nbsp;|&nbsp; 
              925 SILVER: <strong>₹600.00/g*</strong> &nbsp;|&nbsp; 
              COPPER 999: <strong>₹1,800.00/KG</strong>
            </span>
          </div>

          <div className="hidden md:flex items-center gap-3 text-[11px] text-amber-100/90">
            <span>Opp. Bhoomi Complex, Civil Road, Khedbrahma</span>
            <span>·</span>
            <a href="tel:+919427080359" className="hover:underline font-semibold">+91 9427080359</a>
          </div>
        </div>
      </div>

      {/* ========================================================
          Main Minimal Navigation Bar
          ======================================================== */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-20">
          
          {/* Logo & Brand Identity */}
          <Link href="/showroom" className="flex items-center gap-3 group">
            <div className="relative w-11 h-11 rounded-full p-0.5 bg-gradient-to-tr from-amber-500 via-yellow-400 to-amber-600 shadow-sm flex items-center justify-center transition-transform duration-300 group-hover:scale-105">
              <div className="w-full h-full rounded-full overflow-hidden bg-black flex items-center justify-center">
                <Image
                  src="/images/logo.png"
                  alt="ChandraKala Jewellers"
                  width={44}
                  height={44}
                  className="w-full h-full object-cover rounded-full"
                />
              </div>
            </div>
            <div className="flex flex-col">
              <span className="font-serif text-lg sm:text-xl font-bold tracking-tight text-amber-900 dark:text-amber-200 group-hover:text-amber-600 transition-colors">
                ChandraKala Jewellers
              </span>
              <span className="text-[10px] tracking-[0.2em] uppercase font-medium text-amber-700/80 dark:text-amber-400/80 -mt-0.5">
                Elegance &amp; Sparkle · Khedbrahma
              </span>
            </div>
          </Link>

          {/* Desktop Nav Items */}
          <nav className="hidden lg:flex items-center gap-6">
            {navLinks.map((link) => (
              <a
                key={link.label}
                href={link.href}
                className="text-sm font-medium tracking-wide text-stone-700 dark:text-stone-300 hover:text-amber-600 dark:hover:text-amber-400 transition-colors"
              >
                {link.label}
              </a>
            ))}
          </nav>

          {/* Action Bar (Theme, Wishlist, Cart, Mobile Menu) */}
          <div className="flex items-center gap-2 sm:gap-3">
            {/* Wishlist */}
            <a
              href="/wishlist"
              aria-label="Wishlist"
              className="relative p-2 rounded-full border border-amber-300/40 dark:border-stone-800 bg-white/70 dark:bg-stone-900/80 text-amber-700 dark:text-amber-300 hover:scale-105 transition-all shadow-sm"
              title="Wishlist"
            >
              <Heart className="w-4 h-4" />
              {wishlistCount > 0 && (
                <span className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-amber-600 text-[10px] font-bold text-white flex items-center justify-center">
                  {wishlistCount}
                </span>
              )}
            </a>

            {/* Cart */}
            <a
              href="/cart"
              aria-label="Shopping Cart"
              className="relative p-2 rounded-full border border-amber-300/40 dark:border-stone-800 bg-white/70 dark:bg-stone-900/80 text-amber-700 dark:text-amber-300 hover:scale-105 transition-all shadow-sm"
              title="Cart"
            >
              <ShoppingBag className="w-4 h-4" />
              {cartCount > 0 && (
                <span className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-amber-600 text-[10px] font-bold text-white flex items-center justify-center">
                  {cartCount}
                </span>
              )}
            </a>

            {/* Theme Toggle Button (Light / Dark) */}
            <button
              onClick={onToggleTheme}
              aria-label="Toggle Light and Dark Mode"
              title={`Switch to ${theme === 'light' ? 'Dark Mode' : 'Light Mode'}`}
              className="p-2 rounded-full border border-amber-300/40 dark:border-stone-800 bg-white/70 dark:bg-stone-900/80 text-amber-700 dark:text-amber-300 hover:scale-105 transition-all shadow-sm"
            >
              {theme === 'light' ? (
                <Moon className="w-4 h-4 text-amber-800" />
              ) : (
                <Sun className="w-4 h-4 text-yellow-300" />
              )}
            </button>

            {/* Mobile Menu Button */}
            <button
              onClick={() => setMobileMenuOpen((prev) => !prev)}
              aria-label="Open navigation menu"
              className="lg:hidden p-2 rounded-full border border-amber-300/40 dark:border-stone-800 bg-white/70 dark:bg-stone-900/80 text-amber-700 dark:text-amber-300 hover:scale-105 transition-all shadow-sm"
            >
              {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
            </button>
          </div>

        </div>
      </div>

      {/* Mobile Menu Dropdown */}
      {mobileMenuOpen && (
        <div className="lg:hidden border-t border-amber-500/20 bg-[#FAF8F5] dark:bg-[#090B0E] px-6 py-4 shadow-xl">
          <nav className="flex flex-col gap-3">
            {navLinks.map((link) => (
              <a
                key={link.label}
                href={link.href}
                onClick={() => setMobileMenuOpen(false)}
                className="text-base font-medium text-stone-800 dark:text-stone-200 hover:text-amber-600 dark:hover:text-amber-400 py-1"
              >
                {link.label}
              </a>
            ))}
          </nav>
        </div>
      )}
    </header>
  );
}
