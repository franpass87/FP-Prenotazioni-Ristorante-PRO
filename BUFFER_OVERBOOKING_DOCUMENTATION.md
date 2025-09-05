# Buffer e Overbooking - Documentazione

## Panoramica

Il sistema di Buffer e Overbooking fornisce un controllo avanzato della gestione delle prenotazioni, permettendo di:

1. **Buffer Temporali**: Gestire tempi di pulizia/preparazione tra prenotazioni
2. **Overbooking Controllato**: Consentire prenotazioni oltre la capienza normale entro limiti configurabili

## Configurazione Buffer

### Buffer Base
- **Campo**: Buffer Base (minuti)
- **Descrizione**: Tempo minimo di buffer tra prenotazioni
- **Range**: 0-120 minuti
- **Default**: 15 minuti

### Buffer per Persona
- **Campo**: Buffer per Persona (minuti)
- **Descrizione**: Tempo aggiuntivo per ogni persona
- **Range**: 0-30 minuti
- **Default**: 5 minuti

### Calcolo Buffer Totale
```
Buffer Totale = Buffer Base + (Buffer per Persona × Numero Persone)
```

**Esempi:**
- 2 persone: 15 + (5 × 2) = 25 minuti
- 4 persone: 15 + (5 × 4) = 35 minuti
- 8 persone: 15 + (5 × 8) = 55 minuti

## Configurazione Overbooking

### Limite Overbooking
- **Campo**: Limite Overbooking (%)
- **Descrizione**: Percentuale di posti aggiuntivi oltre la capienza normale
- **Range**: 0-50%
- **Default**: 10%

### Calcolo Capienza Effettiva
```
Capienza Effettiva = Capienza Base + (Capienza Base × Limite Overbooking / 100)
```

**Esempi:**
- Capienza 30 + 10% = 33 posti
- Capienza 25 + 15% = 29 posti  
- Capienza 40 + 5% = 42 posti

## Funzionamento del Sistema

### Validazione Buffer
1. **Durante la Prenotazione**: Il sistema verifica che la nuova prenotazione rispetti i buffer richiesti
2. **Calcolo Dinamico**: Il buffer necessario viene calcolato in base al numero di persone
3. **Conflitti**: Se il buffer non è rispettato, la prenotazione viene bloccata

### Validazione Overbooking
1. **Capienza Effettiva**: La capienza massima include il limite di overbooking
2. **Controllo Progressivo**: Le prenotazioni vengono accettate fino al raggiungimento della capienza effettiva
3. **Limite Rigido**: Non vengono accettate prenotazioni oltre il limite di overbooking

## Esempi Pratici

### Scenario Pranzo
- **Configurazione**: Buffer base 15min, Per persona 5min, Overbooking 10%
- **Capienza**: 30 → 33 (con overbooking)
- **Prenotazioni Esistenti**:
  - 12:00 - 4 persone (buffer richiesto: 35min)
  - 13:00 - 2 persone (buffer richiesto: 25min)

**Slot Disponibili**:
- ✅ 12:40 (40min dopo 12:00)
- ❌ 12:30 (solo 30min dopo 12:00, serve 35min)
- ✅ 13:30 (30min dopo 13:00, serve 25min)

### Scenario Cena
- **Configurazione**: Buffer base 20min, Per persona 5min, Overbooking 5%
- **Capienza**: 40 → 42 (con overbooking)
- **Prenotazioni**: 38 persone già prenotate
- **Disponibilità**: 4 posti rimanenti (nell'overbooking)

## Messaggi di Errore

### Buffer Non Rispettato
```
"Questo orario non rispetta il buffer di X minuti richiesto. Scegli un altro orario."
```

### Capienza Superata
```
"Spiacenti, non ci sono abbastanza posti. Rimasti: X. Scegli un altro orario."
```

## Configurazione per Tipo di Servizio

### Pranzo (Configurazione Suggerita)
- Buffer Base: 15 minuti
- Buffer per Persona: 5 minuti
- Overbooking: 10%
- **Razionale**: Servizio veloce, rotazione tavoli media

### Aperitivo (Configurazione Suggerita)
- Buffer Base: 10 minuti
- Buffer per Persona: 3 minuti
- Overbooking: 15%
- **Razionale**: Servizio informale, maggiore flessibilità

### Cena (Configurazione Suggerita)
- Buffer Base: 20 minuti
- Buffer per Persona: 5 minuti
- Overbooking: 5%
- **Razionale**: Servizio elaborato, pulizia accurata

### Brunch (Configurazione Suggerita)
- Buffer Base: 15 minuti
- Buffer per Persona: 5 minuti
- Overbooking: 10%
- **Razionale**: Servizio domenicale, gestione standard

## Note Tecniche

### Prestazioni
- I calcoli di buffer sono cached per 1 ora
- Le verifiche avvengono solo durante la prenotazione
- L'impatto sulle prestazioni è minimo

### Compatibilità
- Funziona con il sistema di pasti personalizzati esistente
- Retrocompatibile con configurazioni precedenti
- I valori di default mantengono il comportamento attuale

### Limitazioni
- Buffer minimo applicato: il maggiore tra quello richiesto e quello esistente
- Overbooking massimo: 50% per evitare problemi operativi
- Buffer massimo per persona: 30 minuti per mantenere flessibilità

## Test e Validazione

Il sistema include test automatizzati per:
- Calcolo corretto dei buffer
- Validazione dei conflitti temporali
- Calcolo della capienza effettiva
- Scenari di overbooking
- Casi limite ed errori

Per eseguire i test:
```bash
php tests/buffer-overbooking-tests.php
```