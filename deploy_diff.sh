#!/usr/bin/env bash
# deploy_diff.sh - list (and optionally zip) every file modified since a date/time.
# newermt decides WHAT is listed/zipped. Git is only used to cross-check the count.
#
# Usage:
#   ./deploy_diff.sh "YYYY-MM-DD HH:MM"          # list + git cross-check
#   ./deploy_diff.sh "YYYY-MM-DD HH:MM" --zip    # also zip all modified files to ~/Downloads
#   ./deploy_diff.sh 14:30 --zip                 # HH:MM alone means today
#   ./deploy_diff.sh --since "2026-10-15 09:00" --zip
#   ./deploy_diff.sh "2026-10-15 09:00" --move-bak
#
# A date/time is required; running without one is an error.
#
# Git cross-check = files in commits made since the cutoff + uncommitted changes
# + untracked files. It should equal the newermt list. If not, the table shows why:
#   NOT IN GIT  modified on disk but git sees nothing (ignored file, or edited then reverted)
#   GIT ONLY    git reports a change but the file's mtime is older than the cutoff

set -euo pipefail

SINCE=""; DO_ZIP=0; DO_MOVE=0
while [ $# -gt 0 ]; do
  case "$1" in
    --zip) DO_ZIP=1;;
    --move-bak) DO_MOVE=1;;
    --since) shift; SINCE="${1:-}";;
    -h|--help) sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//'; exit 0;;
    -*) echo "Unknown option: $1"; exit 1;;
    *) SINCE="$1";;
  esac
  shift
done

if [ -z "$SINCE" ]; then
  echo "ERROR: enter a date and time, e.g.  ./deploy_diff.sh \"2026-10-16 10:07\"  (or just 10:07 for today)" >&2
  exit 1
fi
if [[ "$SINCE" =~ ^[0-9]{1,2}:[0-9]{2}(:[0-9]{2})?$ ]]; then SINCE="$(date +%Y-%m-%d) $SINCE"; fi
date -d "$SINCE" >/dev/null 2>&1 || { echo "ERROR: invalid date/time '$SINCE'" >&2; exit 1; }
SINCE="$(date -d "$SINCE" '+%Y-%m-%d %H:%M:%S')"

git rev-parse --show-toplevel >/dev/null 2>&1 || { echo "Not in a git repo" >&2; exit 1; }
cd "$(git rev-parse --show-toplevel)"

EXCL='(^|/)(tests|scripts|vendor|node_modules|\.git|\.github|\.idea|\.vscode|docker|backups)/'
EXCL+='|^storage/logs/|^storage/framework/(cache|sessions|views|testing)/'
EXCL+='|^storage/app/(rehearsal|public|photo-ids|client-preview)/'
EXCL+='|^public/(client-preview|storage)/|^public/storage_old_broken_backup'
EXCL+='|^bootstrap/cache/.*\.php$'
EXCL+='|\.md$|(^|/)phpunit\.xml$|\.phpunit\.result\.cache$'
EXCL+='|(^|/)\.env(\.local|\.production|\.backup)?$'
EXCL+='|^database/database\.sql$|\.sql$|\.sqlite$|\.zip$'
EXCL+='|(^|/)docker-compose\.yml$|(^|/)Dockerfile$|(^|/)package-lock\.json$'
EXCL+='|\.bak[0-9]*$|\.orig$|(^|/)\.DS_Store$'
EXCL+='|\.pre-[^/]*$'
EXCL+='|^(deploy_diff\.sh|deploy_files\.txt)$'

TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT

# ---- newermt: the source of truth ----
find . -type f -newermt "$SINCE" -not -path './.git/*' -not -path './vendor/*' -not -path './node_modules/*' 2>/dev/null \
  | sed 's|^\./||' | grep -Ev "$EXCL" | sort -u > "$TMP/mtime.txt" || true

# ---- git cross-check (existing files only) ----
{ git log --since="$SINCE" --name-only --pretty=format: 2>/dev/null
  git diff --name-only HEAD 2>/dev/null
  git ls-files --others --exclude-standard; } \
  | grep -v '^$' | grep -Ev "$EXCL" | sort -u | while IFS= read -r f; do [ -f "$f" ] && echo "$f"; done > "$TMP/git.txt" || true

