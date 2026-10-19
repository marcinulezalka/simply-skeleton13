# Changelog

Format oparty na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/).

## [Unreleased]

### Added

- Lokalne pakiety w `packages/<vendor>/<pakiet>/` podpinane przez repozytorium Composera typu `path` (`packages/*/*`, symlink); pierwszy pakiet: `simplysmart/mainboard`.
- Wydawanie wersji przez pakiet deweloperski `simplysmart/tools` (`require-dev`, lokalnie w `packages/simplysmart/tools`): `vendor/bin/version-release` wydaje projekt główny, a z opcją `--root=<katalog>` dowolny pakiet.

- Licencja MIT: plik `LICENSE`.
- Standardy kodu: PSR-4 i PER Coding Style (Laravel Pint z presetem `per`, `composer lint` / `composer format`).

- Plik środowiska lokalnego `.env.local`, ładowany zamiast `.env`, gdy istnieje; `.env` służy produkcji, a w repozytorium jest tylko `.env.example`.
- `infrastructure/README.md`: domena lokalna, struktura i aktywacja vhosta, PHP-FPM i SSL.

### Changed

- `.gitignore` uporządkowany w sekcje; wyklucza zawartość `infrastructure/` (w tym klucz prywatny SSL), wszystkie pliki `.env*` poza `.env.example`, lokalne pliki sesji AI oraz konfiguracje edytorów i IDE.
- Nazwa paczki `simplysmart/skeleton13` (zamiast `laravel/laravel`) i autor w `composer.json`.
- Wymagane PHP ^8.4 (wcześniej ^8.3).
- Kod sformatowany zgodnie z PER Coding Style.
- Plik `USAGE.md` usunięty.
- Skrypt `scripts/version-release.php` zastąpiony nowszą implementacją z `simplysmart/tools`: atomowy zapis plików, zachowanie końców linii CRLF, walidacja pliku `VERSION`, sekcji i opcji, strefa czasowa `RELEASE_TIMEZONE`, `### Deprecated` podbija MINOR.

## [0.1.0] - 2026-10-19

### Added

- Initial scaffold wygenerowany przez Smartboard (2026-10-19).
