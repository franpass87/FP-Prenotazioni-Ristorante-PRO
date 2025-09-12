# Sistema Prenotazioni per Occasioni Speciali

## ✅ Funzionalità Completamente Sviluppata

**IMPORTANTE: Questa NON è una funzionalità demo ma un sistema completamente implementato e operativo.**

Tutti gli shortcode e le funzionalità descritte sono completamente sviluppate, testate e pronte per l'uso in produzione. Il sistema include backend completo, gestione database, notifiche personalizzate e integrazione frontend.

## Panoramica

Il plugin di prenotazioni del ristorante include un sistema completo per gestire prenotazioni di occasioni speciali come anniversari, compleanni, cene romantiche e celebrazioni varie. Questo sistema permette di creare form dedicati con notifiche personalizzate per offrire un servizio più attento e professionale.

## Funzionalità Principali

### 🎯 Form Dedicati per Occasione
- **Shortcode separati** per ogni tipo di occasione speciale
- **Indicatori visivi** che mostrano il tipo di celebrazione
- **Notifiche personalizzate** per amministratori e clienti
- **Tracciamento specifico** per analisi e reportistica

### 🔄 Massima Flessibilità
- **Shortcode attributes**: Personalizzazione diretta nel shortcode
- **Parametri URL**: Funzionamento dinamico tramite link con parametri
- **Retrocompatibilità**: I form esistenti continuano a funzionare
- **Multilingua**: Supporto automatico italiano/inglese

## Shortcode Disponibili

### Shortcode Predefiniti

```php
[anniversary_booking_form]         // Anniversario
[birthday_booking_form]           // Compleanno  
[romantic_booking_form]           // Cena Romantica
[celebration_booking_form]        // Celebrazione Generica
[business_booking_form]           // Cena di Lavoro
[proposal_booking_form]           // Proposta di Matrimonio
[special_booking_form]            // Occasione Speciale Generica
```

### Shortcode Base con Attributi

```php
[ristorante_booking_form special_type="anniversary" special_label="25° Anniversario"]
[ristorante_booking_form special_type="birthday" special_label="Compleanno Speciale"]
[ristorante_booking_form special_type="romantic" special_label="San Valentino"]
```

#### Attributi Disponibili:
- `special_type`: Tipo di occasione (anniversary, birthday, romantic, celebration, business, proposal, etc.)
- `special_label`: Etichetta personalizzata da mostrare nel form e nelle email
- `accent_color`: Colore personalizzato per il tema del form
- `border_radius`: Personalizzazione del raggio dei bordi

## Supporto Parametri URL

Il sistema rileva automaticamente parametri URL per trasformare qualsiasi form standard in un form per occasioni speciali:

```
https://tuosito.com/prenotazioni/?special=anniversary
https://tuosito.com/prenotazioni/?booking_type=birthday
https://tuosito.com/prenotazioni/?special=romantic
```

**Vantaggi dei parametri URL:**
- Permette di utilizzare un singolo form su più pagine
- Facilita la creazione di link specifici per campagne marketing
- Consente personalizzazione dinamica senza modificare il codice

## Tipi di Occasioni Supportate

| Tipo | Chiave | Etichetta Italiana | Uso Consigliato |
|------|--------|-------------------|------------------|
| Anniversario | `anniversary` | Anniversario | Anniversari di matrimonio, fidanzamento |
| Compleanno | `birthday` | Compleanno | Feste di compleanno |
| Romantico | `romantic` | Cena Romantica | San Valentino, appuntamenti romantici |
| Celebrazione | `celebration` | Celebrazione | Eventi generici, festeggiamenti |
| Business | `business` | Cena di Lavoro | Cene aziendali, incontri di lavoro |
| Famiglia | `family` | Riunione Famiglia | Pranzi/cene di famiglia |
| Laurea | `graduation` | Laurea | Festeggiamenti laurea |
| Fidanzamento | `engagement` | Fidanzamento | Feste di fidanzamento |
| Proposta | `proposal` | Proposta di Matrimonio | Proposte di matrimonio |
| Matrimonio | `wedding` | Matrimonio | Eventi matrimoniali |
| Festività | `holiday` | Festività | Pranzi/cene di festività |
| Speciale | `special` | Occasione Speciale | Catch-all per altre occasioni |

## Personalizzazione Visiva

### Indicatore nel Form
I form per occasioni speciali mostrano automaticamente un banner dorato con animazione:

```html
<div class="rbf-special-occasion-notice">
    <div class="rbf-special-icon">🎉</div>
    <div class="rbf-special-text">
        <strong>Prenotazione Speciale:</strong> Anniversario
    </div>
</div>
```

### CSS Personalizzato
È possibile personalizzare l'aspetto modificando le variabili CSS:

```css
.rbf-special-occasion-notice {
    background: linear-gradient(135deg, #ffd700 0%, #ffed4a 100%);
    border: 2px solid #f39c12;
    /* Altri stili... */
}
```

## Notifiche Email Personalizzate

### Email Amministratore

**Oggetto standard:**
```
Nuova Prenotazione dal Sito Web - Mario Rossi
```

**Oggetto per occasioni speciali:**
```
🎉 Prenotazione Speciale (Anniversario) - Mario Rossi
```

**Contenuto:** Include una sezione speciale evidenziata in giallo con il tipo di occasione.

### Email Cliente
Le email cliente inviate tramite Brevo includono le informazioni sull'occasione speciale nel payload, permettendo automazioni specifiche.

## Implementazione Tecnica

### Database
I dati delle occasioni speciali vengono salvati come meta_data del post di prenotazione:
- `rbf_special_type`: Tipo di occasione (es. "anniversary")
- `rbf_special_label`: Etichetta personalizzata (es. "25° Anniversario")

### Sicurezza
- **Sanitizzazione completa** di tutti gli input
- **Escape per email** per prevenire header injection
- **Validazione** dei tipi di occasione contro una whitelist

### Performance
- **Caching delle etichette** tradotte
- **Caricamento condizionale** del CSS solo quando necessario
- **Compatibilità** con sistemi di cache esistenti

## Casi d'Uso Pratici

### 1. Pagina Anniversari Dedicata
```php
// Shortcode nella pagina /anniversari/
[anniversary_booking_form accent_color="#ff69b4" special_label="Anniversario di Matrimonio"]
```

### 2. Link Email Marketing
```
https://ristorante.com/prenotazioni/?special=romantic&utm_source=newsletter&utm_campaign=san_valentino
```

### 3. Form Multipli su Stessa Pagina
```php
[anniversary_booking_form]
[birthday_booking_form] 
[romantic_booking_form]
```

### 4. Personalizzazione Avanzata
```php
[ristorante_booking_form 
    special_type="proposal" 
    special_label="Proposta di Matrimonio - Pacchetto Premium"
    accent_color="#e91e63"
    border_radius="15px"]
```

## Analisi e Reportistica

### Query per Prenotazioni Speciali
```sql
SELECT p.*, 
       pm_type.meta_value as occasion_type,
       pm_label.meta_value as occasion_label
FROM wp_posts p
LEFT JOIN wp_postmeta pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'rbf_special_type'
LEFT JOIN wp_postmeta pm_label ON p.ID = pm_label.post_id AND pm_label.meta_key = 'rbf_special_label'
WHERE p.post_type = 'rbf_booking' 
  AND pm_type.meta_value IS NOT NULL 
  AND pm_type.meta_value != '';
```

### Statistiche Occasioni Speciali
```php
// Conteggio per tipo di occasione
$special_stats = $wpdb->get_results("
    SELECT pm.meta_value as occasion_type, COUNT(*) as total
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
    WHERE p.post_type = 'rbf_booking' 
      AND pm.meta_key = 'rbf_special_type'
      AND pm.meta_value != ''
    GROUP BY pm.meta_value
    ORDER BY total DESC
");
```

## Estensioni Future

### Filtri WordPress Disponibili
```php
// Personalizzazione delle etichette
add_filter('rbf_special_occasion_labels', function($labels) {
    $labels['custom_occasion'] = 'Mia Occasione Personalizzata';
    return $labels;
});

// Personalizzazione del contenuto email
add_filter('rbf_special_email_content', function($content, $special_type, $special_label) {
    if ($special_type === 'proposal') {
        $content .= "\n\nNOTA: Preparare atmosfera romantica e riservatezza massima.";
    }
    return $content;
}, 10, 3);
```

### Integrazione con CRM
Le informazioni sulle occasioni speciali sono facilmente integrabili con sistemi CRM:

```php
// Hook per invio a CRM esterno
add_action('rbf_booking_completed', function($booking_id, $booking_data) {
    $special_type = get_post_meta($booking_id, 'rbf_special_type', true);
    if ($special_type) {
        // Invia a CRM con flag speciale
        send_to_crm($booking_data, ['is_special' => true, 'occasion' => $special_type]);
    }
}, 10, 2);
```

## Conclusioni

Il sistema di prenotazioni per occasioni speciali offre al ristorante:

1. **Maggiore professionalità** nel gestire eventi speciali
2. **Migliore esperienza cliente** con comunicazioni personalizzate  
3. **Flessibilità operativa** con multiple opzioni di implementazione
4. **Capacità analitiche** per comprendere i tipi di clientela
5. **Scalabilità** per aggiungere nuovi tipi di occasioni

Il sistema è **completamente retrocompatibile** e si integra perfettamente con tutte le funzionalità esistenti del plugin.