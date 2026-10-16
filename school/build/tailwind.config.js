module.exports = {
  content: ['./school/app.js', './school/index.php', './school/faculty.js', './school/next-profile/**/*.tsx'],
  theme: { extend: {
    colors: { ink: { DEFAULT: '#17324D', 2: '#0F2438' }, marigold: '#F2A93B', leaf: '#1F8A5B', rose: { DEFAULT: '#C8413B' }, paper: '#EEF2F5', line: '#D9E1E8' },
    borderRadius: { studio: '1.75rem', dock: '2rem' },
    boxShadow: { lift: '0 16px 40px -24px rgb(23 50 77 / .35)', glow: '0 0 0 1px rgb(99 102 241 / .12), 0 12px 30px -20px rgb(99 102 241 / .45)' },
    fontFamily: { sans: ['Lexend', 'Noto Sans Devanagari', 'system-ui', 'sans-serif'] }
  } }, plugins: []
};
