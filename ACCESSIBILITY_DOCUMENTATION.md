# Documentazione Accessibilità - FP Prenotazioni Ristorante PRO

## Panoramica

Questa documentazione descrive le funzionalità di accessibilità implementate nel plugin FP Prenotazioni Ristorante PRO per garantire la conformità agli standard WCAG 2.1 livello AA e un'esperienza inclusiva per tutti gli utenti.

## Standard di Riferimento

- **WCAG 2.1 Livello AA**: Linee guida per l'accessibilità dei contenuti web
- **EN 301 549**: Standard europeo per l'accessibilità
- **Section 508**: Standard americano per l'accessibilità dei prodotti elettronici

## Navigazione da Tastiera

### Tasti di Navigazione Supportati

| Tasto | Funzione | Contesto |
|-------|----------|----------|
| `Tab` | Navigazione in avanti tra elementi | Globale |
| `Shift + Tab` | Navigazione indietro tra elementi | Globale |
| `Enter` | Attivazione pulsanti e conferma selezioni | Pulsanti, radio, calendar |
| `Spazio` | Attivazione pulsanti e checkbox | Pulsanti, checkbox |
| `Escape` | Chiusura di tooltip, dropdown, focus di calendario | Globale |
| `Frecce` | Navigazione tra opzioni radio e giorni calendario | Radio group, calendar |
| `Home/End` | Primo/ultimo elemento | Radio group, calendar |
| `Page Up/Down` | Mese precedente/successivo | Calendar |
| `+/-` | Incrementa/decrementa numero persone | People selector |
| `Ctrl + Enter` | Invio rapido modulo (se privacy accettata) | Form |

### Indicatori di Focus

Tutti gli elementi interattivi hanno indicatori di focus visibili:
- **Contorno blu**: `2px solid #4f93ce` con offset di 2px
- **Contrasto**: Rapporto minimo 3:1 con lo sfondo
- **Persistenza**: Il focus rimane visibile fino al successivo cambio

### Gestione del Focus

- **Focus automatico**: Quando si apre un nuovo step, il focus va al primo elemento interattivo
- **Trap del focus**: Nei modal e dropdown, il focus è limitato agli elementi contenuti
- **Restauro del focus**: Quando si chiude un modal/dropdown, il focus torna all'elemento trigger

## Ruoli ARIA e Attributi

### Form Principal

```html
<form role="form" aria-label="Modulo di prenotazione ristorante">
```

### Indicatore di Progresso

```html
<div role="progressbar" 
     aria-valuenow="1" 
     aria-valuemin="1" 
     aria-valuemax="5" 
     aria-label="Progresso prenotazione"
     aria-describedby="progress-description">
  <div aria-current="step">1</div>
  <div aria-current="false">2</div>
  <!-- ... -->
</div>
```

### Gruppi di Radio Button

```html
<div role="radiogroup" 
     aria-labelledby="meal-label" 
     aria-required="true">
  <input type="radio" aria-describedby="rbf-meal-notice">
  <!-- ... -->
</div>
```

### Calendario

```html
<input role="combobox" 
       aria-expanded="false" 
       aria-haspopup="grid" 
       aria-describedby="date-help">
```

I giorni del calendario hanno:
```html
<div role="button" 
     tabindex="0" 
     aria-describedby="tooltip-id">
```

### Selettore Persone

```html
<div role="group" 
     aria-labelledby="people-label" 
     aria-describedby="people-instructions">
  <input role="spinbutton" 
         aria-valuemin="1" 
         aria-valuenow="1" 
         aria-valuemax="30">
</div>
```

### Tooltip

```html
<div role="tooltip" id="unique-id">
  Contenuto del tooltip
</div>
```

Elementi con tooltip:
```html
<element aria-describedby="unique-id">
```

## Supporto Screen Reader

### Regioni Live

- **Status updates**: `aria-live="polite"` per aggiornamenti non urgenti
- **Error alerts**: `aria-live="assertive"` per errori e avvisi urgenti
- **Atomic updates**: `aria-atomic="true"` per messaggi completi

### Annunci Automatici

Il sistema annuncia automaticamente:
- Cambio di step nel form
- Selezione di date e orari
- Cambio numero di persone
- Errori di validazione
- Stati di caricamento

### Testo Solo per Screen Reader

```css
.sr-only {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}
```

## Contrasto Colori

### Palette Colori Accessibili

| Uso | Colore Originale | Colore Accessibile | Rapporto di Contrasto |
|-----|------------------|--------------------|-----------------------|
| Testo primario | #333333 | #333333 | 12.6:1 (AAA) |
| Testo secondario | #666666 | #666666 | 5.7:1 (AA) |
| Successo | #28a745 | #1e7e34 | 4.5:1 (AA) |
| Avviso | #ffc107 | #856404 | 4.5:1 (AA) |
| Errore | #dc3545 | #dc3545 | 4.5:1 (AA) |
| Secondario | #f8b500 | #b8860b | 4.5:1 (AA) |
| Focus | - | #4f93ce | 4.5:1 (AA) |

### Test Contrasto

Il plugin include uno strumento di test automatico del contrasto:

```php
// Esempio di test
$ratio = calculateContrastRatio('#ffffff', '#1e7e34');
$compliance = checkWCAGCompliance($ratio); // Returns 'AA', 'AAA', or 'FAIL'
```

