/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./vendor/tales-from-a-dev/flowbite-bundle/templates/**/*.html.twig",
        "./assets/**/*.js",
        "./templates/**/*.html.twig",
    ],
    theme: {
        container: {
            center: true,
        },
        colors: {
            yellow: {
                lightest: '#fef9e7',
                light: '#fcedb7',
                DEFAULT: '#f5c40e',
                dark: '#ac890a',
            },
            red: {
                darkest: '#38011b',
                dark: '#460222',
                DEFAULT: '#8C0343',
            },
            blue: {
                light: '#B5CACD',
                DEFAULT: '#526F76',
                dark: '#314347',
                darkest: '#212C2F',
            },
            sunray: {
                light: '#E8CD9B',
                DEFAULT: '#D9AC59',
                dark: '#AE8A47',
            },
            silverpink: {
                light: '#E6CDCC',
                DEFAULT: '#C7A9AE',
                dark: '#9F878B',
                darkest: '#504446',
            }
        },
        fontFamily: {
            Helvetica: ["Helvetica", "sans-serif"],
            MiamoRegular: ["MiamoRegular", "serif"],
            MiamoScript: ["MiamoScript", "cursive"],
        },
        extend: {},
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('flowbite/plugin'),
        require('@tailwindcss/typography'),]
}
