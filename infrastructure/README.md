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
