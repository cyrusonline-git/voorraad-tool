# Voorraad tool — Architectuurregels (verplicht volgen)

Child-app van **Boels CORE** (https://databasehub.sorai.nl). Laravel 12, eigen SQLite-database.

- **Slug in CORE:** `voorraad` · **URL:** https://voorraad.sorai.nl · **Repo:** cyrusonline-git/voorraad-tool
- **Rollen** (beheerd in CORE, Beheer → Applicaties → Voorraad tool): binnendienst, werkplaats, manager, fleet, admin. `config/core.php` bepaalt welke slugs de app kent.
- **Login:** géén eigen login. Middleware `core` (`app/Http/Middleware/CoreAuth.php`) stuurt de CORE-sessiecookie van de bezoeker door naar `GET /api/me` en `GET /api/access/voorraad` (`app/Services/CoreSso.php`), 5 min gecachet in de eigen sessie. Geen cookie/geen rol → redirect naar CORE-login of `/geen-toegang`. Meerdere rollen → rolkeuzescherm; `rol:admin,...`-middleware bewaakt pagina's op de ACTIEVE rol.
- **Depots/areas** komen uit `GET /api/infrastructure` (`app/Services/DepotSync.php`), tabel `depots`; per depot lokaal: `depot_nummer` (materieel-Excel kolom J) en `email` (aanvraagmail). Nooit depots met de hand aanmaken.
- **Data:** alleen eigen tabellen in de eigen SQLite (`database/database.sqlite`, buiten git en buiten de deploy). Geen CORE-tabellen aanraken.
- **Deploy:** push naar `main` → GitHub Action bouwt `laravel_app.zip` + `public_html.zip` als release → `https://voorraad.sorai.nl/__pull_deploy.php?k=<DEPLOY_SECRET>` (draait ook `migrate --force`). Eerste installatie gebeurt vanuit CORE: `https://databasehub.sorai.nl/__deploy-child.php?k=<DEPLOY_SECRET>&app=voorraad`. Log: `__log.php?k=...`, migrate los: `__migrate.php?k=...`. Geen FTP.
- **Lokaal testen:** `.env` met `APP_ENV=local` en `CORE_DEV_FAKE_USER=true` geeft een nep-gebruiker met alle rollen (werkt alleen in local).
- **Blade-valkuil:** nooit `@json()` met komma's in de uitdrukking (splitst op komma's → 500). Layout-wijzigingen zijn hoog risico: eerst lokaal renderen.
- **Huisstijl:** Boels-oranje `#FF6600`, witte B op oranje als favicon/logo, Bootstrap 5 via CDN (geen npm/vite-build nodig).
