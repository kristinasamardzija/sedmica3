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
            $upit = $konekcija->prepare("INSERT INTO racuni (broj_racuna, datum, vrijeme, suma, status, popust) VALUES (?, CURDATE(), CURTIME(), 0, 'otvoren', 0)");
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

    if (isset($_POST["sacuvaj_popust"])) {
        $racunId = (int)$_POST["racun_id"];
        $popust = trim($_POST["popust"]);
        if (!is_numeric($popust) || (float)$popust < 0 || (float)$popust > 100) {
            $_SESSION["poruka"] = "Popust mora biti broj između 0 i 100.";
        } else {
            $upit = $konekcija->prepare("UPDATE racuni SET popust = ? WHERE id = ?");
            $upit->bind_param("di", $popust, $racunId);
            $upit->execute();
            $upit->close();
            $_SESSION["poruka"] = "Popust sačuvan.";
        }
    }

    if (isset($_POST["storniraj_stavku"])) {
        $stavkaId = (int)$_POST["stavka_id"];
        $brojRacuna = (int)$_POST["broj_racuna"];
        $upit = $konekcija->prepare("DELETE FROM stavke WHERE id = ?");
        $upit->bind_param("i", $stavkaId);
        $upit->execute();
        $upit->close();
        $_SESSION["poruka"] = "Stavka stornirana.";
        $_SESSION["redirect_broj"] = $brojRacuna;
    }

    if (isset($_POST["novi_dan"])) {
        $konekcija->query("DELETE FROM racuni");
        $_SESSION["poruka"] = "Novi dan započet, svi podaci obrisani.";
    }

    $lokacija = $_SERVER["PHP_SELF"];
    if (isset($_SESSION["redirect_broj"])) {
        $lokacija .= "?broj=" . $_SESSION["redirect_broj"];
        unset($_SESSION["redirect_broj"]);
    }
    header("Location: " . $lokacija);
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
    $racun["konacna_suma"] = $racun["suma"] - ($racun["suma"] * $racun["popust"] / 100);
    $zatvoreniRacuni[] = $racun;
}

$ukupnaDnevnaSuma = 0;
foreach ($zatvoreniRacuni as $racun) {
    $ukupnaDnevnaSuma += $racun["konacna_suma"];
}

$trazeniRacun = null;
$trazeniBroj = isset($_GET["broj"]) ? trim($_GET["broj"]) : "";
if ($trazeniBroj !== "") {
    $upit = $konekcija->prepare("SELECT * FROM racuni WHERE broj_racuna = ? ORDER BY id DESC LIMIT 1");
    $brojInt = (int)$trazeniBroj;
    $upit->bind_param("i", $brojInt);
    $upit->execute();
    $rez = $upit->get_result();
    if ($rez->num_rows > 0) {
        $trazeniRacun = $rez->fetch_assoc();
        $stavkeUpit = $konekcija->prepare("SELECT * FROM stavke WHERE racun_id = ?");
        $stavkeUpit->bind_param("i", $trazeniRacun["id"]);
        $stavkeUpit->execute();
        $trazeniRacun["stavke"] = $stavkeUpit->get_result()->fetch_all(MYSQLI_ASSOC);
        $stavkeUpit->close();
    }
    $upit->close();
}

