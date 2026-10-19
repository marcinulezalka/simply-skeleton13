#!/usr/bin/env bash
#
# Tworzy bazę MySQL/MariaDB i jej użytkownika na podstawie pliku środowiska.
#
# Konwencja (jak na dhosting.pl): baza i użytkownik mają tę samą nazwę
# z prefiksem konta (x2k7wk_<projekt>), użytkownik ma ALL PRIVILEGES tylko
# na swojej bazie. Kodowanie utf8mb4 / utf8mb4_unicode_ci działa zarówno
# na MySQL 8, jak i na MariaDB (utf8mb4_0900_ai_ci istnieje tylko w MySQL).
#
# Skrypt jest idempotentny. Gdy DB_PASSWORD jest puste, generuje losowe hasło
# i zapisuje je w pliku środowiska.
#
# Użycie:
#   database/create-database.sh [plik-env]       (domyślnie .env.local, a gdy go brak: .env)
#   MYSQL_ADMIN="mysql -uroot -p" database/create-database.sh
#                                                 (domyślnie: sudo mysql)

set -euo pipefail

cd "$(dirname "$0")/.."

env_file="${1:-}"
if [[ -z "$env_file" ]]; then
    if [[ -f .env.local ]]; then env_file=.env.local; else env_file=.env; fi
fi
[[ -f "$env_file" ]] || { echo "Brak pliku $env_file" >&2; exit 1; }

env_value() {
    grep -E "^$1=" "$env_file" | tail -n1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'
}

db_name="$(env_value DB_DATABASE)"
db_user="$(env_value DB_USERNAME)"
db_password="$(env_value DB_PASSWORD)"
db_host="localhost"

[[ -n "$db_name" && -n "$db_user" ]] || { echo "Ustaw DB_DATABASE i DB_USERNAME w $env_file" >&2; exit 1; }
[[ "$db_name" =~ ^[A-Za-z0-9_]+$ && "$db_user" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Nazwa bazy i użytkownika: tylko [A-Za-z0-9_]" >&2; exit 1; }

if [[ -z "$db_password" ]]; then
    db_password="$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 32)"
    sed -i -E "s|^DB_PASSWORD=.*|DB_PASSWORD=${db_password}|" "$env_file"
    echo "Wygenerowano DB_PASSWORD i zapisano w $env_file"
fi

sql_password="${db_password//\\/\\\\}"
sql_password="${sql_password//\'/\\\'}"

read -r -a mysql_admin <<< "${MYSQL_ADMIN:-sudo mysql}"

"${mysql_admin[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${db_user}'@'${db_host}' IDENTIFIED BY '${sql_password}';
ALTER USER '${db_user}'@'${db_host}' IDENTIFIED BY '${sql_password}';
GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'${db_host}';
FLUSH PRIVILEGES;
SQL

echo "Baza ${db_name} i użytkownik ${db_user}@${db_host} gotowe. Dalej: php artisan migrate"
