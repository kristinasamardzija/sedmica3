<?php
session_start();

$nazivButika = "Karolina Boutique";

$konekcija = new mysqli("localhost", "root", "", "karolina_boutique");
if ($konekcija->connect_error) {
    die("Konekcija na bazu nije uspjela: " . $konekcija->connect_error);
}
$konekcija->set_charset("utf8mb4");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["novi_racun"])) {
        $provjera = $konekcija->query("SELECT id FROM racuni WHERE status = 'otvoren' LIMIT 1");

        if ($provjera->num_rows > 0) {
            $_SESSION["poruka"] = "Već postoji otvoren račun.";
        } else {
            $rezultat = $konekcija->query("SELECT COALESCE(MAX(broj_racuna), 0) + 1 AS sledeci FROM racuni");
            $sledeciBroj = $rezultat->fetch_assoc()["sledeci"];

            $upit = $konekcija->prepare("INSERT INTO racuni (broj_racuna, datum, vrijeme, suma, status) VALUES (?, CURDATE(), CURTIME(), 0, 'otvoren')");
            $upit->bind_param("i", $sledeciBroj);
            $upit->execute();
            $upit->close();

            $_SESSION["poruka"] = "Otvoren novi Račun br. $sledeciBroj.";
        }
    }

    if (isset($_POST["dodaj_stavku"])) {
        $naziv = isset($_POST["naziv"]) ? trim($_POST["naziv"]) : "";
        $cijena = isset($_POST["cijena"]) ? trim($_POST["cijena"]) : "";

        if ($naziv === "" || $cijena === "") {
            $_SESSION["poruka"] = "Moraš unijeti naziv i cijenu.";
        } elseif (!is_numeric($cijena) || (float)$cijena <= 0) {
            $_SESSION["poruka"] = "Cijena mora biti broj veći od 0.";
        } else {
            $rezultat = $konekcija->query("SELECT id FROM racuni WHERE status = 'otvoren' ORDER BY id DESC LIMIT 1");

            if ($rezultat->num_rows === 0) {
                $_SESSION["poruka"] = "Nema otvorenog računa.";
            } else {
                $racunId = $rezultat->fetch_assoc()["id"];

                $upit = $konekcija->prepare("INSERT INTO stavke (racun_id, naziv, cijena) VALUES (?, ?, ?)");
                $upit->bind_param("isd", $racunId, $naziv, $cijena);
                $upit->execute();
                $upit->close();

                $_SESSION["poruka"] = "Stavka \"" . htmlspecialchars($naziv) . "\" dodana.";
            }
        }
    }

    if (isset($_POST["zatvori_racun"])) {
        $rezultat = $konekcija->query("SELECT id FROM racuni WHERE status = 'otvoren' ORDER BY id DESC LIMIT 1");

        if ($rezultat->num_rows === 0) {
            $_SESSION["poruka"] = "Nema otvorenog računa za zatvaranje.";
        } else {
            $racunId = $rezultat->fetch_assoc()["id"];
            $brojStavki = $konekcija->query("SELECT COUNT(*) AS broj FROM stavke WHERE racun_id = $racunId")->fetch_assoc()["broj"];

            if ($brojStavki == 0) {
                $_SESSION["poruka"] = "Ne možeš zatvoriti prazan račun.";
            } else {
                $upit = $konekcija->prepare("UPDATE racuni SET status = 'zatvoren' WHERE id = ?");
                $upit->bind_param("i", $racunId);
                $upit->execute();
                $upit->close();

                $_SESSION["poruka"] = "Račun zatvoren.";
            }
        }
    }

    header("Location: " . $_SERVER["PHP_SELF"]);
    exit;
}

$poruka = "";
if (isset($_SESSION["poruka"])) {
    $poruka = $_SESSION["poruka"];
    unset($_SESSION["poruka"]);
}

$trenutniRacun = null;
$rezultat = $konekcija->query("SELECT * FROM racuni WHERE status = 'otvoren' ORDER BY id DESC LIMIT 1");
if ($rezultat->num_rows > 0) {
    $trenutniRacun = $rezultat->fetch_assoc();

    $stavkeUpit = $konekcija->prepare("SELECT * FROM stavke WHERE racun_id = ?");
    $stavkeUpit->bind_param("i", $trenutniRacun["id"]);
    $stavkeUpit->execute();
    $trenutniRacun["stavke"] = $stavkeUpit->get_result()->fetch_all(MYSQLI_ASSOC);
    $stavkeUpit->close();

    $trenutniRacun["suma"] = array_sum(array_column($trenutniRacun["stavke"], "cijena"));
}

$zatvoreniRacuni = [];
$rezultat = $konekcija->query("SELECT * FROM racuni WHERE status = 'zatvoren' ORDER BY broj_racuna ASC");
while ($racun = $rezultat->fetch_assoc()) {
    $stavkeUpit = $konekcija->prepare("SELECT * FROM stavke WHERE racun_id = ?");
    $stavkeUpit->bind_param("i", $racun["id"]);
    $stavkeUpit->execute();
    $racun["stavke"] = $stavkeUpit->get_result()->fetch_all(MYSQLI_ASSOC);
    $stavkeUpit->close();

    $racun["suma"] = array_sum(array_column($racun["stavke"], "cijena"));
    $zatvoreniRacuni[] = $racun;
}

