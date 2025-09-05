# Gestione Tavoli Intelligente - Documentazione Tecnica

## Panoramica

Il sistema di gestione tavoli intelligente implementa un algoritmo di assegnazione ottimizzata che consente di gestire tavoli singoli e combinazioni di tavoli unibili per ottimizzare l'utilizzo dello spazio ristorante.

## Architettura Database

### Schema delle Tabelle

#### 1. `wp_rbf_areas` - Aree del Ristorante
```sql
CREATE TABLE wp_rbf_areas (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    description text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY name (name)
);
```

**Campi:**
- `id`: Identificativo univoco dell'area
- `name`: Nome dell'area (es. "Sala Principale", "Dehors", "Terrazza")
- `description`: Descrizione opzionale dell'area
- `created_at/updated_at`: Timestamp di creazione e aggiornamento

#### 2. `wp_rbf_tables` - Tavoli
```sql
CREATE TABLE wp_rbf_tables (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    area_id mediumint(9) NOT NULL,
    name varchar(100) NOT NULL,
    capacity tinyint(4) NOT NULL DEFAULT 2,
    min_capacity tinyint(4) NOT NULL DEFAULT 1,
    max_capacity tinyint(4) NOT NULL DEFAULT 8,
    position_x int(11) DEFAULT NULL,
    position_y int(11) DEFAULT NULL,
    is_active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY area_id (area_id),
    KEY capacity (capacity),
    KEY is_active (is_active),
    UNIQUE KEY area_table_name (area_id, name)
);
```

**Campi:**
- `id`: Identificativo univoco del tavolo
- `area_id`: Riferimento all'area di appartenenza
- `name`: Nome del tavolo (es. "T1", "Tavolo 1")
- `capacity`: Capacità standard del tavolo
- `min_capacity`: Capacità minima (per flessibilità)
- `max_capacity`: Capacità massima (per flessibilità)
- `position_x/position_y`: Coordinate per futuro layout grafico
- `is_active`: Flag per attivazione/disattivazione

#### 3. `wp_rbf_table_groups` - Gruppi di Tavoli Unibili
```sql
CREATE TABLE wp_rbf_table_groups (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    area_id mediumint(9) NOT NULL,
    max_combined_capacity tinyint(4) NOT NULL DEFAULT 16,
    is_active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY area_id (area_id),
    KEY is_active (is_active)
);
```

**Campi:**
- `id`: Identificativo univoco del gruppo
- `name`: Nome del gruppo (es. "Piccoli Tavoli Sala")
- `area_id`: Area di appartenenza del gruppo
- `max_combined_capacity`: Capacità massima quando i tavoli sono uniti

#### 4. `wp_rbf_table_group_members` - Membri dei Gruppi
```sql
CREATE TABLE wp_rbf_table_group_members (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    group_id mediumint(9) NOT NULL,
    table_id mediumint(9) NOT NULL,
    join_order tinyint(4) NOT NULL DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY group_id (group_id),
    KEY table_id (table_id),
    UNIQUE KEY group_table (group_id, table_id)
);
```

**Campi:**
- `group_id`: Riferimento al gruppo
- `table_id`: Riferimento al tavolo
- `join_order`: Ordine di preferenza per l'unione

#### 5. `wp_rbf_table_assignments` - Assegnazioni Tavoli
```sql
CREATE TABLE wp_rbf_table_assignments (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    booking_id bigint(20) NOT NULL,
    table_id mediumint(9) NOT NULL,
    group_id mediumint(9) DEFAULT NULL,
    assignment_type enum('single','joined') DEFAULT 'single',
    assigned_capacity tinyint(4) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY booking_id (booking_id),
    KEY table_id (table_id),
    KEY group_id (group_id),
    KEY assignment_type (assignment_type)
);
```

**Campi:**
- `booking_id`: Riferimento alla prenotazione WordPress
- `table_id`: Tavolo assegnato
- `group_id`: Gruppo di appartenenza (se assegnazione multipla)
- `assignment_type`: Tipo di assegnazione ('single' o 'joined')
- `assigned_capacity`: Capacità utilizzata

