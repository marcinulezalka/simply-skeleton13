#!/usr/bin/env bash
#
# Wdrożenie aplikacji na serwer jednym poleceniem, uruchamiane na komputerze deweloperskim (nie na serwerze —
# tam działa infrastructure/deploy-remote.sh).
#
# Kroki:
#   1. sprawdza, czy aplikacja i paczki z packages/*/* są wypchnięte (inaczej serwer dostałby stary kod),
#   2. tworzy manifest wdrożeniowy: composer deploy:lock → composer.deploy.json + composer.deploy.lock,
#   3. wysyła oba pliki na serwer (scp),
#   4. uruchamia na serwerze infrastructure/deploy-remote.sh (ssh): git pull, Composer, instalacja, migracje.
#
# Konfiguracja: infrastructure/deploy.env (wzór: infrastructure/deploy.env.example).
#
# Użycie:
#   infrastructure/deploy.sh [plik-konfiguracji]     (domyślnie infrastructure/deploy.env)

set -euo pipefail

cd "$(dirname "$0")/.."

config="${1:-infrastructure/deploy.env}"
[[ -f "$config" ]] || { echo "Brak $config — skopiuj infrastructure/deploy.env.example i uzupełnij." >&2; exit 1; }
# shellcheck disable=SC1090
source "$config"

: "${DEPLOY_SSH:?Ustaw DEPLOY_SSH w $config}"
: "${DEPLOY_PATH:?Ustaw DEPLOY_PATH w $config}"
deploy_php="${DEPLOY_PHP:-php84}"
# Polecenie Composera na tym komputerze (np. "php composer.phar"); domyślnie `composer`, a bez niego composer.phar.
if [[ -n "${LOCAL_COMPOSER:-}" ]]; then
    read -r -a local_composer <<< "$LOCAL_COMPOSER"
elif command -v composer >/dev/null; then
    local_composer=(composer)
elif [[ -f composer.phar ]]; then
    local_composer=(php composer.phar)
else
    echo "Brak Composera na tym komputerze — ustaw LOCAL_COMPOSER w $config (np. \"php composer.phar\")." >&2
    exit 1
fi

echo "==> Sprawdzam, czy wszystko jest wypchnięte"
unpushed=()
for repo in . packages/*/*; do
    [[ -d "$repo/.git" ]] || continue
    git -C "$repo" fetch --quiet origin 2>/dev/null || echo "Uwaga: nie udało się pobrać stanu z origin dla $repo — sprawdzam według ostatniego pobrania." >&2
    ahead="$(git -C "$repo" rev-list --count '@{u}..HEAD' 2>/dev/null || echo 0)"
    if [[ "$ahead" != "0" ]]; then
        unpushed+=("$repo ($ahead)")
    fi
    if [[ -n "$(git -C "$repo" status --porcelain)" ]]; then
        echo "Uwaga: $repo ma niezacommitowane zmiany — nie trafią na serwer." >&2
    fi
done
if (( ${#unpushed[@]} > 0 )); then
    echo "Niewypchnięte commity: ${unpushed[*]}. Zrób git push i uruchom ponownie." >&2
    exit 1
fi

echo "==> Tworzę manifest wdrożeniowy (composer deploy:lock)"
"${local_composer[@]}" deploy:lock
[[ -f composer.deploy.json && -f composer.deploy.lock ]] || { echo "Brak composer.deploy.json / composer.deploy.lock po deploy:lock." >&2; exit 1; }

echo "==> Wysyłam pliki wdrożeniowe na $DEPLOY_SSH:$DEPLOY_PATH"
scp -q composer.deploy.json composer.deploy.lock "$DEPLOY_SSH:$DEPLOY_PATH/"

echo "==> Uruchamiam wdrożenie na serwerze"
# Token idzie przez standardowe wejście, nie przez argumenty (nie trafia do listy procesów).
{
    printf 'export DEPLOY_PHP=%q GITHUB_TOKEN=%q\n' "$deploy_php" "${GITHUB_TOKEN:-}"
    if [[ -n "${REMOTE_COMPOSER:-}" ]]; then
        printf 'export COMPOSER_BIN=%s\n' "$REMOTE_COMPOSER"
    fi
    printf 'cd %s || exit 1\n' "$DEPLOY_PATH"
    cat infrastructure/deploy-remote.sh
} | ssh "$DEPLOY_SSH" 'bash -s'

echo "==> Gotowe"
