<?php
session_start();

$nazivButika = "Karolina Boutique";
$datumIzvjestaja = date("d.m.Y.");

if (!isset($_SESSION["trenutne_stavke"])) {
    $_SESSION["trenutne_stavke"] = []; 
}
if (!isset($_SESSION["zatvoreni_racuni"])) {
    $_SESSION["zatvoreni_racuni"] = []; 
}
if (!isset($_SESSION["sledeci_broj_racuna"])) {
    $_SESSION["sledeci_broj_racuna"] = 1; 
}
$poruka = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    
    if (isset($_POST["dodaj_stavku"])) {
        $naziv = isset($_POST["naziv"]) ? trim($_POST["naziv"]) : "";
        $cijena = isset($_POST["cijena"]) ? trim($_POST["cijena"]) : "";

        if ($naziv === "" || $cijena === "") {
            $poruka = "⛔ Moraš unijeti naziv i cijenu.";
        } elseif (!is_numeric($cijena) || (float)$cijena <= 0) {
            $poruka = "⛔ Cijena mora biti broj veći od 0.";
        } else {
            $_SESSION["trenutne_stavke"][] = [
                "naziv" => htmlspecialchars($naziv),
                "cijena" => (float)$cijena
            ];
            $poruka = "✅ Stavka \"" . htmlspecialchars($naziv) . "\" dodana na trenutni račun.";
        }
    }

    
    if (isset($_POST["zatvori_racun"])) {
        if (count($_SESSION["trenutne_stavke"]) === 0) {
            $poruka = "⛔ Ne možeš zatvoriti prazan račun - dodaj bar jednu stavku.";
        } else {
            $sumaRacuna = 0;
            foreach ($_SESSION["trenutne_stavke"] as $stavka) {
                $sumaRacuna += $stavka["cijena"];
            }

            $_SESSION["zatvoreni_racuni"][] = [
                "broj" => $_SESSION["sledeci_broj_racuna"],
                "vrijeme" => date("H:i"),
                "stavke" => $_SESSION["trenutne_stavke"],
                "suma" => $sumaRacuna
            ];

            $poruka = "🧾 Račun br. " . $_SESSION["sledeci_broj_racuna"] . " zatvoren - ukupno " . number_format($sumaRacuna, 2) . " KM.";

            $_SESSION["sledeci_broj_racuna"]++;
            $_SESSION["trenutne_stavke"] = []; 
        }
    }

   
    if (isset($_POST["resetuj_dan"])) {
        $_SESSION["trenutne_stavke"] = [];
        $_SESSION["zatvoreni_racuni"] = [];
        $_SESSION["sledeci_broj_racuna"] = 1;
        $poruka = "🔄 Dan resetovan, sve obrisano.";
    }

    
    $_SESSION["poruka"] = $poruka;
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit;
}


if (isset($_SESSION["poruka"])) {
    $poruka = $_SESSION["poruka"];
    unset($_SESSION["poruka"]);
}


$sumaTrenutnog = 0;
foreach ($_SESSION["trenutne_stavke"] as $stavka) {
    $sumaTrenutnog += $stavka["cijena"];
}


$ukupnaDnevnaSuma = 0;
foreach ($_SESSION["zatvoreni_racuni"] as $racun) {
    $ukupnaDnevnaSuma += $racun["suma"];
}
?>
<!DOCTYPE html>
<html lang="ba">
<head>
    <meta charset="UTF-8">
    <title><?php echo $nazivButika; ?> - Dnevni račun</title>
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
        .btn-reset { background: #c0392b; float: right; margin-top: 0; }
        .btn-reset:hover { background: #922b21; }
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
    <p class="podnaslov">Dnevni izvještaj za <?php echo $datumIzvjestaja; ?></p>

    <?php if ($poruka !== ""): ?>
        <div class="poruka"><?php echo $poruka; ?></div>
    <?php endif; ?>


    <div class="card">
        <h2>🧾 Trenutni račun (br. <?php echo $_SESSION["sledeci_broj_racuna"]; ?>)</h2>

        <?php if (count($_SESSION["trenutne_stavke"]) === 0): ?>
            <p class="prazno">Nema još stavki na ovom računu.</p>
        <?php else: ?>
            <?php foreach ($_SESSION["trenutne_stavke"] as $stavka): ?>
                <div class="stavka-red">
                    <span><?php echo $stavka["naziv"]; ?></span>
                    <span><?php echo number_format($stavka["cijena"], 2); ?> KM</span>
                </div>
            <?php endforeach; ?>
            <div class="suma-racuna">Suma: <?php echo number_format($sumaTrenutnog, 2); ?> KM</div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="naziv">Naziv stavke:</label>
            <input type="text" id="naziv" name="naziv" placeholder="npr. Haljina">

            <label for="cijena">Cijena (KM):</label>
            <input type="number" step="0.01" id="cijena" name="cijena" placeholder="npr. 45.00">

            <button type="submit" name="dodaj_stavku" class="btn-dodaj">➕ Dodaj stavku</button>
            <button type="submit" name="zatvori_racun" class="btn-zatvori">✅ Zatvori račun</button>
        </form>
    </div>

    
    <h2>📋 Dnevni izvještaj
        <form method="POST" action="" style="display:inline;">
            <button type="submit" name="resetuj_dan" class="btn-reset" onclick="return confirm('Obrisati sve podatke za danas?');">🗑 Resetuj dan</button>
        </form>
    </h2>

    <?php if (count($_SESSION["zatvoreni_racuni"]) === 0): ?>
        <p class="prazno">Još nema zatvorenih računa danas.</p>
    <?php else: ?>
        <?php foreach ($_SESSION["zatvoreni_racuni"] as $racun): ?>
            <div class="racun">
                <h3>Račun br. <?php echo $racun["broj"]; ?> <span style="font-weight:normal; color:#999; font-size:13px;">(<?php echo $racun["vrijeme"]; ?>)</span></h3>
                <?php foreach ($racun["stavke"] as $stavka): ?>
                    <div class="stavka-red">
                        <span><?php echo $stavka["naziv"]; ?></span>
                        <span><?php echo number_format($stavka["cijena"], 2); ?> KM</span>
                    </div>
                <?php endforeach; ?>
                <div class="suma-racuna">Suma računa: <?php echo number_format($racun["suma"], 2); ?> KM</div>
            </div>
        <?php endforeach; ?>

        <div class="ukupno">
            Ukupna dnevna suma (<?php echo count($_SESSION["zatvoreni_racuni"]); ?> računa):
            <strong><?php echo number_format($ukupnaDnevnaSuma, 2); ?> KM</strong>
        </div>
    <?php endif; ?>

</body>
</html>