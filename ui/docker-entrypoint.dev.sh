#!/bin/sh
set -e
cd /app
if [ ! -x node_modules/.bin/vite ]; then
  echo "ui: installing npm dependencies (first run or empty node_modules volume)..."
  npm ci
fi
exec npm run dev -- --host 0.0.0.0 --port 5173
