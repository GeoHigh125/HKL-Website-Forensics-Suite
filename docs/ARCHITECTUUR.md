# Architectuur

## Principes

1. Alleen-lezen als standaard.
2. Eerst platform en bestandstype herkennen, daarna malware beoordelen.
3. Eén gemeenschappelijke kern met platformspecifieke modules.
4. Iedere bevinding bevat context, reden en risiconiveau.
5. Geen automatische verwijdering in de eerste releases.

## Scanvolgorde

1. Platformdetectie
2. Directoryclassificatie
3. Bestandsclassificatie
4. Malware-analyse
5. Rapportage

De scanner voert malwaredetectie pas uit nadat de directory- en bestandsclassificatie zijn voltooid. Hierdoor wordt het aantal false positives sterk verminderd.

## Asynchrone scanarchitectuur

Vanaf Commit 0003a worden websites niet langer binnen één HTTP-request verwerkt.

De scan bestaat uit:

1. Initialiseren
2. Manifest opbouwen
3. Batchverwerking
4. Voortgangsregistratie
5. Rapportage

Hierdoor blijft de scanner schaalbaar voor zeer grote websites en wordt de standaard PHP time-out vermeden.

De communicatie tussen gebruikersinterface en scan-engine verloopt volledig via een JSON API.

## Tweefasige scanarchitectuur

Een volledige scan bestaat vanaf Commit 0003b uit twee onafhankelijke fasen.

### Fase 1 — Bestandsinventarisatie

De scanner verzamelt per bestand:

- relatief en absoluut pad;
- directorycategorie;
- bestandstype;
- metadata;
- SHA-256-hash.

De inventaris wordt opgeslagen als `inventory.jsonl`.

### Fase 2 — Risicoanalyse

De risico-engine verwerkt de bestaande inventaris batchgewijs en opent alleen bestanden waarvoor inhoudsanalyse relevant is.

De resultaten worden opgeslagen als:

- `risk-progress.json`;
- `findings.jsonl`;
- `risk-summary.json`.

Daardoor kan de risicoanalyse opnieuw worden uitgevoerd zonder de volledige bestandsinventaris opnieuw op te bouwen.

