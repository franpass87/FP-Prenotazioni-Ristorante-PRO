# Changelog

Tutte le modifiche significative di **FP Prenotazioni Ristorante PRO** vengono documentate in questo file.
Il formato segue l'approccio *Keep a Changelog* ed è coerente con la numerazione semantica introdotta dalla versione 1.x.

## [1.6.3] – Allineamento Versione Distribuzione (2024)
### Fixed
- Incrementato il numero di versione del plugin e della documentazione per forzare il deploy delle ultime modifiche.

## [1.6.2] – Versionamento Automatico Asset (2024)
### Fixed
- Calcolo della versione degli asset basato sull'ultima modifica del file per propagare immediatamente gli aggiornamenti senza dover incrementare manualmente la release.

## [1.6.1] – Aggiornamento Asset (2024)
### Fixed
- Incrementata la versione del plugin per forzare l'aggiornamento degli asset admin e rendere visibili le ultime ottimizzazioni grafiche e del filtro analytics.

## [1.6] – Documentazione Consolidata (2024)
### Added
- Indice tematico della documentazione con collegamenti diretti alle singole guide specialistiche.
- File `CHANGELOG.md` per mantenere una cronologia ufficiale e facilmente consultabile.

### Changed
- Aggiornati i metadati di autore e contatto (sito web ed email) in tutto il materiale pubblico.
- Allineati numero di versione del plugin principale e del file `fppr-brand.json` alla release 1.6.

## [1.5] – Release Finale (2024)
### Added
- Completamento di tutte le funzionalità core: gestione tavoli, buffer intelligenti e suggerimenti AI.
- Documentazione completa per accessibilità, UX, marketing avanzato e procedure operative.

### Security
- Hardening sistematico con sanificazione input, protezioni CSRF e controlli anti-bot multilivello.

### Performance
- Ottimizzazione del caricamento asset lato frontend e miglioramento dell'esperienza mobile.

## [1.5-rc2 (tag 10.0.2)] – Stabilizzazione Calendario (2024)
### Changed
- Introduzione del limite fisso di un'ora per le prenotazioni con semplificazione dell'interfaccia oraria.
- Aggiornamento della documentazione calendario per illustrare il nuovo flusso di disponibilità.

## [1.5-rc1 (tag 10.0.1)] – Hotfix Disponibilità (2024)
### Fixed
- Risoluzione di un'anomalia che restituiva zero slot disponibili quando mancavano nuove impostazioni.

## [1.5-rc0 (tag 10.0.0)] – Refactor Architetturale (2024)
### Added
- Architettura modulare con 9 componenti dedicati e validazione UTM avanzata.
- Integrazione Meta CAPI, GA4 full funnel e template email responsive Brevo.

### Changed
- Frontend completamente rivisitato con form multi-step accessibile e ottimizzazioni mobile.

## [2.5] – Legacy Monolitica (Storico)
### Notes
- Struttura originale monolitica (>1100 linee) con tracciamento marketing semplificato e logging di base.

---

**Autore:** Francesco Passeri  
**Sito:** <https://francescopasseri.com>  
**Email:** [info@francescopasseri.com](mailto:info@francescopasseri.com)
