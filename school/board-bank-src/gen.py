import json, random, sys
from maths import MATHS, AR
from science import SCIENCE
from sst import SST, ENGLISH
SUBJ = [('maths', 'Mathematics', MATHS), ('science', 'Science', SCIENCE), ('sst', 'Social Science', SST), ('english', 'English', ENGLISH)]
out = {'version': '2026-27.1', 'note': 'Original questions written for SN Mishra Singapore International School Board Prep, aligned to the CBSE 2026-27 Class 10 chapter list (NCERT rationalised books). Not copied from NCERT or CBSE papers.', 'subjects': []}
n = 0; errs = []
for code, name, chs in SUBJ:
    S = {'code': code, 'name': name, 'chapters': []}
    for ci, (cname, qs) in enumerate(chs, 1):
        C = {'n': ci, 'name': cname, 'q': []}
        for qi, q in enumerate(qs, 1):
            qid = f'{code[:2]}{ci:02d}{qi:02d}'
            if isinstance(q, dict):
                C['q'].append({'id': qid, 't': 'ar', 'q': q['q'], 'o': AR, 'a': q['a'], 'e': q['e']})
            else:
                text, right, wrong, expl = q
                opts = [right] + wrong
                if len(set(opts)) != 4: errs.append(qid + ' duplicate options')
                rnd = random.Random(qid); rnd.shuffle(opts)
                C['q'].append({'id': qid, 't': 'mcq', 'q': text, 'o': opts, 'a': opts.index(right), 'e': expl})
            n += 1
        S['chapters'].append(C)
    out['subjects'].append(S)
if errs: print(errs); sys.exit(1)
json.dump(out, open(sys.argv[1], 'w'), ensure_ascii=False, separators=(',', ':'))
from collections import Counter
print(n, 'questions', Counter(q['a'] for s in out['subjects'] for c in s['chapters'] for q in c['q']))
