# WP Umbrella → Anyboard Dashboard

GitHub Actions holt alle 5 Minuten die Daten von der WP Umbrella API und
veröffentlicht sie via GitHub Pages. Anyboard auf dem Apple TV liest die
JSON-Dateien direkt von dort.

## Setup

### 1. Repository erstellen

Neues GitHub Repo anlegen (kann public oder private sein — GitHub Pages
funktioniert bei beiden, bei private brauchst du GitHub Pro/Team).

### 2. Diese Dateien hochladen

```
.github/workflows/update-dashboard.yml   ← Pipeline
scripts/generate-data.php                ← PHP-Script
docs/anyboard-dashboard.json             ← Anyboard-Konfiguration
docs/wp-umbrella-data.json               ← Datei (Platzhalter, wird überschrieben)
README.md
```

### 3. GitHub Secret setzen

Repository → Settings → Secrets and variables → Actions → New repository secret

| Name | Wert |
|------|------|
| `WP_UMBRELLA_API_KEY` | Dein WP Umbrella Developer API Key |

Den Key findest du in WP Umbrella unter: Profil → Developer API Key

### 4. GitHub Pages aktivieren

Repository → Settings → Pages

- Source: **Deploy from a branch**
- Branch: `main` / Ordner: `/docs`
- Speichern

Nach ein paar Minuten ist die Seite unter folgendem URL erreichbar:
```
https://DEIN-USERNAME.github.io/DEIN-REPO/
```

### 5. URLs in anyboard-dashboard.json anpassen

In `docs/anyboard-dashboard.json` alle Vorkommen von:
```
https://DEIN-USERNAME.github.io/DEIN-REPO/wp-umbrella-data.json
```
durch deine echte GitHub Pages URL ersetzen und pushen.

### 6. Anyboard konfigurieren

Auf dem Apple TV in Anyboard die URL zur Konfigurationsdatei eintragen:
```
https://DEIN-USERNAME.github.io/DEIN-REPO/anyboard-dashboard.json
```

## Wie es funktioniert

```
GitHub Actions (alle 5 min)
        │
        ▼
scripts/generate-data.php
        │  holt Daten von WP Umbrella API
        ▼
docs/wp-umbrella-data.json   (wird committed & gepusht)
        │
        ▼  (GitHub Pages)
https://USERNAME.github.io/REPO/wp-umbrella-data.json
        │
        ▼
Anyboard auf Apple TV  (liest alle 5 min)
```

## Daten-Format

`wp-umbrella-data.json` enthält:

```json
{
  "summary": {
    "total": 12,
    "online": 11,
    "offline": 1,
    "updates": 7,
    "php_errors": 3
  },
  "sites": [
    {
      "name": "Kundensite XY",
      "url": "xy.ch",
      "status": "Online",
      "wp_version": "6.5.2",
      "php_version": "8.2",
      "updates_count": 3,
      "php_errors": 0
    }
  ],
  "generated_at": "2026-03-14T10:00:00+01:00"
}
```
