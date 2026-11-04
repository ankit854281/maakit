/* CBSE Grade IX (2026-27) official chapter OUTLINE for the SNMSIS Teacher App.
 * Not full syllabus text, competency tables or lesson plans. Review the official PDFs.
 * Latest scheme of studies: CBSE Acad-70/2026, dated 29 October 2026.
 * This module does not write to school records until a teacher explicitly chooses "Use chapters".
 * Never silently replace old syllabus or change students, attendance, notes or the offline queue.
 */
'use strict';
const CBSE9_OUTLINES = {
  "Mathematics": [
    [
      "Number System",
      "Number System"
    ],
    [
      "Algebra",
      "Introduction to Polynomials"
    ],
    [
      "Algebra",
      "Sequences and Progressions"
    ],
    [
      "Algebra",
      "Exploring Algebraic Identities"
    ],
    [
      "Algebra",
      "Linear Equations in Two Variables"
    ],
    [
      "Coordinate Geometry",
      "Coordinate Geometry"
    ],
    [
      "Geometry",
      "Introduction to Euclid’s Geometry: Axioms and Postulates"
    ],
    [
      "Geometry",
      "Lines and Angles"
    ],
    [
      "Geometry",
      "Triangles – Congruence Theorems"
    ],
    [
      "Geometry",
      "4-gons (Quadrilaterals)"
    ],
    [
      "Geometry",
      "Circles"
    ],
    [
      "Mensuration",
      "Area and Perimeter"
    ],
    [
      "Mensuration",
      "Surface Area and Volume"
    ],
    [
      "Statistics and Probability",
      "Statistics"
    ],
    [
      "Statistics and Probability",
      "Introduction to Probability"
    ]
  ],
  "Science": [
    [
      "World of Living",
      "Cell: The Building Block of Life"
    ],
    [
      "World of Living",
      "Tissues in Action"
    ],
    [
      "World of Living",
      "Reproduction: How Life Continues"
    ],
    [
      "World of Living",
      "Patterns in Life: Diversity and Classification"
    ],
    [
      "Matter - Its Nature and Behaviour",
      "Exploring Mixtures and Their Separation"
    ],
    [
      "Matter - Its Nature and Behaviour",
      "Journey Inside the Atom"
    ],
    [
      "Matter - Its Nature and Behaviour",
      "Atomic Foundations of Matter"
    ],
    [
      "Motion, Force, Work and Sound",
      "Describing Motion Around Us"
    ],
    [
      "Motion, Force, Work and Sound",
      "How Forces Affect Motion"
    ],
    [
      "Motion, Force, Work and Sound",
      "Work, Energy, and Simple Machines"
    ],
    [
      "Motion, Force, Work and Sound",
      "Sound Waves: Characteristics and Applications"
    ],
    [
      "Earth as a system",
      "Earth as a System: Energy, Matter, and Life"
    ]
  ],
  "Social Science": [
    [
      "Part 1",
      "Understanding Social Science"
    ],
    [
      "Part 1",
      "Shaping of the Earth’s Surface"
    ],
    [
      "Part 1",
      "Atmosphere and Climate"
    ],
    [
      "Part 1",
      "Early Humans and Beginning of Civilisation"
    ],
    [
      "Part 1",
      "State and Society (up to 1000 CE)"
    ],
    [
      "Part 1",
      "Democracy"
    ],
    [
      "Part 1",
      "Elections"
    ],
    [
      "Part 1",
      "Building Blocks in Economics"
    ],
    [
      "Part 1",
      "The Price Puzzle: What Drives the Market"
    ],
    [
      "Part 2",
      "Oceans and Life"
    ],
    [
      "Part 2",
      "Life on Earth"
    ],
    [
      "Part 2",
      "Resistance and Resilience (1000 CE–1700 CE)"
    ],
    [
      "Part 2",
      "India and the World-I (1900 BCE–1200 CE)"
    ],
    [
      "Part 2",
      "Authority"
    ],
    [
      "Part 2",
      "From Ideas to Startups"
    ],
    [
      "Part 2",
      "Smart Ways to Manage Your Finances"
    ]
  ],
  "Mathematics Advanced": [
    [
      "Advanced",
      "Sets"
    ],
    [
      "Advanced",
      "Logarithms"
    ],
    [
      "Advanced",
      "Relations and Functions"
    ],
    [
      "Advanced",
      "Coordinate Geometry"
    ],
    [
      "Advanced",
      "Combinatorics"
    ],
    [
      "Advanced",
      "Exploring some more Progressions"
    ]
  ],
  "Science Advanced": [
    [
      "Advanced",
      "Measurement – Foundation of Science"
    ],
    [
      "Advanced",
      "Understanding Motion through Experience"
    ],
    [
      "Advanced",
      "Newton’s Laws of Motion"
    ],
    [
      "Advanced",
      "The Geometry of Power – Advanced Simple Machines"
    ],
    [
      "Advanced",
      "Work and Energy"
    ],
    [
      "Advanced",
      "Structure of Atom"
    ],
    [
      "Advanced",
      "Chemical Bonding"
    ],
    [
      "Advanced",
      "Mixtures and their Separation"
    ],
    [
      "Advanced",
      "Microscope and Microscopy"
    ],
    [
      "Advanced",
      "Engineering Life: Miracles in Biotechnology"
    ]
  ]
};
const CBSE9_SOURCES = {
  "Mathematics": "Maths_SecP1IX_2026-27.pdf",
  "Science": "ScienceSt_SecP1_2026-27.pdf",
  "Social Science": "SocialScience_SecP1IX_2026-27.pdf",
  "Mathematics Advanced": "MathsAd_SecP1_2026-27.pdf",
  "Science Advanced": "ScienceAd_SecP1_2026-27.pdf",
  "English": "English_LL_SecP1IX_2026-27.pdf",
  "Hindi": "Hindi_SecP1IX_2026-27.pdf",
  "Art Education": "Art_Education_SecP1IX_2026-27.pdf",
  "Physical Education & Well-being": "PEWB_SecP1_2026-27.pdf"
};
const CBSE9_BASE = 'https://cbseacademic.nic.in/web_material/CurriculumMain27/SecPart1/';
const CBSE9_PORTAL = 'https://cbseacademic.nic.in/curriculum_2027.html';
const CBSE9_CIRCULAR = 'https://cbseacademic.nic.in/web_material/Circulars/2026/70_Circular_2026.pdf';
function cbse9Subject(s) {
  if (s === 'Mathematics' || s === 'Maths') return 'Mathematics';
  if (s === 'Social Science') return 'Social Science';
  if (s === 'Physics/Chemistry' || s === 'Biology' || s === 'Science') return 'Science';
  if (s === 'Mathematics Advanced' || s === 'Science Advanced') return s;
  if (s === 'English' || s === 'Hindi') return s;
  return '';
}
function cbse9Href(subject) {
  return CBSE9_SOURCES[subject] ? CBSE9_BASE + CBSE9_SOURCES[subject] : CBSE9_PORTAL;
}
function cbse9Box(key, y) {
  const [cls, subject] = key.split('|');
  if (cls !== '9th') return '';
  const group = cbse9Subject(subject);
  const rows = CBSE9_OUTLINES[group] || [];
  const isSplit = subject === 'Biology' || subject === 'Physics/Chemistry';
  const importable = subject === 'Mathematics' || subject === 'Maths' || subject === 'Social Science';
  const official = cbse9Href(group);
  const chapterList = rows.map(([unit, name], i) =>
    '<div class="li"><div class="t"><b>' + (i + 1) + '. ' + esc(name) +
    '</b><span>' + esc(unit) + '</span></div></div>').join('');
  const adopted = y && y.cbseEdition === '2026-27';
  return `<section class="card cbse9" aria-label="CBSE Class 9 2026-27 official curriculum">
    <h3>CBSE Class 9 · 2026–27 <small>Official reference</small></h3>
    <p class="mut" style="margin:0 0 8px">New CBSE course outline, not the full PDF text. Check the official curriculum before teaching or setting exams.</p>
    <p><a class="btn s l" href="${CBSE9_PORTAL}" target="_blank" rel="noopener noreferrer">All subjects · CBSE</a>
      <a class="btn s l" href="${CBSE9_CIRCULAR}" target="_blank" rel="noopener noreferrer">Latest scheme</a></p>
    ${group ? `<p class="mut" style="margin:8px 0">Selected: <b>${esc(subject)}</b> ${rows.length ? '· ' + rows.length + ' chapter/theme outlines' : ''}</p>
    <a class="btn l full" href="${official}" target="_blank" rel="noopener noreferrer">Open official ${esc(group)} PDF</a>` :
    '<div class="ban inf">For this subject, choose the applicable official syllabus from the CBSE subject portal. The school must confirm its language and skill choices.</div>'}
    ${isSplit ? '<div class="ban warn">CBSE treats Science as one subject. This school splits it into Biology and Physics/Chemistry; the list below is the WHOLE Science outline, not a verified split. Do not import it automatically.</div>' : ''}
    ${rows.length ? `<details class="more"><summary>See official chapter/theme outline (${rows.length})</summary>
      <div style="padding-top:8px">${chapterList}</div></details>` : ''}
    ${importable ? (adopted
      ? '<div class="ban ok">The 2026–27 chapter list is in your saved tracker.</div>'
      : `<button class="btn go full" style="margin-top:10px" onclick="cbse9Adopt(${jsq(key)})">Use 2026–27 chapters in my tracker</button>`)
      + (y && y.cbsePreviousChapters ? `<button class="btn w full" style="margin-top:8px" onclick="cbse9Restore(${jsq(key)})">Restore my previous chapter list</button>` : '')
      : ''}
    <p class="mut" style="font-size:12px;margin:10px 0 0">Topic-by-topic competencies and classroom materials are not imported here. English, Hindi, R3, Art, PE and skill subjects depend on the school's subject selection.</p>
  </section>`;
}
function cbse9Adopt(key) {
  const [cls, subject] = key.split('|');
  if (cls !== '9th' || !['Mathematics','Maths','Social Science'].includes(subject)) return;
  const canonical = cbse9Subject(subject), rows = CBSE9_OUTLINES[canonical];
  if (!rows || !rows.length) return;
  if (!confirm('Replace this subject\'s current tracker chapters with the CBSE 2026–27 outline? A backup of the old chapters will be kept in the school record. Existing completion ticks are retained only for chapters with exactly matching names.')) return;
  const y = sylCopy(key), old = Array.isArray(y.chapters) ? y.chapters : [];
  if (!Array.isArray(y.cbsePreviousChapters)) y.cbsePreviousChapters = JSON.parse(JSON.stringify(old));
  const same = new Map(old.map(ch => [String(ch.n || '').toLocaleLowerCase('en'), ch]));
  y.chapters = rows.map(([unit,n]) => {
    const existing = same.get(n.toLocaleLowerCase('en'));
    return { n, done: existing ? existing.done || null : null,
      plan: existing ? existing.plan || null : null,
      topics: existing && Array.isArray(existing.topics) ? existing.topics : [], cbseUnit: unit };
  });
  y.cbseEdition = '2026-27';
  y.cbseSource = cbse9Href(canonical);
  put('syl', key, y, 'Syllabus', 'Class 9 ' + subject + ': loaded CBSE 2026–27 chapter outline (previous chapters backed up)');
  toast('CBSE 2026–27 chapters saved.'); render();
}
function cbse9Restore(key) {
  const y = sylCopy(key);
  if (!Array.isArray(y.cbsePreviousChapters)) return;
  if (!confirm('Restore the chapter list saved before the CBSE import? Changes to the new list will be replaced.')) return;
  y.chapters = y.cbsePreviousChapters;
  delete y.cbsePreviousChapters;
  delete y.cbseEdition;
  delete y.cbseSource;
  put('syl', key, y, 'Syllabus', 'Class 9: restored pre-import chapter list from backup');
  toast('Previous chapters restored.'); render();
}
