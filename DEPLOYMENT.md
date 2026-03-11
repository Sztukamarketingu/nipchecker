# Bitrix Production Endpoints

Use the following values in Bitrix24 Local App settings:

- Handler path: `https://nip.aikuznia.cloud/index.php`
- Initial installation path: `https://nip.aikuznia.cloud/install.php`

## Versioning

Application version is managed in one place:

- File: `config.php`
- Constant: `APP_VERSION`

### How to release next version

1. Increase `APP_VERSION` in `config.php` (for example: `2026.03.06.2`).
2. Deploy updated files to server.
3. In Bitrix24 click `Reinstall` for the local app.

`index.php` and `install.php` automatically use `APP_VERSION` for cache busting and build URL.

## GUS BIR1.1 (fallback)

When MF (Biała Lista) returns no data (e.g. entity not on VAT list), the app can fall back to GUS BIR1.1.

Set on the server:

- `GUS_BIR1_KEY` – klucz użytkownika z GUS (z wniosku)
- `GUS_BIR1_USE_TEST` – `true` dla środowiska testowego (opcjonalne)

### Hostinger VPS + Docker (php:8.2-apache)

**Opcja A – plik `.env`**

Na serwerze w katalogu projektu (np. `/home/.../nipchecker/`):

```bash
# .env
GUS_BIR1_KEY=twoj_klucz_produkcyjny
GUS_BIR1_USE_TEST=false
```

W `docker-compose.yml` dodaj:

```yaml
services:
  nipchecker:
    env_file: .env
```

**Opcja B – bezpośrednio w `docker-compose.yml`**

```yaml
services:
  nipchecker:
    environment:
      - GUS_BIR1_KEY=twoj_klucz_produkcyjny
      - GUS_BIR1_USE_TEST=false
```

**Opcja C – bez Dockera (np. Apache + PHP)**

W `.htaccess` w katalogu aplikacji:

```apache
SetEnv GUS_BIR1_KEY twoj_klucz_produkcyjny
SetEnv GUS_BIR1_USE_TEST false
```

Po zmianie: `docker compose up -d` lub restart kontenera.
