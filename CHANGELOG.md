# Changelog

Format oparty na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/).

## [Unreleased]

### Added

- Wdrożenie od zera: `vendor/bin/simplysmart deploy:fresh --confirm=<DEPLOY_SSH>` (`migrate:fresh` na serwerze — usuwa wszystkie dane — seedery i konta z `deploy.onboarding.env`; `--dry-run` pokazuje plan z ostrzeżeniem).
- Zmienna `MAINBOARD_ORGANIZATION_BRAND_DISK` w `.env.example` (dysk plików marki organizacji, logo w e-mailach stref).
- Wdrożenie jednym poleceniem z narzędzi deweloperskich `simplysmart/admiro`: `vendor/bin/simplysmart deploy` (sprawdza wypchnięcie repozytoriów, manifest wdrożeniowy, wysyłka plików, skrypt serwerowy przez SSH: Composer, `.env`, `git pull`, token GitHub, instalacja `--no-dev`, `APP_KEY`, migracje, role, słowniki, cache) i `vendor/bin/simplysmart deploy:onboarding` (wdrożenie plus konta pierwszej instalacji); wzory konfiguracji `infrastructure/deploy.env.example` i `infrastructure/deploy.onboarding.env.example`.
- Wdrożenie bez katalogu `packages/` na serwerze: skrypt `composer deploy:lock` (manifest i lock z paczkami z repozytoriów git), pliki `composer.deploy.json` i `composer.deploy.lock` w `.gitignore`.

### Changed

- Narzędzia deweloperskie (`vendor/bin/simplysmart`, `version-release`, `composer-private`) i wspólne skille AI pochodzą z `simplysmart/admiro`; `simplysmart/tools` usunięty z `require-dev`, `boost.json` wskazuje `simplysmart/admiro`.

### Fixed

- `composer private:install` i `composer deploy:lock` wywołują narzędzie przez proxy `vendor/bin/composer-private`; bezpośrednia ścieżka `vendor/simplysmart/tools/bin/composer-private` nie znajdowała autoloadera w projekcie, w którym `simplysmart/tools` nie ma własnego `vendor/`.

## [0.2.0] - 2026-10-24

### Added

