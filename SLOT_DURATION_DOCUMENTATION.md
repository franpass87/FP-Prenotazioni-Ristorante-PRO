# Documentazione Durata Slot Dinamica

## Panoramica

Il sistema di prenotazioni ora supporta la durata dinamica degli slot basata sul tipo di servizio e il numero di coperti. Questa funzionalità permette una gestione più accurata dei tempi di occupazione dei tavoli.

## Regole di Durata Slot

### Durata Base per Servizio

- **Pranzo**: 60 minuti
- **Cena**: 90 minuti  
- **Aperitivo**: 75 minuti
- **Brunch**: 60 minuti

### Regola per Gruppi Numerosi

Per tutti i servizi, i gruppi con **più di 6 persone** ricevono automaticamente una durata di **120 minuti**, indipendentemente dal tipo di servizio.

## Esempi Pratici

| Servizio | N. Persone | Durata Slot |
|----------|------------|-------------|
| Pranzo   | 2         | 60 minuti   |
| Pranzo   | 6         | 60 minuti   |
| Pranzo   | 7         | 120 minuti  |
| Cena     | 2         | 90 minuti   |
| Cena     | 6         | 90 minuti   |
| Cena     | 8         | 120 minuti  |
| Aperitivo| 4         | 75 minuti   |
| Aperitivo| 10        | 120 minuti  |

## Configurazione Backend

### Nuovi Campi

Nel pannello amministrativo, ogni pasto ora include:

- **Durata Slot (minuti)**: Campo configurabile per impostare la durata base del servizio
- Range: 30-240 minuti
- Default: 90 minuti per nuovi pasti

### Funzioni API

#### `rbf_calculate_slot_duration($meal_id, $people_count)`

Calcola la durata dinamica dello slot.

**Parametri:**
- `$meal_id` (string): ID del pasto (es. 'pranzo', 'cena')
- `$people_count` (int): Numero di persone

**Ritorna:**
- `int`: Durata in minuti

**Esempio:**
```php
$duration = rbf_calculate_slot_duration('pranzo', 4); // Ritorna 60
$duration = rbf_calculate_slot_duration('pranzo', 8); // Ritorna 120
$duration = rbf_calculate_slot_duration('cena', 2);   // Ritorna 90
```

## Logica di Implementazione

1. **Recupero Configurazione**: Il sistema recupera la durata base dal campo `slot_duration_minutes` della configurazione del pasto
2. **Applicazione Regola Gruppi**: Se il numero di persone > 6, la durata viene sovrascritta a 120 minuti
3. **Fallback**: Se il pasto non viene trovato, viene utilizzata una durata default di 90 minuti

## Compatibilità

- La nuova funzionalità è completamente retrocompatibile
- I pasti esistenti riceveranno automaticamente le durate predefinite al prossimo salvataggio
- Il sistema continua a funzionare normalmente se il campo `slot_duration_minutes` non è presente

## Test e Validazione

Il sistema include test completi per:
- Durate base per ogni tipo di servizio
- Regola gruppi >6 persone  
- Casi limite (0 persone, pasti inesistenti)
- Matrice completa servizi x dimensioni gruppo

## Note Tecniche

- I buffer time esistenti rimangono invariati e continuano a funzionare come prima
- La durata slot è un concetto separato dal buffer time
- La durata slot rappresenta quanto tempo un tavolo rimane occupato
- Il buffer time rappresenta il tempo minimo tra prenotazioni consecutive