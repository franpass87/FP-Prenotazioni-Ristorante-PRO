# Phase 10 – Documentazione & Release

## Sommario
- Aggiornati README, CHANGELOG e `fppr-brand.json` alla versione **1.7.0** con le principali novità dell'audit.
- Creata la guida [UPGRADE.md](../../UPGRADE.md) con istruzioni dettagliate su migrazioni, multisite e checklist post-release.
- Preparato il pacchetto distribuibile `dist/fp-prenotazioni-ristorante-pro-1.7.0.zip` con checksum SHA256.

## Test eseguiti
- `composer install`
- `vendor/bin/phpunit`

## QA manuale
- Verifica delle note di migrazione in `includes/core/upgrade-manager.php` e confronto con la guida UPGRADE.
- Controllo dei collegamenti incrociati nella documentazione (`README`, `docs/audit/*`).
- Conferma della presenza di log runtime e utilities multisite nella documentazione aggiornata.

## Packaging
- Copia selettiva dei file di produzione (PHP, assets, languages, readme, brand config) in una directory temporanea.
- Creazione dello zip `dist/fp-prenotazioni-ristorante-pro-1.7.0.zip` e del relativo checksum `dist/fp-prenotazioni-ristorante-pro-1.7.0.zip.sha256`.
- Convalida manuale dell'archivio verificando la presenza del bootstrap, della cartella `includes/`, delle traduzioni e del file `fppr-brand.json`.

## Note
- Restano da affrontare in un ciclo successivo le segnalazioni pendenti di PHPCS/PHPStan (documentate nella fase linting).
- Dopo il deploy in produzione monitorare i registri runtime e il cron `rbf_update_booking_statuses` come indicato in UPGRADE.md.
