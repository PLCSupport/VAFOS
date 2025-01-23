<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stažení aplikace VAFOS APK</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
        }

        .container {
            margin-top: 50px;
        }

        .apk-card {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            transition: transform 0.3s ease;
            position: relative; /* Relativní pozice pro zarovnání tlačítka */
            overflow: hidden;
        }

        .apk-card:hover {
            transform: translateY(-5px);
        }

        .apk-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #2ec2ce;
            color: white;
            padding: 5px 10px;
            font-weight: bold;
            font-size: 14px;
            border-bottom-left-radius: 8px;
        }

        .apk-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .btn-download {
            position: absolute; /* Absolutní pozice */
            bottom: 0; /* Přesně k dolnímu okraji */
            right: 0; /* Přesně k pravému okraji */
            border-radius: 0 0 10px 0; /* Zaoblení dolního pravého rohu */
            padding: 4px 8px; /* Menší velikost tlačítka */
            font-size: 12px; /* Menší text */
            font-weight: bold; /* Tučné písmo */
        }

        .small-info {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: -10px;
        }

        @media (max-width: 576px) {
            .apk-card {
                padding-bottom: 60px;
            }

            .btn-download {
                font-size: 10px;
                padding: 3px 6px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h1 class="text-center mb-4">Aktuální aplikace VAFOS</h1>
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <ul class="apk-list">
                <?php
                // Pole s APK soubory (název, cesta, verze a specifické informace o aktualizacích)
                $apk_files = [
                    //["name" => "Vafos ČÍČENICE", "file" => "https://vafos.vafo.cz/vafosapppro-cic/Vafos_PRO_CIC.apk", "version" => "v.5.0.5", "updates" => "Přidaný filtr CIC dle lokace čtečky, upravený vzhled SJ a upravený vzhled vyskakovacího okna pro úpravu množství a celkový vzhled polí"],
                    //["name" => "Vafos CHELČICE", "file" => "https://vafos.vafo.cz/vafosapppro-che/Vafos_PRO_CHE.apk", "version" => "v.5.0.5", "updates" => "Přidaný filtr CHE dle lokace čtečky, upravený vzhled SJ a upravený vzhled vyskakovacího okna pro úpravu množství a celkový vzhled polí"],
                    //["name" => "Vafos CHOTOVINY", "file" => "https://vafos.vafo.cz/vafosapppro-cho/Vafos_PRO_CHO.apk", "version" => "v.5.0.5", "updates" => "Přidaný filtr CHO dle lokace čtečky, upravený vzhled SJ a upravený vzhled vyskakovacího okna pro úpravu množství a celkový vzhled polí"],
                    //["name" => "Vafos CHRÁŠŤANY", "file" => "https://vafos.vafo.cz/vafosapppro-chr/Vafos_PRO_CHR.apk", "version" => "Připravuje se", "updates" => "Nové logo, vylepšené rozhraní, optimalizace výkonu"],
                    //["name" => "Vafos RATMÍROV", "file" => "https://vafos.vafo.cz/vafosapppro-rat/Vafos_PRO_RAT.apk", "version" => "v.5.0.5", "updates" => "Přidaný filtr RAT dle lokace čtečky, upravený vzhled SJ a upravený vzhled vyskakovacího okna pro úpravu množství a celkový vzhled polí"],
                    ["name" => "Vafos PRO", "file" => "https://vafos.vafo.cz/vafosapppro/Vafos_PRO.apk", "version" => "v.6.0.0", "updates" => "Změna politiky přihlašování pomocí už.jména a hesla, RFID. Není nutné mít pro každou lokaci aplikaci."],
                    ["name" => "Vafos TEST", "file" => "https://vafos.vafo.cz/vafosapppro/Vafos_TST.apk", "version" => "v.6.0.0", "updates" => "Změna politiky přihlašování pomocí už.jména a hesla, RFID. Není nutné mít pro každou lokaci aplikaci."]
                ];

                // Procházení pole a zobrazení souborů ke stažení
                foreach ($apk_files as $apk) {
                    // Nastavení značky (badge) - pokud je název VAFOS TEST, změníme značku na TST
                    $badge_text = ($apk['name'] === "Vafos TEST") ? "TST" : "PRO";

                    // Nastavení třídy a akce tlačítka na základě verze
                    $button_class = ($apk['version'] === 'není dostupná') ? 'btn-danger' : (($apk['version'] === 'Připravuje se') ? 'btn-warning' : 'btn-success');
                    $button_text = ($apk['version'] === 'není dostupná') ? 'Není dostupné' : (($apk['version'] === 'Připravuje se') ? 'Připravuje se' : 'Stáhnout');
                    $button_disabled = ($apk['version'] === 'není dostupná' || $apk['version'] === 'Připravuje se') ? 'disabled' : '';

                    // Alarmující informace
                    $alert_text = "Upozornění: Před stažením si ověřte kompatibilitu!";

                    echo "
                    <li class='apk-item mb-3'>
                        <div class='card apk-card p-3'>
                            <div class='apk-badge'>{$badge_text}</div>
                            <div class='d-flex justify-content-between align-items-center'>
                                <div>
                                    <h5>{$apk['name']}</h5>
                                    <p class='text-muted'>Verze: {$apk['version']}</p>
                                    <p class='small-info'>Aktualizace: {$apk['updates']}</p>
                                    <p class='text-danger fw-bold'>{$alert_text}</p>
                                </div>
                            </div>
                            <a href='{$apk['file']}' class='btn {$button_class} btn-sm btn-download' {$button_disabled} download>
                                <i class='fa fa-download'></i> {$button_text}
                            </a>
                        </div>
                    </li>";
                }
                ?>
            </ul>
        </div>
    </div>
</div>

<!-- Bootstrap JS & dependencies -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

</body>
</html>