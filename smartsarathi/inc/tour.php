<?php
// ============================================================
//  Guide (owner, 30 Oct 2026): "login hote hi instruction mil
//  jaye — kisse kya hoga". Ek hi dhancha dono app me:
//   - pehli baar apne aap khulta hai (har guide ki apni yaad, localStorage)
//   - har kadam par 🔊, "सब सुनिए", "समझ गया"
//   - ?guide=1 ya [data-guide-open] se dobara khulta hai
//  Grahak = hara, Sarathi = peela. Kabhi doosre app ki baat nahi.
// ============================================================

/**
 * @param string $id     e.g. 'cust-book', 'partner-work' (yaad ki chaabi)
 * @param string $title  upar ka shirshak
 * @param array  $steps  [[icon, heading, text], ...]
 * @param string $theme  'mint' (grahak) | 'amber' (Sarathi)
 * @param bool   $auto   pehli baar apne aap khule?
 */
function sr_guide(string $id, string $title, array $steps, string $theme = 'mint', bool $auto = true): void {
    $accent = $theme === 'amber' ? '#FFC83D' : '#10C98F';
    $all = implode(' ', array_map(fn($s, $i) => ($i + 1) . '. ' . $s[1] . '. ' . $s[2], $steps, array_keys($steps)));
    ?>
<div class="sg" id="sg-<?= h($id) ?>" data-guide="<?= h($id) ?>" data-auto="<?= $auto ? '1' : '0' ?>" hidden role="dialog" aria-modal="true" aria-labelledby="sg-t-<?= h($id) ?>" style="--sg:<?= $accent ?>">
  <div class="sg-card">
    <div class="sg-top"><h2 id="sg-t-<?= h($id) ?>"><?= h($title) ?></h2>
      <button type="button" class="sg-say" data-sg-say="<?= h($all) ?>" aria-label="<?= h(t('Listen to all', 'सब सुनिए')) ?>">🔊 <?= h(t('Listen', 'सुनिए')) ?></button></div>
    <ol class="sg-steps">
    <?php foreach ($steps as $i => [$ic, $hd, $tx]): ?>
      <li><span class="sg-ic" aria-hidden="true"><?= h($ic) ?></span><span class="sg-tx"><b><?= ($i + 1) . '. ' . h($hd) ?></b><small><?= h($tx) ?></small></span>
        <button type="button" class="sg-one" data-sg-say="<?= h($hd . '. ' . $tx) ?>" aria-label="<?= h(t('Listen', 'सुनिए')) ?>">🔊</button></li>
    <?php endforeach; ?>
    </ol>
    <button type="button" class="sg-ok" data-sg-close><?= h(t('Got it', 'समझ गया')) ?></button>
  </div>
</div>
<?php static $css = false; if (!$css): $css = true; ?>
<style>
.sg{position:fixed;inset:0;z-index:2500;background:rgba(3,7,13,.6);display:flex;align-items:flex-end;justify-content:center}
.sg[hidden]{display:none}
.sg-card{width:100%;max-width:520px;max-height:92vh;overflow:auto;background:#fff;border-radius:24px 24px 0 0;padding:18px 16px calc(18px + env(safe-area-inset-bottom));display:grid;gap:12px;border-top:6px solid var(--sg)}
.sg-top{display:flex;align-items:center;gap:10px}.sg-top h2{flex:1;margin:0;font:800 20px/1.3 'Noto Sans Devanagari','Plus Jakarta Sans',sans-serif;color:#0B1F3A}
.sg-say{min-height:44px;border:0;border-radius:12px;background:#5B4BDB;color:#fff;font:700 14px 'Noto Sans Devanagari',sans-serif;padding:0 14px;cursor:pointer}
.sg-steps{list-style:none;margin:0;padding:0;display:grid;gap:10px}
.sg-steps li{display:flex;align-items:center;gap:12px;background:#F7F9FB;border:1px solid #E3E8EF;border-radius:16px;padding:10px 10px 10px 12px}
.sg-ic{flex:none;width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:var(--sg);font-size:22px}
.sg-tx{flex:1;min-width:0}.sg-tx b{display:block;font:700 15px/1.35 'Noto Sans Devanagari',sans-serif;color:#0B1F3A}.sg-tx small{display:block;margin-top:2px;font:500 14px/1.45 'Noto Sans Devanagari',sans-serif;color:#41505F}
.sg-one{flex:none;width:44px;height:44px;border-radius:12px;border:1px solid #E3E8EF;background:#fff;font-size:18px;cursor:pointer}
.sg-ok{min-height:54px;border:0;border-radius:16px;background:#0B1F3A;color:#fff;font:700 17px 'Noto Sans Devanagari',sans-serif;cursor:pointer}
</style>
<script>
(function () {
  var lang = document.documentElement.lang === 'en' ? 'en-IN' : 'hi-IN';
  function say(t) { try { if (!('speechSynthesis' in window) || !t) return; speechSynthesis.cancel(); var u = new SpeechSynthesisUtterance(t); u.lang = lang; u.rate = .95; speechSynthesis.speak(u); } catch (e) {} }
  function seen(id) { try { return localStorage.getItem('sr_guide_' + id) === '1'; } catch (e) { return false; } }
  function mark(id) { try { localStorage.setItem('sr_guide_' + id, '1'); } catch (e) {} }
  window.srGuideOpen = function (id) { var g = document.getElementById('sg-' + id); if (g) g.hidden = false; };
  document.addEventListener('click', function (e) {
    var s = e.target.closest('[data-sg-say]'); if (s) { e.preventDefault(); say(s.getAttribute('data-sg-say')); return; }
    var c = e.target.closest('[data-sg-close]'); if (c) { var g = c.closest('.sg'); g.hidden = true; mark(g.getAttribute('data-guide')); try { speechSynthesis.cancel(); } catch (x) {} return; }
    var o = e.target.closest('[data-guide-open]'); if (o) { e.preventDefault(); window.srGuideOpen(o.getAttribute('data-guide-open')); }
  });
  window.addEventListener('load', function () {
    var force = /[?&]guide=1/.test(location.search);
    document.querySelectorAll('.sg[data-auto="1"]').forEach(function (g) {
      // Doosra overlay (bhasha / welcome) khula ho to baad me
      if (force || !seen(g.getAttribute('data-guide'))) setTimeout(function () {
        var busy = document.querySelector('.ob:not([hidden]), .of:not([hidden])');
        if (!busy || force) g.hidden = false;
      }, 600);
    });
  });
})();
</script>
<?php endif;
}
