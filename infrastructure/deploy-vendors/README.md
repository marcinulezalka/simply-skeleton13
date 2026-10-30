# Konfiguracje wdrażanych projektów

Jeden plik na projekt, poza repozytorium (`.gitignore`; w Git jest tylko ten plik):

- `<projekt>.env` — przed-produkcja (`PREPRODUCTION_PATH`, `PREPRODUCTION_PHP`, `PREPRODUCTION_COMPOSER`) i produkcja
  (`DEPLOY_SSH`, `DEPLOY_PATH`, `DEPLOY_PHP`, `REMOTE_COMPOSER`, `LOCAL_COMPOSER`, `GITHUB_TOKEN`);
  wzór `../deploy-vendor.env.example`;
- `<projekt>.onboarding.env` — konta pierwszej instalacji; wzór `../deploy.onboarding.env.example`.

Wdrożenie (w projekcie bazowym z paczkami w `packages/`):

```bash
vendor/bin/simplysmart deploy <projekt> --with-push   # przed-produkcja, potem pytanie o produkcję
```

Opis: `../README.md` (sekcja „Wdrożenie projektu”) i `docs/narzedzia.md` w `simplysmart/admiro`.