- Test tekstów ekranów logowania stref (`SignInTextsTest`).
- `.env.example`: `HOLIDAYS_SCHEDULE`; testy dni wolnych w Core i panelu administratorów (`HolidaysTest`, `AdmiroHolidaysTest`).
- `.env.example`: `COOKIE_CONSENT`; testy zgody na cookies w Core i polityki cookies w panelu administratorów (`CookieConsentTest`, `AdmiroCookiePolicyTest`).
- `.env.example`: kursy walut z NBP (`EXCHANGE_RATES_SCHEDULE_AT`, `EXCHANGE_RATES_DAYS_BACK`); testy kursów walut w Core i panelu administratorów (`ExchangeRatesTest`, `AdmiroExchangeRatesTest`).
- Test podpisu odręcznego z Core (`AppLayoutTest`).
- Testy menu i widoków strefy administratorów (`Admiro/AdmiroNavigationTest`) i dodatków wysokiego kontrastu (`DesignSystemPageTest`).
- Testy przełącznika czterech motywów i motywów kontrastu WCAG, braku przełącznika kolorystyki w layoutach, połączonych kart logowania z banerem i tła z gradientem, strony treści w design systemie oraz czytelnego błędu triggerów historii przy binlogu (`AppLayoutTest`, `Zones/AuthLayoutTest`, `DesignSystemPageTest`, `Database/HistoryTest`).
- Testy filtra i nazw zdarzeń dziennika, stronicowania i pełnej wartości maski (`Zones/AuditLogTest`, `AppLayoutTest`); asercje historii konta na `audit.events.*`.
- Test strony design systemu na wspólnych tekstach kroków (`layout.steps.*`).
- Testy konta strefy: profil (imię, język, organizacje z rolami), język konta po logowaniu i strona bezpieczeństwa (`Zones/AccountProfileTest`, `Zones/AccountLocaleTest`, `Zones/AccountSecurityTest`); zaktualizowane adresy PIN-u i urządzeń.
- Testy kolorystyk stref i ikon strony per strefa (`AppLayoutTest`, `DesignSystemPageTest`, `Zones/ZoneLayoutTest`).
- Testy przełączania motywu i tokenów `light-dark()` (`AppLayoutTest`, `DesignSystemPageTest`).
- Testy podpowiedzi w polach (pole e-mail logowania, maski).
- Testy arkuszy stref (`Zones/ZoneLayoutTest`).
- Testy banera logowania strefy (`Zones/AuthLayoutTest`).
- Testy layoutu stref na layoucie aplikacji (`Zones/ZoneLayoutTest`, menu testowe `Tests\Fixtures\Zones\ShopNavigation`); zaktualizowane asercje przełącznika organizacji i słowników admiro.
- Testy strony „Nie pamiętam PIN-u”, linku z ekranu PIN-u i komunikatu po wylogowaniu (`Zones/SignInFlowTest`).
- Design system z Core pod `/design-system` (tylko poza produkcją, `DESIGN_SYSTEM_PAGE`), nowe strony logowania (układ `AUTH_LAYOUT=centered|split`), strony błędów HTTP, layout aplikacji z menu bocznym, toastami i powiadomieniami, profil konta z kolorem awatara (`account/profile` w każdej strefie); testy `AppLayoutTest`, `DesignSystemPageTest`, `DesignSystemCatalogTest`, `Zones/AuthLayoutTest`, `Zones/AvatarColorTest`, `Zones/PinLengthTest`.
- Słowniki i stawki VAT w panelu administratora (z Core i `simplysmart/admiro`), wspólna baza testów panelu `tests/Feature/Admiro/AdmiroTestCase.php`, testy `AdmiroDictionariesTest.php`; po aktualizacji `php artisan migrate` i `php artisan mainboard:permissions:sync`.
- Dziennik zdarzeń i ustawienia systemu z Core i `simplysmart/admiro`: po aktualizacji `php artisan migrate` (tabela `audit_events`), zmienna `AUDIT_RETENTION_DAYS` w `.env.example`, harmonogram (`schedule:run` z crona) czyści dziennik codziennie; testy `tests/Feature/Zones/AuditLogTest.php` i `tests/Feature/Admiro/AdmiroAuditAndSettingsTest.php`.
- Organizacje w panelu administratora i przełącznik organizacji w strefach z organizacjami klientów (z Core); testy `tests/Feature/Admiro/AdmiroOrganizationsTest.php` i przełącznika w `CurrentOrganizationTest`.
- Panel administratora z `simplysmart/admiro`: pierwszy administrator `php artisan admiro:account:create <email> <imię i nazwisko> --role=hero` (po `mainboard:organizations:seed` i `mainboard:permissions:sync`); testy `tests/Feature/Admiro/AdmiroPanelTest.php`.
- Maskowanie błędów, blokada agentów AI i pułapki na boty z Core: nowe zmienne w `.env.example` (`SECURITY_MASK_ERRORS`, `HONEYPOT_*`, a także `AUTH_TRUSTED_DEVICES`, `AUTH_TRUSTED_DEVICE_DAYS`, `ADMIRO_TRUSTED_DEVICES`); testy `tests/Feature/Zones/HoneypotTest.php` i `ErrorMaskingTest.php`.
- Zaufane urządzenia, zmiana PIN-u i akcje administracyjne na kontach z Core: po aktualizacji `php artisan migrate` (tabela urządzeń strefy `admiro`, `organization_members`); testy `tests/Feature/Zones/{TrustedDevice,PinChange,AccountAdministration}Test.php`.
- Role stref z plików JSON i uprawnienia w żądaniu z Core: `php artisan mainboard:permissions:sync` przy wdrożeniu (po `mainboard:organizations:seed`); strefa testowa `alpha` z organizacjami klientów (`teams`), `beta` bez zaufanych urządzeń; testy `tests/Feature/Permissions/` (pliki ról, synchronizacja, organizacja żądania, trasy z uprawnieniem, nadawanie ról bez eskalacji, właściciele) i `tests/Feature/Admiro/AdmiroPermissionsTest.php`.
- Organizacje i uprawnienia stref z Core (`spatie/laravel-permission`, bez publikowania `config/permission.php`): `php artisan mainboard:organizations:seed` po migracji zakłada organizację operatora; testy `tests/Feature/Permissions/` (schemat, historia, etykiety, izolacja stref i organizacji, odmowy, seeder).
- Konta stref i tabela `users` wyrównane do kolumn globalnych z Core (kolumny standardowe, historia `*_history`, id od 1000); testy `tests/Feature/Zones/ZoneAccountTablesTest.php`, `tests/Feature/Database/UsersTableTest.php` i odrzucenie adresu usuniętego konta w `CreateAdmiroAccountTest`.
- Strona startowa `/` z Core (`simplysmart/mainboard`): logo i opis simplysmart zamiast stockowej strony Laravela; stockowy `resources/views/welcome.blade.php` usunięty (plik o tej nazwie w aplikacji nadal nadpisuje stronę z Core); test `tests/Feature/WelcomePageTest.php`.
- Paczki prywatne: lokalna lista `private-packages.json` i `composer private:install` (narzędzie `composer-private` z `simplysmart/tools`), pliki `composer.private.*` poza repozytorium, suite PHPUnit `Packages` dla testów integracji paczek (`packages/*/*/tests/Integration`), zasady „Private Packages” w `CLAUDE.md`.
- Słowniki lokalizacji z Core: `php artisan mainboard:localization:seed` (waluty, języki, kraje, stawki VAT), historia zmian tabel na triggerach MySQL (wymaga uprawnienia `TRIGGER`), zmienne `APP_COUNTRY` i `APP_DEFAULT_VAT` w `.env.example`; testy fundamentu bazy i słowników w `tests/Feature/Database/` i `tests/Feature/Localization/`.
- Strefa administratorów: paczka `simplysmart/admiro` (adres `/admiro`, zmienna `ADMIRO_SLUG` w `.env.example`), konta zakładane komendą `php artisan admiro:account:create <email> <imię i nazwisko>`; testy integracji w `tests/Feature/Admiro/`, a testy stref uruchamiają migracje Core (`RefreshDatabase` w `ZoneTestCase`).
- Testy logowania stref kodem i PIN-em (`tests/Feature/Zones/SignIn*`, `StrongPinTest`): pełny przepływ, decoy, najnowszy kod, kroki i ich czas, blokady stopniowe, limity niezależne od IP, rehash PIN-u, epoka i dezaktywacja, 419, czyszczenie kodów; konta testowe na `ZoneAccount` i tabelach z `ZoneTables`; zmienne `AUTH_CODE_TTL`, `AUTH_PIN_LENGTH`, `AUTH_LOG_CHANNEL` w `.env.example`.
- Testy integracji stref z Core (`tests/Feature/Zones/`, strefy testowe `alpha`, `beta`, `open` w `tests/Fixtures/Zones/`): rejestracja, izolacja sesji i guardów, nagłówki bezpieczeństwa, tryby języka, zaufane proxy, reguły kodu (guard zawsze nazwany, brak `Cache::flush()`); zmienne `APP_LOCALES`, `ZONE_SESSION_LIFETIME`, `TRUSTED_PROXIES`, `SECURITY_HSTS`, `SECURITY_CSP` w `.env.example`.
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

- Długość PIN-u jest zakresem: w `.env` zmienne `AUTH_PIN_MIN_LENGTH` (4) i `AUTH_PIN_MAX_LENGTH` (16) zastępują `AUTH_PIN_LENGTH`, a `ADMIRO_PIN_MIN_LENGTH` (8) zastępuje `ADMIRO_PIN_LENGTH` — po aktualizacji przenieś własne wartości do nowych zmiennych.
- Nagłówek PHPDoc z prawami autorskimi i licencją w każdym pliku testów, wymuszany przez Pint (`pint.header.json` tylko dla `tests/`, bo stockowe pliki Laravela zostają bez zmian); `composer lint` i `composer format` sprawdzają i dopisują go. Nowa bramka jakości w `CLAUDE.md`: brak ostrzeżeń Intelephense w zmienionych plikach PHP.
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
