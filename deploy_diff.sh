#!/usr/bin/env bash
# deploy_diff.sh - find files to deploy since the last deployed zip.
# Run from the project (git) root.
#
# Stages:
#   1. find -newermt  : files modified since SINCE
#   2. git            : keep only those that differ from BASE (default HEAD) or are untracked
#   3. last zip       : keep only those that are new/different vs the 13 Oct zip
#   4. exclusions     : applied at every stage
#
# Usage:
#   ./deploy_diff.sh                 # dry run: prints lists, writes deploy_files.txt
#   ./deploy_diff.sh --zip           # also builds ~/Downloads/deploy_<timestamp>.zip
#   ./deploy_diff.sh --move-bak      # also moves *bak* files to ~/moved_bak_files
#   LAST_ZIP=~/Downloads/x.zip BASE=<commit> SINCE="2026-10-12 17:00:00" ./deploy_diff.sh

set -euo pipefail

SINCE="${SINCE:-2026-10-12 17:00:00}"
BASE="${BASE:-HEAD}"
DL="$HOME/Downloads"
OUT="deploy_files.txt"
DO_ZIP=0; DO_MOVE=0
for a in "$@"; do
  case "$a" in --zip) DO_ZIP=1;; --move-bak) DO_MOVE=1;; esac
done

git rev-parse --show-toplevel >/dev/null 2>&1 || { echo "Not in a git repo"; exit 1; }
cd "$(git rev-parse --show-toplevel)"

# ---- exclusion regex (applied to repo-relative paths) ----
EXCL='(^|/)(tests|scripts|vendor|node_modules|\.git|\.github|\.idea|\.vscode|docker|backups)/'
EXCL+='|^storage/logs/|^storage/framework/(cache|sessions|views|testing)/'
EXCL+='|^storage/app/(rehearsal|public|photo-ids|client-preview)/'
EXCL+='|^public/(client-preview|storage)/|^public/storage_old_broken_backup'
EXCL+='|^bootstrap/cache/.*\.php$'
EXCL+='|\.md$|(^|/)phpunit\.xml$|\.phpunit\.result\.cache$'
EXCL+='|(^|/)\.env(\.local|\.production|\.backup)?$'
EXCL+='|^database/database\.sql$|\.sql$|\.sqlite$|\.zip$'
EXCL+='|(^|/)docker-compose\.yml$|(^|/)Dockerfile$|(^|/)package-lock\.json$'
EXCL+='|\.bak$|\.orig$|(^|/)\.DS_Store$'
EXCL+='|\.pre-[^/]*$'
EXCL+='|^(deploy_diff\.sh|deploy_files\.txt|deploy_final\.txt|git_only_changes\.txt|deleted_files\.txt|dropped_unchanged_in_git\.txt)$'

TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT

# ---- 1. modified since SINCE ----
find . -type f -newermt "$SINCE" \
  -not -path './.git/*' -not -path './vendor/*' -not -path './node_modules/*' \
  2>/dev/null \
  | sed 's|^\./||' | grep -Ev "$EXCL" | sort > "$TMP/mtime.txt" || true
echo "[1] modified since $SINCE (after exclusions): $(wc -l < "$TMP/mtime.txt")"

# ---- 2. git: changed vs BASE, plus untracked ----
{ git diff --name-only "$BASE" 2>/dev/null
  git ls-files --others --exclude-standard; } | grep -Ev "$EXCL" | sort -u > "$TMP/git.txt" || true
comm -12 "$TMP/mtime.txt" "$TMP/git.txt" > "$TMP/stage2.txt"
echo "[2] also changed in git vs $BASE (or untracked):  $(wc -l < "$TMP/stage2.txt")"

# files modified by mtime but identical to BASE in git (dropped at stage 2)
comm -23 "$TMP/mtime.txt" "$TMP/git.txt" > dropped_unchanged_in_git.txt
echo "    note: $(wc -l < dropped_unchanged_in_git.txt) mtime-modified files match $BASE and were dropped (dropped_unchanged_in_git.txt)"

# files git sees as modified but mtime missed (e.g. checkout/touch) - informational
comm -13 "$TMP/mtime.txt" "$TMP/git.txt" > "$TMP/git_only.txt"
[ -s "$TMP/git_only.txt" ] && echo "    note: $(wc -l < "$TMP/git_only.txt") git-changed files have older mtimes (see git_only_changes.txt)" \
  && cp "$TMP/git_only.txt" git_only_changes.txt

# ---- 3. compare with the 13 Oct zip ----
if [ -z "${LAST_ZIP:-}" ]; then
  LAST_ZIP="$(find "$DL" -maxdepth 1 -name '*.zip' \
      -newermt '2026-10-13 00:00:00' ! -newermt '2026-10-14 00:00:00' -printf '%T@ %p\n' \
      | sort -n | tail -1 | cut -d' ' -f2-)"
fi
[ -n "$LAST_ZIP" ] && [ -f "$LAST_ZIP" ] || { echo "Could not find 13 Oct zip in $DL. Set LAST_ZIP=..."; exit 1; }
echo "[3] comparing against: $LAST_ZIP"

mkdir -p "$TMP/zip"; unzip -q "$LAST_ZIP" -d "$TMP/zip"
ROOT="$TMP/zip"
# if the zip has a single top-level folder, descend into it
if [ "$(ls -A "$ROOT" | wc -l)" -eq 1 ] && [ -d "$ROOT/$(ls -A "$ROOT")" ]; then
  # only descend if that folder doesn't look like an app dir itself (app/, public/ ...)
  case "$(ls -A "$ROOT")" in app|public|config|routes|resources|database|bootstrap|storage) ;; *) ROOT="$ROOT/$(ls -A "$ROOT")";; esac
fi

: > "$OUT"
while IFS= read -r f; do
  if [ ! -f "$ROOT/$f" ]; then echo "$f" >> "$OUT"; echo "  NEW      $f"
  elif ! cmp -s "$f" "$ROOT/$f"; then echo "$f" >> "$OUT"; echo "  CHANGED  $f"
  fi
done < "$TMP/stage2.txt"
echo "[3] files that differ from last deployed zip: $(wc -l < "$OUT")  -> $OUT"

# deleted files (in git, gone from disk) - you may need to remove these on the server
git ls-files --deleted | grep -Ev "$EXCL" > deleted_files.txt || true
[ -s deleted_files.txt ] && echo "    note: $(wc -l < deleted_files.txt) tracked files deleted locally (deleted_files.txt)"

# ---- 4. zip ----
if [ "$DO_ZIP" -eq 1 ] && [ -s "$OUT" ]; then
  Z="$DL/deploy_$(date +%Y%m%d_%H%M).zip"
  zip -q "$Z" -@ < "$OUT"
  echo "[4] created $Z"; unzip -l "$Z" | tail -1
fi

# ---- *bak* files ----
echo; echo "== backup-looking files (*bak*) =="
find . -type f \( -iname '*bak*' -o -name '*.pre-*' \) \
  -not -path './.git/*' -not -path './vendor/*' -not -path './node_modules/*' \
  2>/dev/null | tee "$TMP/bak.txt" || true
if [ "$DO_MOVE" -eq 1 ] && [ -s "$TMP/bak.txt" ]; then
  DEST="$HOME/moved_bak_files/$(date +%Y%m%d_%H%M)"
  while IFS= read -r b; do
    mkdir -p "$DEST/$(dirname "$b")"; mv "$b" "$DEST/$b"
  done < "$TMP/bak.txt"
  echo "Moved to $DEST"
else
  echo "(listed only; re-run with --move-bak to move them out of the project)"
fi
