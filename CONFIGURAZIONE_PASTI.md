# Configurazione Pasti Personalizzati

## Panoramica

Questo sistema permette di configurare in modo completamente flessibile i pasti disponibili per le prenotazioni. La configurazione personalizzata è l'unico metodo supportato, permettendo la massima flessibilità per ogni tipo di ristorante.

> ⚠️ Dopo l'installazione la lista dei pasti è vuota: il proprietario del sito deve creare manualmente ogni servizio disponibile. Il modulo di prenotazione mostrerà le opzioni solo dopo aver salvato e attivato almeno un pasto.

## Caratteristiche

### 🎛️ Configurazione Flessibile
- **Numero illimitato di pasti**: Aggiungi tutti i pasti che desideri
- **Nomi personalizzati**: Definisci il testo che appare nei pulsanti (es: "Pranzo", "Aperitivo", "Cena", "Brunch", "Merenda", etc.)
- **ID univoci**: Ogni pasto ha un identificatore univoco per il sistema

### 📅 Disponibilità per Giorno
- **Controllo granulare**: Scegli esattamente in quali giorni della settimana ogni pasto è disponibile
- **Esempi**:
  - Brunch solo la domenica
  - Aperitivo dal lunedì al sabato
  - Pranzo tutti i giorni
  - Cena speciale solo venerdì e sabato

### 🏪 Impostazioni per Pasto
Per ogni pasto puoi configurare:
- **Capienza**: Numero massimo di persone
- **Orari**: Slot temporali disponibili (formato: `12:00,12:30,13:00`)
- **Prezzo**: Valore economico per tracking e analytics
- **Stato**: Attivo/Disattivo
- **Tooltip**: Testo informativo opzionale per ogni pasto

## Come Usare

### 1. Accedere alla Configurazione
1. Vai in **Prenotazioni > Impostazioni**
2. Naviga alla sezione "Configurazione Pasti"
3. Il sistema di configurazione personalizzata è sempre attivo

### 2. Configurare i Pasti
1. All'avvio la lista è vuota: usa "Aggiungi Pasto" per creare i servizi del tuo locale (es. Pranzo, Cena, Degustazione)
2. Per ogni pasto configura:
   - **Attivo**: Spunta per attivare il pasto
   - **ID**: Identificatore univoco (es: `pranzo`, `cena_speciale`)
   - **Nome**: Testo che appare nel frontend (es: "Pranzo", "Cena Gourmet")
   - **Capienza**: Numero massimo di posti
   - **Orari**: Orari separati da virgola (es: `19:00,19:30,20:00`)
   - **Prezzo**: Valore in euro per tracking
   - **Giorni disponibili**: Seleziona i giorni della settimana
   - **Tooltip**: Testo informativo opzionale che appare nel frontend

### 3. Salvare le Impostazioni
Clicca "Salva le modifiche" per applicare la nuova configurazione.

## Esempi di Configurazione

### Ristorante Classico
```
Pasto 1: Pranzo
- ID: pranzo
- Orari: 12:00,12:30,13:00,13:30
- Giorni: Lun-Dom
- Capienza: 40

Pasto 2: Cena  
- ID: cena
- Orari: 19:00,19:30,20:00,20:30
- Giorni: Lun-Sab
- Capienza: 50
```

### Bar con Aperitivi
```
Pasto 1: Colazione
- ID: colazione
- Orari: 07:00,07:30,08:00,08:30
- Giorni: Lun-Dom
- Capienza: 20

Pasto 2: Aperitivo
- ID: aperitivo
- Orari: 17:00,17:30,18:00,18:30,19:00
- Giorni: Lun-Sab
- Capienza: 30

Pasto 3: Brunch Weekend
- ID: brunch
- Orari: 10:00,10:30,11:00,11:30
- Giorni: Sab-Dom
- Capienza: 25
```

### Ristorante Gourmet
```
Pasto 1: Pranzo Business
- ID: pranzo_business
- Orari: 12:00,12:15,12:30,12:45
- Giorni: Lun-Ven
- Capienza: 30

Pasto 2: Cena Standard
- ID: cena_standard  
- Orari: 19:00,19:30,20:00
- Giorni: Lun-Dom
- Capienza: 40

Pasto 3: Cena Degustazione
- ID: cena_degustazione
- Orari: 20:30,21:00
- Giorni: Ven-Sab
- Capienza: 16
```

## Note Tecniche

- Il sistema mantiene piena compatibilità con le prenotazioni esistenti
- I nuovi pasti appaiono automaticamente nel frontend di prenotazione
- La validazione impedisce prenotazioni per pasti non disponibili nel giorno selezionato
- Il sistema di capacità e analytics è completamente integrato
- Tutte le traduzioni italiano/inglese sono supportate
- La configurazione personalizzata è l'unico metodo supportato per la massima flessibilità
- Il modulo frontend resta inattivo finché non è attivo almeno un pasto personalizzato
- Il sistema è predisposto per future estensioni (nuovi campi, regole, automazioni)
