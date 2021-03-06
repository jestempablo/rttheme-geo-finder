<?php
/**
* Plugin Name: Rubycon: RT Theme 18 Geo Finder
* Description: 1.0.1 FIX: SKRYPTY TYLKO NA STRONIE GŁÓWNEJ. Przeszukuje templatki RT Theme 18 w poszukiwaniu danych klienta w postaci adresów i lokalizacji GPS placówek, ustala lokalizację odwiedzającego i wyświetla najbliższy punkt. Uruchamiane poprzez shortcode "[rtgeoloc]". Wtyczka wymaga obsługi JavaScript u odwiedzającego.
* Version: 1.0.1
* Author: Paweł Foryński
* Author URI: http://rubycon.pl
* License: GNU GPL v2.0
*/

defined('ABSPATH') or die();











function rtgeoloc_add_scripts(){
	if(is_front_page()) {
		wp_register_script( 'rtgeoloc_script', plugins_url( '/script.js', __FILE__ ), array('jquery') );
		wp_enqueue_script( 'rtgeoloc_script');
		wp_localize_script( 'rtgeoloc_script', 'adresurl', get_bloginfo('url','display') );
		wp_register_style( 'rtgeoloc_style', plugins_url( '/style.css', __FILE__ ), array(), 'all' );
		wp_enqueue_style('rtgeoloc_style');
		wp_register_script( 'google_places_script', 'https://maps.googleapis.com/maps/api/js?v=3.exp&libraries=places&language=pl', array('jquery'));
		wp_enqueue_script( 'google_places_script');
	}
}
// dodaj wymagane pliki do head
add_action( 'wp_enqueue_scripts', 'rtgeoloc_add_scripts' );



function bez_gplaces_poza_frontem() {
	if ( !is_front_page() ) {
		remove_action( 'wp_register_script', 'google_places_script' );
		remove_action( 'wp_enqueue_scripts', 'google_places_script' );
	}
}
add_action( 'get_header', 'bez_gplaces_poza_frontem' );


// start drukowania szkieletu (pamiętaj żeby wywołać)
function rtgeoloc_szkielet($atts) {

	if(isset($_POST['geoemail'])) {
		$adres = get_bloginfo('url','display');
		return '
		<!--[if gte IE 9]>
		  <style type="text/css">
		    .gradient {
		       filter: none;
		    }
		  </style>
		<![endif]-->
		<div id="zaprowrap">
		<div id="zaproszenie" class="clearfix">
			Wizytówka została wysłana na adres
			<h2>'.$_POST['geoemail'].'</h2>
			<span class="dziekizielone">Dziękujemy za zainteresowanie,<BR />
			<img src="'.$adres.'/wp-content/uploads/2014/07/daiglob-logo.png" width="150" height="26">
			</span>
			<br /><a class="ponownielink" href="'.$adres.'">kliknij tutaj by wyszukać ponownie</a>
		</div>
		</div>
		';
	}else{
		return '
		<!--[if gte IE 9]>
		  <style type="text/css">
		    .gradient {
		       filter: none;
		    }
		  </style>
		<![endif]-->
		<div id="zaprowrap">
		<div id="zaproszenie" class="clearfix">

		<div id="geolokalizacja">
			<p>
				<span id="twojaLokalizacja">Twoja<span class="przyblizona"> </span>lokalizacja: <BR /></span>
				<span id="twojeMiasto"><span class="wyszukuje">wyszukuję...</span></span>
				<span id="twojeKoordynaty"></span>
			</p>
			<label>
				<span><b>Inna miejscowość?</b><br />Wpisz poniżej i wybierz:</span>
				<input type="text" id="wlasnemiasto">
			</label>
		</div>

		<div id="placowkiwrap">
			<p id="placowkitext"></p>
			<p class="placowka"></p>
			<p class="placowka2"></p>
		</div>

		<div id="przeslij">
			<form name="formularz" id="geoformularz" method="post" action="">
				<textarea class="text-placowka" name="geoplace"></textarea>
				<textarea class="text-placowka2" name="geoplace2"></textarea>

				<input type="hidden" id="dbgeoloctype" name="dbgeoloctype">
				<input type="hidden" id="dbgeoloccity" name="dbgeoloccity">
				<input type="hidden" id="dbgeolocstate" name="dbgeolocstate">

				<span><b>Pobierz naszą wizytówkę</b><BR /></span>
				<label class="geoimielabel">
				<span>wpisz swoje imię:</span>
				<input type="text" name="geoimie" id="przeslijimie" autocomplete="off">
				</label>

				<label class="geoemaillabel">
				<span>oraz adres email:</span>
				<input type="text" name="geoemail" id="przeslijemail" autocomplete="off">
				</label>

				<label class="geoprzycisklabel">
				<input type="submit" value="prześlij" id="przeslijprzycisk">
				</label>

				<label class="geozgodalabel">
				<input type="checkbox" value="zgoda" id="zgoda" checked="checked" disabled="disabled">
				<span>wyrażam zgodę na przetwarzanie danych</span>
				</label>
			</form>
		</div>
		</div>
		';

	// koniec if przesłano formularz
	}
// koniec szkieletu
} 
// dodaj shortcode
add_shortcode( 'rtgeoloc', 'rtgeoloc_szkielet' );