$konekcija->close();
?>
<!DOCTYPE html>
<html lang="ba">
<head>
    <meta charset="UTF-8">
    <title><?php echo $nazivButika; ?></title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 0 20px; background: #f5f5f5; }
        h1 { color: #2c3e50; margin-bottom: 0; }
        h2 { color: #34495e; margin-top: 35px; }
        .podnaslov { color: #7f8c8d; margin-top: 4px; }
        .card { background: white; padding: 20px 25px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
        label { display: block; margin-top: 10px; font-weight: bold; font-size: 14px; }
        input[type=text], input[type=number] { width: 100%; padding: 8px; margin-top: 4px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { margin-top: 15px; padding: 10px 18px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; color: white; }
        .btn-dodaj { background: #2980b9; }
        .btn-zatvori { background: #27ae60; margin-left: 8px; }
        .btn-novi { background: #8e44ad; }
        .btn-storno { background: #e67e22; padding: 6px 12px; font-size: 13px; margin-top: 0; }
        .btn-popust { background: #16a085; padding: 6px 12px; font-size: 13px; margin-top: 8px; }
        .btn-reset { background: #c0392b; }
        .poruka { margin-top: 15px; padding: 10px; border-radius: 4px; background: #eef; font-size: 14px; }
        .stavka-red { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px dashed #eee; }
        .racun { background: #fbfbfb; border: 1px solid #eee; padding: 15px 20px; border-radius: 8px; margin-bottom: 12px; }
        .racun h3 { margin-top: 0; color: #34495e; }
        .suma-racuna { text-align: right; font-weight: bold; margin-top: 6px; color: #2980b9; }
        .konacna-suma { text-align: right; font-weight: bold; color: #16a085; font-size: 16px; }
        .ukupno { background: #2c3e50; color: white; padding: 18px 25px; border-radius: 8px; font-size: 19px; text-align: center; margin-top: 10px; }
        .prazno { color: #999; font-style: italic; }
        .popust-forma { display: flex; gap: 8px; align-items: center; margin-top: 10px; }
        .popust-forma input { width: 80px; }
        .search-forma { display: flex; gap: 8px; }
        .search-forma input { flex: 1; }
        .search-forma button { margin-top: 0; }
    </style>
</head>
<body>

    <h1><?php echo $nazivButika; ?></h1>
    <p class="podnaslov">Dan 6 — uređivanje i brisanje podataka</p>

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

    <div class="card">
        <h2>Storniraj stavku</h2>
        <form method="GET" action="" class="search-forma">
            <input type="text" name="broj" placeholder="Unesi broj računa (npr. 1)" value="<?php echo htmlspecialchars($trazeniBroj); ?>">
            <button type="submit" class="btn-storno">Pretraži</button>
        </form>

        <?php if ($trazeniBroj !== ""): ?>
            <?php if ($trazeniRacun === null): ?>
                <p class="prazno" style="margin-top:15px;">Račun br. <?php echo htmlspecialchars($trazeniBroj); ?> nije pronađen.</p>
            <?php else: ?>
                <h3 style="margin-top:20px;">Račun br. <?php echo $trazeniRacun["broj_racuna"]; ?></h3>
                <?php if (count($trazeniRacun["stavke"]) === 0): ?>
                    <p class="prazno">Ovaj račun nema stavki.</p>
                <?php else: ?>
                    <?php foreach ($trazeniRacun["stavke"] as $stavka): ?>
                        <div class="stavka-red">
                            <span><?php echo htmlspecialchars($stavka["naziv"]); ?> — <?php echo number_format($stavka["cijena"], 2); ?> KM</span>
                            <form method="POST" action="" onsubmit="return confirm('Storniraj ovu stavku?');">
                                <input type="hidden" name="stavka_id" value="<?php echo $stavka["id"]; ?>">
                                <input type="hidden" name="broj_racuna" value="<?php echo $trazeniRacun["broj_racuna"]; ?>">
                                <button type="submit" name="storniraj_stavku" class="btn-storno">Storniraj</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <h2>Zatvoreni računi
        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Zapoceti novi dan? Svi trenutni podaci ce biti obrisani.');">
            <button type="submit" name="novi_dan" class="btn-reset" style="float:right; margin-top:0;">Započni novi dan</button>
        </form>
    </h2>

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
                <?php if (count($racun["stavke"]) === 0): ?>
                    <p class="prazno">Nema stavki (sve stornirane).</p>
                <?php else: ?>
                    <?php foreach ($racun["stavke"] as $stavka): ?>
                        <div class="stavka-red">
                            <span><?php echo htmlspecialchars($stavka["naziv"]); ?></span>
                            <span><?php echo number_format($stavka["cijena"], 2); ?> KM</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="suma-racuna">Suma stavki: <?php echo number_format($racun["suma"], 2); ?> KM</div>

                <form method="POST" action="" class="popust-forma">
                    <input type="hidden" name="racun_id" value="<?php echo $racun["id"]; ?>">
                    <label style="margin:0;">Popust %:</label>
                    <input type="number" step="0.01" name="popust" value="<?php echo $racun["popust"]; ?>">
                    <button type="submit" name="sacuvaj_popust" class="btn-popust">Sačuvaj popust</button>
                </form>

                <div class="konacna-suma">Za naplatu: <?php echo number_format($racun["konacna_suma"], 2); ?> KM</div>
            </div>
        <?php endforeach; ?>

        <div class="ukupno">
            Ukupna dnevna suma (<?php echo count($zatvoreniRacuni); ?> računa):
            <strong><?php echo number_format($ukupnaDnevnaSuma, 2); ?> KM</strong>
        </div>
    <?php endif; ?>

</body>
</html>