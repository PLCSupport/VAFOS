$(document).ready(function() {
    var barcodeInput = document.getElementById('barcodeInput');
    var isManualInput = false;

    var modeSwitch = document.getElementById('modeSwitch');
    var isAutomatic = modeSwitch.checked;

    // Při změně přepínače aktualizujeme režim
    modeSwitch.addEventListener('change', function() {
        isAutomatic = this.checked;
    });

    barcodeInput.addEventListener('focus', function() {
        this.value = '';
        hideAlert();  // Skryje alert při focuse vstupního pole
    });

    barcodeInput.addEventListener('input', function() {
        var barcodeValue = this.value;
        var isScan = barcodeValue.length > 0;

        if (isScan && isAutomatic) {
            checkBarcodeTypeAndRedirect(barcodeValue);
        } else if (isScan && !isAutomatic) {
            isManualInput = true;
        }
    });

    $('#confirmButton').on('click', function() {
        var barcodeValue = barcodeInput.value;
        if (barcodeValue.length > 0 && isManualInput) {
            checkBarcodeTypeAndRedirect(barcodeValue);
        }
    });

    barcodeInput.addEventListener('keypress', function(e) {
        if (e.keyCode === 13 && !isManualInput) {
            e.preventDefault();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            barcodeInput.focus();
        }
    });

    function checkBarcodeTypeAndRedirect(barcode) {
        $.ajax({
            url: 'http://srv-vafos.vafo.local/vafosapp/check_barcode_type.php', 
            type: 'POST',
            data: { barcode: barcode },
            success: function(response) {
                console.log("Response from server: ", response);

                var barcodeType = response.replace(/"/g, '');  
                console.log("Parsed response: ", barcodeType);

                if (barcodeType === 'no_result') {
                    showAlert('Objekt nenalezen.');
                    barcodeInput.value = '';
                    barcodeInput.focus(); // Nastaví focus zpět na vstupní pole
                    return;
                }

                var currentUrl = window.location.href;

                switch(barcodeType) {
                    case 'StockUnitCode':
                    case 'StockUnitSSCC':
                        if (!currentUrl.includes('detail_sklad_doklad.php')) {
                            window.location.href = 'http://srv-vafos.vafo.local/vafosapp/pages/detail_sklad_jednotka.php?barcode=' + encodeURIComponent(barcode);
                        }
                        break;
                    case 'StockReceiptCode':
                        window.location.href = 'http://srv-vafos.vafo.local/vafosapp/pages/detail_sklad_doklad.php?barcode=' + encodeURIComponent(barcode);
                        break;
                    case 'SkladoveMistoName':
                    case 'SkladoveMistoNazev':
                        if (!currentUrl.includes('detail_sklad_jednotka.php')) {
                            window.location.href = 'http://srv-vafos.vafo.local/vafosapp/pages/detail_sklad_misto.php?barcode=' + encodeURIComponent(barcode);
                        }
                        break;
                }
            },
            error: function() {
                showAlert('Chyba při volání procedury.');
            }
        });
    }

    function showAlert(message) {
        var trimmedMessage = message.trim();  // Oříznutí případných mezer
        var alertHtml = `<div class="custom-alert alert-dismissible fade show" role="alert">
                            ${trimmedMessage}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>`;
        $('#alertContainer').html(alertHtml);  // Vloží alert do divu s id 'alertContainer'
    }

    function hideAlert() {
        $('#alertContainer').html('');
    }
});
