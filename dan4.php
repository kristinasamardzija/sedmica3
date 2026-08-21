<?php
// ============================================
// DAN 4 - Povezivanje PHP-a i MySQL-a
// PROJEKAT: Karolina Boutique
// Sada citamo podatke direktno iz baze (Dan 3)
// umjesto iz $_SESSION (Dan 2) ili hardkodiranog niza (Dan 1)
// ============================================

$nazivButika = "Karolina Boutique";

// --------------------------------------------
// 1) KONEKCIJA NA BAZU
// --------------------------------------------
// Parametri: (host, korisnik, lozinka, ime_baze)
// Podrazumijevano za XAMPP: host="localhost", user="root", lozinka=""
$host = "localhost";
$korisnik = "root";
$lozinka = "";
$imeBaze = "karolina_boutique";

$konekcija = new mysqli($host, $korisnik, $lozinka, $imeBaze);

// Ako konekcija ne uspije, prekidamo izvrsavanje i ispisujemo gresku
if ($konekcija->connect_error) {
    die("⛔ Konekcija na bazu nije uspjela: " . $konekcija->connect_error);
}

// Postavljamo enkodiranje da se ne bi lomila slova (č, š, ž...)
$konekcija->set_charset("utf8mb4");


// --------------------------------------------
// 2) DOHVATANJE RACUNA IZ BAZE (SELECT)
// --------------------------------------------
$racuni = [];

$upitRacuni = "SELECT * FROM racuni ORDER BY broj_racuna ASC";
$rezultatRacuni = $konekcija->query($upitRacuni);

// query() vraca rezultat kao "tabelu" - fetch_assoc() vadi jedan red kao niz
// petlja se ponavlja dok ima redova (kad ih nestane, fetch_assoc vraca null)
while ($red = $rezultatRacuni->fetch_assoc()) {
    $racuni[] = $red;
}


// --------------------------------------------
// 3) ZA SVAKI RACUN, DOHVATI NJEGOVE STAVKE
// --------------------------------------------
foreach ($racuni as $indeks => $racun) {
    $racunId = (int)$racun["id"]; // (int) - osiguravamo da je broj, radi sigurnosti

    $upitStavke = "SELECT * FROM stavke WHERE racun_id = $racunId";
    $rezultatStavke = $konekcija->query($upitStavke);

    $stavke = [];
    while ($stavka = $rezultatStavke->fetch_assoc()) {
        $stavke[] = $stavka;
    }

    // Dodajemo stavke unutar postojeceg racuna (kao sto smo radili Dan 1)
    $racuni[$indeks]["stavke"] = $stavke;
}


// --------------------------------------------
// 4) UKUPNA DNEVNA SUMA - direktno preko SQL-a
// --------------------------------------------
// Umjesto da sabiramo u PHP-u, mozemo tražiti da baza sama sabere (SUM)
$upitSuma = "SELECT SUM(suma) AS ukupno FROM racuni";
$rezultatSuma = $konekcija->query($upitSuma);
$redSuma = $rezultatSuma->fetch_assoc();
$ukupnaDnevnaSuma = $redSuma["ukupno"] !== null ? $redSuma["ukupno"] : 0;


// Zatvaramo konekciju kad vise nije potrebna (dobra praksa)
$konekcija->close();
?>
<!DOCTYPE html>
<html lang="ba">
<head>
    <meta charset="UTF-8">
    <title><?php echo $nazivButika; ?> - Podaci iz baze</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 0 20px; background: #f5f5f5; }
        h1 { color: #2c3e50; margin-bottom: 0; }
        .podnaslov { color: #7f8c8d; margin-top: 4px; }
        .racun { background: white; padding: 18px 22px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 15px; }
        .racun h3 { margin-top: 0; color: #34495e; }
        .stavka { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px dashed #eee; }
        .suma-racuna { text-align: right; font-weight: bold; margin-top: 8px; color: #2980b9; }
        .ukupno { background: #2c3e50; color: white; padding: 20px 25px; border-radius: 8px; font-size: 20px; text-align: center; margin-top: 20px; }
        .info-box { background: #eafaf1; border: 1px solid #a3e4bc; padding: 10px 15px; border-radius: 6px; font-size: 13px; color: #196f3d; margin-bottom: 20px; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 8px; overflow: hidden; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #2c3e50; color: white; }
        tr:hover { background: #f9f9f9; }
    </style>
</head>
<body>

    <h1><?php echo $nazivButika; ?></h1>
    <p class="podnaslov">Podaci učitani direktno iz MySQL baze</p>

    <div class="info-box">
        ✅ Uspješno povezano na bazu <strong><?php echo $imeBaze; ?></strong> — pronađeno <strong><?php echo count($racuni); ?></strong> računa.
    </div>

    <!-- PRIKAZ 1: KARTICE (isti stil kao Dan 1, ali sad iz baze) -->
    <h2>Prikaz u karticama</h2>

    <?php foreach ($racuni as $racun): ?>
        <div class="racun">
            <h3>Račun br. <?php echo htmlspecialchars($racun["broj_racuna"]); ?>
                <span style="font-weight:normal; color:#999; font-size:14px;">
                    (<?php echo htmlspecialchars($racun["datum"]); ?> u <?php echo htmlspecialchars($racun["vrijeme"]); ?>)
                </span>
            </h3>

            <?php foreach ($racun["stavke"] as $stavka): ?>
                <div class="stavka">
                    <span><?php echo htmlspecialchars($stavka["naziv"]); ?></span>
                    <span><?php echo number_format($stavka["cijena"], 2); ?> KM</span>
                </div>
            <?php endforeach; ?>

            <div class="suma-racuna">Suma računa: <?php echo number_format($racun["suma"], 2); ?> KM</div>
        </div>
    <?php endforeach; ?>

    <div class="ukupno">
        Ukupna dnevna suma: <strong><?php echo number_format($ukupnaDnevnaSuma, 2); ?> KM</strong>
    </div>

    <!-- PRIKAZ 2: OBICNA HTML TABELA (druga opcija iz zadatka) -->
    <h2>Prikaz u tabeli (svaka stavka posebno)</h2>

    <table>
        <thead>
            <tr>
                <th>Račun br.</th>
                <th>Stavka</th>
                <th>Cijena</th>
                <th>Vrijeme</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($racuni as $racun): ?>
                <?php foreach ($racun["stavke"] as $stavka): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($racun["broj_racuna"]); ?></td>
                        <td><?php echo htmlspecialchars($stavka["naziv"]); ?></td>
                        <td><?php echo number_format($stavka["cijena"], 2); ?> KM</td>
                        <td><?php echo htmlspecialchars($racun["vrijeme"]); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>