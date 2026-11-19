/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,
  trailingSlash: false,
  typescript: {
    // Ignore legacy non-Next.js directories (like backend/ and admin_app/) during Vercel builds
    ignoreBuildErrors: true,
  },
  eslint: {
    ignoreDuringBuilds: true,
  },
  images: {
    remotePatterns: [
      {
        protocol: 'https',
        hostname: 'res.cloudinary.com',
        pathname: '/**',
      },
      {
        protocol: 'https',
        hostname: 'www.chandrakalajewellers.in',
        pathname: '/**',
      },
    ],
  },
};

module.exports = nextConfig;
