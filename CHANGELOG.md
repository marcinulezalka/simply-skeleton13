# Changelog

Format oparty na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/).

## [Unreleased]

### Added

- Lokalne pakiety w `packages/<vendor>/<pakiet>/` podpinane przez repozytorium Composera typu `path` (`packages/*/*`, symlink); pierwszy pakiet: `simplysmart/mainboard`.
- Wydawanie wersji przez pakiet deweloperski `simplysmart/tools` (`require-dev`, lokalnie w `packages/simplysmart/tools`): `vendor/bin/version-release` wydaje projekt główny, a z opcją `--root=<katalog>` dowolny pakiet.

- Licencja Simplysmart Restricted Source License 1.0: pliki `LICENSE` i `THIRD_PARTY.md`, w `composer.json` `"license": "proprietary"`; użycie niekomercyjne bez ograniczeń, komercyjne po deklaracji w pliku `THIRD_PARTY.md` projektu (wzór wpisu w `THIRD_PARTY.md`), sprzedaż i komercyjna redystrybucja kodu jako produktu za pisemną zgodą autora.
- Standardy kodu: PSR-4 i PER Coding Style (Laravel Pint z presetem `per`, `composer lint` / `composer format`).

- Plik środowiska lokalnego `.env.local`, ładowany zamiast `.env`, gdy istnieje; `.env` służy produkcji, a w repozytorium jest tylko `.env.example`.
- `infrastructure/README.md`: domena lokalna, struktura i aktywacja vhosta, PHP-FPM i SSL.
- Laravel Boost (`require-dev`): serwer MCP dla Claude Code (`.mcp.json`), wytyczne w `CLAUDE.md` i skille w `.claude/skills/` (`boost.json`, bez Laravel Cloud); `AGENTS.md` odsyła do `CLAUDE.md`.
- Wspólne skille AI (`solid-dry`, `docs-after-tests`, `changelog-before-commit`) instalowane przez Boost z `simplysmart/tools`; zastępują lokalne `solid-dry`, `phpdoc-after-tests` i `docs-before-commit`. Zasady własności skilli w `CLAUDE.md`.
- Zasady granic pakietów w `CLAUDE.md`: kontrakty w `src/Contracts/` pakietu, zasoby ładowane przez Service Provider pakietu, wspólne bramki jakości.
- Analiza statyczna PHPStan z Larastanem na poziomie `max` (`phpstan.neon`, `composer analyse`).
- Test integracji z pakietami: provider `simplysmart/mainboard` jest wykrywany i ładowany.
- Skrypt `database/create-database.sh`: tworzy bazę MySQL/MariaDB `x2k7wk_skeleton13` i jej użytkownika (utf8mb4_unicode_ci), generuje hasło, gdy brak.
- Poczta przez skrzynkę `noreply@simplysmart.pl` (SMTP `smtp.dpoczta.pl:587`); `MAIL_REQUIRE_TLS` wymusza STARTTLS.
- Osobny dziennik maili `storage/logs/email-RRRR-MM-DD.log` (kanał `mail`): mailer `log` zapisuje tam treść wiadomości, a listener `LogSentMail` odnotowuje każdą wysyłkę przez SMTP (odbiorcy, temat, Message-ID).

### Changed

- Domyślny język aplikacji `pl`, także jako język zapasowy (`APP_FALLBACK_LOCALE`), i dane Fakera `pl_PL` — w `.env.example` (`config/app.php` bez zmian).
- `.gitignore` uporządkowany w sekcje; wyklucza zawartość `infrastructure/` (w tym klucz prywatny SSL), wszystkie pliki `.env*` poza `.env.example`, lokalne pliki sesji AI oraz konfiguracje edytorów i IDE.
- Nazwa paczki `simplysmart/skeleton13` (zamiast `laravel/laravel`) i autor w `composer.json`.
- Wymagane PHP ^8.4 (wcześniej ^8.3).
- Kod sformatowany zgodnie z PER Coding Style.
- `declare(strict_types=1);` we wszystkich plikach PHP, wymuszane przez Pint (`declare_strict_types` w `pint.json`).
- Plik `USAGE.md` usunięty.
- Dziennik poczty, wymuszony STARTTLS i listener `LogSentMail` przeniesione do Core `simplysmart/mainboard`; `config/app.php`, `config/logging.php` i `config/mail.php` wróciły do wersji z Laravela (poza `declare(strict_types=1);`).
- Zasady w `CLAUDE.md`: migracje i seedery wszystkich tabel (Core i stref) w `simplysmart/mainboard`, a nie w paczce-właścicielu; build assetów paczek przez npm workspaces i orkiestrator z `simplysmart/tools` jedną ogólną zmianą `package.json` szkieletu (koncepcja, `vite.config.js` bez zmian).
- Środowisko: nazwa aplikacji `Simplysmart`, baza MySQL zamiast SQLite, logi w plikach dziennych (`LOG_STACK=daily`, 14 dni).
- Skrypt `scripts/version-release.php` zastąpiony nowszą implementacją z `simplysmart/tools`: atomowy zapis plików, zachowanie końców linii CRLF, walidacja pliku `VERSION`, sekcji i opcji, strefa czasowa `RELEASE_TIMEZONE`, `### Deprecated` podbija MINOR.

## [0.1.0] - 2026-10-19

### Added

- Initial scaffold wygenerowany przez Smartboard (2026-10-19).