## Algoritmi di Assegnazione

### 1. First-Fit Algorithm

L'algoritmo principale implementato è il **First-Fit** con ottimizzazioni:

```php
function rbf_assign_tables_first_fit($people_count, $date, $time, $meal)
```

#### Step 1: Ricerca Tavolo Singolo
1. Ottenere tutti i tavoli disponibili per data/ora/pasto
2. Ordinare i tavoli per capacità (dal più piccolo)
3. Trovare il primo tavolo che soddisfa:
   - `table.capacity >= people_count`
   - `people_count >= table.min_capacity`

#### Step 2: Ricerca Combinazioni di Tavoli
Se nessun tavolo singolo è disponibile:
1. Iterare per ogni area
2. Per ogni gruppo di tavoli unibili nell'area:
   - Filtrare i tavoli disponibili nel gruppo
   - Cercare combinazioni ottimali (pairs, triplets)
   - Verificare che la capacità combinata non ecceda il limite del gruppo

#### Step 3: Algoritmo di Combinazione
```php
function rbf_find_table_combination($available_tables, $people_count, $max_capacity)
```

**Strategia di ricerca:**
1. **Single table**: Prova ogni tavolo singolarmente
2. **Pairs**: Prova tutte le combinazioni di 2 tavoli
3. **Triplets**: Prova tutte le combinazioni di 3 tavoli

**Criteri di ottimizzazione:**
- Preferenza per la capacità combinata più vicina al richiesto
- Ordinamento per capacità crescente (per evitare spreco)

### 2. Split/Merge Logic

Il sistema supporta automaticamente:

#### Split (Divisione)
- Un tavolo può essere utilizzato per gruppi più piccoli della sua capacità
- Verifica che il gruppo non sia sotto la `min_capacity`

#### Merge (Unione)
- Più tavoli dello stesso gruppo possono essere uniti
- Verifica che la capacità totale non ecceda `max_combined_capacity`
- Rispetta l'ordine di preferenza (`join_order`)

## API Functions

### Funzioni Principali

#### Gestione Aree
```php
rbf_get_areas()                    // Ottiene tutte le aree
rbf_get_tables_by_area($area_id)   // Ottiene tavoli per area
```

#### Gestione Tavoli
```php
rbf_get_all_tables()               // Ottiene tutti i tavoli attivi
rbf_check_table_availability($date, $time, $meal)  // Verifica disponibilità
```

#### Gestione Gruppi
```php
rbf_get_table_groups_by_area($area_id)  // Ottiene gruppi per area
rbf_get_group_tables($group_id)         // Ottiene tavoli di un gruppo
```

#### Assegnazione
```php
rbf_assign_tables_first_fit($people_count, $date, $time, $meal)  // Assegnazione automatica
rbf_save_table_assignment($booking_id, $assignment)             // Salva assegnazione
rbf_get_booking_table_assignment($booking_id)                   // Ottiene assegnazione
rbf_remove_table_assignment($booking_id)                        // Rimuove assegnazione
```

## Integrazione con Booking System

### 1. Assegnazione Automatica

Nel file `booking-handler.php`, dopo la creazione della prenotazione:

```php
// Automatic table assignment
$table_assignment = rbf_assign_tables_first_fit($people, $date, $time, $meal);
if ($table_assignment) {
    rbf_save_table_assignment($post_id, $table_assignment);
    
    // Store assignment metadata
    update_post_meta($post_id, 'rbf_table_assignment_type', $table_assignment['type']);
    update_post_meta($post_id, 'rbf_assigned_tables', $table_assignment['total_capacity']);
    
    if ($table_assignment['type'] === 'joined') {
        update_post_meta($post_id, 'rbf_table_group_id', $table_assignment['group_id']);
    }
}
```

### 2. Visualizzazione Admin

Nella lista prenotazioni, colonna "Tavoli":
- Mostra tavoli assegnati con area
- Indica se l'assegnazione è singola o unita
- Mostra capacità totale utilizzata

### 3. Gestione Conflitti