$ukupnaDnevnaSuma = 0;
foreach ($zatvoreniRacuni as $racun) {
    $ukupnaDnevnaSuma += $racun["suma"];
}

$konekcija->close();
?>
<!DOCTYPE html>
<html lang="ba">
<head>
    <meta charset="UTF-8">
    <title><?php echo $nazivButika; ?></title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 650px; margin: 40px auto; padding: 0 20px; background: #f5f5f5; }
        h1 { color: #2c3e50; margin-bottom: 0; }
        h2 { color: #34495e; margin-top: 35px; }
        .podnaslov { color: #7f8c8d; margin-top: 4px; }
        .card { background: white; padding: 20px 25px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
        label { display: block; margin-top: 10px; font-weight: bold; font-size: 14px; }
        input[type=text], input[type=number] { width: 100%; padding: 8px; margin-top: 4px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { margin-top: 15px; padding: 10px 18px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; color: white; }
        .btn-dodaj { background: #2980b9; }
        .btn-dodaj:hover { background: #206694; }
        .btn-zatvori { background: #27ae60; margin-left: 8px; }
        .btn-zatvori:hover { background: #1e8449; }
        .btn-novi { background: #8e44ad; }
        .btn-novi:hover { background: #6c3483; }
        .poruka { margin-top: 15px; padding: 10px; border-radius: 4px; background: #eef; font-size: 14px; }
        .stavka-red { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed #eee; }
        .racun { background: #fbfbfb; border: 1px solid #eee; padding: 15px 20px; border-radius: 8px; margin-bottom: 12px; }
        .racun h3 { margin-top: 0; color: #34495e; }
        .suma-racuna { text-align: right; font-weight: bold; margin-top: 6px; color: #2980b9; }
        .ukupno { background: #2c3e50; color: white; padding: 18px 25px; border-radius: 8px; font-size: 19px; text-align: center; margin-top: 10px; }
        .prazno { color: #999; font-style: italic; }
    </style>
</head>
<body>

    <h1><?php echo $nazivButika; ?></h1>
    <p class="podnaslov">Dnevni izvještaj — unos direktno u bazu</p>

    <?php if ($poruka !== ""): ?>
        <div class="poruka"><?php echo $poruka; ?></div>
    <?php endif; ?>

    <div class="card">
        <?php if ($trenutniRacun === null): ?>
            <h2>Nema otvorenog računa</h2>
            <form method="POST" action="">
                <button type="submit" name="novi_racun" class="btn-novi">Novi račun</button>
            </form>
        <?php else: ?>
            <h2>Trenutni račun (br. <?php echo $trenutniRacun["broj_racuna"]; ?>)</h2>

            <?php if (count($trenutniRacun["stavke"]) === 0): ?>
                <p class="prazno">Nema još stavki na ovom računu.</p>
            <?php else: ?>
                <?php foreach ($trenutniRacun["stavke"] as $stavka): ?>
                    <div class="stavka-red">
                        <span><?php echo htmlspecialchars($stavka["naziv"]); ?></span>
                        <span><?php echo number_format($stavka["cijena"], 2); ?> KM</span>
                    </div>
                <?php endforeach; ?>
                <div class="suma-racuna">Suma: <?php echo number_format($trenutniRacun["suma"], 2); ?> KM</div>
            <?php endif; ?>

            <form method="POST" action="">
                <label for="naziv">Naziv stavke:</label>
                <input type="text" id="naziv" name="naziv" placeholder="npr. Haljina">

                <label for="cijena">Cijena (KM):</label>
                <input type="number" step="0.01" id="cijena" name="cijena" placeholder="npr. 45.00">

                <button type="submit" name="dodaj_stavku" class="btn-dodaj">Dodaj stavku</button>
                <button type="submit" name="zatvori_racun" class="btn-zatvori">Zatvori račun</button>
            </form>
        <?php endif; ?>
    </div>

    <h2>Zatvoreni računi</h2>

    <?php if (count($zatvoreniRacuni) === 0): ?>
        <p class="prazno">Još nema zatvorenih računa.</p>
    <?php else: ?>
        <?php foreach ($zatvoreniRacuni as $racun): ?>
            <div class="racun">
                <h3>Račun br. <?php echo $racun["broj_racuna"]; ?>
                    <span style="font-weight:normal; color:#999; font-size:13px;">
                        (<?php echo htmlspecialchars($racun["datum"]); ?> u <?php echo htmlspecialchars($racun["vrijeme"]); ?>)
                    </span>
                </h3>
                <?php foreach ($racun["stavke"] as $stavka): ?>
                    <div class="stavka-red">
                        <span><?php echo htmlspecialchars($stavka["naziv"]); ?></span>
                        <span><?php echo number_format($stavka["cijena"], 2); ?> KM</span>
                    </div>
                <?php endforeach; ?>
                <div class="suma-racuna">Suma računa: <?php echo number_format($racun["suma"], 2); ?> KM</div>
            </div>
        <?php endforeach; ?>

        <div class="ukupno">
            Ukupna dnevna suma (<?php echo count($zatvoreniRacuni); ?> računa):
            <strong><?php echo number_format($ukupnaDnevnaSuma, 2); ?> KM</strong>
        </div>
    <?php endif; ?>

</body>
</html>