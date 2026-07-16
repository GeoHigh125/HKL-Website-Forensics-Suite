# Ontwerpbeslissing 0001

## Eerst classificeren, daarna analyseren

De HKL Website Forensics Suite voert nooit direct malwaredetectie uit.

Iedere scan bestaat uit:

1. Platformdetectie
2. Directoryclassificatie
3. Bestandsclassificatie
4. Malwareanalyse

Hierdoor worden false positives aanzienlijk verminderd.

# Ontwerpbeslissing 0002

## Batchverwerking

Een volledige inventarisatie van een website wordt nooit in één HTTP-request uitgevoerd.

Redenen:

- voorkomt time-outs;
- maakt een voortgangsbalk mogelijk;
- schaalbaar naar honderdduizenden bestanden;
- geschikt voor alle ondersteunde platformen.

# Ontwerpbeslissing 0003

## Asynchrone verwerking

Alle grote scans worden uitgevoerd via batchverwerking.

Voordelen:

- geen time-outs;
- schaalbaar;
- realtime voortgang;
- herstartbare scans;
- voorbereiding voor parallelle analyses.

De scan-engine is volledig losgekoppeld van de gebruikersinterface via een API-laag.

