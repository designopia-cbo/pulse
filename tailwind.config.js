/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./ajax/*.php", 
    "./includes/*.php",
    "./profile_options/*.php",
    "./js/*.js",
    "./node_modules/preline/preline.js",
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('preline/plugin'),
  ],
}
