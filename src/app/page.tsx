import React from 'react';
import CurtainSplash from '@/components/splash/CurtainSplash';

export default function SplashPage() {
  return <CurtainSplash targetUrl="/showroom" autoRedirectSeconds={6} />;
}
