# 🚀 GitHub Actions Workflows per Build e Release

Questo repository include un sistema automatico di build e release per il plugin WordPress tramite GitHub Actions.

## 📦 Workflows Disponibili

### 1. Build WordPress Plugin (`build-wordpress-plugin.yml`)

**Trigger:**
- Push su `main` o `develop`
- Push di tag `v*`
- Pull request su `main`
- Esecuzione manuale

**Funzionalità:**
- ✅ Estrazione automatica della versione dal plugin
- ✅ Creazione directory plugin strutturata per WordPress
- ✅ Copia di tutti i file necessari (PHP, CSS, JS, documentazione)
- ✅ Rimozione file di sviluppo (demo, test HTML, script)
- ✅ Generazione `readme.txt` per WordPress Repository
- ✅ Validazione struttura plugin
- ✅ Creazione artifact zip pronto per installazione
- ✅ Retention: 30 giorni per build versionate, 90 giorni per "latest"

**Artifact generati:**
- `fp-prenotazioni-ristorante-pro-v{version}.zip`
- `fp-prenotazioni-ristorante-pro-latest.zip`

### 2. Test Build (`test-build.yml`)

**Trigger:**
- Esecuzione manuale

**Funzionalità:**
- ✅ Test rapido della struttura del repository
- ✅ Validazione file richiesti
- ✅ Conteggio file per verifica

### 3. Release (`release.yml`)

**Trigger:**
- Push di tag che iniziano con `v` (es: `v1.6`, `v2.0`)

**Funzionalità:**
- ✅ Creazione release GitHub automatica
- ✅ Upload del plugin zip come asset
- ✅ Descrizione release con istruzioni di installazione
- ✅ Metadata completi per WordPress

## 🛠️ Come Usare i Workflows

### Per Build di Sviluppo

1. **Push automatico**: Ogni push su `main` genera automaticamente un build
2. **Build manuale**: Vai su "Actions" → "Build WordPress Plugin" → "Run workflow"
3. **Download**: Vai su "Actions" → seleziona il run → scarica l'artifact

### Per Release Ufficiali

1. **Aggiorna la versione** nel file `fp-prenotazioni-ristorante-pro.php`:
   ```php
   * Version:     1.6  // Aggiorna questo numero
   ```

2. **Crea e push del tag**:
   ```bash
   git tag -a v1.6 -m "Release version 1.6"
   git push origin v1.6
   ```

3. **Release automatica**: GitHub Actions creerà automaticamente:
   - Una release su GitHub
   - Il file zip pronto per WordPress
   - Documentazione di installazione

### Per Download Diretto

**Artifact da Build:**
- Vai su [Actions](../../actions/workflows/build-wordpress-plugin.yml)
- Seleziona l'ultimo run successful
- Scarica `fp-prenotazioni-ristorante-pro-latest`

**Release Ufficiali:**
- Vai su [Releases](../../releases)
- Scarica il file `.zip` dall'ultima release

## 📁 Struttura del Plugin Generato

```
fp-prenotazioni-ristorante-pro-v1.6/
├── fp-prenotazioni-ristorante-pro.php    # File principale
├── readme.txt                            # WordPress repository format
├── README.md                             # Documentazione completa
├── includes/                             # Moduli PHP core
│   ├── admin.php
│   ├── frontend.php
│   ├── booking-handler.php
│   └── ...
├── assets/                               # CSS e JavaScript
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
├── tests/                                # Test suite
└── *.md                                  # Documentazione tecnica
```

## 🔧 File Esclusi dal Build

Per mantenere il plugin pulito, questi file vengono automaticamente esclusi:

- ❌ `demo*.html` - File demo
- ❌ `test-*.html` - File di test HTML
- ❌ `*.sh` - Script shell
- ❌ `*.png` - Immagini di documentazione
- ❌ `.git*` - File Git
- ❌ File HTML di sviluppo

## 📊 Monitoraggio Build

### Status Badge
Aggiungi questo badge al README per mostrare lo status del build:

```markdown
![Build Status](../../actions/workflows/build-wordpress-plugin.yml/badge.svg)
```

### Notifiche
- ✅ Build success: Artifact disponibile per download
- ❌ Build failed: Controlla i log in Actions tab
- 🏷️ Release: Notifica automatica su release tag

## 🆘 Troubleshooting

### Build Fallisce

1. **Controlla i log**: Actions tab → seleziona il run fallito → visualizza logs
2. **File mancanti**: Verifica che tutti i file richiesti esistano
3. **Sintassi PHP**: Verifica syntax PHP nei file modificati

### Version Mismatch

Se la versione nel tag non corrisponde alla versione nel plugin:
- Il workflow userà la versione dal file PHP
- Considera l'aggiornamento del tag o del file

### Artifact Non Disponibile

- Controlla che il workflow sia completato con successo
- Gli artifact scadono dopo 30-90 giorni
- Rigenera con un nuovo push o workflow manuale

## 📝 Esempi di Utilizzo

### Rilascio Rapido
```bash
# Aggiorna versione nel plugin
# Commit e push
git add fp-prenotazioni-ristorante-pro.php
git commit -m "Bump version to 1.6"
git push

# Crea release
git tag -a v1.6 -m "Version 1.6"
git push origin v1.6
```

### Download per Testing
```bash
# Usa GitHub CLI
gh run list --workflow=build-wordpress-plugin.yml
gh run download [RUN_ID]
```

---

**🎯 Risultato**: Sistema completamente automatizzato per generare plugin WordPress pronti per l'installazione con un singolo tag push!