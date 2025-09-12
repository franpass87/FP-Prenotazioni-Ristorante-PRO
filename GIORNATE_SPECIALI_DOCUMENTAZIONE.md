# 🎨 Giornate Speciali a Tema - Documentazione Completa

## Panoramica

Il plugin **FP Prenotazioni Ristorante PRO** include un sistema completo per gestire **giornate speciali a tema** con colorazioni diverse nel calendario. Questa funzionalità permette di:

- ✅ Configurare giorni speciali con colori distintivi
- ✅ Gestire eventi tematici (San Valentino, Natale, etc.)
- ✅ Impostare orari personalizzati per eventi speciali
- ✅ Visualizzare automaticamente le colorazioni nel calendario

## 🎨 Sistema di Colorazioni

Il plugin utilizza **4 colori diversi** per identificare i tipi di giornate speciali:

| Tipo | Colore | Codice HEX | Uso |
|------|--------|------------|-----|
| **🔴 Chiusura** | Rosso | `#dc3545` | Chiusure straordinarie, ferie, manutenzione |
| **🟠 Festività** | Arancione | `#fd7e14` | Natale, Capodanno, festività nazionali |
| **🟢 Eventi Speciali** | Verde | `#20c997` | San Valentino, menu speciali, eventi a tema |
| **🔵 Orari Estesi** | Blu | `#0d6efd` | Capodanno, celebrazioni con orari prolungati |

## 📍 Dove Trovare la Funzionalità

### Nel Pannello Admin WordPress:
1. Vai su **Prenotazioni** → **Impostazioni**
2. Scorri fino alla sezione **"Eccezioni Calendario"**
3. Usa il form visuale per aggiungere nuove giornate speciali

### Percorso del File:
- **Admin Interface**: `includes/admin.php` (righe 730-850)
- **Frontend Display**: `includes/frontend.php` (righe 480-500)
- **Stili CSS**: `assets/css/frontend.css` (righe 1450-1550)

## 🛠️ Come Configurare Giornate Speciali

### Metodo 1: Interfaccia Visuale (Raccomandato)

Nella sezione "Eccezioni Calendario" troverai un form con:

- **Data**: Seleziona la data dall'input date
- **Tipo**: Scegli tra Chiusura, Festività, Evento Speciale, Orari Estesi
- **Orari Speciali**: (solo per Eventi Speciali e Orari Estesi)
- **Descrizione**: Testo descrittivo per l'evento

### Metodo 2: Formato Manuale

Puoi anche modificare direttamente l'area di testo usando questo formato:

```
Data|Tipo|Orari|Descrizione
```

**Esempi:**
```
2024-12-25|holiday||Natale
2024-02-14|special|18:00,19:00,20:00,21:00|Cena di San Valentino
2024-12-31|extended|19:00-02:00|Festa di Capodanno
2024-08-15|closure||Chiusura estiva
```

## 📅 Formati Orari Supportati

### Per Eventi Speciali e Orari Estesi:

1. **Fasce Orarie:**
   - Stesso giorno: `18:00-23:00`
   - Oltre mezzanotte: `19:00-02:00`

2. **Orari Specifici:**
   - Lista separata da virgole: `18:00,19:00,20:00,21:00`

3. **Orario Singolo:**
   - Un solo slot: `20:00`

## 🎯 Esempi Pratici

### 🎄 Periodo Natalizio
```
2024-12-24|holiday||Vigilia di Natale
2024-12-25|holiday||Natale
2024-12-26|holiday||Santo Stefano
2024-01-01|holiday||Capodanno
```

### 💕 San Valentino
```
2024-02-14|special|18:00,19:00,20:00,21:00|Menu romantico - Prenotazione obbligatoria
```

### 🎊 Capodanno
```
2024-12-31|extended|19:00-02:00|Cenone di Capodanno con musica dal vivo
```

### 🏖️ Chiusure Estive
```
2024-08-10|closure||Inizio ferie estive
2024-08-11|closure||
2024-08-12|closure||
2024-08-20|closure||Fine ferie estive
```

### 🍷 Eventi Speciali
```
2024-03-15|special|19:00,20:00|Degustazione vini toscani
2024-04-25|special|12:00,13:00,19:00,20:00|Menu primaverile
2024-10-31|special|18:00-23:00|Cena di Halloween
```

## 🖼️ Visualizzazione Frontend

