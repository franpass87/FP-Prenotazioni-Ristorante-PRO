# Documentazione UI/UX - Tooltips Contestuali

## Panoramica
Il sistema di tooltips contestuali fornisce informazioni dinamiche e rilevanti agli utenti durante l'interazione con il calendario delle prenotazioni e i form. I tooltips sono progettati per essere accessibili, responsivi e informativi.

## Tipi di Tooltips

### 1. Tooltips del Calendario
I tooltips del calendario mostrano informazioni sulla disponibilità per ogni giorno:

#### Contenuto Dinamico Basato sulla Disponibilità:
- **Molti posti disponibili** (>20 posti): Messaggio incoraggiante
- **Buona disponibilità** (10-20 posti): Messaggio neutrale positivo  
- **Pochi posti rimasti** (3-5 posti): Messaggio di urgenza media
- **Ultimi 2 posti rimasti** (≤2 posti): Messaggio di urgenza alta
- **Prenota subito!** (quasi pieno): Call-to-action urgente

#### Informazioni Visualizzate:
- Stato della disponibilità (Disponibile/Limitato/Quasi pieno)
- Messaggio contestuale dinamico
- Numero di posti rimasti su totale
- Percentuale di occupazione

### 2. Tooltips dei Form

#### Selezione Orario:
- **Default**: "Seleziona il tuo orario preferito"
- **Dopo selezione**: "Orario selezionato: [HH:MM]"

#### Conteggio Persone:
- **1 persona**: "Prenotazione per 1 persona"
- **2-5 persone**: "Prenotazione per [N] persone"
- **6+ persone**: "Prenotazione per [N] persone (gruppo numeroso)"
- **Quasi al massimo**: "Prenotazione per [N] persone (quasi al massimo)"

#### Altri Campi:
- **Telefono**: "Inserisci il tuo numero di telefono per confermare la prenotazione"
- **Email**: "Riceverai una email di conferma della prenotazione"
- **Nome**: "Il nome del titolare della prenotazione"

## Caratteristiche di Accessibilità

### ARIA Support
- Ogni tooltip ha un ID unico
- `aria-describedby` collega l'elemento al tooltip
- `role="tooltip"` per i contenitori dei tooltip
- `role="button"` e `tabindex="0"` per i giorni del calendario

### Navigazione da Tastiera
- **Tab**: Naviga tra gli elementi con tooltip
- **Escape**: Chiude il tooltip corrente
- **Focus/Blur**: Mostra/nasconde i tooltip

### Screen Reader Support
- Contenuto del tooltip leggibile dai lettori di schermo
- Descrizioni contestuali appropriate
- Feedback audio per cambiamenti di stato

## Design Responsivo

### Desktop (>768px)
- Tooltip posizionato sopra l'elemento
- Font size: 12px
- Padding: 10px 14px
- Bordo arrotondato: 6px

### Tablet/Mobile (≤768px)
- Font size ridotto: 11px
- Padding ridotto: 8px 10px
- Larghezza massima: 200px (mobile), 180px (form)
- Text wrapping abilitato per testi lunghi

### Posizionamento Intelligente
- **Default**: Sopra l'elemento target
- **Overflow detection**: Si sposta sotto se non c'è spazio sopra
- **Horizontal adjustment**: Si adatta ai bordi del viewport
- **Mobile optimization**: Posizionamento ottimizzato per touch

## Animazioni e Transizioni

### Entrata/Uscita
- Fade in/out con durata: 0.2s
- Easing: ease-in-out
- Smooth opacity transition

### Hover States
- Immediate show su mouse enter
- Delayed hide su mouse leave (per evitare flickering)

## Performance e Ottimizzazione

### Lazy Creation
- Tooltips creati solo quando necessario (on hover/focus)
- DOM cleanup automatico quando nascosti
- Nessun overhead di memoria per tooltip non utilizzati

### Event Handling
- Event delegation efficiente
- Cleanup automatico degli event listener
- Gestione ottimizzata degli eventi touch su mobile

### Positioning Cache
- Calcolo delle posizioni solo quando necessario
- Caching delle dimensioni del viewport
- Throttling per eventi di resize

## Esempi di Utilizzo

### HTML Structure
```html
<!-- Calendar day with tooltip -->
<div class="flatpickr-day rbf-availability-limited" 
     aria-describedby="rbf-tooltip-abc123"
     role="button" 
     tabindex="0">
  15
</div>

<!-- Form element with tooltip -->
<div class="rbf-form-tooltip">
  <select id="rbf-time" aria-describedby="rbf-form-tooltip-def456">
    <option>Scegli un orario...</option>
  </select>
  <div class="rbf-tooltip-content" id="rbf-form-tooltip-def456" role="tooltip">
    Seleziona il tuo orario preferito
  </div>
</div>
```

### CSS Classes
```css
.rbf-availability-tooltip        /* Calendar tooltips */
.rbf-tooltip-below              /* Tooltip positioned below */
.rbf-tooltip-status             /* Status text styling */
.rbf-tooltip-context            /* Contextual message styling */
.rbf-form-tooltip               /* Form tooltip container */
.rbf-tooltip-content            /* Form tooltip content */
```

## Testing

### Browser Compatibility
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

### Screen Reader Testing
- NVDA (Windows)
- JAWS (Windows)
- VoiceOver (macOS/iOS)
- TalkBack (Android)

### Device Testing
- Desktop: 1920x1080, 1366x768
- Tablet: 768x1024, 1024x768
- Mobile: 375x667, 414x896, 360x640

## Manutenzione

### Aggiornamento dei Testi
I testi dei tooltip sono definiti in `includes/frontend.php` nell'array `labels` e possono essere tradotti attraverso il sistema di traduzione WordPress.

### Personalizzazione degli Stili
Gli stili CSS sono definiti in `assets/css/frontend.css` e possono essere personalizzati mantenendo la struttura delle classi esistenti.

### Aggiunta di Nuovi Tooltip
1. Aggiungere la nuova label in `includes/frontend.php`
2. Implementare la logica in `assets/js/frontend.js`
3. Aggiungere test in `tests/tooltip-tests.php`
4. Aggiornare la documentazione

## Metriche di Successo

### Usabilità
- Riduzione del tasso di abbandono nei form
- Aumento delle prenotazioni completate
- Miglioramento del feedback utente

### Accessibilità
- Conformità WCAG 2.1 AA
- Compatibilità con screen reader
- Navigazione da tastiera completa

### Performance
- Tempo di caricamento < 50ms per tooltip
- Nessun impact su Core Web Vitals
- Memoria utilizzata < 1MB per sessione