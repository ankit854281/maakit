// ============================================================
// Maakit — kaun se page jaanchne hain
//
// Nayi page banao to bas yahan ek line jod dijiye. Bot khud
// usko bhi dekhne lagega.
//
//   path     — pata, maakit.in ke baad wala hissa
//   naam     — galti batate waqt aapko yahi naam dikhega
//   chahiye  — ye shabd page par hone hi chahiye (na mile to galti)
//   nahi     — ye shabd page par nahi hone chahiye
//   nabz     — true matlab har 15 minute wali jaanch me bhi shaamil
// ============================================================

// Ye shabd kisi bhi page par mile to kuchh toota hai
export const KHARAB = [
  'Fatal error',
  'Parse error',
  'Uncaught',
  'Warning: ',
  'Notice: ',
  'Call to undefined',
  'SQLSTATE',
  'Database se connection nahi',
  'डेटाबेस से कनेक्शन',
];

// Ye shabd un page par mile jahan home page ka koi dibba le gaya ho,
// to matlab dibba galat jagah le ja raha hai. (Page khud khul jata hai,
// code 200 bhi deta hai — isliye sirf code dekhna kaafi nahi.)
export const GALAT_JAGAH = [
  'किताब नहीं मिली',
  'Book not found',
  'नहीं मिला',
  'Not found',
  'यह पेज नहीं है',
];

export const PAGES = [
  // ---------- rozmarra ke page (nabz me bhi) ----------
  { path: '/',            naam: 'होम पेज',        chahiye: ['Maakit'],              nabz: true },
  { path: '/order.php',   naam: 'ऑर्डर',          chahiye: ['Maakit'],              nabz: true },
  { path: '/sewa.php',    naam: 'बुकिंग की सूची', chahiye: ['Maakit'],              nabz: true },
  { path: '/track.php',   naam: 'ऑर्डर देखिए',    chahiye: ['Maakit'],              nabz: true },

  // ---------- booking ke form ----------
  // "किताब नहीं मिली" isliye roka hai kyonki ek baar book.php ke
  // naam ki takkar se saare booking page wahi dikhane lage the.
  { path: '/sewa.php?s=safar',  naam: 'गाड़ी — सवारी या सामान', chahiye: ['Maakit'], nahi: ['किताब नहीं मिली', 'Book not found'] },
  { path: '/sewa.php?s=gaadi',  naam: 'गाड़ी बुकिंग',           chahiye: ['Maakit'], nahi: ['किताब नहीं मिली', 'Book not found'] },
  { path: '/sewa.php?s=maal',   naam: 'माल ढुलाई',              chahiye: ['Maakit'], nahi: ['किताब नहीं मिली', 'Book not found'] },
  { path: '/sewa.php?s=lawn',   naam: 'लॉन / हॉल',              chahiye: ['Maakit'], nahi: ['किताब नहीं मिली', 'Book not found'] },
  { path: '/sewa.php?s=tent',   naam: 'टेंट, साउंड',            chahiye: ['Maakit'], nahi: ['किताब नहीं मिली', 'Book not found'] },
  { path: '/sewa.php?s=halwai', naam: 'हलवाई',                  chahiye: ['Maakit'], nahi: ['किताब नहीं मिली', 'Book not found'] },
  { path: '/sewa.php?s=pandit', naam: 'पंडित जी',               chahiye: ['Maakit'], nahi: ['किताब नहीं मिली', 'Book not found'] },
  { path: '/sewa.php?s=mistri', naam: 'मिस्त्री',               chahiye: ['Maakit'], nahi: ['किताब नहीं मिली', 'Book not found'] },
  { path: '/sewa.php?s=photo',  naam: 'फोटो / वीडियो',          chahiye: ['Maakit'], nahi: ['किताब नहीं मिली', 'Book not found'] },

  // ---------- baaki ----------
  { path: '/books.php',     naam: 'पुरानी किताबें', chahiye: ['Maakit'] },
  { path: '/book-add.php',  naam: 'किताब डालिए',    chahiye: ['Maakit'] },
  { path: '/directory.php', naam: 'दुकानें',        chahiye: ['Maakit'] },
  { path: '/area.php',      naam: 'नया गाँव',       chahiye: ['Maakit'] },
  { path: '/login.php',     naam: 'टीम का लॉगिन',   chahiye: ['Maakit'] },
  { path: '/transport.php', naam: 'गाड़ी रजिस्टर',  chahiye: ['Maakit'] },
  { path: '/salon.php',     naam: 'सैलून',          chahiye: ['Maakit'] },
];
