=== FP Prenotazioni Ristorante PRO ===
Contributors: francescopasseri
Tags: prenotazioni, ristorante, calendario, booking, marketing
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sistema avanzato di prenotazioni per ristoranti con calendario Flatpickr multilingue, gestione intelligente dei tavoli, notifiche email resilienti e tracciamento marketing GA4/Meta pronto per la produzione.

== Descrizione ==

FP Prenotazioni Ristorante PRO offre un flusso di prenotazione completo progettato per ristoranti che vogliono una soluzione professionale e conforme agli standard WordPress.

* Calendario Flatpickr con lingue italiana/inglese e tooltip di disponibilità avanzati.
* Gestione capienza per servizio con buffer configurabili e controllo overbooking.
* Suggerimenti automatici di fasce orarie alternative quando non c'è disponibilità.
* Integrazione email affidabile con failover SMTP e log consultabili.
* Tracking marketing completo: GA4, Meta Pixel/CAPI, Google Ads, bucket di attribuzione.
* Gestione tavoli intelligente con aree, tavoli unibili e ottimizzazione delle assegnazioni.
* Privacy ready: esportazione/cancellazione dati, consenso marketing, policy configurabile.
* UX curata: validazione live, pulsante di invio intelligente, riepilogo in tempo reale.

== Installazione ==

1. Carica l'archivio del plugin nella directory `/wp-content/plugins/` oppure utilizza "Aggiungi nuovo" da WordPress.
2. Attiva il plugin dal menu "Plugin" di WordPress.
3. Visita **Prenotazioni → Impostazioni** per configurare servizi, disponibilità, notifiche e tracking.
4. Inserisci lo shortcode `[ristorante_booking_form]` nella pagina di prenotazione desiderata.

== FAQ ==

= Il plugin funziona in modalità multisite? =
Sì. Durante l'attivazione di rete vengono create le tabelle necessarie su ogni sito e le nuove installazioni ereditano automaticamente la configurazione di base.

= È possibile personalizzare i colori del brand? =
Certamente. Dal pannello impostazioni trovi la sezione "Branding" con variabili CSS centralizzate, oppure puoi sovrascrivere gli stili caricando un file `frontend-custom.css` nel tema child.

= Come vengono gestite le festività o chiusure straordinarie? =
Dalla scheda "Disponibilità" puoi configurare giorni singoli, intervalli o eccezioni con slot personalizzati. Le informazioni vengono sincronizzate con il calendario e mostrate all'utente tramite tooltip.

== Changelog ==

= 1.6 =
* Refactor completo in architettura modulare.
* Aggiunta gestione tavoli e join multipli.
* Nuovi controlli buffer/overbooking e suggerimenti AI.
* Integrazioni marketing potenziate con GA4 Hybrid Tracking e Meta CAPI.
* Sistema di failover email e log con retention configurabile.

== Note ==

Per documentazione avanzata, demo interattive e guide operative consulta il repository GitHub: https://github.com/franpass87/FP-Prenotazioni-Ristorante-PRO
