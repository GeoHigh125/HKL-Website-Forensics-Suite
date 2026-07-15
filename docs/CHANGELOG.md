# Changelog

## 0.1.0 — Commit 0001
- Eerste projectstructuur.
- PSR-4 Composer-autoloading.
- Applicatiekern en MediaWiki-module toegevoegd.
- Alleen-lezen basisdashboard toegevoegd.

# Changelog

## 0.2.0 — Commit 0002

### Nieuw

- MediaWiki Detector toegevoegd.
- Directory Classifier toegevoegd.
- ScanService toegevoegd.
- Dashboard uitgebreid met scanfunctionaliteit.
- Offline scanmodus geïntroduceerd.
- Scan-ID toegevoegd.
- Statistieken per categorie toegevoegd.
- Overzicht van onbekende mappen toegevoegd.

### Verbeterd

- Directory Classifier uitgebreid met:
  - Installer (`mw-config`)
  - Database (`sql`)
- MediaWiki-directoryherkenning verhoogd naar **100%**.

### Resultaat

- 4136 mappen geclassificeerd.
- 4136 bekende mappen.
- 0 onbekende mappen.

## Commit 0003 – File Inventory Engine

### Nieuw

- FileClassifier toegevoegd.
- MetadataEngine toegevoegd.
- HashEngine toegevoegd.
- FileInventory toegevoegd.
- Dashboard uitgebreid met bestandsinventarisatie.
- Eerste integratie tussen directory-, file-, metadata- en hashanalyse.

### Praktijktest

Tijdens de eerste scan van een volledige MediaWiki-installatie bleek dat een complete inventarisatie van alle bestanden niet binnen de standaard PHP-requestlimiet van 30 seconden past.

### Ontwerpbeslissing

De inventarisatie wordt vanaf Commit 0003a vervangen door een asynchrone batch-engine.

### Resultaat

- Fundament voor bestandsanalyse gereed.
- Batcharchitectuur voorbereid.
- SHA-256 gekozen als standaard integriteitshash.