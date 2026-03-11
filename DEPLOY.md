# Wdrożenie nipchecker (bez ngrok)

## Opcja 1: GitHub + Hostinger (zalecane)

1. **Utwórz repozytorium** na GitHub (np. `sztukamarketingu/nipchecker`).

2. **Wypchnij kod:**
   ```bash
   cd /Users/tomaszszwecki/n8n/Vibecoding/Bitrix24
   git init
   git add .
   git commit -m "Initial"
   git remote add origin https://github.com/TWOJ_USER/nipchecker.git
   git push -u origin main
   ```

3. **Wdróż przez Hostinger MCP** – podaj adres repo, np. `https://github.com/TWOJ_USER/nipchecker`.

## Opcja 2: Build lokalny + push obrazu

```bash
docker build -t ghcr.io/sztukamarketingu/nipchecker:latest .
docker push ghcr.io/sztukamarketingu/nipchecker:latest
```

Potem w `docker-compose.yml` zamień `build: .` na `image: ghcr.io/sztukamarketingu/nipchecker:latest`.
