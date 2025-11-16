// tailwind.config.js
// import preset from './vendor/filament/filament/tailwind.config.preset.js'

export default {
//   presets: [preset],
  darkMode: 'class',
  content: [
    './resources/views/**/*.blade.php',
    './resources/css/**/*.css',
    './app/Filament/**/*.php',
    './resources/js/**/*.js',
    // Muy importante: incluye las vistas de vendor para que no purgue variantes de Filament
    './vendor/filament/**/*.blade.php',
    './vendor/awcodes/**/*.blade.php',
    './vendor/bezhanSalleh/**/*.blade.php',
  ],
  safelist: [
    // Grid helpers que usarás libremente en tus vistas
    { pattern: /(grid|col|row)-.*/ },
    { pattern: /grid-cols-(1|2|3|4|5|6|7|8|9|10|11|12)/ },
    { pattern: /col-span-(1|2|3|4|5|6|7|8|9|10|11|12)/ },
    // Opcional: si usas muchas variantes responsive/dark
    { pattern: /(sm|md|lg|xl|2xl):grid-cols-(1|2|3|4|6|12)/ },
  ],
  theme: {
    extend: {
      // aquí puedes extender sombras, colores, etc. si lo deseas
    },
  },
}
