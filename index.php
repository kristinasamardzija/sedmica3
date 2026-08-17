<?php


$nazivButika = "Karolina Boutique";
$datumIzvjestaja = date("d.m.Y.");


$racuni = [
    [
        "vrijeme" => "09:15",
        "stavke" => [
            ["naziv" => "Haljina", "cijena" => 45.00],
            ["naziv" => "Šal", "cijena" => 15.00],
        ]
    ],
    [
        "vrijeme" => "11:40",
        "stavke" => [
            ["naziv" => "Jakna", "cijena" => 80.00],
        ]
    ],
    [
        "vrijeme" => "14:20",
        "stavke" => [
            ["naziv" => "Majica", "cijena" => 20.00],
            ["naziv" => "Farmerke", "cijena" => 55.00],
            ["naziv" => "Kaiš", "cijena" => 12.50],
        ]
    ],
];


$ukupnaDnevnaSuma = 0;
$brojRacuna = 0;


$obradjeniRacuni = []; 

foreach ($racuni as $racun) {
    $brojRacuna++;
    $sumaRacuna = 0;

    foreach ($racun["stavke"] as $stavka) {
        $sumaRacuna += $stavka["cijena"];
    }

    
    $ukupnaDnevnaSuma += $sumaRacuna;

    $obradjeniRacuni[] = [
        "broj" => $brojRacuna,
        "vrijeme" => $racun["vrijeme"],
        "stavke" => $racun["stavke"],
        "suma" => $sumaRacuna
    ];
}


if ($ukupnaDnevnaSuma >= 100) {
    $ocjenaDana = "Odličan dan! 🎉";
} elseif ($ukupnaDnevnaSuma >= 50) {
    $ocjenaDana = "Solidan dan.";
} else {
    $ocjenaDana = "Slabiji dan.";
}
?>
<!DOCTYPE html>
<html lang="ba">
<head>
    <meta charset="UTF-8">
    <title>Dnevni izvještaj - <?php echo $nazivButika; ?></title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 650px; margin: 40px auto; padding: 0 20px; background: #f5f5f5; }
        h1 { color: #2c3e50; margin-bottom: 0; }
        .podnaslov { color: #7f8c8d; margin-top: 4px; }
        .racun { background: white; padding: 18px 22px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 15px; }
        .racun h3 { margin-top: 0; color: #34495e; }
        .stavka { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px dashed #eee; }
        .suma-racuna { text-align: right; font-weight: bold; margin-top: 8px; color: #2980b9; }
        .ukupno { background: #2c3e50; color: white; padding: 20px 25px; border-radius: 8px; font-size: 20px; text-align: center; }
        .ocjena { text-align: center; margin-top: 10px; font-size: 16px; color: #27ae60; }
    </style>
</head>
<body>

    <h1><?php echo $nazivButika; ?></h1>
    <p class="podnaslov">Dnevni izvještaj za <?php echo $datumIzvjestaja; ?></p>

    <?php foreach ($obradjeniRacuni as $r): ?>
        <div class="racun">
            <h3>Račun br. <?php echo $r["broj"]; ?> <span style="font-weight:normal; color:#999; font-size:14px;">(<?php echo $r["vrijeme"]; ?>)</span></h3>

            <?php foreach ($r["stavke"] as $stavka): ?>
                <div class="stavka">
                    <span><?php echo $stavka["naziv"]; ?></span>
                    <span><?php echo number_format($stavka["cijena"], 2); ?> KM</span>
                </div>
            <?php endforeach; ?>

            <div class="suma-racuna">Suma računa: <?php echo number_format($r["suma"], 2); ?> KM</div>
        </div>
    <?php endforeach; ?>

    <div class="ukupno">
        Ukupna dnevna suma (<?php echo $brojRacuna; ?> računa): 
        <strong><?php echo number_format($ukupnaDnevnaSuma, 2); ?> KM</strong>
    </div>

    <p class="ocjena"><?php echo $ocjenaDana; ?></p>

</body>
</html>