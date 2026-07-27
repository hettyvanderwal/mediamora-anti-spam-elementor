<?php
/**
 * Plugin Name: Mediamora Anti-Spam voor Elementor Forms
 * Description: Weigert Elementor Pro formulier-inzendingen op basis van meerdere signalen (link injection, onleesbare tekst, niet-Latijns schrift, herhaalde trefwoorden, dubbele inhoud). Logt geweigerde inzendingen, stuurt een samenvattend rapport, en heeft een instellingenscherm in wp-admin.
 * Update URI: https://github.com/hettyvanderwal/mediamora-anti-spam-elementor
 *
 * GROTE WIJZIGING t.o.v. 4.6: dit is vanaf nu een ECHTE, activeerbare
 * WordPress-plugin (niet langer een mu-plugin) met automatische updates
 * via GitHub. Zie install-instructies onderaan deze header.
 *
 * BELANGRIJKE NOTITIE VOOR TOEKOMSTIGE BEWERKINGEN:
 * Sla dit bestand altijd op als UTF-8 (zonder BOM) in je editor, ook al
 * bevat de huidige code alleen platte ASCII-tekens. Reden: een eerdere
 * versie gebruikte letterlijke accenttekens (À-Ö, ø-ÿ) in een regex, en
 * die raakten beschadigd doordat het bestand ooit met een andere codering
 * is opgeslagen/overgezet. Gevolg was dat de complete anti-spam check
 * stilzwijgend stopte met werken, zonder foutmelding. Dat specifieke risico
 * is nu weggehaald (alleen nog \x{00C0}-achtige hex-codes, geen letterlijke
 * accenttekens meer), maar bij het toevoegen van nieuwe tekst met accenten
 * of andere speciale tekens is UTF-8 opslaan nog steeds belangrijk.
 *
 * Version: 5.1
 * Author: Mediamora
 *
 * Wijzigingen t.o.v. 5.0:
 * - Geen functionele wijziging. Testrelease om te bevestigen dat de
 *   GitHub-updateketen end-to-end werkt (tag aanmaken → wp-admin toont
 *   "Update beschikbaar" → 1-klik-update).
 *
 * Wijzigingen t.o.v. 4.6:
 * - Omgezet van mu-plugin naar een normale, activeerbare plugin met
 *   automatische updates via GitHub (Plugin Update Checker library).
 *   Bewuste trade-off: een normale plugin kan (per ongeluk) door een
 *   klant gedeactiveerd worden, wat bij een mu-plugin niet kan. Check
 *   daarom af en toe via MainWP of de plugin nog actief is op elke site.
 *
 * Wijzigingen t.o.v. 4.5:
 * - "telegra.ph" (Telegram's gratis publiceerplatform, veelgebruikt voor
 *   crypto-scampagina's) toegevoegd aan de lijst met verkorte/wegwerp-
 *   linkdiensten. Functioneel gedraagt dit zich hetzelfde als een
 *   verkorte URL, ook al is het technisch geen verkorter: een lang,
 *   beschrijvend pad, dus de structurele herkenning ving dit niet.
 *
 * Wijzigingen t.o.v. 4.4:
 * - Nieuwe check: een link naar een afspraken-boekingsdienst (Calendly,
 *   Cal.com, HubSpot Meetings, enz.) wordt geweigerd. Typerend voor
 *   cold-outreach/verkooppitches die de eigen domeinnaam niet noemen (en
 *   dus de "reject_own_domain_mention" check omzeilen), maar wel altijd
 *   een link naar hun eigen boekingsagenda meesturen.
 *
 * Wijzigingen t.o.v. 4.3:
 * - Verkorte-URL-detectie herkent nu ook onbekende/nieuwe diensten
 *   structureel (een kort URL-pad met cijfers/hoofdletters erin, typisch
 *   voor gegenereerde shortener-codes), in plaats van alleen een vaste
 *   naamlijst. Voorkomt de eindeloze kat-en-muis-race van steeds nieuwe
 *   verkorters moeten toevoegen. "clickto.cc" ook aan de naamlijst
 *   toegevoegd.
 *
 * Wijzigingen t.o.v. 4.2:
 * - Vangnet-mail vermeldt nu per twijfelgeval ook of de HELE inzending
 *   (alle velden samen) uiteindelijk is geweigerd omdat een ander veld
 *   wel aansloeg, of dat de inzending als geheel is geaccepteerd. Eerder
 *   kon "dit veld is geaccepteerd" verwarrend zijn: een ander veld in
 *   dezelfde inzending kon alsnog voor een volledige weigering zorgen,
 *   waardoor de inzending niet in Elementor's Inzendingen-lijst
 *   terechtkwam, ook al leek het vangnet te suggereren dat hij was
 *   doorgekomen.
 *
 * Wijzigingen t.o.v. 4.1:
 * - Nieuwe check: verkorte URL-services (bit.ly, tinyurl, psee.io, enz.)
 *   worden altijd geweigerd, ongeacht het aantal. Deze worden vrijwel nooit
 *   door echte bezoekers in contactformulieren gebruikt, maar constant door
 *   spammers (gokwebsites, valse investeringen, enz.).
 *
 * Wijzigingen t.o.v. 4.0:
 * - Nieuwe check: een bericht dat de eigen domeinnaam van de site noemt
 *   ("Ik zag net [domeinnaam] en...") wordt geweigerd. Vangt
 *   bulk-outreach/verkooppitches die met een sjabloon-tool naar veel
 *   sites tegelijk worden gestuurd, ongeacht taal, links of herhaling,
 *   want dat patroon zat in geen van de eerdere checks.
 *
 * GROTE WIJZIGING t.o.v. 3.5:
 * - Alle instellingen staan nu in de database (via get_option/update_option)
 *   in plaats van als vaste define()-waardes in dit bestand. Dat betekent:
 *   als je dit bestand ooit vervangt door een nieuwere versie, blijven
 *   per-site aanpassingen gewoon behouden, want die zitten niet meer in het
 *   bestand zelf.
 * - Nieuw instellingenscherm: wp-admin > Instellingen > Mediamora Anti-Spam.
 *   Alles wat je voorheen via code aanpaste kan daar nu met een formulier.
 * - De rapportmail toont nu ALLE geweigerde inzendingen, geen maximum meer.
 * - Nieuwe instelling: de rapportmail is nu per site aan/uit te zetten
 *   (het logbestand op de server blijft altijd bijgehouden, ook als de
 *   mail uit staat).
 * - Nieuw, onafhankelijk vangnet: bij een GEACCEPTEERDE inzending die
 *   verdacht dicht bij een weigeringsdrempel zat, wordt apart gelogd en
 *   (indien ingeschakeld) gemaild, los van de gewone rapportage. Dit is
 *   een heuristiek en geen garantie, zie de toelichting in het
 *   instellingenscherm en in de mail zelf.
 *
 * BELANGRIJK bij het updaten van een site van vóór versie 4.0: de oude
 * define()-instellingen (bijvoorbeeld een handmatig aangepaste
 * REJECT_NON_LATIN_TEXT op een specifieke site) worden NIET automatisch
 * overgenomen in de database, want define() schreef nooit naar de
 * database. Controleer na het updaten van elke site eenmalig het nieuwe
 * instellingenscherm en zet eventuele per-site afwijkingen opnieuw.
 *
 * Wijzigingen t.o.v. 3.3:
 * - Nieuwe check: overmatige woordherhaling (keyword stuffing), werkt op
 *   verhouding (percentage van het totale bericht) i.p.v. een vast
 *   aantal, zodat lange, natuurlijke berichten niet onterecht geraakt
 *   worden.
 *
 * Wijzigingen t.o.v. 3.2:
 * - MEDIAMORA_ANTISPAM_REJECT_NON_LATIN_TEXT staat standaard op true,
 *   aangezien vrijwel alle klanten een Nederlands- of Europees-talig
 *   publiek hebben.
 *
 * Wijzigingen t.o.v. 2.9 t/m 3.1 (samengevat):
 * - Niet-Latijns-schrift detectie kijkt ook naar een ABSOLUUT aantal
 *   niet-Latijnse letters, naast de verhouding.
 * - KRITIEKE BUGFIX: het e-mailveld wordt volledig overgeslagen door alle
 *   checks (voorheen werd het domeingedeelte van elk e-mailadres
 *   herkend als "verdachte link", wat vrijwel elke inzending blokkeerde).
 *
 * Wijzigingen t.o.v. 2.6 t/m 2.8 (samengevat):
 * - Kale domeinnamen zonder http(s):// worden ook als link herkend.
 * - Link + overwegend niet-Latijns schrift wordt altijd geweigerd.
 * - Alle drempelwaardes kregen benoemde instellingen i.p.v. vaste
 *   getallen in de functies (in 4.0 verder doorgevoerd naar de database).
 *
 * Wijzigingen t.o.v. 2.1 t/m 2.5 (samengevat):
 * - DE GROTE ENCODING-BUG GEVONDEN EN GEFIXT: de regex gebruikte
 *   letterlijke accenttekens die beschadigd raakten door een
 *   coderingsmismatch, waardoor preg_replace() op ELKE aanroep faalde.
 *   Vervangen door Unicode hex-escapes.
 * - Logging naar bestand, samenvattende rapportmail, automatische
 *   opschoning na bewaartermijn, logmap afgeschermd met .htaccess.
 * - Array-waarden (samengestelde velden) correct verwerkt i.p.v. naar de
 *   letterlijke string "Array" gecast.
 *
 * Installatie:
 * - De hele MAP "mediamora-anti-spam-elementor" (dit bestand + de
 *   meegeleverde map "plugin-update-checker") uploaden naar
 *   /wp-content/plugins/ en activeren via wp-admin > Plugins.
 * - Vereist Elementor Pro (voor de forms validation hook).
 * - Vereist dat wp_mail() op deze site werkt (bijvoorbeeld via FluentSMTP),
 *   anders komen de mails mogelijk niet aan.
 * - Instellingen aanpassen via wp-admin > Instellingen > Mediamora Anti-Spam.
 * - Updates: wp-admin toont automatisch "Update beschikbaar" zodra er een
 *   nieuwere versie op GitHub getagd is, met een normale 1-klik-update,
 *   of in bulk via MainWP voor meerdere sites tegelijk.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =====================================================================
// AUTOMATISCHE UPDATES VIA GITHUB
// =====================================================================

require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$mediamora_antispam_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/hettyvanderwal/mediamora-anti-spam-elementor/',
	__FILE__,
	'mediamora-anti-spam-elementor'
);

// Kijkt standaard naar getagde versies (bijv. tag "v5.1") i.p.v. de losse
// hoofdbranch, zodat een halverwege-gepushte wijziging nooit per ongeluk
// als "update" naar klantsites gaat. Een gewone git-tag is voldoende,
// geen handmatige zip-upload per release nodig.

// =====================================================================
// PADEN — vaste, afgeleide bestandslocaties (geen "instelling", niet in
// het instellingenscherm, want dit zijn technische implementatiedetails)
// =====================================================================

define( 'MEDIAMORA_ANTISPAM_LOG_DIR', WP_CONTENT_DIR . '/uploads/mediamora-antispam' );
define( 'MEDIAMORA_ANTISPAM_LOG_FILE', MEDIAMORA_ANTISPAM_LOG_DIR . '/log.txt' );
define( 'MEDIAMORA_ANTISPAM_NEARMISS_FILE', MEDIAMORA_ANTISPAM_LOG_DIR . '/near-miss.txt' );

// =====================================================================
// INSTELLINGEN — opgeslagen in de database (get_option/update_option),
// bewerkbaar via wp-admin > Instellingen > Mediamora Anti-Spam
// =====================================================================

/**
 * Standaardwaardes. Worden gebruikt zolang er nog niets is opgeslagen in
 * de database, en gelden ook als een toekomstige code-update een nieuwe
 * instelling toevoegt die nog niet in de opgeslagen database-waarde zit.
 *
 * @return array
 */