function zakoduj_placowki(){

	// sprawdź czy plik był zapisany w ostatnich 3 godzinach
	$json_zapisany_src = file_get_contents(ABSPATH . 'bazaplacowek.json');
	$tester = json_decode($json_zapisany_src, true);
	$pieczec = $tester[count($tester) - 1][timestamp];
	$czasteraz = time();

	// jeśli nie był to zapisz
	if ((time() - $pieczec) > 10800) {

		// podłącz do bazy
		global $wpdb;

		// pobierz z bazy
		$nazwa_tabeli = $wpdb->prefix."options";
		$cala_zawartosc = $wpdb->get_results("SELECT option_id,option_value FROM $nazwa_tabeli WHERE option_id>891 AND option_id!=2127 AND option_id!=901 AND option_name REGEXP '^rttheme18_templateid_.*_content_output'");

		global $json_elementow;

		// walker
		foreach ($cala_zawartosc as $element) {

			//deklaracje
			$id_elementu = $element->option_id;
			$tresc_elementu = $element->option_value;
			//przeszukanie dla lat i lon
			$wzor_latlon = '/lat="(.*?)".*?lon="(.*?)".*?/s';
			preg_match_all($wzor_latlon,$tresc_elementu, $znalezione);

			//przeszukanie dla nazw i adresów
			$wzor_adres = '/icon_list title="(.*?)"|icon_list_line.*?](.*?)\[\/icon_list_line\]/';
			preg_match_all($wzor_adres,$tresc_elementu, $znaladres);

			//pobieracz adresow
			$licz1 = 1;
			$licz2 = 0;
			$numerplacowki = 0;
			$licznik_top = count($znaladres[0]);
			for ($licznik = 0; $licznik < $licznik_top; $licznik++) {
				if ($znaladres[$licz1][$licz2] !== '') {
					//gdy pole niepuste czyli to tytuł to wtedy:
					$numerplacowki++;

					$placowka_dane[$numerplacowki][] = $znaladres[$licz1][$licz2];

					$licz2++;
				}else{
					$licz1++;
					//gdy pole tytułu puste to wtedy dodaj linię dla tej placówki
					$placowka_dane[$numerplacowki][] = $znaladres[$licz1][$licz2];

					$licz1--;
					$licz2++;
				}
			}

			//wypisz tablice
			if ($znalezione[1][0] != '') {
				$json_elementow[] = array(
					'gpslat' => $znalezione[1][0], 
					'gpslon' => $znalezione[2][0],
					$id_elementu => $placowka_dane,
					'id' => $id_elementu
					);
			}
		    unset ($placowka_dane);
		} //foreach

		// dopisz pieczęć
		$json_elementow[] = array('timestamp' => time());


		// zakoduj do json po pobraniu
		$file = 'bazaplacowek.json';
		file_put_contents($file, json_encode($json_elementow));
	}
}
// wykonaj sprawdzenie i/lub zapis pliku z placówkami
zakoduj_placowki();










