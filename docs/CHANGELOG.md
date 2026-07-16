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

## Commit 0003a – Asynchronous Scan Engine

### Nieuw

- BatchFileInventory geïntroduceerd.
- Asynchrone scanarchitectuur geïmplementeerd.
- ScanController toegevoegd.
- BatchController toegevoegd.
- ProgressController toegevoegd.
- ApiRouter toegevoegd.
- JSON API (`public/api.php`) toegevoegd.
- JavaScript Scan Engine ontwikkeld.
- Dashboard uitgebreid met live voortgang.

### Verbeteringen

- Time-out van grote scans opgelost door batchverwerking.
- SHA-256 als standaard integriteitshash voor inventarisaties.
- Scanresultaten worden tussentijds opgeslagen.
- Voortgang wordt realtime bijgewerkt.

### Resultaat

- Grote websites kunnen zonder PHP time-out worden verwerkt.
- Eerste succesvolle scan uitgevoerd op een MediaWiki-installatie.
- Getest op ruim **46.000 bestanden**.

## 0.3.1 — Commit 0003b

### Nieuw

- MediaWikiRiskScanner toegevoegd.
- BatchMediaWikiRiskScanner toegevoegd.
- RiskController toegevoegd.
- Nieuwe risico-endpoints toegevoegd aan de API-router.
- JavaScript Risk Engine toegevoegd.
- Risicoanalyse gekoppeld aan de bestaande inventarisatiescan.
- Live risicotellingen toegevoegd voor:
  - kritiek;
  - hoog;
  - middel;
  - laag.
- Bevindingenoverzicht toegevoegd met bestand, categorie, reden en indicatoren.

### Technische werking

Na voltooiing van de bestandsinventarisatie start automatisch een tweede batchfase:

1. inventarisrecords lezen;
2. uitvoerbare bestanden in risicolocaties herkennen;
3. verdachte bestandsnamen controleren;
4. PHP-codepatronen beoordelen;
5. bevindingen opslaan in `findings.jsonl`;
6. samenvatting opslaan in `risk-summary.json`.

### Praktijktest

De eerste volledige MediaWiki-risicoscan is succesvol uitgevoerd op:

- 46.044 bestanden;
- 68 initiële bevindingen;
- 6 critical;
- 4 high;
- 0 medium;
- 58 low.

### Evaluatie

De volledige technische keten werkt:

- asynchrone inventarisatie;
- automatische risicoanalyse;
- batchverwerking;
- live voortgang;
- opslag en weergave van bevindingen.

De praktijktest toont daarnaast dat de detectieregels nog contextgevoeliger moeten worden. Officiële MediaWiki-core-, extension-, maintenance- en vendorbestanden veroorzaken momenteel nog false positives.

