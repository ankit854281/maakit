module.exports = {
  content: ['./school/app.js', './school/index.php', './school/faculty.js', './school/next-profile/**/*.tsx'],
  theme: { extend: {
    colors: { ink: { DEFAULT: '#0B1F3A', 2: '#071529' }, marigold: '#E3A72F', leaf: '#0F8A5F', rose: { DEFAULT: '#C0352B' }, paper: '#F3F5F9', line: '#E2E7EF', brand: { DEFAULT: '#1E4FD8', soft: '#EAF0FE' } },
    borderRadius: { studio: '1.75rem', dock: '2rem' },
    boxShadow: { lift: '0 16px 40px -24px rgb(23 50 77 / .35)', glow: '0 0 0 1px rgb(99 102 241 / .12), 0 12px 30px -20px rgb(99 102 241 / .45)' },
    fontFamily: { sans: ['Manrope', 'Noto Sans Devanagari', 'system-ui', 'sans-serif'] }
  } }, plugins: []
};
