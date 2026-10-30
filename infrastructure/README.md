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

## Wdrożenie projektu: przed-produkcja, potem produkcja

Wdrożenie robi polecenie `vendor/bin/simplysmart` z narzędzi deweloperskich pakietu `simplysmart/admiro`, uruchamiane
w tym projekcie bazowym (z paczkami w `packages/`) na komputerze deweloperskim. Każdy wdrażany projekt ma konfigurację
w `infrastructure/deploy-vendors/` (katalog w `.gitignore`):

- `deploy-vendors/<projekt>.env` — przed-produkcja (`PREPRODUCTION_PATH`: katalog projektu na tym komputerze, PHP
  i Composer) i produkcja (`DEPLOY_SSH`, `DEPLOY_PATH`, `DEPLOY_PHP`, `REMOTE_COMPOSER`, `GITHUB_TOKEN`); wzór
  `deploy-vendor.env.example`;
- `deploy-vendors/<projekt>.onboarding.env` — konta pierwszej instalacji (wzór `deploy.onboarding.env.example`).

```bash
cp infrastructure/deploy-vendor.env.example infrastructure/deploy-vendors/shop.env   # raz, uzupełnij
vendor/bin/simplysmart deploy shop --dry-run          # podgląd: konfiguracja, repozytoria, plan obu środowisk
vendor/bin/simplysmart deploy shop                    # przed-produkcja, potem pytanie „Wdrożyć też na produkcję?”
vendor/bin/simplysmart deploy shop --with-push        # najpierw git push paczek (także prywatnych) i projektu
vendor/bin/simplysmart deploy shop --no-production    # tylko przed-produkcja
vendor/bin/simplysmart deploy shop --production       # produkcja bez pytania (np. bez terminala)
vendor/bin/simplysmart deploy:onboarding shop         # jak deploy + konta z shop.onboarding.env w obu środowiskach
vendor/bin/simplysmart deploy:fresh shop --confirm=<DEPLOY_SSH>  # od zera: usuwa dane przed-produkcji i produkcji
```

`deploy <projekt>`:
1. Sprawdza, czy poprawki są wypchnięte w tym repozytorium i w paczkach z `packages/*/*`, także prywatnych. Niewypchnięte commity zatrzymują wdrożenie.
2. Buduje świeży `composer.deploy.json` / `composer.deploy.lock`, przypięty do commitów z repozytoriów zdalnych.
3. Aktualizuje przed-produkcję tym samym skryptem serwerowym co produkcję: `git pull`, composer z manifestu bez narzędzi deweloperskich, migracje, role, słowniki, cache. Jej `.env` zostaje bez zmian.
4. Pyta, czy wdrożyć też produkcję. Bez terminala odpowiedź to „nie”. Po potwierdzeniu wysyła ten sam manifest na serwer i uruchamia tam skrypt przez SSH.

Błąd przed-produkcji zatrzymuje wdrożenie. Na serwerze musi istnieć repozytorium projektu (`git clone`) z `.env.production`
(kopiowany jako `.env`). Szczegóły: `docs/narzedzia.md` i `docs/wdrozenie.md` w `simplysmart/admiro`.
