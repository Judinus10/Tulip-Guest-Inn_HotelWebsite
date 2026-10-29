/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        background: 'rgb(var(--theme-background-rgb) / <alpha-value>)',
        dark: 'rgb(var(--theme-text-rgb) / <alpha-value>)',
        'deep-green': 'rgb(var(--theme-secondary-rgb) / <alpha-value>)',
        'light-green': 'rgb(var(--theme-secondary-light-rgb) / <alpha-value>)',
        gold: 'rgb(var(--theme-primary-rgb) / <alpha-value>)',
        'gold-light': 'rgb(var(--theme-primary-light-rgb) / <alpha-value>)',
        'gold-dark': 'rgb(var(--theme-text-rgb) / <alpha-value>)',
        surface: 'rgb(var(--theme-surface-rgb) / <alpha-value>)',
        muted: 'rgb(var(--theme-muted-rgb) / <alpha-value>)',
        footer: 'rgb(var(--theme-footer-rgb) / <alpha-value>)',
        'on-primary': 'var(--theme-on-primary)',
        'on-secondary': 'var(--theme-on-secondary)',
        'on-footer': 'var(--theme-on-footer)',
        border: 'rgb(var(--theme-border-rgb) / <alpha-value>)',
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
        gold: '0 10px 40px rgb(var(--theme-primary-rgb) / 0.3)',
      },
    },
  },
  plugins: [],
};
