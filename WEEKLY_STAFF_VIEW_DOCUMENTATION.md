# Vista Settimanale Staff - Documentazione Feature

## Panoramica

La **Vista Settimanale Staff** è una nuova funzionalità amministrativa progettata per fornire al personale del ristorante un'interfaccia compatta e intuitiva per la gestione delle prenotazioni con funzionalità drag & drop.

## Accesso alla Funzionalità

1. Accedi al pannello amministrativo di WordPress
2. Naviga su **Prenotazioni** nel menu laterale
3. Seleziona **Vista Settimanale Staff**

## Caratteristiche Principali

### 📅 Vista Settimanale Compatta
- Layout settimanale ottimizzato per la visualizzazione del personale
- Visualizzazione compatta delle prenotazioni con informazioni essenziali
- Griglia temporale dalle 11:00 alle 23:00 con slot di 30 minuti
- Colori distintivi per stato prenotazione:
  - **Verde**: Confermata
  - **Rosso**: Cancellata  
  - **Grigio**: Completata

### 🖱️ Funzionalità Drag & Drop
- **Trascinamento diretto**: Clicca e trascina una prenotazione per spostarla
- **Validazione in tempo reale**: Il sistema verifica automaticamente la disponibilità
- **Conferma movimento**: Richiesta di conferma prima di applicare le modifiche
- **Feedback immediato**: Notifiche di successo o errore istantanee

### 📱 Design Responsivo
- Ottimizzato per tablet e dispositivi mobili del personale
- Layout adattivo per diverse dimensioni di schermo
- Touch-friendly per dispositivi tattili

## Come Utilizzare la Funzionalità

### Spostamento di una Prenotazione

1. **Identifica la prenotazione** da spostare nella griglia settimanale
2. **Clicca e trascina** l'evento verso il nuovo orario/giorno desiderato
3. **Rilascia** l'evento nella nuova posizione
4. **Conferma** il movimento quando richiesto
5. **Verifica** la notifica di successo o eventuali errori

### Visualizzazione Dettagli Prenotazione

1. **Clicca** su una prenotazione nella griglia
2. Si aprirà un **modal compatto** con i dettagli:
   - Nome cliente
   - Data e orario
   - Numero di persone
   - Tipo di pasto
   - Stato prenotazione
3. Utilizza il pulsante **"Modifica Completa"** per accedere alla modifica avanzata

## Validazioni di Sicurezza

Il sistema applica automaticamente le seguenti validazioni durante lo spostamento:

### ✅ Controlli di Disponibilità
- **Capacità del servizio**: Verifica che il nuovo slot abbia capacità sufficiente
- **Conflitti temporali**: Previene sovrapposizioni con altre prenotazioni
- **Buffer time**: Rispetta i tempi di buffer configurati tra prenotazioni
- **Orari validi**: Accetta solo orari validi per il tipo di pasto

### ❌ Blocchi Automatici
- **Date passate**: Non è possibile spostare prenotazioni nel passato
- **Slot non disponibili**: Blocca spostamenti verso slot non configurati
- **Capacità esaurita**: Previene overbooking oltre i limiti configurati
- **Giorni chiusi**: Impedisce spostamenti in giorni di chiusura

## Messaggi di Sistema

### Notifiche di Successo
- ✅ **"Prenotazione spostata con successo"**: Il movimento è stato completato
- Visualizzata in verde nell'angolo superiore destro

### Messaggi di Errore
- ❌ **"Nuovo slot non disponibile"**: Capacità insufficiente o slot non valido
- ❌ **"Conflitto di orario rilevato"**: Violazione del buffer time
- ❌ **"Formato data o orario non valido"**: Errore nei dati di spostamento
- Visualizzati in rosso nell'angolo superiore destro

## Configurazione e Personalizzazione

### Impostazioni Influenti
La vista settimanale rispetta tutte le configurazioni esistenti:
- **Configurazione pasti**: Orari, capacità, giorni disponibili
- **Buffer time**: Tempi di pausa tra prenotazioni
- **Overbooking**: Limiti di sovrapprenottazione
- **Giorni di chiusura**: Date specifiche di chiusura
- **Brand personalizzazione**: Colori e stili del tema

### Personalizzazione CSS
Gli amministratori possono personalizzare l'aspetto attraverso:
```css
/* Stili per eventi compatti */
.rbf-compact-event {
    font-size: 11px !important;
    padding: 2px 4px !important;
}

/* Personalizzazione colori notifiche */
#rbf-move-notification.notice-success {
    border-left-color: #46b450;
}
```

## Risoluzione Problemi

### Problema: Drag & Drop Non Funziona
**Soluzioni:**
1. Verifica che JavaScript sia abilitato nel browser
2. Controlla la connessione internet
3. Ricarica la pagina
4. Verifica i permessi utente (necessario `manage_options`)

### Problema: Movimento Bloccato
**Possibili Cause:**
- Capacità esaurita nel nuovo slot
- Violazione del buffer time
- Data/orario non valido
- Slot non configurato per il tipo di pasto

### Problema: Prenotazioni Non Visibili
**Verifiche:**
1. Controlla che le prenotazioni siano pubblicate
2. Verifica i filtri di data
3. Assicurati che i meta dati siano corretti

## Best Practices per il Personale

### 📋 Utilizzo Quotidiano
1. **Controlla sempre** la capacità rimanente prima di spostare gruppi numerosi
2. **Verifica i buffer time** per evitare conflitti con altre prenotazioni
3. **Usa la vista settimanale** per pianificare distribuzioni ottimali
4. **Conferma sempre** i movimenti importanti

### ⚡ Efficienza Operativa
- Utilizza la funzionalità su tablet per gestione al banco
- Sfrutta la vista compatta per quick check durante il servizio
- Combina con la vista completa per modifiche dettagliate

### 🔒 Sicurezza dei Dati
- Solo utenti con permessi amministrativi possono utilizzare la funzionalità
- Tutti i movimenti sono loggati nel sistema
- I dati sensibili sono sempre protetti

## Supporto Tecnico

### Log di Debug
In caso di problemi, verifica i log di WordPress in:
```
wp-content/debug.log
```

### Informazioni Tecniche
- **Tecnologia**: FullCalendar 5.11.3 + AJAX WordPress
- **Compatibilità**: WordPress 5.0+, PHP 7.4+
- **Browser supportati**: Chrome, Firefox, Safari, Edge (ultimi 2 versioni)

### Aggiornamenti Futuri
La funzionalità è progettata per essere estendibile con:
- Filtri avanzati per staff
- Notifiche push per modifiche
- Integrazione con sistemi esterni
- Statistiche di utilizzo staff

---

## Changelog

### Versione 1.0 (Corrente)
- ✅ Implementazione vista settimanale base
- ✅ Funzionalità drag & drop completa
- ✅ Validazioni di sicurezza e disponibilità
- ✅ Design responsivo per mobile
- ✅ Integrazione con sistema esistente

---

*Per supporto tecnico o segnalazione bug, contatta il team di sviluppo o apri un ticket nel sistema di gestione.*