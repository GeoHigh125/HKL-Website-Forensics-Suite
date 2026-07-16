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