n_m=$(wc -l < "$TMP/mtime.txt"); n_g=$(wc -l < "$TMP/git.txt")
comm -23 "$TMP/mtime.txt" "$TMP/git.txt" > "$TMP/m_only.txt"
comm -13 "$TMP/mtime.txt" "$TMP/git.txt" > "$TMP/g_only.txt"
n_mo=$(wc -l < "$TMP/m_only.txt"); n_go=$(wc -l < "$TMP/g_only.txt")

echo "Since: $SINCE"
echo
printf '  %-34s %5s\n' "newermt (modified since cutoff)" "$n_m"
printf '  %-34s %5s\n' "git (commits+changes+untracked)"  "$n_g"
printf '  %-34s %5s\n' "NOT IN GIT (newermt only)"        "$n_mo"
printf '  %-34s %5s\n' "GIT ONLY (older mtime)"           "$n_go"
if [ "$n_mo" -eq 0 ] && [ "$n_go" -eq 0 ]; then echo "  RESULT: counts and files MATCH"; else echo "  RESULT: DIFFERENT - see table"; fi
echo

# ---- table ----
{ awk '{print $0 "\tYES\tYES"}' <(comm -12 "$TMP/mtime.txt" "$TMP/git.txt")
  awk '{print $0 "\tYES\tNO"}'  "$TMP/m_only.txt"
  awk '{print $0 "\tNO\tYES"}'  "$TMP/g_only.txt"; } | sort -t$'\t' -k1,1 > "$TMP/rows.tsv"

if [ -s "$TMP/rows.tsv" ]; then
  w=$(awk -F'\t' '{ if (length($1)>m) m=length($1) } END { print (m<4?4:m) }' "$TMP/rows.tsv")
  line="+-$(printf '%*s' "$w" '' | tr ' ' '-')-+---------------------+----------+--------+--------------+"
  echo "$line"; printf '| %-*s | %-19s | %-8s | %-6s | %-12s |\n' "$w" FILE "MODIFIED (mtime)" NEWERMT GIT STATUS; echo "$line"
  while IFS=$'\t' read -r f a b; do
    ts="-"; [ -e "$f" ] && ts="$(stat -c '%y' "$f" | cut -c1-19)"
    if [ "$a$b" = YESYES ]; then st="OK"; elif [ "$a" = YES ]; then st="NOT IN GIT"; else st="GIT ONLY"; fi
    printf '| %-*s | %-19s | %-8s | %-6s | %-12s |\n' "$w" "$f" "$ts" "$a" "$b" "$st"
  done < "$TMP/rows.tsv"
  echo "$line"
else
  echo "(no files modified since $SINCE)"
fi
echo

# ---- zip: ALL newermt files ----
if [ "$DO_ZIP" -eq 1 ]; then
  if [ -s "$TMP/mtime.txt" ]; then
    mkdir -p "$HOME/Downloads"; Z="$HOME/Downloads/deploy_$(date +%Y%m%d_%H%M).zip"
    zip -q "$Z" -@ < "$TMP/mtime.txt"
    echo "Zipped $n_m file(s) -> $Z"; unzip -l "$Z" | tail -1
  else echo "Nothing to zip."; fi
fi

# ---- *bak* files ----
echo; echo "== backup-looking files (*bak*) =="
find . -type f \( -iname '*bak*' -o -name '*.pre-*' \) -not -path './.git/*' -not -path './vendor/*' -not -path './node_modules/*' \
  2>/dev/null | sed 's|^\./||' | tee "$TMP/bak.txt" || true
if [ "$DO_MOVE" -eq 1 ] && [ -s "$TMP/bak.txt" ]; then
  DEST="$HOME/moved_bak_files/$(date +%Y%m%d_%H%M)"
  while IFS= read -r b; do mkdir -p "$DEST/$(dirname "$b")"; mv "$b" "$DEST/$b"; done < "$TMP/bak.txt"
  echo "Moved to $DEST"
else echo "(listed only; --move-bak moves them out of the project)"; fi