function mediamora_antispam_default_settings() {
	return array(
		// Rapportage
		'alert_email'             => 'hetty@mediamora.nl',
		'report_enabled'          => true,
		'email_interval'          => DAY_IN_SECONDS,
		'log_max_age'             => 30 * DAY_IN_SECONDS,

		// Modus
		'debug'                   => false,
		'reject_non_latin_text'   => true,
		'reject_own_domain_mention' => true,
		'reject_shortened_urls'    => true,
		'reject_scheduling_links'  => true,

		// Vangnet voor mogelijke vals-negatieven
		'nearmiss_alert_enabled'  => true,
		'nearmiss_email_interval' => DAY_IN_SECONDS,

		// Gibberish-detectie (automatisch gegenereerde tekst)
		'gibberish_min_length'       => 8,
		'gibberish_vowel_ratio'      => 0.25,
		'gibberish_transitions'      => 4,
		'gibberish_structure_length' => 12,
		'gibberish_threshold'        => 2,

		// Link-detectie
		'max_links_textarea' => 2,

		// Duplicate-content detectie
		'duplicate_min_length' => 50,

		// Niet-Latijns schrift detectie
		'non_latin_min_letters' => 15,
		'non_latin_ratio'       => 0.5,
		'non_latin_min_count'   => 3,

		// Herhaling-detectie (keyword stuffing)
		'repetition_min_words'       => 10,
		'repetition_min_word_length' => 4,
		'repetition_min_count'       => 4,
		'repetition_max_ratio'       => 0.15,
	);
}

/**
 * Haalt de huidige instellingen op (database, aangevuld met standaardwaardes
 * voor eventueel nog ontbrekende sleutels). Gecachet per pageload.
 *
 * @return array
 */
function mediamora_antispam_settings() {

	static $settings = null;

	if ( null === $settings ) {
		$stored   = get_option( 'mediamora_antispam_settings', array() );
		$settings = wp_parse_args( $stored, mediamora_antispam_default_settings() );
	}

	return $settings;
}

// =====================================================================
// INSTELLINGENSCHERM (wp-admin > Instellingen > Mediamora Anti-Spam)
// =====================================================================

add_action( 'admin_menu', 'mediamora_antispam_add_settings_page' );

function mediamora_antispam_add_settings_page() {
	add_options_page(
		'Mediamora Anti-Spam',
		'Mediamora Anti-Spam',
		'manage_options',
		'mediamora-antispam',
		'mediamora_antispam_render_settings_page'
	);
}

/**
 * Valideert en bewaart de ingestuurde instellingen. Retourneert de
 * opgeslagen waardes (met standaardwaardes aangevuld), zodat de pagina
 * de nieuwe waardes direct kan tonen.
 *
 * @param array $post
 * @return array
 */