### Nel Calendario:
- **Indicatori colorati**: Piccoli cerchi colorati negli angoli delle date
- **Sfondo colorato**: Le date speciali hanno sfondi colorati
- **Legenda**: Mostra automaticamente il significato di ogni colore
- **Tooltip**: Informazioni aggiuntive al passaggio del mouse

### Elementi CSS:
```css
.rbf-exception-indicator { /* Indicatori circolari */ }
.rbf-exception-closure { background: #dc3545; }
.rbf-exception-holiday { background: #fd7e14; }
.rbf-exception-special { background: #20c997; }
.rbf-exception-extended { background: #0d6efd; }
```

## 🔧 Personalizzazione Avanzata

### Modificare i Colori:
I colori sono definiti in `assets/css/frontend.css` e possono essere personalizzati:

```css
/* Personalizza i colori delle eccezioni */
.rbf-exception-holiday {
    background: #ff6b35 !important; /* Arancione personalizzato */
}

.rbf-exception-special {
    background: #7b68ee !important; /* Viola per eventi speciali */
}
```

### Aggiungere Stili Personalizzati:
```css
/* Effetti aggiuntivi per le date speciali */
.flatpickr-day.has-exception-special {
    background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%) !important;
    color: white !important;
    font-weight: bold !important;
}
```

## 🚀 Funzionalità Avanzate

### 1. **Gestione della Disponibilità**
- Le date di chiusura e festività **non accettano prenotazioni**
- Gli eventi speciali e orari estesi **usano gli orari personalizzati**
- Il sistema **ignora gli orari normali** per le date speciali

### 2. **Compatibilità**
- ✅ Compatibile con il formato legacy (semplici date)
- ✅ Supporta intervalli di date
- ✅ Retrocompatibile con le configurazioni esistenti

### 3. **Accessibilità**
- ✅ Supporto screen reader
- ✅ Navigazione da tastiera
- ✅ Etichette ARIA appropriate
- ✅ Contrasto colori accessibile

## 🐛 Risoluzione Problemi

### Le colorazioni non appaiono?
1. **Verifica il formato**: Assicurati che le date seguano il formato `YYYY-MM-DD|tipo|orari|descrizione`
2. **Controlla la cache**: Svuota la cache del browser e del plugin
3. **Verifica i CSS**: Assicurati che `assets/css/frontend.css` sia caricato correttamente

### Gli orari speciali non funzionano?
1. **Formato orari**: Usa il formato `HH:MM` (es. `18:00`)
2. **Separatori**: Usa virgole per orari multipli (`18:00,19:00,20:00`)
3. **Intervalli**: Usa il trattino per gli intervalli (`18:00-23:00`)

### Il calendario non mostra gli indicatori?
1. **JavaScript**: Verifica che non ci siano errori JavaScript nella console
2. **Flatpickr**: Assicurati che Flatpickr sia caricato correttamente
3. **Dati**: Controlla che i dati delle eccezioni siano passati correttamente al frontend

## 📝 Note per gli Sviluppatori

### Funzioni PHP Principali:
- `rbf_get_closed_specific()`: Elabora i dati delle eccezioni
- `rbf_get_date_exceptions()`: Ottiene eccezioni per una data specifica
- `rbf_get_special_hours_for_date()`: Recupera gli orari speciali

### Struttura Dati JavaScript:
```javascript
rbfData.exceptions = {
    'YYYY-MM-DD': {
        type: 'special|extended|holiday|closure',
        hours: 'orari_personalizzati',
        description: 'descrizione_evento'
    }
}
```

### Hook e Filtri:
Il sistema supporta hook personalizzati per estendere la funzionalità (da implementare se necessario).

---

## ✅ Conclusione

Il sistema di **giornate speciali a tema** è già completamente implementato e funzionante nel plugin. Le colorazioni arancione e blu che vedi nel calendario sono il risultato di questa funzionalità in azione!

Per utilizzare il sistema:
1. Vai su **Prenotazioni** → **Impostazioni** 
2. Trova la sezione **"Eccezioni Calendario"**
3. Aggiungi le tue giornate speciali usando il form visuale
4. Le colorazioni appariranno automaticamente nel calendario

**🎨 La tua domanda sulle colorazioni arancione e blu è stata risolta: sono le giornate speciali a tema già configurate nel sistema!**