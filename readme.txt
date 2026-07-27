=== Mediamora Anti-Spam voor Elementor Forms ===
Contributors: mediamora
Tags: elementor, anti-spam, forms
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 5.1
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
