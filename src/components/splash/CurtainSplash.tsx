'use client';

import React, { useState, useEffect, useRef } from 'react';
import Image from 'next/image';
import { Sparkles, Sun, Moon, RotateCcw, Volume2, VolumeX, ArrowRight, ShieldCheck, Gem, MapPin } from 'lucide-react';

interface CurtainSplashProps {
  targetUrl?: string;
  autoRedirectSeconds?: number;
}

export default function CurtainSplash({
  targetUrl = '/showroom',
  autoRedirectSeconds = 5,
}: CurtainSplashProps) {
  // Theme state: 'light' is the primary requested focus, 'dark' is fully supported
  const [theme, setTheme] = useState<'light' | 'dark'>('light');
  const [curtainsOpen, setCurtainsOpen] = useState(false);
  const [autoRedirectPaused, setAutoRedirectPaused] = useState(false);
  const [countdown, setCountdown] = useState(autoRedirectSeconds);
  const [soundEnabled, setSoundEnabled] = useState(true);
  const audioContextRef = useRef<AudioContext | null>(null);

  // Play a soft luxury harmonic chime via Web Audio API (zero audio file dependencies)
  const playChime = () => {
    if (!soundEnabled) return;
    try {
      const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
      if (!AudioCtx) return;
      
      if (!audioContextRef.current) {
        audioContextRef.current = new AudioCtx();
      }
      const ctx = audioContextRef.current;
      if (ctx.state === 'suspended') {
        ctx.resume();
      }

      // Royal harmonic notes (F# maj chord: F#4, A#4, C#5, F#5)
      const freqs = [369.99, 466.16, 554.37, 739.99];
      const now = ctx.currentTime;

      freqs.forEach((freq, idx) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, now + idx * 0.08);

        gain.gain.setValueAtTime(0, now);
        gain.gain.linearRampToValueAtTime(0.08 / (idx + 1), now + idx * 0.08 + 0.05);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.08 + 2.2);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(now + idx * 0.08);
        osc.stop(now + idx * 0.08 + 2.5);
      });
    } catch {
      // Audio play best effort
    }
  };

  // Trigger curtain open sequence after brief initial suspense
  useEffect(() => {
    const timer = setTimeout(() => {
      setCurtainsOpen(true);
      playChime();
    }, 650);

    return () => clearTimeout(timer);
  }, []);

  // Handle countdown auto-redirect to index1.php
  useEffect(() => {
    if (!curtainsOpen || autoRedirectPaused) return;

    if (countdown <= 0) {
      window.location.href = targetUrl;
      return;
    }

    const interval = setInterval(() => {
      setCountdown((prev) => prev - 1);
    }, 1000);

    return () => clearInterval(interval);
  }, [curtainsOpen, autoRedirectPaused, countdown, targetUrl]);

  const handleReplay = () => {
    setCurtainsOpen(false);
    setCountdown(autoRedirectSeconds);
    setTimeout(() => {
      setCurtainsOpen(true);
      playChime();
    }, 750);
  };

  const toggleTheme = () => {
    setTheme((prev) => (prev === 'light' ? 'dark' : 'light'));
  };

  return (
    <main className={`showroom-stage theme-${theme} ${curtainsOpen ? 'curtains-open' : ''}`}>
      {/* Dynamic Ambient Background Spotlight */}
      <div className="ambient-spotlight" />

      {/* Floating subtle gold particle glitter background */}
      <div className="absolute inset-0 pointer-events-none opacity-40">
        <div className="absolute top-1/4 left-1/5 w-1.5 h-1.5 rounded-full bg-amber-400 blur-[1px] animate-ping" />
        <div className="absolute top-1/3 right-1/4 w-2 h-2 rounded-full bg-yellow-300 blur-[1px] animate-pulse" />
        <div className="absolute bottom-1/4 left-1/3 w-1.5 h-1.5 rounded-full bg-amber-300 blur-[1px] animate-pulse" />
        <div className="absolute bottom-1/3 right-1/5 w-2 h-2 rounded-full bg-yellow-400 blur-[1px] animate-ping" />
      </div>

      {/* ========================================================
          TOP LUXURY CONTROLS (Discreet Luxury Toolbar)
          ======================================================== */}
      <header className="absolute top-6 left-0 right-0 z-50 flex items-center justify-end px-6 max-w-6xl mx-auto w-full">
        <div className="flex items-center gap-2.5">
          {/* Sound Toggle */}
          <button
            onClick={() => setSoundEnabled((prev) => !prev)}
            aria-label="Toggle royal chime sound"
            title={soundEnabled ? 'Chime sound: Enabled' : 'Chime sound: Muted'}
            className="p-2.5 rounded-full border border-amber-600/30 bg-white/70 dark:bg-stone-900/70 text-amber-700 dark:text-amber-300 hover:scale-105 transition-all shadow-sm backdrop-blur-md"
          >
            {soundEnabled ? <Volume2 className="w-4 h-4" /> : <VolumeX className="w-4 h-4" />}
          </button>

          {/* Replay Curtain Animation */}
          <button
            onClick={handleReplay}
            aria-label="Replay curtain opening animation"
            title="Replay center-split curtain animation"
            className="flex items-center gap-1.5 px-3.5 py-2 rounded-full border border-amber-600/30 bg-white/70 dark:bg-stone-900/70 text-amber-700 dark:text-amber-300 hover:scale-105 transition-all shadow-sm text-xs font-semibold backdrop-blur-md"
          >
            <RotateCcw className="w-3.5 h-3.5" />
            <span>Replay Curtain</span>
          </button>

          {/* Light / Dark Mode Toggle */}
          <button
            onClick={toggleTheme}
            aria-label="Toggle light and dark mode views"
            title={`Switch to ${theme === 'light' ? 'Dark Mode' : 'Light Mode'}`}
            className="p-2.5 rounded-full border border-amber-600/30 bg-white/70 dark:bg-stone-900/70 text-amber-700 dark:text-amber-300 hover:scale-105 transition-all shadow-sm backdrop-blur-md"
          >
            {theme === 'light' ? <Moon className="w-4 h-4" /> : <Sun className="w-4 h-4" />}
          </button>
        </div>
      </header>

      {/* ========================================================
          CINEMATIC CURTAIN SYSTEM (Split Center to Sides)
          ======================================================== */}
      {/* Left Curtain Panel */}
      <div className="curtain-panel curtain-left" aria-hidden="true">
        <div className="curtain-fabric" />
        <div className="curtain-edge-trim" />
        <div className="curtain-hem-fringe" />
      </div>

      {/* Right Curtain Panel */}
      <div className="curtain-panel curtain-right" aria-hidden="true">
        <div className="curtain-fabric" />
        <div className="curtain-edge-trim" />
        <div className="curtain-hem-fringe" />
      </div>

      {/* Royal Center Emblem Seal (Locks Center Seam Before Opening) */}
      <div
        className="center-seal cursor-pointer group"
        onClick={() => setCurtainsOpen(true)}
        title="Tap to open curtains"
      >
        <div className="relative w-24 h-24 rounded-full p-1 bg-gradient-to-tr from-amber-600 via-yellow-400 to-amber-700 shadow-2xl flex items-center justify-center transform group-hover:scale-105 transition-transform duration-300">
          <div className="w-full h-full rounded-full bg-stone-900 flex flex-col items-center justify-center border border-amber-400/50 shadow-inner">
            <span className="font-serif text-2xl font-bold tracking-wider text-amber-300 drop-shadow">
              CJ
            </span>
            <span className="text-[8px] font-sans font-semibold tracking-widest text-amber-200/80 uppercase -mt-0.5">
              Khedbrahma
            </span>
          </div>
          {/* Subtle pulsating gold ring */}
          <div className="absolute inset-0 rounded-full border border-yellow-300/60 animate-ping pointer-events-none" />
        </div>
      </div>

      {/* ========================================================
          SHOWROOM REVEAL CONTENT (Visible when Curtains Open)
          ======================================================== */}
      <section className="relative z-10 max-w-xl w-full text-center flex flex-col items-center justify-center px-4">
        {/* Ornate Medallion Logo Wrapper */}
        <div className="relative mb-6 group">
          {/* Rotating Decorative Sunburst Ring */}
          <div className="absolute -inset-3.5 rounded-full border border-dashed border-amber-500/40 spin-halo pointer-events-none" />
          
          <div className="relative w-28 h-28 md:w-32 md:h-32 rounded-full p-1.5 bg-gradient-to-b from-amber-300 via-amber-500 to-amber-700 shadow-[0_12px_45px_rgba(212,175,55,0.32)] flex items-center justify-center">
            <div className="w-full h-full rounded-full overflow-hidden border-2 border-amber-300/80 shadow-inner bg-black flex items-center justify-center">
              <Image
                src="/images/logo.png"
                alt="ChandraKala Jewellers Logo"
                width={128}
                height={128}
                priority
                style={{ clipPath: 'circle(50% at 50% 50%)' }}
                className="w-full h-full object-cover rounded-full scale-105 filter drop-shadow hover:scale-110 transition-transform duration-300"
              />
            </div>
          </div>
        </div>

        {/* Location Tagline Badge */}
        <div className="mb-3 inline-flex items-center px-4 py-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300 text-xs font-semibold tracking-[0.25em] uppercase backdrop-blur-sm">
          <span>Khedbrahma · Gujarat</span>
        </div>

        {/* Grand Headline */}
        <h1 className="font-serif text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight mb-2 gold-shimmer-text">
          ChandraKala Jewellers
        </h1>

        {/* Gujarati Subtitle & Trust Guarantee */}
        <p className="text-sm md:text-base font-medium text-amber-700 dark:text-amber-400 mb-2 font-sans tracking-wide">
          વિશ્વાસ અને પરંપરાનું પ્રતીક · ૧૦૦% BIS હોલમાર્ક સુવર્ણ
        </p>

        {/* Description Prose */}
        <p className="max-w-md text-xs md:text-sm font-normal text-stone-700 dark:text-stone-300 leading-relaxed mb-6 font-sans">
          Step inside our royal showroom for exquisite 916 BIS Hallmark Gold, pure silver ornaments, and real-time live daily bullion rates.
        </p>

        {/* Luxury Trust Pillars */}
        <div className="grid grid-cols-3 gap-2 sm:gap-3.5 w-full max-w-md mb-8">
          <div className="flex flex-col items-center p-3 rounded-2xl border border-amber-400/40 dark:border-amber-500/30 bg-white/90 dark:bg-stone-900/75 backdrop-blur-md shadow-md shadow-amber-900/5">
            <ShieldCheck className="w-5 h-5 text-amber-600 dark:text-amber-400 mb-1.5" />
            <span className="text-[11px] font-bold text-stone-900 dark:text-stone-100">100% 916 BIS</span>
            <span className="text-[9px] font-medium text-stone-600 dark:text-stone-400">Government Certified</span>
          </div>

          <div className="flex flex-col items-center p-3 rounded-2xl border border-amber-400/40 dark:border-amber-500/30 bg-white/90 dark:bg-stone-900/75 backdrop-blur-md shadow-md shadow-amber-900/5">
            <Gem className="w-5 h-5 text-amber-600 dark:text-amber-400 mb-1.5" />
            <span className="text-[11px] font-bold text-stone-900 dark:text-stone-100">Pure Silver</span>
            <span className="text-[9px] font-medium text-stone-600 dark:text-stone-400">& 925 Sterling Art</span>
          </div>

          <div className="flex flex-col items-center p-3 rounded-2xl border border-amber-400/40 dark:border-amber-500/30 bg-white/90 dark:bg-stone-900/75 backdrop-blur-md shadow-md shadow-amber-900/5">
            <MapPin className="w-5 h-5 text-amber-600 dark:text-amber-400 mb-1.5" />
            <span className="text-[11px] font-bold text-stone-900 dark:text-stone-100">Khedbrahma</span>
            <span className="text-[9px] font-medium text-stone-600 dark:text-stone-400">Opp. Bhoomi Complex</span>
          </div>
        </div>

        {/* Action Button & Timer Controls */}
        <div className="flex flex-col items-center gap-3 w-full max-w-xs">
          <a
            href={targetUrl}
            className="group relative w-full flex items-center justify-center gap-2 py-3.5 px-6 rounded-full font-semibold text-sm text-white shadow-lg shadow-amber-600/30 overflow-hidden transition-all duration-300 hover:scale-[1.02] active:scale-[0.98] bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-700 hover:from-amber-500 hover:to-amber-600"
          >
            <span>Enter Showroom</span>
            <ArrowRight className="w-4 h-4 transition-transform group-hover:translate-x-1" />
            {/* Shimmer light bar across button */}
            <span className="absolute top-0 -left-full w-full h-full bg-gradient-to-r from-transparent via-white/30 to-transparent skew-x-12 group-hover:left-full transition-all duration-1000" />
          </a>

          {/* Countdown & Pause Control */}
          <div className="flex items-center gap-2 text-xs text-stone-500 dark:text-stone-400">
            {!autoRedirectPaused ? (
              <>
                <span>Entering showroom in <strong className="text-amber-600 dark:text-amber-400">{countdown}s</strong></span>
                <span className="text-stone-400">·</span>
                <button
                  onClick={() => setAutoRedirectPaused(true)}
                  className="underline hover:text-amber-600 transition-colors"
                >
                  Hold screen
                </button>
              </>
            ) : (
              <span className="text-amber-700 dark:text-amber-300 font-medium">Auto-entry paused. Explore as long as you like.</span>
            )}
          </div>
        </div>
      </section>

      {/* Subtle Bottom Heritage Watermark */}
      <footer className="absolute bottom-4 left-0 right-0 text-center z-10 pointer-events-none">
        <p className="text-[10px] tracking-widest uppercase text-stone-400/80 dark:text-stone-500/80">
          ChandraKala Jewellers · Crafted with Pride in Gujarat
        </p>
      </footer>
    </main>
  );
}
