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

# Ontwerpbeslissing 0004

## Signaal is geen malwareconclusie

De HKL Website Forensics Suite behandelt een detectieregel als een signaal voor nader onderzoek en niet automatisch als bewijs van malware.

Reden:

- legitieme software kan functies zoals `eval`, `assert`, `proc_open`, `gzinflate` en `base64_decode` gebruiken;
- bestandsnamen zoals `Shell.php`, `License.php` en `Cache.php` kunnen binnen officiële software normaal zijn;
- de locatie en platformcontext zijn noodzakelijk voor een betrouwbare beoordeling.

De risico-engine moet daarom altijd rekening houden met:

1. platform;
2. directorycategorie;
3. bestandstype;
4. combinatie van indicatoren;
5. bekende veilige context;
6. onbekende of risicovolle locatie.

Deze beslissing vormt de basis voor Commit 0003c.

