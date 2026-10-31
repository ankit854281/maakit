module.exports = {
  content: ['./school/app.js', './school/index.php', './school/faculty.js', './school/next-profile/**/*.tsx'],
  theme: { extend: {
    colors: { ink: { DEFAULT: '#1C1C1E', 2: '#000000' }, marigold: '#E3A72F', leaf: '#0F8A5F', rose: { DEFAULT: '#C0352B' }, paper: '#EDEDF0', line: '#E6E6EB', brand: { DEFAULT: '#2563EB', soft: '#EEF3FF' } },
    borderRadius: { studio: '1.75rem', dock: '2rem' },
    boxShadow: { lift: '0 16px 40px -24px rgb(23 50 77 / .35)', glow: '0 0 0 1px rgb(99 102 241 / .12), 0 12px 30px -20px rgb(99 102 241 / .45)' },
    fontFamily: { sans: ['Inter', 'Noto Sans Devanagari', 'system-ui', 'sans-serif'] }
  } }, plugins: []
};
