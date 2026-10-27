#!/usr/bin/env bash
# ============================================================
#  सारी जाँच — एक ही हुक्म से
#
#  Kyun chahiye: zyadatar jaanch "php <file>" se chal jaati hai,
#  par backup-flow.php NAHI. Use teen kadam chahiye --
#
#      export  ->  nakal ko nayi database me daalo  ->  verify
#
#  Use khali chalane par wo theek hi mana karti hai ("export ya
#  verify, ek chuniye"). Ek baar yahi dekh kar ye maan liya gaya
#  tha ki wo TOOTI HUI hai, aur Ankit ko galat ginti bata di gayi.
#
#  Galat ginti sabse mehengi hoti hai: ya to aadmi tooti hui cheez
#  ko theek maan leta hai, ya theek cheez ko mahino theek karne
#  baithta hai. Isliye ab wo tareeka yahin likha hua hai, kisi ke
#  dimaag me nahi.
#
#  Chalane ka tareeka:
#      bash .github/jaanch/sab.sh
#
#  Pehle se chahiye: maakit_jaanch database bhari hui, root par
#  config.php usi database ki, aur 127.0.0.1:8099 par server.
# ============================================================
set -uo pipefail
cd "$(dirname "$0")/../.." || exit 1

MY=${MYSQL:-mysql}
theek=0; tooti=0; naam_tooti=()

chalao() {                       # chalao <naam> <hukm...>
  local naam=$1; shift
  if out=$("$@" 2>&1); then
    theek=$((theek+1)); printf '  ठीक   %s\n' "$naam"
  else
    tooti=$((tooti+1)); naam_tooti+=("$naam")
    wajah=$(echo "$out" | grep -oE 'RuntimeException: .*|Fatal error: .*|Error: .*' | head -1 | cut -c1-200)
    printf '  टूटी   %s\n' "$naam"
    printf '         %s\n' "$(echo "$wajah" | cut -c1-90)"
    # GitHub par log padhna aasan nahi hota -- wo doosre server se
    # aata hai. "::error::" likhne par wajah SEEDHA run ke panne par
    # dikh jati hai, bina log kholne ke. Ek baar iski kami me ghante
    # lag gaye the sirf ye pata karne me ki kaun si jaanch laal hai.
    if [ -n "${GITHUB_ACTIONS:-}" ]; then
      echo "::error title=जाँच टूटी: ${naam}::${wajah:-$(echo "$out" | tail -3 | tr '\n' ' ')}"
    fi
  fi
}

echo "सारी जाँच चल रही है…"
echo

# ---- साधारण जाँच — सीधे चलती हैं ----
for f in .github/jaanch/*.php; do
  base=$(basename "$f")
  [ "$base" = "backup-flow.php" ] && continue      # iska apna tareeka hai, niche
  chalao "$base" php "$f"
done

# ---- नक़ल वाली जाँच — तीन कदम ----
# Nakal banti hai, ek BILKUL NAYI khali database me daali jaati hai,
# aur phir dono ko mila kar dekha jata hai. Isi se pata chalta hai ki
# nakal sach me kaam ki hai -- sirf file ban jana kaafi nahi.
nakal() {
  rm -rf /tmp/maakit-backup-ci && mkdir -p /tmp/maakit-backup-ci
  php .github/jaanch/backup-flow.php export || return 1
  $MY -e 'DROP DATABASE IF EXISTS maakit_restore_jaanch' || return 1
  $MY -e 'CREATE DATABASE maakit_restore_jaanch CHARACTER SET utf8mb4' || return 1
  gzip -dc "$(cat /tmp/maakit-backup-path)" | $MY maakit_restore_jaanch || return 1
  php .github/jaanch/backup-flow.php verify || return 1
  $MY -e 'DROP DATABASE IF EXISTS maakit_restore_jaanch'
}
chalao "backup-flow.php (export → restore → verify)" nakal

echo
if [ "$tooti" -eq 0 ]; then
  echo "सब ठीक — $theek में से $theek।"
else
  echo "ठीक: $theek   टूटी: $tooti"
  for n in "${naam_tooti[@]}"; do echo "   • $n"; done
  if [ -n "${GITHUB_ACTIONS:-}" ]; then
    echo "::error title=जाँच::$tooti जाँच टूटी हैं — ऊपर हर एक की वजह लिखी है"
  fi
fi
exit "$tooti"
