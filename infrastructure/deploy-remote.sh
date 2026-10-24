#!/usr/bin/env bash
#
# Część serwerowa wdrożenia (uruchamiana przez infrastructure/deploy.sh albo ręcznie w katalogu aplikacji):
#   1. Composer 2.8+ w ~/bin/composer (instaluje, gdy go nie ma; oficjalny instalator ze sprawdzeniem sumy),
#   2. .env i pliki wdrożeniowe na miejscu, git pull --ff-only aplikacji,
#   3. token GitHub dla Composera (GITHUB_TOKEN, jeśli podany),
#   4. paczki z manifestu wdrożeniowego (COMPOSER=composer.deploy.json, --no-dev),
#   5. APP_KEY, migracje, role i słowniki (każda komenda *:dictionaries:sync paczek), cache.
# Zatrzymuje się przy pierwszym błędzie.
#
# Użycie na serwerze:
#   cd ~/moja-domena.pl && DEPLOY_PHP=php84 bash infrastructure/deploy-remote.sh

set -euo pipefail

php_bin="${DEPLOY_PHP:-php84}"
# Plik Composera na serwerze: COMPOSER_BIN (np. composer.phar w katalogu aplikacji), domyślnie ~/bin/composer.
composer_bin="${COMPOSER_BIN:-$HOME/bin/composer}"

fail() { echo "BŁĄD: $*" >&2; exit 1; }
step() { echo "--> $*"; }

command -v "$php_bin" >/dev/null || fail "Brak $php_bin. Ustaw DEPLOY_PHP na polecenie PHP 8.4 na serwerze."
"$php_bin" -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);' || fail "$php_bin to nie PHP 8.4 ($("$php_bin" -r 'echo PHP_VERSION;'))."
[[ -f artisan && -f composer.json ]] || fail "Uruchom w katalogu aplikacji (brak artisan / composer.json w $(pwd))."

if [[ ! -f "$composer_bin" ]]; then
    step "Instaluję Composera w $composer_bin"
    mkdir -p "$(dirname "$composer_bin")"
    setup="$(mktemp)"
    "$php_bin" -r "copy('https://getcomposer.org/installer', '$setup');" || fail "Nie udało się pobrać instalatora Composera."
    expected="$("$php_bin" -r "echo trim(file_get_contents('https://composer.github.io/installer.sig'));")"
    actual="$("$php_bin" -r "echo hash_file('sha384', '$setup');")"
    [[ -n "$expected" && "$expected" == "$actual" ]] || { rm -f "$setup"; fail "Błędna suma kontrolna instalatora Composera."; }
    "$php_bin" "$setup" --quiet --install-dir="$(dirname "$composer_bin")" --filename="$(basename "$composer_bin")"
    rm -f "$setup"
fi
composer=("$php_bin" "$composer_bin")
if ! "${composer[@]}" --version --no-ansi 2>/dev/null | grep -qE 'version (2\.(8|9|[1-9][0-9])|[3-9])\.'; then
    step "Aktualizuję Composera (wymagany 2.8+ dla PHP 8.4)"
    "${composer[@]}" self-update --2 --no-interaction 2>/dev/null || fail "Nie udało się zaktualizować Composera ($composer_bin)."
fi
step "$("${composer[@]}" --version --no-ansi 2>/dev/null | head -n1)"

[[ -f .env ]] || fail "Brak .env. Skopiuj .env.example jako .env i uzupełnij (baza, APP_URL, poczta)."
[[ -f composer.deploy.json && -f composer.deploy.lock ]] || fail "Brak composer.deploy.json / composer.deploy.lock — uruchom wdrożenie przez infrastructure/deploy.sh."

step "Pobieram kod aplikacji (git pull)"
git pull --ff-only || fail "git pull się nie udał (zmiany lokalne na serwerze albo brak gałęzi śledzonej) — popraw i uruchom ponownie."

# Katalogi, których git nie przenosi (puste), a Laravel ich wymaga (widoki aplikacji, cache, logi).
mkdir -p resources/views storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache


if [[ -n "${GITHUB_TOKEN:-}" ]]; then
    step "Zapisuję token GitHub w konfiguracji Composera"
    "${composer[@]}" config --global github-oauth.github.com "$GITHUB_TOKEN"
fi

step "Instaluję paczki (composer.deploy.json, bez narzędzi deweloperskich)"
if ! grep -qE '^APP_KEY=.+' .env; then
    # Aplikacja nie uruchomi się bez APP_KEY, więc klucz powstaje bez artisana (losowe 32 bajty, base64).
    step "Generuję APP_KEY"
    key="base64:$("$php_bin" -r 'echo base64_encode(random_bytes(32));')"
    if grep -qE '^APP_KEY=' .env; then
        sed -i -E "s|^APP_KEY=.*|APP_KEY=${key}|" .env
    else
        printf '\nAPP_KEY=%s\n' "$key" >> .env
    fi
fi
COMPOSER=composer.deploy.json "${composer[@]}" install --no-dev --optimize-autoloader --no-interaction
[[ -f vendor/autoload.php ]] || fail "Instalacja nie utworzyła vendor/autoload.php."

step "Migracje"
"$php_bin" artisan migrate --force

step "Role, słowniki i dane startowe"
"$php_bin" artisan mainboard:permissions:sync
"$php_bin" artisan mainboard:localization:seed
for command in $("$php_bin" artisan list --raw | awk '{print $1}' | grep -E ':dictionaries:sync$' || true); do
    "$php_bin" artisan "$command"
done

step "Cache konfiguracji, tras i widoków"
"$php_bin" artisan optimize:clear >/dev/null
"$php_bin" artisan config:cache
"$php_bin" artisan route:cache
"$php_bin" artisan view:cache

step "Wdrożenie zakończone: $("$php_bin" artisan --version)"
