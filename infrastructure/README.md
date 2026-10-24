# Infrastruktura lokalna (localhost)

Konfiguracja środowiska lokalnego wygenerowana przez [Smartboard](https://smartboard.local).
Zawartość katalogu nie trafia do repozytorium (`.gitignore`) — w Git jest tylko ten plik.

## Domena

https://skeleton13.local

## Struktura

- `ssl/` — certyfikat SSL (mkcert): `skeleton13.local.pem`, klucz prywatny `skeleton13.local-key.pem`
- `config/apache-vhost.conf` — vhost Apache, document root `/var/www/skeleton13/public`
- `config/php-fpm-pool.conf` — pula PHP-FPM `skeleton13` (socket: `/run/php/php8.5-fpm-skeleton13.sock`)

## Aktywacja (wymaga sudo)

```bash
sudo cp /var/www/skeleton13/infrastructure/config/apache-vhost.conf /etc/apache2/sites-available/skeleton13.local.conf
sudo a2ensite skeleton13.local
sudo cp /var/www/skeleton13/infrastructure/config/php-fpm-pool.conf /etc/php/8.5/fpm/pool.d/skeleton13.conf
sudo systemctl reload php8.5-fpm
sudo systemctl reload apache2
echo "127.0.0.1 skeleton13.local" | sudo tee -a /etc/hosts
```

## Wdrożenie na serwer (jednym poleceniem)

Wdrożenie robi polecenie `vendor/bin/simplysmart` z pakietu `simplysmart/tools` (uruchamiane na komputerze
deweloperskim, nie na serwerze); w repozytorium są tylko wzory konfiguracji (wyjątki w `.gitignore`):

- `deploy.env.example` — skopiuj jako `deploy.env` (ignorowany) i uzupełnij `DEPLOY_SSH`, `DEPLOY_PATH`,
  `DEPLOY_PHP` i jednorazowo `GITHUB_TOKEN` (tylko do odczytu); opcjonalnie `REMOTE_COMPOSER` (np. `composer.phar`
  w katalogu aplikacji — skrypt serwerowy aktualizuje go do 2.8+, a gdy go nie ma, instaluje) i `LOCAL_COMPOSER`
  (polecenie Composera na komputerze, np. `php composer.phar`);
- `deploy.onboarding.env.example` — wartości pierwszej instalacji (konta, np. pierwszy administrator); skopiuj
  jako `deploy.onboarding.env` (ignorowany) i uzupełnij.

```bash
cp infrastructure/deploy.env.example infrastructure/deploy.env                        # raz, uzupełnij
cp infrastructure/deploy.onboarding.env.example infrastructure/deploy.onboarding.env  # raz, uzupełnij
vendor/bin/simplysmart deploy:onboarding --dry-run   # podgląd: konfiguracja, repozytoria, konta
vendor/bin/simplysmart deploy:onboarding             # pierwsza instalacja: wdrożenie + konta
vendor/bin/simplysmart deploy                        # każde kolejne wdrożenie
```

`deploy` sprawdza, czy aplikacja i paczki z `packages/*/*` są wypchnięte, tworzy `composer.deploy.json`
i `composer.deploy.lock`, wysyła je na serwer i uruchamia tam przez SSH skrypt serwerowy z tools (Composer,
`.env`, `git pull`, paczki z GitHuba bez narzędzi deweloperskich, `APP_KEY`, migracje, role, słowniki, cache).
`deploy:onboarding` robi to samo i zakłada konta z `deploy.onboarding.env`; istniejące są pomijane.

Na serwerze musi istnieć repozytorium aplikacji (`git clone`) z plikiem `.env`. Szczegóły: README `simplysmart/tools`
i `docs/wdrozenie.md` w mainboard.
