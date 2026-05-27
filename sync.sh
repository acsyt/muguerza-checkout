#!/bin/bash

REMOTE="appusr@prastvdcol901.muguerza.com.mx:/home/appusr/containers/muguerza_wp/code/muguerza-checkout/"
LOCAL="/Users/ijurado/Local Sites/muguerza/app/public/wp-content/plugins/muguerza-checkout/"

while true; do
  rsync -az --delete \
    --exclude ".git" \
    --exclude "node_modules" \
    --exclude ".DS_Store" \
    --exclude "sync.sh" \
    "$LOCAL" "$REMOTE"

  if [ $? -eq 0 ]; then
    echo "Sync OK: $(date)"
  else
    echo "Sync FAILED: $(date)"
  fi

  sleep 5
done