function mediamora_antispam_sanitize_and_save_settings( $post ) {

	$defaults = mediamora_antispam_default_settings();
	$clean    = array();

	$clean['alert_email']    = isset( $post['alert_email'] ) ? sanitize_email( $post['alert_email'] ) : $defaults['alert_email'];
	$clean['report_enabled'] = ! empty( $post['report_enabled'] );
	$clean['email_interval'] = max( 1, (int) ( $post['email_interval_hours'] ?? 24 ) ) * HOUR_IN_SECONDS;
	$clean['log_max_age']    = max( 1, (int) ( $post['log_max_age_days'] ?? 30 ) ) * DAY_IN_SECONDS;

	$clean['debug']                    = ! empty( $post['debug'] );
	$clean['reject_non_latin_text']    = ! empty( $post['reject_non_latin_text'] );
	$clean['reject_own_domain_mention'] = ! empty( $post['reject_own_domain_mention'] );
	$clean['reject_shortened_urls']    = ! empty( $post['reject_shortened_urls'] );
	$clean['reject_scheduling_links']  = ! empty( $post['reject_scheduling_links'] );

	$clean['nearmiss_alert_enabled']  = ! empty( $post['nearmiss_alert_enabled'] );
	$clean['nearmiss_email_interval'] = max( 1, (int) ( $post['nearmiss_email_interval_hours'] ?? 24 ) ) * HOUR_IN_SECONDS;

	$clean['gibberish_min_length']       = max( 1, (int) ( $post['gibberish_min_length'] ?? 8 ) );
	$clean['gibberish_vowel_ratio']      = min( 1, max( 0, (float) ( $post['gibberish_vowel_ratio'] ?? 0.25 ) ) );
	$clean['gibberish_transitions']      = max( 1, (int) ( $post['gibberish_transitions'] ?? 4 ) );
	$clean['gibberish_structure_length'] = max( 1, (int) ( $post['gibberish_structure_length'] ?? 12 ) );
	$clean['gibberish_threshold']        = max( 1, min( 3, (int) ( $post['gibberish_threshold'] ?? 2 ) ) );

	$clean['max_links_textarea'] = max( 0, (int) ( $post['max_links_textarea'] ?? 2 ) );

	$clean['duplicate_min_length'] = max( 1, (int) ( $post['duplicate_min_length'] ?? 50 ) );

	$clean['non_latin_min_letters'] = max( 1, (int) ( $post['non_latin_min_letters'] ?? 15 ) );
	$clean['non_latin_ratio']       = min( 1, max( 0, (float) ( $post['non_latin_ratio'] ?? 0.5 ) ) );
	$clean['non_latin_min_count']   = max( 1, (int) ( $post['non_latin_min_count'] ?? 3 ) );

	$clean['repetition_min_words']       = max( 1, (int) ( $post['repetition_min_words'] ?? 10 ) );
	$clean['repetition_min_word_length'] = max( 1, (int) ( $post['repetition_min_word_length'] ?? 4 ) );
	$clean['repetition_min_count']       = max( 1, (int) ( $post['repetition_min_count'] ?? 4 ) );
	$clean['repetition_max_ratio']       = min( 1, max( 0, (float) ( $post['repetition_max_ratio'] ?? 0.15 ) ) );

	update_option( 'mediamora_antispam_settings', $clean, false );

	return wp_parse_args( $clean, $defaults );
}

