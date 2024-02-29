/** @type {import('tailwindcss').Config} */

const defaultTheme = require("tailwindcss/defaultTheme");
const colors = require("tailwindcss/colors");

module.exports = {
  content: [
    "./layouts/**/*.{html,js,md}",
    "./static/**/*.{html,js,php}",
    "./data/**/*.yaml",
    "./i18n/**/*.yaml",
  ],
  safelist: ["text-green"],
  theme: {
    container: {
      center: true,
      padding: "2rem",
    },
    colors: {
      green: {
        DEFAULT: "#b3d133",
        light: "#cbdb8b",
        dark: "#92a72e",
        extralight: "#e8efc8",
      },
      purple: {
        DEFAULT: "#390075",
        dark: "#230143",
        light: "#937da8",
        extralight: "#c3b2d3",
      },
      gray: colors.gray,
      red: "#ee4322",
      white: "#ffffff",
      black: "#000000",
      current: "currentColor",
      transparent: "transparent",
    },
    extend: {
      fontFamily: {
        sans: ["Poppins", ...defaultTheme.fontFamily.sans],
      },
      maxWidth: {
        "8xl": "90rem",
      },
      height: {
        0.75: "3px",
      },
      boxShadow: {
        header: "0px 4px 10px rgba(0,0,0,0.05)",
      },
      keyframes: {
        defile: {
          "0%": { transform: "translateX(calc(-100%/3 - 2rem / 3))" },
          "100%": { transform: "translateX(calc(2 * (-100%/3 - 2rem / 3)))" },
        },
      },
      animation: {
        defile: "defile 50s linear infinite",
      },
      typography: (theme) => ({
        quoteless: {
          css: {
            "blockquote p:first-of-type::before": { content: "none" },
            "blockquote p:first-of-type::after": { content: "none" },
          },
        },
        DEFAULT: {
          css: {
            h1: {
              color: theme("colors.purple.DEFAULT"),
              borderBottom: `1px solid ${theme("colors.gray.300")}`,
              paddingBottom: "8px",
            },
            h2: {
              color: theme("colors.purple.DEFAULT"),
              position: "relative",
              display: "block",
              "&::after": {
                content: `""!important`,
                backgroundColor: theme("colors.green.DEFAULT"),
                height: "4px",
                position: "absolute",
                width: "64px",
                bottom: "-8px",
                left: 0,
              },
            },
            h4: {
              color: theme("colors.gray.700"),
            },
            h3: {
              color: theme("colors.gray.700"),
            },
            h5: {
              color: theme("colors.gray.700"),
            },
            h6: {
              color: theme("colors.gray.700"),
            },
            pre: {
              backgroundColor: theme("colors.purple.dark"),
            },
            a: {
              color: theme("colors.purple.DEFAULT"),
              textDecoration: "none",
              fontWeight: 600,
              transition: "all ease 0.3s",
              "&:hover": {
                backgroundColor: theme("colors.green.extralight"),
              },
            },
          },
        },
      }),
    },
  },
  plugins: [
    require("@tailwindcss/typography"),
    // ...
  ],
};