## Responsività e Mobile

### Touch Target

- **Dimensione minima**: 44px × 44px per tutti gli elementi interattivi
- **Spaziatura**: Minimo 8px tra target adiacenti
- **Area di tocco**: Estesa oltre i bordi visibili quando necessario

### Zoom e Ingrandimento

- **Supporto zoom**: Contenuto funzionale fino al 200% di zoom
- **Responsive**: Layout si adatta senza scroll orizzontale
- **Font scaling**: Rispetta le impostazioni di sistema per la dimensione del testo

### Orientamento

- **Portrait/Landscape**: Funzionalità complete in entrambi gli orientamenti
- **Rotazione**: Contenuto si riorganizza automaticamente

## Funzionalità Avanzate

### Preferenze di Sistema

```css
/* Movimento ridotto */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}

/* Alto contrasto */
@media (prefers-contrast: high) {
    :root {
        --rbf-border: #000000;
        --rbf-focus-color: #ff0000;
    }
}
```

### Modalità Stampa

- Tooltip nascosti automaticamente
- Bordi ad alto contrasto
- Layout ottimizzato per stampa

### Gestione Errori

- **Prevenzione errori**: Validazione in tempo reale
- **Identificazione errori**: Messaggi chiari e specifici
- **Correzione errori**: Suggerimenti per la risoluzione
- **Annunci**: Errori comunicati ai screen reader

## Testing di Accessibilità

### Browser Supportati

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

### Screen Reader Testati

- **NVDA** (Windows)
- **JAWS** (Windows)
- **VoiceOver** (macOS/iOS)
- **TalkBack** (Android)

### Strumenti di Test

1. **axe-core**: Test automatici di accessibilità
2. **WAVE**: Valutazione web accessibilità
3. **Color Contrast Analyzers**: Test rapporti di contrasto
4. **Keyboard navigation**: Test solo tastiera
5. **Screen reader**: Test con lettori di schermo

### Checklist di Test

#### Navigazione da Tastiera
- [ ] Tutti gli elementi raggiungibili con Tab
- [ ] Ordine di tabulazione logico
- [ ] Focus visibile su tutti gli elementi
- [ ] Escape funziona per chiudere modal/dropdown
- [ ] Frecce funzionano per radio group e calendario
- [ ] Enter/Space attivano correttamente gli elementi

#### ARIA e Semantica
- [ ] Ruoli ARIA appropriati per componenti custom
- [ ] Labels e descrizioni appropriate
- [ ] Relazioni tra elementi (labelledby, describedby)
- [ ] Stati dinamici aggiornati (aria-expanded, aria-selected)
- [ ] Regioni live funzionanti

#### Contrasto e Design
- [ ] Rapporti di contrasto conformi WCAG AA
- [ ] Focus indicators visibili
- [ ] Contenuto leggibile al 200% zoom
- [ ] Touch target minimi di 44px

#### Screen Reader
- [ ] Contenuto letto correttamente
- [ ] Navigazione strutturale funzionante
- [ ] Annunci di stato appropriati
- [ ] Forme e controlli identificabili

## Implementazione Tecnica

### File Principali

- **frontend.css**: Stili di accessibilità e focus indicators
- **frontend.js**: Logica di navigazione da tastiera e ARIA
- **frontend.php**: Markup semantico e attributi ARIA
- **accessibility-tests.php**: Test automatici di conformità

### Funzioni JavaScript Chiave

```javascript
// Navigazione da tastiera
initializeKeyboardNavigation()

// Gestione focus
enhanceFocusManagement()

// Annunci ARIA
enhanceARIAAnnouncements()

// Annunci screen reader
announceToScreenReader(message, isAlert)
```

### Classi CSS Importanti

```css
/* Focus indicators */
*:focus { outline: var(--rbf-focus-outline); }

/* Skip links */
.rbf-skip-link { /* ... */ }

/* Screen reader only */
.sr-only { /* ... */ }

/* High contrast support */
@media (prefers-contrast: high) { /* ... */ }
```

## Manutenzione e Aggiornamenti

### Aggiornamento Colori

1. Testare nuovi colori con lo strumento di contrasto
2. Aggiornare le variabili CSS
3. Verificare la conformità WCAG
4. Testare con utenti reali

### Aggiunta Nuove Funzionalità

1. Progettare con l'accessibilità in mente
2. Implementare supporto keyboard
3. Aggiungere ARIA appropriati
4. Testare con screen reader
5. Documentare i pattern utilizzati

### Test Regressione

- Eseguire test automatici di accessibilità
- Verificare navigazione da tastiera
- Testare con screen reader
- Controllare contrasti colori
- Validare markup HTML

## Risorse e Riferimenti

- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [ARIA Authoring Practices](https://www.w3.org/WAI/ARIA/apg/)
- [WebAIM Screen Reader Testing](https://webaim.org/articles/screenreader_testing/)
- [Color Contrast Checker](https://webaim.org/resources/contrastchecker/)

## Supporto e Feedback

Per segnalazioni di problemi di accessibilità o suggerimenti di miglioramento, utilizzare i canali standard di supporto del plugin, specificando "ACCESSIBILITY" nel titolo della richiesta.

---

*Questa documentazione viene aggiornata regolarmente per riflettere le migliori pratiche di accessibilità e le modifiche al plugin.*