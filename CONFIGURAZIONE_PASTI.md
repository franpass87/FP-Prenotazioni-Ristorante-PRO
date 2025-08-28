# Configurazione Pasti Personalizzati

## Panoramica

Questo sistema permette di configurare in modo flessibile i pasti disponibili per le prenotazioni, sostituendo la configurazione hardcoded precedente con un sistema completamente personalizzabile.

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

### 🔄 Retrocompatibilità
- **Modalità Legacy**: Il sistema continua a funzionare con le impostazioni classiche
- **Migrazione graduale**: Puoi passare alla nuova configurazione quando sei pronto
- **Fallback automatico**: Se mancano configurazioni personalizzate, usa quelle classiche

## Come Usare

### 1. Abilitare la Configurazione Personalizzata
1. Vai in **Prenotazioni > Impostazioni**
2. Nella sezione "Configurazione Pasti"
3. Cambia da "No - Usa impostazioni classiche" a "Sì - Configura pasti personalizzati"

### 2. Configurare i Pasti
1. La sezione "Pasti Personalizzati" diventerà visibile
2. Modifica i pasti esistenti o usa "Aggiungi Pasto" per crearne di nuovi
3. Per ogni pasto configura:
   - **Attivo**: Spunta per attivare il pasto
   - **ID**: Identificatore univoco (es: `pranzo`, `cena_speciale`)
   - **Nome**: Testo che appare nel frontend (es: "Pranzo", "Cena Gourmet")
   - **Capienza**: Numero massimo di posti
   - **Orari**: Orari separati da virgola (es: `19:00,19:30,20:00`)
   - **Prezzo**: Valore in euro per tracking
   - **Giorni disponibili**: Seleziona i giorni della settimana

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
