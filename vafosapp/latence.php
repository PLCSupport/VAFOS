<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Latence sítě</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding: 10px;
        }

        .info-box {
            background-color: #fff;
            padding: 15px;
            margin-bottom: 10px; /* Zmenšený margin */
            border-radius: 8px;
            box-shadow: 0px 3px 8px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.3s ease;
        }

        .info-box:hover {
            box-shadow: 0px 5px 12px rgba(0, 0, 0, 0.12);
        }

        h1 {
            margin-bottom: 20px;
            color: #111638;
            text-align: center;
            font-weight: bold;
        }

        .info-box strong {
            color: #17a2b8;
        }

        .info-box p {
            color: #495057;
            margin-bottom: 5px; /* Zmenšený spodní margin u odstavců */
        }

        .highlight {
            color: #28a745;
            font-weight: bold;
        }

        #results {
            background-color: #e9ecef;
            border-left: 4px solid #28a745;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Informace o latenci</h1>

        <div class="info-box">
            <?php
            ob_start();

            $start = microtime(true);
            $clientIp = $_SERVER['REMOTE_ADDR'];
            $serverName = $_SERVER['SERVER_NAME'];
            $remotePort = $_SERVER['REMOTE_PORT'];
            $phpVersion = phpversion();
            $maxExecutionTime = ini_get('max_execution_time');
            $end = microtime(true);
            $executionTime = ($end - $start) * 1000;

            echo "<p><strong>Vaše IP adresa:</strong> $clientIp</p>";
            echo "<p><strong>Port klienta:</strong> $remotePort</p>";
            echo "<p><strong>Název serveru:</strong> $serverName</p>";
            echo "<p><strong>Verze PHP:</strong> $phpVersion</p>";
            echo "<p><strong>Maximální povolená doba běhu skriptu:</strong> $maxExecutionTime s</p>";
            echo "<p><strong>Čas zpracování na serveru:</strong> <span class='highlight'>" . round($executionTime, 2) . " ms</span></p>";

            $responseSize = ob_get_length();
            echo "<p><strong>Velikost odpovědi:</strong> $responseSize bajtů</p>";
            ob_end_flush();
            ?>
        </div>

        <div id="results"></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        window.addEventListener('beforeunload', function () {
            localStorage.setItem('scrollPosition', window.scrollY);
        });

        window.addEventListener('load', function () {
            if (localStorage.getItem('scrollPosition') !== null) {
                window.scrollTo(0, parseInt(localStorage.getItem('scrollPosition')));
            }
        });

        setTimeout(function () {
            window.location.reload();
        }, 5000);

        window.onload = function () {
            var performance = window.performance.timing;

            var dnsLookup = performance.domainLookupEnd - performance.domainLookupStart;
            var connectionTime = performance.connectEnd - performance.connectStart;
            var latency = performance.responseEnd - performance.requestStart;
            var ttfb = performance.responseStart - performance.requestStart;
            var domContentLoaded = performance.domContentLoadedEventEnd - performance.navigationStart;
            var pageLoadTime = performance.loadEventEnd - performance.navigationStart;

            var pageLoadTimeText = "";
            if (pageLoadTime >= 0) {
                pageLoadTimeText = "Celkový čas načítání stránky: " + pageLoadTime + " ms<br>";
            }

            var results = "<strong>Doba hledání DNS:</strong> " + dnsLookup + " ms<br>";
            results += "<strong>Doba připojení:</strong> " + connectionTime + " ms<br>";
            results += "<strong>Latence (RTT) mezi klientem a serverem:</strong> " + latency + " ms<br>";
            results += "<strong>Doba do prvního bajtu (TTFB):</strong> " + ttfb + " ms<br>";
            results += "<strong>Čas načtení HTML dokumentu (DomContentLoaded):</strong> " + domContentLoaded + " ms<br>";
            results += pageLoadTimeText;

            document.getElementById('results').innerHTML = results;
        };
    </script>
</body>

</html>
