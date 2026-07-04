/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        background: '#FAF8F4',
        dark: '#1A1A1A',
        'deep-green': '#19352B',
        'light-green': '#2A5044',
        gold: '#C7A86B',
        'gold-light': '#D4B97F',
        'gold-dark': '#A88A52',
        border: '#ECE7DF',
      },
      fontFamily: {
        serif: ['Cormorant Garamond', 'serif'],
        sans: ['Inter', 'sans-serif'],
      },
      letterSpacing: {
        luxury: '0.15em',
        ultra: '0.25em',
      },
      boxShadow: {
        luxury: '0 20px 60px rgba(0,0,0,0.08)',
        'luxury-lg': '0 30px 80px rgba(0,0,0,0.12)',
        gold: '0 10px 40px rgba(199,168,107,0.3)',
      },
    },
  },
  plugins: [],
};