Il sistema previene automaticamente:
- **Double booking**: Stesso tavolo per stesso slot temporale
- **Overcapacity**: Assegnazioni che eccedono i limiti
- **Invalid combinations**: Tavoli non unibili tra loro

## Performance e Ottimizzazioni

### 1. Indicizzazione Database
- Indici su `area_id`, `capacity`, `is_active` per query veloci
- Indici composti per relazioni many-to-many
- Indici su `booking_id` per lookup assegnazioni

### 2. Caching Strategy
- Transient cache per disponibilità tavoli (15 minuti)
- Cache invalidation automatico su nuove prenotazioni
- Lazy loading per gruppi e relazioni

### 3. Query Optimization
- Join efficienti per ottenere dati aggregati
- Prepared statements per sicurezza
- Limitazione risultati per paginazione

## Testing

### Test Suite Inclusi

Il sistema include test completi in `tests/table-management-tests.php`:

1. **Single Table Assignment Tests**
   - Assegnazione tavolo ottimale
   - Gestione capacità
   - Scenari di fallback

2. **Joined Table Assignment Tests**
   - Combinazioni multiple tavoli
   - Rispetto limiti gruppo
   - Ottimizzazione capacità

3. **Constraint Tests**
   - Verifica min/max capacity
   - Controlli di validazione
   - Edge cases

4. **Availability Tests**
   - Filtraggio tavoli occupati
   - Verifica conflitti temporali
   - Stato tavoli attivi/inattivi

### Esecuzione Test
```bash
cd /path/to/plugin
php tests/table-management-tests.php
```

## Setup Automatico

### Configurazione Default

Il plugin crea automaticamente all'attivazione:

#### Aree Default
- **Sala Principale**: Area principale del ristorante
- **Dehors**: Area esterna

#### Tavoli Default
**Sala Principale:**
- T1, T2 (capacità 2)
- T3, T4 (capacità 4)  
- T5, T6 (capacità 6)
- T7, T8 (capacità 8)

**Dehors:**
- D1, D2 (capacità 4)
- D3, D4 (capacità 6)

#### Gruppi Default
- **Piccoli Tavoli Sala**: T1, T2, T3, T4 (max 8 persone)
- **Tavoli Medi Sala**: T5, T6 (max 12 persone)
- **Tavoli Dehors**: D1, D2, D3, D4 (max 12 persone)

## Esempi d'Uso

### Scenario 1: Prenotazione 2 Persone
```
Input: 2 persone, Pranzo, 2024-01-15, 13:00
Algoritmo: First-fit single table
Risultato: Assegnazione T1 (capacità 2)
Tipo: single
```

### Scenario 2: Prenotazione 10 Persone
```
Input: 10 persone, Cena, 2024-01-15, 20:00
Algoritmo: First-fit joined tables
Ricerca: Nessun tavolo singolo sufficiente
Combinazione: T5 (6) + T3 (4) = 10 persone
Risultato: Assegnazione T5+T3 nel gruppo "Piccoli Tavoli Sala"
Tipo: joined
```

### Scenario 3: Prenotazione 16 Persone
```
Input: 16 persone, Cena, 2024-01-15, 20:00
Algoritmo: First-fit con multiple combinazioni
Ricerca: Combinazione massima nel gruppo
Risultato: T7 (8) + T8 (8) = 16 persone
Tipo: joined (se configurato gruppo per tavoli grandi)
```

## Manutenzione e Monitoring

### Log e Debug
- Errori di assegnazione loggati in WordPress error log
- Transient cache con TTL configurabile
- Debug mode per tracciare algoritmo di assegnazione

### Pulizia Dati
- Rimozione automatica assegnazioni per prenotazioni cancellate
- Cleanup transient cache all'attivazione plugin
- Verifiche integrità referenziale

### Backup e Migrazione
- Esportazione configurazione tavoli via CSV
- Importazione batch tramite admin interface
- Compatibilità con backup WordPress standard

---

**Versione**: 1.0  
**Compatibilità**: WordPress 5.0+, PHP 7.4+  
**Database**: MySQL 5.7+, MariaDB 10.2+