function mediamora_antispam_render_settings_page() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$saved = false;

	if ( isset( $_POST['mediamora_antispam_save'] ) && check_admin_referer( 'mediamora_antispam_save_settings' ) ) {
		$s     = mediamora_antispam_sanitize_and_save_settings( wp_unslash( $_POST ) );
		$saved = true;
	} else {
		$s = mediamora_antispam_settings();
	}
	?>
	<div class="wrap">
		<h1>Mediamora Anti-Spam voor Elementor Forms</h1>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen.</p></div>
		<?php endif; ?>

		<p>Deze instellingen staan in de database van deze site en blijven dus behouden als het plugin-bestand zelf een keer wordt vervangen door een nieuwere versie.</p>

		<form method="post">
			<?php wp_nonce_field( 'mediamora_antispam_save_settings' ); ?>

			<h2>Rapportage</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="alert_email">E-mailadres voor meldingen</label></th>
					<td><input type="email" id="alert_email" name="alert_email" class="regular-text" value="<?php echo esc_attr( $s['alert_email'] ); ?>" required></td>
				</tr>
				<tr>
					<th scope="row">Dagelijkse rapportmail</th>
					<td>
						<label><input type="checkbox" name="report_enabled" value="1" <?php checked( $s['report_enabled'] ); ?>> Stuur een samenvattend rapport van geweigerde inzendingen</label>
						<p class="description">Zet dit per site uit zodra je vertrouwt dat de check hier stabiel draait. Het logbestand op de server blijft altijd bijgehouden, ook als dit uit staat, en toont nu alle geweigerde inzendingen zonder maximum.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="email_interval_hours">Minimale tijd tussen rapportmails</label></th>
					<td><input type="number" min="1" id="email_interval_hours" name="email_interval_hours" value="<?php echo esc_attr( round( $s['email_interval'] / HOUR_IN_SECONDS ) ); ?>" class="small-text"> uur</td>
				</tr>
				<tr>
					<th scope="row"><label for="log_max_age_days">Bewaartermijn logregels</label></th>
					<td><input type="number" min="1" id="log_max_age_days" name="log_max_age_days" value="<?php echo esc_attr( round( $s['log_max_age'] / DAY_IN_SECONDS ) ); ?>" class="small-text"> dagen</td>
				</tr>
			</table>

			<h2>Vangnet voor mogelijk gemiste spam</h2>
			<p class="description">Waarschuwt bij individuele VELDEN die net niet werden geweigerd, maar verdacht dicht bij een drempel zaten. De mail geeft ook aan of de bijbehorende hele inzending uiteindelijk toch (door een ander veld) is geweigerd, of gewoon is doorgekomen. Dit is een heuristiek: het vangt twijfelgevallen, geen garantie op elke gemiste spamvorm. Draait onafhankelijk van de rapportmail hierboven.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Waarschuwing bij twijfelgevallen</th>
					<td><label><input type="checkbox" name="nearmiss_alert_enabled" value="1" <?php checked( $s['nearmiss_alert_enabled'] ); ?>> Waarschuw mij bij mogelijke twijfelgevallen</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="nearmiss_email_interval_hours">Minimale tijd tussen vangnet-mails</label></th>
					<td><input type="number" min="1" id="nearmiss_email_interval_hours" name="nearmiss_email_interval_hours" value="<?php echo esc_attr( round( $s['nearmiss_email_interval'] / HOUR_IN_SECONDS ) ); ?>" class="small-text"> uur</td>
				</tr>
			</table>

			<h2>Modus</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Niet-Latijns schrift altijd weigeren</th>
					<td>
						<label><input type="checkbox" name="reject_non_latin_text" value="1" <?php checked( $s['reject_non_latin_text'] ); ?>> Weiger overwegend niet-Latijnse tekst (Cyrillisch, Arabisch, Chinees, etc.), ook zonder link</label>
						<p class="description">Alleen uitzetten bij een site met legitiem internationaal publiek buiten het Latijnse schrift.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Debug-modus</th>
					<td>
						<label><input type="checkbox" name="debug" value="1" <?php checked( $s['debug'] ); ?>> Log elk veld van elke inzending naar debug.txt, ongeacht of het geweigerd wordt</label>
						<p class="description">Alleen tijdelijk aanzetten om te troubleshooten, hierna weer uitzetten.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Eigen domeinnaam in bericht</th>
					<td>
						<label><input type="checkbox" name="reject_own_domain_mention" value="1" <?php checked( $s['reject_own_domain_mention'] ); ?>> Weiger berichten die de domeinnaam van deze site zelf noemen</label>
						<p class="description">Vangt bulk-outreach/verkooppitches die met een sjabloon-tool naar veel sites tegelijk worden gestuurd, met de domeinnaam als variabele erin ("Ik zag net [domeinnaam].nl en..."). Een echte bezoeker heeft geen reden om de naam van de site waar hij al op staat te typen.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Verkorte URLs &amp; wegwerp-linkdiensten</th>
					<td>
						<label><input type="checkbox" name="reject_shortened_urls" value="1" <?php checked( $s['reject_shortened_urls'] ); ?>> Weiger berichten met verkorte URLs of bekende wegwerp-linkdiensten (bit.ly, tinyurl, psee.io, telegra.ph, enz.)</label>
						<p class="description">Vangt bekende diensten (op naam) én onbekende verkorters via een structureel kenmerk: een kort URL-pad met cijfers/hoofdletters erin (zoals "/DYm8Ld") is typisch voor een gegenereerde verkorter-code. Kanttekening: dit kan zelden een legitieme URL raken die toevallig een cijfer in het laatste paddeel heeft (bijv. "/actie2026").</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Afspraken-boekingslinks</th>
					<td>
						<label><input type="checkbox" name="reject_scheduling_links" value="1" <?php checked( $s['reject_scheduling_links'] ); ?>> Weiger berichten met een link naar een afspraken-boekingsdienst (Calendly, Cal.com, HubSpot Meetings, enz.)</label>
						<p class="description">Typerend voor cold-outreach/verkooppitches: de afzender wil dat jij een afspraak bij hén inplant. Een echte bezoeker stuurt in zijn eigen bericht nooit zo'n boekingslink mee.</p>
					</td>
				</tr>
			</table>

			<h2>Geavanceerd: detectiedrempels</h2>
			<p class="description">De standaardwaardes zijn getest en werken goed op de meeste sites. Alleen aanpassen als je een concrete reden hebt, bijvoorbeeld op basis van het dagrapport.</p>

			<h3>Onleesbare tekst (gibberish)</h3>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="gibberish_min_length">Minimale lengte</label></th><td><input type="number" min="1" id="gibberish_min_length" name="gibberish_min_length" value="<?php echo esc_attr( $s['gibberish_min_length'] ); ?>" class="small-text"> letters</td></tr>
				<tr><th scope="row"><label for="gibberish_vowel_ratio">Klinker-ratio drempel</label></th><td><input type="number" min="0" max="1" step="0.01" id="gibberish_vowel_ratio" name="gibberish_vowel_ratio" value="<?php echo esc_attr( $s['gibberish_vowel_ratio'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="gibberish_transitions">Case-wisselingen drempel</label></th><td><input type="number" min="1" id="gibberish_transitions" name="gibberish_transitions" value="<?php echo esc_attr( $s['gibberish_transitions'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="gibberish_structure_length">Structuur-lengte drempel</label></th><td><input type="number" min="1" id="gibberish_structure_length" name="gibberish_structure_length" value="<?php echo esc_attr( $s['gibberish_structure_length'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="gibberish_threshold">Aantal signalen nodig (van de 3)</label></th><td><input type="number" min="1" max="3" id="gibberish_threshold" name="gibberish_threshold" value="<?php echo esc_attr( $s['gibberish_threshold'] ); ?>" class="small-text"></td></tr>
			</table>

			<h3>Links</h3>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="max_links_textarea">Max. links in een berichtveld</label></th><td><input type="number" min="0" id="max_links_textarea" name="max_links_textarea" value="<?php echo esc_attr( $s['max_links_textarea'] ); ?>" class="small-text"></td></tr>
			</table>

			<h3>Dubbele inhoud</h3>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="duplicate_min_length">Minimale lengte om mee te tellen</label></th><td><input type="number" min="1" id="duplicate_min_length" name="duplicate_min_length" value="<?php echo esc_attr( $s['duplicate_min_length'] ); ?>" class="small-text"> tekens</td></tr>
			</table>

			<h3>Niet-Latijns schrift</h3>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="non_latin_min_letters">Minimaal aantal letters</label></th><td><input type="number" min="1" id="non_latin_min_letters" name="non_latin_min_letters" value="<?php echo esc_attr( $s['non_latin_min_letters'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="non_latin_ratio">Latijns-ratio drempel</label></th><td><input type="number" min="0" max="1" step="0.01" id="non_latin_ratio" name="non_latin_ratio" value="<?php echo esc_attr( $s['non_latin_ratio'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="non_latin_min_count">Absoluut aantal niet-Latijnse letters</label></th><td><input type="number" min="1" id="non_latin_min_count" name="non_latin_min_count" value="<?php echo esc_attr( $s['non_latin_min_count'] ); ?>" class="small-text"></td></tr>
			</table>

			<h3>Herhaling (keyword stuffing)</h3>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="repetition_min_words">Minimaal aantal woorden</label></th><td><input type="number" min="1" id="repetition_min_words" name="repetition_min_words" value="<?php echo esc_attr( $s['repetition_min_words'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="repetition_min_word_length">Minimale woordlengte</label></th><td><input type="number" min="1" id="repetition_min_word_length" name="repetition_min_word_length" value="<?php echo esc_attr( $s['repetition_min_word_length'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="repetition_min_count">Minimaal aantal herhalingen</label></th><td><input type="number" min="1" id="repetition_min_count" name="repetition_min_count" value="<?php echo esc_attr( $s['repetition_min_count'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="repetition_max_ratio">Max. aandeel van het bericht</label></th><td><input type="number" min="0" max="1" step="0.01" id="repetition_max_ratio" name="repetition_max_ratio" value="<?php echo esc_attr( $s['repetition_max_ratio'] ); ?>" class="small-text"></td></tr>
			</table>

			<?php submit_button( 'Instellingen opslaan', 'primary', 'mediamora_antispam_save' ); ?>
		</form>
	</div>
	<?php
}

// =====================================================================
// VALIDATIE
// =====================================================================

add_action( 'elementor_pro/forms/validation', 'mediamora_antispam_validate_form', 10, 2 );

/**
 * Hoofdvalidatie. Loopt eenmaal door alle velden en past alle checks toe.
 *
 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
 */
function mediamora_antispam_validate_form( $record, $ajax_handler ) {

	$s = mediamora_antispam_settings();

	// Veldtypes waar we deze checks niet op toepassen.
	$skip_types = array(
		'email', // een domeinnaam is een verplicht, normaal onderdeel van elk e-mailadres, geen spamsignaal
		'select',
		'radio',
		'checkbox',
		'acceptance',
		'upload',
		'hidden',
		'date',
		'time',
		'number',
		'url',
		'recaptcha',
		'recaptcha_v3',
		'honeypot',
	);

	$fields              = $record->get( 'fields' );
	$long_values         = array();
	$near_misses         = array();
	$submission_rejected = false;

	foreach ( $fields as $id => $field ) {

		$type      = isset( $field['type'] ) ? $field['type'] : 'text';
		$raw_value = isset( $field['value'] ) ? $field['value'] : '';

		// Sommige veldtypes (zoals een samengesteld "Naam" veld met
		// voornaam/achternaam als subvelden) leveren een array i.p.v. een
		// string. Zonder deze afhandeling zou (string) $array simpelweg
		// "Array" opleveren, en dan wordt er nooit iets herkend.
		if ( is_array( $raw_value ) ) {
			$value = trim( implode( ' ', array_map( 'strval', $raw_value ) ) );
		} else {
			$value = trim( (string) $raw_value );
		}

		if ( '' === $value || in_array( $type, $skip_types, true ) ) {
			if ( $s['debug'] ) {
				mediamora_antispam_debug_log( $id, $type, $value, false, null, 'overgeslagen (leeg of skip-type)' );
			}
			continue;
		}

		$is_link         = mediamora_has_link_injection( $value, $type );
		$gibberish_score = mediamora_gibberish_score( $value );
		$is_gibberish    = ! $is_link && $gibberish_score['is_gibberish'];

		if ( $s['debug'] ) {
			mediamora_antispam_debug_log( $id, $type, $value, $is_link, $gibberish_score, 'beoordeeld' );
		}

		// Check 1: link injection (BBCode, of te veel URLs voor het veldtype)
		if ( $is_link ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'verdachte link', $id, $type, $value );
			$submission_rejected = true;
			continue; // dit veld is al afgekeurd, geen zin om ook nog op gibberish te checken
		}

		// Check 1b: niet-Latijns schrift, ook zonder link (alleen als expliciet ingeschakeld)
		if ( $s['reject_non_latin_text'] && mediamora_is_mostly_non_latin( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'niet-Latijns schrift', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 1c: bericht noemt de domeinnaam van de site zelf (bulk-outreach patroon)
		if ( $s['reject_own_domain_mention'] && mediamora_mentions_own_domain( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'noemt eigen domeinnaam', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 1d: verkorte URL-services (bit.ly, tinyurl, psee.io, enz.)
		if ( $s['reject_shortened_urls'] && mediamora_contains_shortened_url( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'verkorte URL', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 1e: afspraken-boekingslink (Calendly, Cal.com, enz. — cold-outreach patroon)
		if ( $s['reject_scheduling_links'] && mediamora_contains_scheduling_link( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'boekingslink', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 2: automatisch gegenereerde ("random") tekst
		if ( $is_gibberish ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'onleesbare tekst', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 2b: overmatige woordherhaling (keyword stuffing)
		if ( mediamora_has_excessive_repetition( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'herhaalde trefwoorden', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Dit veld is op zichzelf geaccepteerd. Vangnet: zat het griezelig
		// dicht bij een weigeringsdrempel? Dan verzamelen we dat, maar
		// loggen het nog niet: een ander veld verderop, of de
		// dubbele-inhoud check hieronder, kan de hele inzending alsnog
		// laten weigeren, en die uiteindelijke status willen we erbij
		// vermelden.
		if ( $s['nearmiss_alert_enabled'] ) {
			$near_miss_reasons = mediamora_antispam_find_near_misses( $value, $type, $gibberish_score );
			if ( ! empty( $near_miss_reasons ) ) {
				$near_misses[] = array(
					'id'      => $id,
					'type'    => $type,
					'value'   => $value,
					'reasons' => $near_miss_reasons,
				);
			}
		}

		// Bewaren voor de duplicate-check hieronder
		if ( mb_strlen( $value ) > $s['duplicate_min_length'] ) {
			$long_values[ $id ] = $value;
		}
	}

	// Check 3: identieke lange inhoud in meerdere velden (bot die overal dezelfde tekst plakt)
	if ( count( $long_values ) > 1 && count( $long_values ) !== count( array_unique( $long_values ) ) ) {
		foreach ( $long_values as $id => $value ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'dubbele inhoud', $id, 'n.v.t.', $value );
			$submission_rejected = true;
		}
	}

	// Nu pas de verzamelde near-misses loggen, met de uiteindelijke status
	// van de hele inzending erbij, zodat "geaccepteerd" in de vangnet-mail
	// ook echt betekent dat de hele inzending is doorgekomen, niet alleen
	// dit ene veld.
	if ( ! empty( $near_misses ) ) {

		$status = $submission_rejected
			? 'inzending als geheel WEL geweigerd (ander veld sloeg aan)'
			: 'inzending als geheel geaccepteerd';

		foreach ( $near_misses as $near_miss ) {
			mediamora_antispam_nearmiss_log( $near_miss['id'], $near_miss['type'], $near_miss['value'], $near_miss['reasons'], $status );
		}
	}
}

/**
 * Herkent verkorte URL-services en andere bekende, vrijwel altijd
 * misbruikte wegwerp-linkdiensten (zoals telegra.ph, Telegram's gratis
 * publiceerplatform, veelgebruikt voor crypto-scampagina's omdat het
 * gratis is en moeilijk snel offline te halen). Functioneel gedraagt dit
 * zich hetzelfde als een verkorte URL: het is altijd een wegwerp-link,
 * nooit iemands eigen site, dus dezelfde lijst en logica.
 *
 * Op twee manieren:
 *
 * 1. Een lijst met bekende diensten. Dit is nooit compleet, spammers
 *    duiken steeds op met nieuwe/minder bekende diensten.
 * 2. Daarom ook een STRUCTURELE herkenning die niet van de naam van de
 *    dienst afhangt: een URL-pad dat bestaat uit precies één kort
 *    alfanumeriek stukje met cijfers en/of hoofdletters erin
 *    (bijvoorbeeld "/DYm8Ld", "/sbRhA") is typisch voor hoe
 *    verkorters hun codes genereren. Een normaal, door een mens
 *    geschreven URL-pad ("/contact", "/diensten") bestaat vrijwel
 *    altijd uit gewone kleine letters. Dit vangt dus ook onbekende of
 *    nieuwe verkorters, zonder dat we de naam hoeven te kennen. Let op:
 *    dit vangt GEEN diensten zoals telegra.ph met een lang, beschrijvend
 *    pad, die moeten dus met naam op de lijst staan.
 *
 *    Kanttekening: dit kan in theorie een legitieme URL raken die
 *    toevallig een cijfer in het laatste pad-segment heeft (bijvoorbeeld
 *    "voorbeeld.nl/actie2026"). Dat risico wordt zo klein mogelijk
 *    gehouden door een kort segment (4-10 tekens) te vereisen zonder
 *    verdere sub-paden, maar is niet volledig uit te sluiten.
 *
 * Elke (mogelijke) verkorte/misbruikte URL verdient direct afkeuring,
 * ongeacht het aantal.
 *
 * @param string $value
 * @return bool
 */
function mediamora_contains_shortened_url( $value ) {

	$shorteners = array(
		'bit\.ly',
		'tinyurl\.com',
		'ow\.ly',
		'short\.link',
		'psee\.io',
		'goo\.gl',
		'buff\.ly',
		'adf\.ly',
		'bitly\.com',
		'tiny\.cc',
		'tr\.im',
		'is\.gd',
		'snipr\.com',
		'snipurl\.com',
		'cl\.lk',
		'lnk\.co',
		'shortened\.me',
		'sh\.st',
		'go2l\.ink',
		'x\.co',
		'shorturl\.at',
		'1url\.com',
		'rlnk\.co',
		'u\.to',
		'v\.gd',
		'short2url\.com',
		'clickto\.cc',
		'telegra\.ph',
	);

	$pattern = '/(' . implode( '|', $shorteners ) . ')/i';

	if ( preg_match( $pattern, $value ) ) {
		return true;
	}

	// Structurele herkenning, ongeacht de naam van de dienst.
	if ( preg_match_all( '#https?://[a-z0-9.-]+\.[a-z]{2,10}/([A-Za-z0-9]{4,10})(?=[?#]|$|\s)#i', $value, $matches ) ) {
		foreach ( $matches[1] as $slug ) {
			// Volledig kleine letters is een normaal, door mensen
			// geschreven URL-pad. Cijfers of hoofdletters erin zijn
			// typisch voor een gegenereerde shortener-code.
			if ( ! preg_match( '/^[a-z]+$/', $slug ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Herkent of een tekst de domeinnaam van de site zelf noemt. Typisch voor
 * bulk-outreach/verkooppitches die met een sjabloon-tool naar veel sites
 * tegelijk worden gestuurd, met de domeinnaam als ingevoegde variabele
 * ("Ik zag net [domeinnaam] en..."). Een echte bezoeker die het
 * contactformulier van een site invult heeft geen reden om de naam van
 * die site aan zichzelf te typen.
 *
 * @param string $value
 * @return bool
 */
function mediamora_mentions_own_domain( $value ) {

	$host = wp_parse_url( home_url(), PHP_URL_HOST );

	if ( empty( $host ) ) {
		return false;
	}

	$host = preg_replace( '/^www\./i', '', $host );

	return false !== stripos( $value, $host );
}

/**
 * Herkent links naar afspraken-boekingsdiensten (Calendly, Cal.com,
 * HubSpot Meetings, etc.). Typerend voor cold-outreach/verkooppitches: de
 * afzender wil dat de ontvanger een afspraak bij HEN inplant. Een echte
 * bezoeker die een contactformulier invult stuurt in zijn eigen bericht
 * nooit zo'n boekingslink mee, dat is een tool die uitsluitend door de
 * afzender van dit soort pitches wordt gebruikt.
 *
 * @param string $value
 * @return bool
 */
function mediamora_contains_scheduling_link( $value ) {

	$schedulers = array(
		'calendly\.com',
		'cal\.com',
		'meetings\.hubspot\.com',
		'acuityscheduling\.com',
		'doodle\.com',
		'chilipiper\.com',
		'youcanbook\.me',
		'appointlet\.com',
		'setmore\.com',
		'schedulicity\.com',
		'oncehub\.com',
		'zcal\.co',
		'savvycal\.com',
		'koalendar\.com',
	);

	$pattern = '/(' . implode( '|', $schedulers ) . ')/i';

	return (bool) preg_match( $pattern, $value );
}

/**
 * Herkent link injection. BBCode [url=] is in geen enkel veld legitiem.
 * Kale domeinnamen (bijvoorbeeld "voorbeeld.ru" zonder "http://" ervoor)
 * worden hetzelfde behandeld als volledige URLs, want spam-bots laten het
 * protocol vaak expres weg om linkherkenning te omzeilen.
 *
 * Voor het aantal toegestane links geldt een drempel per veldtype: korte
 * velden zoals naam/telefoon horen nooit een link te bevatten, terwijl een
 * berichtveld best 1-2 legitieme links kan bevatten.
 *
 * Uitzondering op die drempel: een link gecombineerd met overwegend
 * niet-Latijns schrift wordt altijd geweigerd, ongeacht het aantal.
 *
 * @param string $value
 * @param string $type
 * @return bool
 */
function mediamora_has_link_injection( $value, $type ) {

	$s = mediamora_antispam_settings();

	if ( preg_match( '/\[url=/i', $value ) ) {
		return true;
	}

	$protocol_urls = preg_match_all( '/https?:\/\/\S+/i', $value );

	// Gevonden volledige URLs uit de tekst halen, zodat een domein daarbinnen
	// niet nog een keer meetelt bij de kale-domeinen check hieronder.
	$value_without_urls = preg_replace( '/https?:\/\/\S+/i', ' ', $value );

	$bare_domains = preg_match_all(
		'/\b[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.(?:ru|su|com|net|org|info|biz|nl|be|de|fr|eu|io|co|me|shop|online|site|xyz|top|club|store|website|space|pro|vip|icu|cn|ua|by|kz|pl|it|es|uk|us)\b/i',
		$value_without_urls
	);

	$url_count = $protocol_urls + $bare_domains;

	if ( $url_count < 1 ) {
		return false;
	}

	if ( mediamora_is_mostly_non_latin( $value ) ) {
		return true;
	}

	if ( 'textarea' === $type ) {
		return $url_count > $s['max_links_textarea'];
	}

	return $url_count >= 1;
}

/**
 * Bepaalt of een tekst niet-Latijns schrift bevat (Cyrillisch, Arabisch,
 * Chinees, etc.), op twee manieren: een absoluut aantal niet-Latijnse
 * letters (vangt bijv. Cyrillische tekst met Latijnse merknamen ertussen),
 * of anders de verhouding Latijns/totaal bij voldoende tekst.
 *
 * Gebruikt PCRE Unicode-properties; als de server-PCRE die niet
 * ondersteunt, wordt fail-safe niets gerapporteerd als niet-Latijns.
 *
 * @param string $value
 * @return bool
 */
function mediamora_is_mostly_non_latin( $value ) {

	$s = mediamora_antispam_settings();

	$total_letters = @preg_match_all( '/\p{L}/u', $value );
	$latin_letters = @preg_match_all( '/\p{Latin}/u', $value );

	if ( false === $total_letters || false === $latin_letters || 0 === $total_letters ) {
		return false;
	}

	$non_latin_letters = $total_letters - $latin_letters;

	if ( $non_latin_letters >= $s['non_latin_min_count'] ) {
		return true;
	}

	if ( $total_letters < $s['non_latin_min_letters'] ) {
		return false;
	}

	return ( $latin_letters / $total_letters ) < $s['non_latin_ratio'];
}

/**
 * Berekent de gibberish-signalen voor een stuk tekst en geeft alle
 * tussenliggende meetwaarden terug (handig voor debugging), naast het
 * eindoordeel.
 *
 * 1. Klinker-ratio: normale taal zit rond de 35-45% klinkers.
 * 2. Case-wisselingen: een random string wisselt vaak grillig tussen
 *    hoofdletters en kleine letters.
 * 3. Geen natuurlijke structuur: langere tekst zonder spaties/leestekens
 *    is atypisch voor door mensen getypte input.
 *
 * @param string $value
 * @return array
 */
function mediamora_gibberish_score( $value ) {

	$s = mediamora_antispam_settings();

	$letters_only = preg_replace( '/[^A-Za-z\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{00FF}]/u', '', $value );

	if ( null === $letters_only ) {
		$letters_only = '';
	}

	$length = mb_strlen( $letters_only );

	$result = array(
		'length'                => $length,
		'vowel_ratio'           => null,
		'transitions'           => null,
		'has_natural_structure' => null,
		'suspicious'            => 0,
		'is_gibberish'          => false,
	);

	if ( $length < $s['gibberish_min_length'] ) {
		return $result;
	}

	$vowels      = preg_match_all( '/[aeiouAEIOU\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{00FF}]/u', $letters_only );
	$vowel_ratio = $vowels / $length;

	$transitions = 0;
	$prev_upper  = null;
	for ( $i = 0; $i < $length; $i++ ) {
		$char     = mb_substr( $letters_only, $i, 1 );
		$is_upper = ( $char === mb_strtoupper( $char ) && $char !== mb_strtolower( $char ) );
		if ( null !== $prev_upper && $is_upper !== $prev_upper ) {
			$transitions++;
		}
		$prev_upper = $is_upper;
	}

	$has_natural_structure = (bool) preg_match( '/[\s,.!?\'"-]/', $value );

	$suspicious = 0;

	if ( $vowel_ratio < $s['gibberish_vowel_ratio'] ) {
		$suspicious++;
	}
	if ( $transitions >= $s['gibberish_transitions'] ) {
		$suspicious++;
	}
	if ( ! $has_natural_structure && $length >= $s['gibberish_structure_length'] ) {
		$suspicious++;
	}

	$result['vowel_ratio']           = $vowel_ratio;
	$result['transitions']           = $transitions;
	$result['has_natural_structure'] = $has_natural_structure;
	$result['suspicious']            = $suspicious;
	$result['is_gibberish']          = ( $suspicious >= $s['gibberish_threshold'] );

	return $result;
}

/**
 * Herkent overmatige herhaling van eenzelfde woord binnen één veld, een
 * typisch patroon bij keyword-stuffing spam. Kijkt naar de verhouding
 * t.o.v. de totale berichtlengte, zodat een lang, natuurlijk bericht
 * automatisch meer ruimte krijgt dan een kort bericht.
 *
 * @param string $value
 * @return bool
 */
function mediamora_has_excessive_repetition( $value ) {

	$s = mediamora_antispam_settings();

	$words = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( $value ), -1, PREG_SPLIT_NO_EMPTY );

	if ( false === $words ) {
		return false;
	}

	$total = count( $words );

	if ( $total < $s['repetition_min_words'] ) {
		return false;
	}

	$counts = array_count_values( $words );

	foreach ( $counts as $word => $count ) {
		if ( mb_strlen( $word ) < $s['repetition_min_word_length'] ) {
			continue;
		}
		if ( $count < $s['repetition_min_count'] ) {
			continue;
		}
		if ( ( $count / $total ) >= $s['repetition_max_ratio'] ) {
			return true;
		}
	}

	return false;
}

/**
 * Vangnet: onderzoekt een GEACCEPTEERD veld op tekenen dat het griezelig
 * dicht bij een weigeringsdrempel zat. Dit is een heuristiek, geen
 * garantie: het vangt alleen twijfelgevallen die al bijna een bestaand
 * signaal raakten, niet compleet nieuwe spamvormen die nergens bij in de
 * buurt komen.
 *
 * @param string $value
 * @param string $type
 * @param array  $gibberish_score Resultaat van mediamora_gibberish_score() voor dit veld.
 * @return string[] Lijst met redenen, leeg als er niets opvallends is.
 */
function mediamora_antispam_find_near_misses( $value, $type, $gibberish_score ) {

	$s       = mediamora_antispam_settings();
	$reasons = array();

	// Gibberish: precies 1 signaal te weinig om te weigeren.
	if ( $gibberish_score['suspicious'] > 0 && $gibberish_score['suspicious'] === ( $s['gibberish_threshold'] - 1 ) ) {
		$reasons[] = 'onleesbare tekst (net niet genoeg signalen)';
	}

	// Links: exact op de toegestane grens voor berichtvelden.
	if ( 'textarea' === $type ) {
		$protocol_urls       = preg_match_all( '/https?:\/\/\S+/i', $value );
		$value_without_urls  = preg_replace( '/https?:\/\/\S+/i', ' ', $value );
		$bare_domains        = preg_match_all(
			'/\b[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.(?:ru|su|com|net|org|info|biz|nl|be|de|fr|eu|io|co|me|shop|online|site|xyz|top|club|store|website|space|pro|vip|icu|cn|ua|by|kz|pl|it|es|uk|us)\b/i',
			$value_without_urls
		);
		$url_count = $protocol_urls + $bare_domains;
		if ( $url_count === (int) $s['max_links_textarea'] ) {
			$reasons[] = 'links (precies op de toegestane grens)';
		}
	}

	// Niet-Latijns schrift: net onder de absolute drempel.
	$total_letters = @preg_match_all( '/\p{L}/u', $value );
	$latin_letters = @preg_match_all( '/\p{Latin}/u', $value );
	if ( false !== $total_letters && false !== $latin_letters && $total_letters > 0 ) {
		$non_latin = $total_letters - $latin_letters;
		if ( $non_latin > 0 && $non_latin < $s['non_latin_min_count'] ) {
			$reasons[] = 'niet-Latijns schrift (net onder de drempel)';
		}
	}

	// Herhaling: ratio net onder de drempel (binnen 5 procentpunt).
	$words = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( $value ), -1, PREG_SPLIT_NO_EMPTY );
	if ( is_array( $words ) && count( $words ) >= $s['repetition_min_words'] ) {
		$total  = count( $words );
		$counts = array_count_values( $words );
		foreach ( $counts as $word => $count ) {
			if ( mb_strlen( $word ) < $s['repetition_min_word_length'] || $count < $s['repetition_min_count'] ) {
				continue;
			}
			$ratio = $count / $total;
			if ( $ratio >= ( $s['repetition_max_ratio'] - 0.05 ) && $ratio < $s['repetition_max_ratio'] ) {
				$reasons[] = 'herhaalde trefwoorden (net onder de drempel)';
				break;
			}
		}
	}

	return $reasons;
}

// =====================================================================
// LOGGING EN RAPPORTAGE
// =====================================================================

/**
 * Tijdelijke debug-logging: legt van elk beoordeeld veld het type, de
 * waarde en de details van beide checks vast, ongeacht of het veld wordt
 * geweigerd. Alleen actief als de debug-instelling aan staat.
 *
 * @param string     $field_id
 * @param string     $field_type
 * @param string     $value
 * @param bool       $is_link
 * @param array|null $gibberish_score
 * @param string     $status
 */
function mediamora_antispam_debug_log( $field_id, $field_type, $value, $is_link, $gibberish_score, $status ) {

	mediamora_antispam_ensure_log_dir();

	$debug_file = MEDIAMORA_ANTISPAM_LOG_DIR . '/debug.txt';

	$snippet = str_replace( array( "\r", "\n" ), ' ', $value );
	$snippet = mb_substr( $snippet, 0, 80 );

	if ( is_array( $gibberish_score ) ) {
		$score_text = sprintf(
			'lengte=%d klinker-ratio=%s wisselingen=%s structuur=%s suspicious=%d/3 gibberish=%s',
			$gibberish_score['length'],
			null === $gibberish_score['vowel_ratio'] ? 'n.v.t. (te kort)' : round( $gibberish_score['vowel_ratio'], 2 ),
			null === $gibberish_score['transitions'] ? 'n.v.t.' : $gibberish_score['transitions'],
			null === $gibberish_score['has_natural_structure'] ? 'n.v.t.' : ( $gibberish_score['has_natural_structure'] ? 'ja' : 'nee' ),
			$gibberish_score['suspicious'],
			$gibberish_score['is_gibberish'] ? 'ja' : 'nee'
		);
	} else {
		$score_text = 'n.v.t.';
	}

	$line = sprintf(
		'%s | status: %s | veld-id: %s | type: %s | waarde: "%s" | link-check: %s | gibberish-detail: %s',
		gmdate( 'Y-m-d H:i:s' ),
		$status,
		$field_id,
		$field_type,
		$snippet,
		$is_link ? 'ja' : 'nee',
		$score_text
	);

	file_put_contents( $debug_file, $line . "\n", FILE_APPEND | LOCK_EX );
}

/**
 * Schrijft een regel naar het logbestand en triggert (indien nodig) de
 * rapportmail.
 *
 * @param string $reason
 * @param string $field_id
 * @param string $field_type
 * @param string $value
 */
function mediamora_antispam_log( $reason, $field_id, $field_type, $value ) {

	mediamora_antispam_ensure_log_dir();

	$snippet = str_replace( array( "\r", "\n" ), ' ', $value );
	$snippet = mb_substr( $snippet, 0, 50 );

	$line = sprintf(
		'%s | veld: %s (%s) | reden: %s | waarde: %s',
		gmdate( 'Y-m-d H:i:s' ),
		$field_id,
		$field_type,
		$reason,
		$snippet
	);

	file_put_contents( MEDIAMORA_ANTISPAM_LOG_FILE, $line . "\n", FILE_APPEND | LOCK_EX );

	mediamora_antispam_maybe_send_report();
}

/**
 * Schrijft een regel naar het vangnet-logbestand en triggert (indien
 * nodig) de vangnet-mail.
 *
 * @param string   $field_id
 * @param string   $field_type
 * @param string   $value
 * @param string[] $reasons
 * @param string   $submission_status Wat er uiteindelijk met de hele inzending is gebeurd (niet alleen dit veld).
 */
function mediamora_antispam_nearmiss_log( $field_id, $field_type, $value, $reasons, $submission_status ) {

	mediamora_antispam_ensure_log_dir();

	$snippet = str_replace( array( "\r", "\n" ), ' ', $value );
	$snippet = mb_substr( $snippet, 0, 80 );

	$line = sprintf(
		'%s | veld: %s (%s) | vermoedelijke reden(en): %s | inzending: %s | waarde: %s',
		gmdate( 'Y-m-d H:i:s' ),
		$field_id,
		$field_type,
		implode( ', ', $reasons ),
		$submission_status,
		$snippet
	);

	file_put_contents( MEDIAMORA_ANTISPAM_NEARMISS_FILE, $line . "\n", FILE_APPEND | LOCK_EX );

	mediamora_antispam_maybe_send_nearmiss_alert();
}

/**
 * Zorgt dat de logmap bestaat en afgeschermd is tegen direct web-bezoek.
 */
function mediamora_antispam_ensure_log_dir() {

	if ( ! file_exists( MEDIAMORA_ANTISPAM_LOG_DIR ) ) {
		wp_mkdir_p( MEDIAMORA_ANTISPAM_LOG_DIR );
	}

	$htaccess = MEDIAMORA_ANTISPAM_LOG_DIR . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		// Werkt op Apache/LiteSpeed. Op Nginx-hosts biedt dit geen bescherming,
		// controleer dan of de map anderszins is afgeschermd.
		file_put_contents( $htaccess, "Require all denied\nDeny from all\n" );
	}
}

/**
 * Haalt de timestamp uit een logregel (begint met "Y-m-d H:i:s | ...").
 *
 * @param string $line
 * @return int|false
 */
function mediamora_antispam_parse_line_timestamp( $line ) {

	$parts = explode( ' | ', $line, 2 );

	if ( empty( $parts[0] ) ) {
		return false;
	}

	$timestamp = strtotime( $parts[0] . ' UTC' );

	return false !== $timestamp ? $timestamp : false;
}

/**
 * Stuurt (indien ingeschakeld) maximaal 1x per ingestelde periode een
 * samenvattend rapport met ALLE geweigerde inzendingen sinds de vorige
 * mail, en ruimt tegelijk logregels op die ouder zijn dan de bewaartermijn.
 * De opschoning gebeurt altijd, ook als de rapportmail zelf uit staat.
 */
function mediamora_antispam_maybe_send_report() {

	$s = mediamora_antispam_settings();

	$last_run = (int) get_option( 'mediamora_antispam_last_report', 0 );

	if ( ( time() - $last_run ) < $s['email_interval'] ) {
		return;
	}

	if ( ! file_exists( MEDIAMORA_ANTISPAM_LOG_FILE ) ) {
		return;
	}

	$lines = file( MEDIAMORA_ANTISPAM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

	if ( empty( $lines ) ) {
		return;
	}

	$cutoff_email     = time() - $s['email_interval'];
	$cutoff_retention = time() - $s['log_max_age'];

	$recent_for_email   = array();
	$kept_for_retention = array();

	foreach ( $lines as $line ) {

		$timestamp = mediamora_antispam_parse_line_timestamp( $line );

		if ( false === $timestamp ) {
			continue;
		}

		if ( $timestamp >= $cutoff_retention ) {
			$kept_for_retention[] = $line;
		}

		if ( $timestamp >= $cutoff_email ) {
			$recent_for_email[] = $line;
		}
	}

	// Logbestand opschonen op bewaartermijn (geen persoonsgegevens onbeperkt bewaren).
	// Gebeurt altijd, onafhankelijk van of de mail hieronder verstuurd wordt.
	file_put_contents(
		MEDIAMORA_ANTISPAM_LOG_FILE,
		implode( "\n", $kept_for_retention ) . ( empty( $kept_for_retention ) ? '' : "\n" ),
		LOCK_EX
	);

	update_option( 'mediamora_antispam_last_report', time(), false );

	if ( ! $s['report_enabled'] || empty( $recent_for_email ) ) {
		return;
	}

	$count = count( $recent_for_email );
	$site  = wp_parse_url( home_url(), PHP_URL_HOST );

	$body  = sprintf(
		"De anti-spam check op je Elementor formulier(en) heeft de afgelopen periode %d inzending(en) geweigerd.\n\n",
		$count
	);
	$body .= "Details:\n\n";
	$body .= implode( "\n", $recent_for_email );
	$body .= "\n\nVolledige log (incl. oudere, nog niet gerapporteerde regels binnen de bewaartermijn) staat in wp-content/uploads/mediamora-antispam/log.txt op de server.";

	wp_mail(
		$s['alert_email'],
		sprintf( '[%s] Anti-spam rapport: %d geweigerde inzending(en)', $site, $count ),
		$body
	);
}

/**
 * Stuurt (indien ingeschakeld) maximaal 1x per ingestelde periode een
 * vangnet-mail met alle mogelijke twijfelgevallen sinds de vorige mail.
 * Draait volledig onafhankelijk van mediamora_antispam_maybe_send_report(),
 * dus ook als de gewone rapportmail op deze site uit staat.
 */
function mediamora_antispam_maybe_send_nearmiss_alert() {

	$s = mediamora_antispam_settings();

	if ( ! $s['nearmiss_alert_enabled'] ) {
		return;
	}

	$last_run = (int) get_option( 'mediamora_antispam_last_nearmiss_alert', 0 );

	if ( ( time() - $last_run ) < $s['nearmiss_email_interval'] ) {
		return;
	}

	if ( ! file_exists( MEDIAMORA_ANTISPAM_NEARMISS_FILE ) ) {
		return;
	}

	$lines = file( MEDIAMORA_ANTISPAM_NEARMISS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

	if ( empty( $lines ) ) {
		return;
	}

	$cutoff_email     = time() - $s['nearmiss_email_interval'];
	$cutoff_retention = time() - $s['log_max_age'];

	$recent = array();
	$kept   = array();

	foreach ( $lines as $line ) {
		$timestamp = mediamora_antispam_parse_line_timestamp( $line );
		if ( false === $timestamp ) {
			continue;
		}
		if ( $timestamp >= $cutoff_retention ) {
			$kept[] = $line;
		}
		if ( $timestamp >= $cutoff_email ) {
			$recent[] = $line;
		}
	}

	file_put_contents(
		MEDIAMORA_ANTISPAM_NEARMISS_FILE,
		implode( "\n", $kept ) . ( empty( $kept ) ? '' : "\n" ),
		LOCK_EX
	);

	update_option( 'mediamora_antispam_last_nearmiss_alert', time(), false );

	if ( empty( $recent ) ) {
		return;
	}

	$count = count( $recent );
	$site  = wp_parse_url( home_url(), PHP_URL_HOST );

	$body  = sprintf(
		"Let op: %d veld(en) in je Elementor formulier(en) zaten verdacht dicht bij een weigeringsdrempel, zonder dat dit specifieke veld zelf werd geweigerd.\n\n" .
		"Dit kan wijzen op spam die net niet werd gevangen (een mogelijk vals negatief), maar kan net zo goed een volkomen legitieme inzending zijn die toevallig ongewone kenmerken had. Dit is een heuristiek, geen harde constatering, dus check dit met een kritische blik.\n\n" .
		"Let op het onderdeel \"inzending:\" per regel hieronder: dat geeft aan of de HELE inzending (dus alle velden samen) uiteindelijk toch is geweigerd omdat een ander veld wel aansloeg, of dat de inzending als geheel is geaccepteerd en dus in je Elementor Inzendingen-lijst staat.\n\n",
		$count
	);
	$body .= "Details:\n\n";
	$body .= implode( "\n", $recent );
	$body .= "\n\nDit vangnet draait onafhankelijk van de gewone rapportage, en blijft dus actief ook als je die op deze site hebt uitgezet.";

	wp_mail(
		$s['alert_email'],
		sprintf( '[%s] Mogelijk gemiste spam: %d twijfelgeval(len)', $site, $count ),
		$body
	);
}
