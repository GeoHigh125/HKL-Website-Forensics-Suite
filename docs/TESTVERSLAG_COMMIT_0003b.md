# Testverslag Commit 0003b

## Doel

Controleren of de MediaWiki IOC Detection Engine automatisch kan starten na de bestandsinventarisatie en de bevindingen batchgewijs kan verwerken.

## Testomgeving

Platform:

MediaWiki

Bron:

Lokale kopie van de productieomgeving.

## Uitvoering

De scanner heeft eerst een volledige bestandsinventarisatie uitgevoerd en daarna automatisch de risicoanalyse gestart.

## Resultaten

| Onderdeel | Resultaat |
|---|---:|
| Geïnventariseerde bestanden | 46.044 |
| Geanalyseerde inventarisrecords | 46.044 |
| Critical | 6 |
| High | 4 |
| Medium | 0 |
| Low | 58 |
| Totaal | 68 |
| PHP-time-out | Niet opgetreden |
| API-koppeling | Werkend |
| Live voortgang | Werkend |
| Opslag bevindingen | Werkend |

## Bevindingen

De eerste detectieregels herkennen risicopatronen correct, maar melden ook officiële MediaWiki-code.

Voorbeelden van vermoedelijke false positives:

- officiële Math-extension;
- MediaWiki Shell-classes;
- maintenance-scripts;
- Wikimedia vendorbibliotheken;
- taal- en wachtwoordenbestanden.

## Conclusie

Commit 0003b is functioneel geslaagd.

De volgende ontwikkelfase richt zich op precisie en context, zodat officiële MediaWiki-code niet langer onterecht als kritisch of hoog risico wordt geclassificeerd.