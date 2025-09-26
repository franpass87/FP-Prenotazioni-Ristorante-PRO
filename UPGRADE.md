# Guida all'aggiornamento

Questa guida descrive i passaggi consigliati per aggiornare FP Prenotazioni Ristorante PRO dalla serie 1.6.x alla release **1.7.0** completata con il playbook di audit.

## ✅ Prerequisiti

- **WordPress 6.0+** e **PHP 7.4+** (il plugin supporta fino a PHP 8.2).
- Backup completo di database e file `wp-content/uploads/`.
- Permessi per eseguire WP-CLI (opzionale ma consigliato) e accesso al cron di WordPress.

## 🔄 Procedura consigliata

1. **Metti in manutenzione lo staging** e aggiorna il plugin caricando il pacchetto `dist/fp-prenotazioni-ristorante-pro-1.7.0.zip`.
2. **Visita l'area admin** (o esegui `wp option get rbf_plugin_version`) per permettere al nuovo `RBF_Upgrade_Manager` di rilevare la versione e avviare le migrazioni.
3. Attendi il completamento del redirect: al termine l'upgrade manager invalida cache, transients, opcache e registra la nuova versione in `rbf_plugin_version`.
4. **Verifica i registri runtime** in `Prenotazioni → Registri Runtime` per assicurarti che non siano presenti notice o warning post-upgrade.
5. Una volta confermato il corretto funzionamento in staging, replica la stessa procedura in produzione.

## 🗃️ Migrazioni 1.7.0

- Creazione della tabella dedicata `{$wpdb->prefix}rbf_booking_status` con indice sullo stato e timestamp aggiornato.
- Backfill automatico da `postmeta` legacy (`rbf_booking_status`) e normalizzazione dei valori ammessi.
- Pianificazione/riattivazione del cron giornaliero `rbf_update_booking_statuses` per mantenere sincronizzata la tabella.
- Aggiornamento delle signature build, invalidazione delle cache runtime (`rbf_invalidate_settings_cache`) e flush di `wp_cache`/OPcache.

## 🌐 Ambienti multisite

- Il manager utilizza `rbf_get_network_aware_option()` per tracciare la versione installata a livello di rete. Effettua l'aggiornamento dal **Network Admin** per propagare le migrazioni ai singoli siti.
- Dopo l'upgrade, gli helper di invalidazione cancellano transients sia sul blog corrente che a livello network, mantenendo in sync notice e metadati condivisi.

## 🧪 Verifiche post-upgrade

- **Prenotazioni esistenti**: apri alcune prenotazioni create prima dell'aggiornamento e conferma che lo stato venga caricato dalla nuova tabella.
- **Cron**: controlla `wp cron event list | grep rbf_update_booking_statuses` (via WP-CLI) per assicurarti che l'evento sia schedulato.
- **Log runtime**: conferma che non vengano registrati errori dopo la migrazione.
- **CI/Test**: esegui `composer install && vendor/bin/phpunit` per verificare che la suite continui a passare nel tuo ambiente.

## ❓ Troubleshooting

- Se la tabella `rbf_booking_status` non viene creata automaticamente, verifica i permessi del database e riesegui `wp option delete rbf_plugin_version` seguito da un refresh dell'admin per forzare nuovamente la migrazione.
- In caso di multi-server con OPcache persistente, assicurati che la funzione `rbf_flush_plugin_opcache()` sia consentita; in alternativa svuota manualmente OPcache dopo il deploy.
- Per reinstallare il cron giornaliero puoi eseguire `wp cron event schedule rbf_update_booking_statuses now daily`.

Per ulteriori dettagli sulle migrazioni consulta anche [docs/audit/upgrade-migrations.md](docs/audit/upgrade-migrations.md).
