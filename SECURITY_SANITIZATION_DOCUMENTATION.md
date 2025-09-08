# Documentazione Tecniche di Sanitizzazione e Sicurezza

## Panoramica

Questo documento descrive le tecniche di sanitizzazione e sicurezza implementate nel plugin FP Prenotazioni Ristorante PRO per prevenire vulnerabilità di sicurezza come XSS, injection attacks e altri vettori di attacco.

## Sanitizzazione degli Input

### Funzioni di Sanitizzazione Principali

#### `rbf_sanitize_input_fields()`
Funzione centralizzata per la sanitizzazione degli input che supporta diversi tipi di dati:

- **text**: Sanitizzazione rigorosa per campi di testo
- **email**: Validazione e sanitizzazione email 
- **textarea**: Sanitizzazione per campi di testo multi-linea
- **name**: Validazione specifica per nomi (solo caratteri alfabetici)
- **phone**: Validazione per numeri di telefono
- **int/float**: Conversione sicura a numeri
- **url**: Sanitizzazione URL

#### `rbf_sanitize_text_strict()`
Sanitizzazione rigorosa che rimuove:
- Tag HTML e script
- Protocolli pericolosi (javascript:, data:, vbscript:)
- Event handlers (onclick, onload, etc.)
- Caratteri di controllo e null bytes

#### `rbf_sanitize_name_field()`
Validazione specifica per nomi che:
- Permette solo caratteri alfabetici, spazi, trattini, apostrofi
- Limita la lunghezza a 100 caratteri
- Rimuove caratteri potenzialmente pericolosi

#### `rbf_sanitize_phone_field()`
Validazione per numeri di telefono che:
- Permette solo numeri, spazi, trattini, parentesi, segno più
- Limita la lunghezza a 20 caratteri
- Rimuove caratteri non validi

## Sicurezza Email Templates

### Escaping per Contesti HTML

#### `rbf_escape_for_email()`
Funzione per l'escaping sicuro nei template email che supporta diversi contesti:

- **html**: Escaping per contenuto HTML (`esc_html()`)
- **attr**: Escaping per attributi HTML (`esc_attr()`)
- **url**: Escaping per URL (`esc_url()`)
- **subject**: Prevenzione header injection in oggetti email

### Prevenzione Header Injection
- Rimozione di caratteri CR/LF (\r\n) dagli oggetti email
- Sanitizzazione di tutti gli input utente utilizzati negli header

### Template Email Sicuri
Tutti i dati utente nei template email sono processati attraverso:
```php
$safe_first_name_html = rbf_escape_for_email($first_name, 'html');
$safe_email_html = rbf_escape_for_email($email, 'html');
// etc.
```

## Generazione ICS Sicura

### `rbf_generate_ics_content()`
Genera file calendario ICS con sanitizzazione completa:
- Escaping specifico per formato ICS
- Validazione datetime
- Generazione UID sicuri
- Limitazione lunghezza contenuti

### `rbf_escape_for_ics()`
Escaping specifico per formato ICS:
- Escaping di caratteri speciali (`;`, `,`, `\`)
- Conversione line breaks (`\n`)
- Rimozione caratteri di controllo
- Limitazione lunghezza a 250 caratteri

## Validazioni Avanzate

### Validazione Telefono Potenziata
```php
function rbf_validate_phone($phone) {
    // Sanitizzazione base
    $phone = rbf_sanitize_phone_field($phone);
    
    // Validazione lunghezza (min 8 cifre)
    $digits_only = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits_only) < 8) {
        return ['error' => true, 'message' => '...'];
    }
    
    // Rilevamento pattern sospetti (tutte cifre uguali)
    if (preg_match('/^(\d)\1+$/', $digits_only)) {
        return ['error' => true, 'message' => '...'];
    }
    
    return $phone;
}
```

### Prevenzione Attacchi Specifici

#### XSS (Cross-Site Scripting)
- Rimozione tag `<script>`, `<iframe>`, `<object>`, `<embed>`
- Blocco protocolli `javascript:`, `data:`, `vbscript:`
- Rimozione event handlers (`onclick`, `onload`, etc.)
- Escaping HTML in tutti i contesti di output

#### Injection Attacks
- Rimozione null bytes (`\0`)
- Sanitizzazione caratteri di controllo
- Prevenzione header injection in email
- Validazione rigorosa dei formati di input

#### Buffer Overflow Prevention
- Limitazione lunghezza input (nomi: 100 char, telefoni: 20 char)
- Troncamento stringhe eccessive
- Validazione range numerici

## Test di Sicurezza

### Test Suite Completa
Il file `tests/security-sanitization-tests.php` include:

1. **Test Sanitizzazione Input**
   - Rimozione XSS
   - Validazione campi specifici
   - Gestione caratteri speciali

2. **Test Prevenzione XSS**
   - 19 payload XSS diversi
   - Vettori di attacco moderni
   - Bypass encoding

3. **Test Injection Attacks**
   - Header injection
   - Null byte injection
   - Caratteri di controllo

4. **Test Sicurezza Email**
   - Escaping HTML
   - Escaping attributi
   - Sicurezza URL

5. **Test Generazione ICS**
   - Escaping formato ICS
   - Gestione line breaks
   - Validazione contenuti

6. **Test Edge Cases**
   - Input vuoti
   - Input molto lunghi
   - Caratteri Unicode
   - Pattern sospetti

7. **Test Payload Malevoli**
   - 16 categorie di attacchi
   - Test su tutti i contesti
   - Validazione output sicuro

## Linee Guida per Sviluppatori

### Best Practices

1. **Input Validation**
   ```php
   // Sempre sanitizzare input utente
   $sanitized = rbf_sanitize_input_fields($_POST, [
       'nome' => 'name',
       'email' => 'email',
       'telefono' => 'phone'
   ]);
   ```

2. **Output Escaping**
   ```php
   // Sempre escape nei template
   echo rbf_escape_for_email($user_input, 'html');
   ```

3. **Validazione Specifica**
   ```php
   // Usare validatori specifici
   $email = rbf_validate_email($_POST['email']);
   $phone = rbf_validate_phone($_POST['phone']);
   ```

### Aggiunta Nuovi Campi

Quando si aggiungono nuovi campi:

1. Definire il tipo di sanitizzazione appropriato
2. Aggiungere validazione specifica se necessario
3. Assicurarsi dell'escaping corretto nell'output
4. Aggiungere test di sicurezza

### Monitoraggio

- I log di errore registrano tentativi di input malevoli
- Test di sicurezza regolari con `php tests/security-sanitization-tests.php`
- Revisione periodica delle funzioni di sanitizzazione

## Checklist Sicurezza

- ✅ Tutti gli input utente sono sanitizzati
- ✅ Output escapato in tutti i contesti (HTML, attributi, URL)
- ✅ Prevenzione header injection nelle email
- ✅ Generazione ICS sicura
- ✅ Validazione telefoni avanzata
- ✅ Limitazione lunghezza input
- ✅ Rimozione caratteri pericolosi
- ✅ Test di sicurezza completi
- ✅ Documentazione aggiornata

## Riferimenti

- [WordPress Security Handbook](https://developer.wordpress.org/plugins/security/)
- [OWASP Input Validation](https://owasp.org/www-project-web-security-testing-guide/v42/4-Web_Application_Security_Testing/07-Input_Validation_Testing/)
- [RFC 5545 - iCalendar Specification](https://tools.ietf.org/html/rfc5545)