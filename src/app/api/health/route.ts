import { NextResponse } from 'next/server';

export const dynamic = 'force-dynamic';

export async function GET() {
  return NextResponse.json({
    status: 'healthy',
    timestamp: new Date().toISOString(),
    service: 'ChandraKala Jewellers Modern App Router API',
    stack: 'Next.js 14 + TypeScript',
    region: process.env.VERCEL_REGION || 'sin1',
  });
}
