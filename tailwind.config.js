/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/**/*.{js,ts,jsx,tsx,mdx}",
    "./*.php",
    "./includes/**/*.php",
    "./admin/**/*.php",
    "./js/**/*.js"
  ],
  darkMode: ['class', '.theme-dark'],
  theme: {
    extend: {
      colors: {
        brand: {
          gold: '#d4af37',
          dark: '#b8972c',
          light: '#fdf8ec'
        }
      },
      fontFamily: {
        serif: ['"Playfair Display"', 'Georgia', 'serif'],
        royal: ['Cinzel', 'serif'],
        sans: ['Poppins', 'system-ui', 'sans-serif'],
      }
    },
  },
  plugins: [],
};
