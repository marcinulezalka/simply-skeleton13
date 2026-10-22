# Skeleton13

Utworzono: 2026-10-19 przez [Smartboard](https://smartboard.local).

## Struktura

- `public/` — document root (punkt wejścia: `public/index.php`)
- `infrastructure/` — lokalne środowisko (vhost, PHP-FPM, SSL), poza repozytorium; zob. [infrastructure/README.md](infrastructure/README.md)
- `packages/<vendor>/<pakiet>/` — lokalne pakiety (osobne repozytoria Git, ignorowane przez to repozytorium), podpięte przez repozytorium Composera typu `path`
- `packages/simplysmart/admiro/` — strefa administratorów `simplysmart/admiro` (adres `/admiro`, `ADMIRO_SLUG`); pierwsze konto: `php artisan admiro:account:create <email> "<imię i nazwisko>"`, PIN administrator ustawia przy pierwszym logowaniu
- `packages/simplysmart/tools/` — przybornik deweloperski `simplysmart/tools` (`require-dev`): `vendor/bin/version-release`, wspólne wydawanie wersji projektu i pakietów
- `CLAUDE.md` (i `AGENTS.md`, który do niego odsyła) — instrukcje dla agentów AI; blok `<laravel-boost-guidelines>` generuje Laravel Boost, a każdy pakiet ma własny `CLAUDE.md`
- `.mcp.json`, `boost.json` — serwer MCP i konfiguracja [Laravel Boost](https://laravel.com/docs/boost) (`php artisan boost:update` odświeża wytyczne i skille). Skille w `.claude/skills/` są generowane: wspólne (`solid-dry`, `docs-after-tests`, `changelog-before-commit`) pochodzą z `simplysmart/tools` (`resources/boost/skills/`), pozostałe z samego Boosta; zasady podziału opisuje `CLAUDE.md` (Skills Ownership)

## Środowisko

| Plik | Środowisko | W repozytorium |
| --- | --- | --- |
| `.env.local` | lokalne — ładowany zamiast `.env`, gdy istnieje (`bootstrap/app.php`) | nie |
| `.env` | produkcja — tworzony na serwerze na podstawie `.env.example` | nie |
| `.env.example` | szablon zmiennych | tak |

Adres aplikacji: lokalnie `https://skeleton13.local`, na produkcji `https://skeleton13.simplysmart.pl`. Logi trafiają do plików dziennych (`storage/logs/laravel-RRRR-MM-DD.log`, przechowywane `LOG_DAILY_DAYS` dni). Poczta wychodzi ze skrzynki `noreply@simplysmart.pl` (SMTP `smtp.dpoczta.pl:587`, wymagany STARTTLS); hasło `MAIL_PASSWORD` uzupełnij ręcznie. Maile są logowane osobno w `storage/logs/email-RRRR-MM-DD.log` (kanał `mail`, `MAIL_LOG_CHANNEL`): przy `MAIL_MAILER=log` cała treść wiadomości, przy SMTP wpis o wysyłce (odbiorcy, temat, Message-ID) — dostarcza to Core `simplysmart/mainboard` (`config/mainboard.php`, listener `LogSentMail`), bez zmian w plikach `config/` szkieletu.

### Baza danych

MySQL (lokalnie 8.4; na dhosting.pl MariaDB). Baza i użytkownik mają nazwę `x2k7wk_skeleton13`, kodowanie `utf8mb4_unicode_ci`. Tworzenie (idempotentne; puste `DB_PASSWORD` zostanie wygenerowane i zapisane w pliku środowiska):

```bash
database/create-database.sh            # domyślnie .env.local, administrator: sudo mysql
php artisan migrate
php artisan mainboard:localization:seed  # słowniki: waluty, języki, kraje, stawki VAT (można powtarzać)
php artisan mainboard:organizations:seed # organizacja operatora: zakres ról stref bez firm klientów (można powtarzać)
```

Tabele danych mają historię zmian zapisywaną triggerami MySQL (`<tabela>_history`), więc użytkownik bazy potrzebuje uprawnienia `TRIGGER`. Schemat, modele i słowniki opisuje `packages/simplysmart/mainboard/docs/baza-danych.md`. Języki interfejsu wybiera administrator w słowniku języków, ale tylko spośród `APP_LOCALES`.

## Paczki prywatne

Część paczek w `packages/` może mieć prywatne repozytoria. Publiczne pliki projektu ich nie wymieniają — lista jest w lokalnym `private-packages.json` (poza repozytorium):

```json
{
    "require": {
        "<vendor>/<paczka>": "@dev"
    }
}
```

```bash
composer private:install   # buduje composer.private.json/.lock i instaluje paczki prywatne
```

Zwykłe `composer install` / `update` / `require` / `remove` usuwa paczki prywatne z `vendor/` — uruchom potem ponownie `composer private:install`. Bez `private-packages.json` komenda nic nie robi. Testy integracji paczek prywatnych (`packages/*/*/tests/Integration`) uruchamia suite `Packages`; bez paczki są pomijane. Zasady: `CLAUDE.md`, sekcja „Private Packages”.

## Wymagania

- PHP ^8.4

## Standardy kodu

PSR-4 (autoload: jedna klasa na plik, namespace odpowiada katalogowi) i styl PER Coding Style (następca PSR-12) pilnowany przez Laravel Pint z presetem `per` (`pint.json`). Pint wymaga też `declare(strict_types=1);` w każdym pliku PHP oraz nagłówka PHPDoc z prawami autorskimi i licencją (`header_comment`): w pakietach w każdym pliku PHP, w szkielecie tylko w `tests/` (`pint.header.json`), bo stockowe pliki Laravela zostają bez zmian. Analiza statyczna: PHPStan z Larastanem na poziomie `max` (`phpstan.neon`).

```bash
composer lint      # sprawdzenie stylu i strict types
composer format    # poprawienie stylu i dodanie strict types
composer analyse   # PHPStan (Larastan)
composer test      # PHPUnit
```

Te same komendy `lint`, `format` i `analyse` działają w każdym pakiecie z `packages/`.

### Intelephense (VS Code)

Edytorem PHP jest VS Code z rozszerzeniem [Intelephense](https://intelephense.com) (`bmewburn.vscode-intelephense-client`). Zmienione pliki PHP — w szkielecie i w pakietach, w `src/` i w `tests/` — nie mogą mieć żadnych jego zgłoszeń (błędów, ostrzeżeń ani podpowiedzi), tak samo jak nie mogą mieć błędów PHPStana.

Usuwaj przyczynę, nie zgłoszenie. Nie używaj `@intelephense-ignore` i nie wyłączaj diagnostyki w ustawieniach.

| Zgłoszenie | Typowa przyczyna | Poprawka |
| --- | --- | --- |
| `Undefined method 'shouldReceive'` (P1013) | mock Mockery bez typu | `/** @var LoggerInterface&MockInterface $logger */` nad `Mockery::mock(...)` |
| `Undefined method 'setAttribute'`, `'saving'`… (P1013) | trait wywołuje metody modelu Eloquent | tagi `@method` w PHPDoc traitu (Intelephense ignoruje `@mixin` w traitach) |
| `Undefined method 'query'` (P1013) | klasa modelu jako `string`, np. z configu | `/** @var class-string<Model> $model */` po sprawdzeniu typu |
| `Parameter $app has no type information` (P1132) | parametr bez typu | prawdziwy typ, np. `Application $app` |
| `Symbol '$x' is declared but not used` (P1003) | nieużywany parametr, zmienna lub import | usuń go |

Panel **Problems** pokazuje stan indeksu Intelephense, a ten bywa nieaktualny w obie strony:

- pliku, którego Intelephense jeszcze nie przeanalizował (zwykle nieotwartego), panel nie pokazuje, choć może on mieć zgłoszenia;
- po zmianach zrobionych poza edytorem (Git, Composer, generatory, agent AI) indeks może nie znać nowych klas i zgłaszać fałszywe `Undefined type` (P1009), choć klasa istnieje i PHPStan jej nie kwestionuje.

Gdy zgłoszenie wygląda na fałszywe, przebuduj indeks: **Ctrl+Shift+P → „Intelephense: Index workspace”**. Jeśli to nie pomoże, użyj **„Developer: Reload Window”**. Dopiero zgłoszenie, które zostaje po przebudowie indeksu, wymaga zmiany w kodzie.

## Licencja

Simplysmart Restricted Source License 1.0 — © 2026 Marcin Ulezalka. Do celów niekomercyjnych
kod możesz bezpłatnie przeglądać, kopiować, uruchamiać i modyfikować. Użycie komercyjne (także
w projektach dla klientów) jest dozwolone po wpisaniu deklaracji do pliku `THIRD_PARTY.md` w
Twoim projekcie — wzór wpisu jest w [THIRD_PARTY.md](THIRD_PARTY.md). Sprzedaż,
sublicencjonowanie i komercyjna redystrybucja kodu jako produktu wymagają pisemnej zgody autora.
Kopie muszą zachować informację o prawach autorskich i treść licencji. Wiążąca jest angielska
treść pliku [LICENSE](LICENSE).
