jQuery(document).ready(function($) {
    $('#start-scan').on('click', function() {
        let offset = 0;
        $('#scan-status').html('<p>Starting scan...</p>');

        function scanBatch() {
            $.post(ytScannerAjax.ajax_url, {
                action: 'yt_scan_batch',
                offset: offset
            }, function(response) {
                if (response.success) {
                    if (response.data.done) {
                        $('#scan-status').append('<p>Scan complete!</p>');
                    } else {
                        offset = response.data.offset;
                        response.data.scanned.forEach(item => {
                            $('#scan-status').append('<p>' + item + '</p>');
                        });
                        scanBatch(); // Continue scanning
                    }
                } else {
                    $('#scan-status').append('<p style="color: red;">Error: ' + response.data + '</p>');
                }
            }).fail(function(jqXHR) {
                $('#scan-status').append('<p style="color: red;">AJAX Error: ' + jqXHR.responseText + '</p>');
            });
        }

        scanBatch(); // Start the first batch
    });
});
