#!/usr/bin/env bash
#
# tests/manual/smoke.sh — basic HTTP smoke test for the live site.
#
# Exits 0 if https://ai.zuhrirey.my.id/ responds with HTTP 200 within
# a reasonable timeout. Otherwise exits non-zero with a diagnostic.
#
# Designed for both local development and CI; safe to run any time
# before changes that might break the public landing page.

set -u

TARGET_URL="${BLUEPRINTFORGE_SMOKE_URL:-https://ai.zuhrirey.my.id/}"
TIMEOUT_SECONDS="${BLUEPRINTFORGE_SMOKE_TIMEOUT:-10}"

echo "==> smoke test: GET ${TARGET_URL} (timeout ${TIMEOUT_SECONDS}s)"

# -k: skip cert verification for environments with self-signed CA.
# -s: silent (no progress).
# -S: still show errors.
# -o: discard body.
# -w: print only the HTTP code on success.
http_code=$(curl -k -s -S -o /dev/null -w "%{http_code}" \
    --max-time "${TIMEOUT_SECONDS}" \
    "${TARGET_URL}" 2>&1)
curl_exit=$?

if [ "${curl_exit}" -ne 0 ]; then
    echo "FAIL: curl exited with code ${curl_exit}" >&2
    exit 1
fi

echo "    HTTP ${http_code}"

if [ "${http_code}" = "200" ]; then
    echo "==> smoke OK"
    exit 0
fi

echo "FAIL: expected HTTP 200, got ${http_code}" >&2
exit 1