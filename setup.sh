#!/usr/bin/env bash
#
# Convenience bootstrap: start a local MatrixOne, install deps, run the suite.
# Override the target with MO_HOST/MO_PORT/MO_USER/MO_PASS to test a remote
# instance instead (in which case skip the docker step).
set -euo pipefail

cd "$(dirname "$0")"

if [[ "${SKIP_DOCKER:-0}" != "1" ]]; then
  if ! docker ps --format '{{.Names}}' | grep -q '^matrixone$'; then
    echo "Starting local MatrixOne container..."
    docker run -d --name matrixone -p 6001:6001 matrixorigin/matrixone:latest
  fi

  echo "Waiting for MatrixOne to accept connections on 6001..."
  for _ in $(seq 1 30); do
    if php -r '$f=@fsockopen("127.0.0.1",6001,$e,$s,2); exit($f?0:1);' 2>/dev/null; then
      echo "MatrixOne is up."
      break
    fi
    sleep 4
  done
fi

[[ -d vendor ]] || COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction

php run.php "$@"