// formularz
if(isset($_POST['geoemail'])) {

	zapisz_do_bazy('daneosobiste');

	$odbiorca = $_post['geoemail'];
	$temat = "DAIGLOB.PL - wizytówka";

	function niepoprawne($blad) {
		echo "<meta charset=utf-8>";
		echo "Wystąpił błąd: <br />";
		echo $blad;
		echo "<br /> Spróbuj ponownie. Jeśli to nie pomoże skontaktuj się z nami przez standardowy formularz.";
		die();
		//wstaw powrót
	}

	if(!isset($_POST['geoimie']) || !isset($_POST['geoemail']) || !isset($_POST['geoplace'])) niepoprawne("- błąd z formularzem");

	$imie = $_POST['geoimie'];
	$email = $_POST['geoemail'];
	$placowka = nl2br(htmlspecialchars($_POST['geoplace']));
	$placowka2 = nl2br(htmlspecialchars($_POST['geoplace2']));

	$wiadomoscbledu = "";
	$email_wzor = '/^[A-ZĄĆĘŁŃÓŚŹŻa-ząćęłńóśźż0-9._%-]+@[A-ZĄĆĘŁŃÓŚŹŻa-ząćęłńóśźż0-9.-]+\.[A-Za-z]{2,4}$/';
	$imie_wzor = "/^[A-ZĄĆĘŁŃÓŚŹŻa-ząćęłńóśźż .'-]+$/";


 	if(!preg_match($email_wzor,$email)) {
 		$wiadomoscbledu .= '- email wydaje się być niepoprawny <br />';
 	}
 	if(!preg_match($imie_wzor,$imie)) {
 		$wiadomoscbledu .= '- wpisane imię jest niepoprawne <br />';
 	}
 	if(strlen($placowka) == 0) {
 		$wiadomoscbledu .= '- nie wykryto placówki - proszę się upewnić, że wyświetlono najbliższe oddziały <br />';
 	}
	if(strlen($wiadomoscbledu) > 0) {
		niepoprawne($wiadomoscbledu);
	}

	$tresc_wizytowki = "Dzień dobry! <BR /><BR />Oto dane, o które prosiłeś:<BR /><BR />".$placowka."<BR /><BR />".$placowka2."<BR /><BR />Pozdrawiamy,<BR />DAIGLOB FINANCE";

	$naglowki = "From: DAIGLOB <daiglob@telvinet.pl>\r\n";
	$naglowki .= "Reply-To:biuro@daiglob.pl\r\n";
	$naglowki .= "MIME-Version: 1.0\r\n";
	$naglowki .= "Content-Type: text/html; charset=utf-8\r\n";
	$naglowki .= "Content-Transfer-Encoding: 8bit\r\n";

	@mail($email, $temat, $tresc_wizytowki, $naglowki);  
}










function rttheme_geo_instalacja () {

	global $wpdb;
	$tablica_statystyk = $wpdb->prefix."rgfstatystyki";

	$charset_collate = '';

	if ( ! empty( $wpdb->charset ) ) {
	  $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
	}

	if ( ! empty( $wpdb->collate ) ) {
	  $charset_collate .= " COLLATE {$wpdb->collate}";
	}

	$sql = "CREATE TABLE " . $tablica_statystyk . " (
	  		numer mediumint(9) NOT NULL AUTO_INCREMENT,
	  		ip varchar(45) NOT NULL,
	  		czas datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  		lokalizacja varchar(20),
	  		miasto varchar(50),
	  		wojewodztwo varchar(50),
	  		imie varchar(25),
	  		email varchar(60),
	  		hash varchar(50),
	  		UNIQUE KEY (numer)
	) ". $charset_collate .";";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}
// wykonaj przy aktywacji
register_activation_hook( __FILE__, 'rttheme_geo_instalacja' );










// dane testowe na start wtyczki
function rttheme_geo_testdata () {
	require_once(ABSPATH . 'wp-config.php');

	global $wpdb;
	$tablica_statystyk = $wpdb->prefix."rgfstatystyki";

	$temp0 = 'OK';
	$temp1 = date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) );


	$wpdb->insert(
		$tablica_statystyk,
		array (
			'ip' => 			$temp0,
			'czas' => 			$temp1,
			)
		);
}
// wykonaj przy aktywacji
register_activation_hook( __FILE__, 'rttheme_geo_testdata' );










function zapisz_do_bazy() {

	$idgoscia = NULL;
	if(!isset($_COOKIE['ciastkoStatystyczne'])) setcookie( 'ciastkoStatystyczne', bin2hex(mcrypt_create_iv(22, MCRYPT_DEV_URANDOM)), 2000000000);
	if(isset($_COOKIE['ciastkoStatystyczne'])) $idgoscia = $_COOKIE['ciastkoStatystyczne'];

	require_once(ABSPATH . 'wp-config.php');

	global $wpdb;
	$tablica_statystyk = $wpdb->prefix."rgfstatystyki";


	$wpdb->insert(
		$tablica_statystyk,
		array (
			'ip' =>								$_SERVER['REMOTE_ADDR'],
			'czas' =>							date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ),
			'lokalizacja' =>					$_POST['dbgeoloctype'],
			'miasto' =>							$_POST['dbgeoloccity'],
			'wojewodztwo' =>					$_POST['dbgeolocstate'],
			'imie' =>							$_POST['geoimie'],
			'email' => 							$_POST['geoemail'],
			'hash' => 							$idgoscia
			)
		);

}





?>