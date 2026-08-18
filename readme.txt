=== Mediamora Anti-Spam voor Elementor Forms ===
Contributors: mediamora
Tags: elementor, anti-spam, forms
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 5.4
License: GPLv2 or later

Weigert Elementor Pro formulier-inzendingen op basis van meerdere signalen: link injection, onleesbare tekst, niet-Latijns schrift, herhaalde trefwoorden, dubbele inhoud, verkorte/wegwerp-links, boekingslinks en vermelding van de eigen domeinnaam.

== Description ==

Interne Mediamora-plugin voor klantsites. Zie de header van
mediamora-anti-spam-elementor.php voor de volledige changelog en
technische toelichting per check.

Instellingen: wp-admin > Instellingen > Mediamora Anti-Spam.

== Installation ==

1. Upload de hele map naar /wp-content/plugins/
2. Activeer de plugin via wp-admin > Plugins
3. Vereist Elementor Pro
4. Stel het rapportage-e-mailadres en overige instellingen in via
   wp-admin > Instellingen > Mediamora Anti-Spam

== Changelog ==

= 5.4 =
* Verwijderd: de structurele herkenning van onbekende verkorte-URL-diensten
  (toegevoegd in 4.4). Die heeft nooit gewerkt: het regex-patroon gebruikte
  '#' als delimiter en bevatte zelf een niet-ontsnapte '#', waardoor
  preg_match_all() bij elke aanroep faalde en een warning naar de errorlog
  schreef. Bij reparatie bleek hij ook normale links te weigeren (7 van 12
  legitieme test-URL's, waaronder /2024, /Contact en /ORD1234), dus in
  plaats van repareren is de check eruit gehaald.
* De lijst met bekende verkorters is ongewijzigd en werkt gewoon; die zat
  in een apart patroon en is nooit door de bug geraakt. Onbekende
  verkorters glippen er nu bewust doorheen: liever een gemiste spammail
  dan een gemiste klant. Kom je er een tegen, zet de domeinnaam op de lijst.
* Uitlegtekst op het instellingenscherm aangepast, die beloofde nog de
  structurele herkenning.

= 5.3 =
* Bugfix: case-wisselings-signaal telt nu per woord i.p.v. over de hele
  spatieloze tekst, zodat namen/meerdere zinnen niet onterecht als
  twijfelgeval worden gemarkeerd.

= 5.2 =
* Belangrijke correctie: de vangnet-mail claimde eerder "inzending als
  geheel geaccepteerd", wat de plugin niet echt kan weten (Elementor's
  eigen honeypot kan los van deze plugin ingrijpen). Tekst nu eerlijker.

= 5.1 =
* Testrelease, geen functionele wijziging (bevestigt de GitHub-updateketen).

= 5.0 =
* Omgezet van mu-plugin naar een normale, activeerbare plugin met
  automatische updates via GitHub.

= 4.6 =
* "telegra.ph" toegevoegd aan de lijst met verkorte/wegwerp-linkdiensten.

= 4.5 =
* Nieuwe check: afspraken-boekingslinks (Calendly, Cal.com, enz.).

= 4.4 =
* Structurele herkenning van onbekende verkorte-URL-diensten.

= 4.3 =
* Vangnet-mail vermeldt nu ook de status van de hele inzending.

= 4.2 =
* Verkorte-URL-detectie toegevoegd.

= 4.1 =
* Detectie van vermelding van de eigen domeinnaam (bulk-outreach).

= 4.0 =
* Instellingen naar de database verplaatst, instellingenscherm toegevoegd.

Voor volledige details per versie: zie de header van
mediamora-anti-spam-elementor.php.
