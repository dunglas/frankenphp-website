/** @type {import('tailwindcss').Config} */

const defaultTheme = require('tailwindcss/defaultTheme');
const colors = require('tailwindcss/colors');

module.exports = {
  content: ['../layouts/**/*.{html,js,md}', '../static/**/*.{html,js,php}'],
  safelist: ['text-green'],
  theme: {
    container: {
      center: true,
      padding: '2rem',
    },
    colors: {
      green: {
        DEFAULT: '#b3d133',
        light: '#cbdb8b',
        dark: '#7e9324',
      },
      purple: {
        DEFAULT: '#390075',
        dark: '#230143',
        light: '#937da8',
        extralight: '#c3b2d3',
      },
      gray: colors.gray,
      red: '#ee4322',
      white: '#ffffff',
      black: '#000000',
      current: 'currentColor',
      transparent: 'transparent',
    },
    extend: {
      fontFamily: {
        sans: ['Poppins', ...defaultTheme.fontFamily.sans],
      },
      maxWidth: {
        '8xl': '90rem',
      },
      boxShadow: {
        header: '0px 4px 10px rgba(0,0,0,0.05)',
      },
      keyframes: {
        defile: {
          '0%': { transform: 'translateX(calc(-100%/3 - 2rem / 3))' },
          '100%': { transform: 'translateX(calc(2 * (-100%/3 - 2rem / 3)))' },
        },
      },
      animation: {
        defile: 'defile 50s linear infinite',
      },
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
    // ...
  ],
};

