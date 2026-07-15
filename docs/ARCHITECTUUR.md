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

Vanaf Commit 0003a worden volledige websites niet meer binnen één HTTP-request verwerkt.

Iedere scan bestaat uit:

1. Initialiseren
2. Bestanden verzamelen
3. Batchverwerking
4. Voortgangsregistratie
5. Rapportage

Hierdoor blijft de scanner geschikt voor zeer grote websites en worden PHP time-outs voorkomen.