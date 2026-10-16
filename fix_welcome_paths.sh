#!/usr/bin/env bash
set -u
# sed -i differs on macOS
if sed --version >/dev/null 2>&1; then SEDI=(sed -i); else SEDI=(sed -i ''); fi

# 1. Blade files: use url() so /welcome is included
"${SEDI[@]}" 's|fetch(`/training/|fetch(`{{ url("/training") }}/|' \
  resources/views/training/show.blade.php resources/views/sessions/training_required.blade.php
"${SEDI[@]}" 's|fetch(`/tasks/|fetch(`{{ url("/tasks") }}/|' \
  resources/views/properties/tasks/__task_form.blade.php \
  resources/views/properties/property-tasks/__property_task_form.blade.php
"${SEDI[@]}" "s|fetch('/guest/'|fetch('{{ url(\"/guest\") }}/'|" resources/views/guest/category.blade.php
"${SEDI[@]}" "s|fetch('/api/properties/'|fetch('{{ url(\"/api/properties\") }}/'|" resources/views/sessions/manage/index.blade.php

# 2. JS files: APP_BASE constant (once) and use it
for f in resources/js/resources-menu.js resources/js/cleaning-app.js; do
  grep -q 'const APP_BASE' "$f" || "${SEDI[@]}" '1i\
const APP_BASE = document.querySelector("meta[name=app-base]")?.content ?? "";
' "$f"
  "${SEDI[@]}" 's|fetch(`/api/|fetch(`${APP_BASE}/api/|' "$f"
done

# 3. meta tag in the three layouts that load cleaning-app.js
for f in cleaner cleaning-guest unified; do
  L=resources/views/layouts/$f.blade.php
  grep -q 'name="app-base"' "$L" || "${SEDI[@]}" 's|</head>|    <meta name="app-base" content="{{ rtrim(url('"'"'/'"'"'), '"'"'/'"'"') }}">\
</head>|' "$L"
done
echo done
