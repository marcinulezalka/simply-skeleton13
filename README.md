# Skeleton13

Utworzono: 2026-10-19 przez [Smartboard](https://smartboard.local).

## Struktura

- `public/` — document root (punkt wejścia: `public/index.php`)
- `infrastructure/` — lokalne środowisko (vhost, PHP-FPM, SSL), poza repozytorium; zob. [infrastructure/README.md](infrastructure/README.md)
- `packages/<vendor>/<pakiet>/` — lokalne pakiety (osobne repozytoria Git, ignorowane przez to repozytorium), podpięte przez repozytorium Composera typu `path`
- `packages/simplysmart/tools/` — przybornik deweloperski `simplysmart/tools` (`require-dev`): `vendor/bin/version-release`, wspólne wydawanie wersji projektu i pakietów

## Środowisko

| Plik | Środowisko | W repozytorium |
| --- | --- | --- |
| `.env.local` | lokalne — ładowany zamiast `.env`, gdy istnieje (`bootstrap/app.php`) | nie |
| `.env` | produkcja — tworzony na serwerze na podstawie `.env.example` | nie |
| `.env.example` | szablon zmiennych | tak |

## Wymagania

- PHP ^8.4

## Standardy kodu

PSR-4 (autoload: jedna klasa na plik, namespace odpowiada katalogowi) i styl PER Coding Style (następca PSR-12) pilnowany przez Laravel Pint z presetem `per` (`pint.json`).

```bash
composer lint     # sprawdzenie stylu
composer format   # poprawienie stylu
```

## Licencja

[MIT](LICENSE) — © 2026 Marcin Ulezalka.
