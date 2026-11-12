<?php
declare(strict_types=1);
$token = $_GET['token'] ?? '';
?>

<!DOCTYPE html>
<html lang=en>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail Verification</title>
</head>

<body>
    <h1>E-Mail Verification</h1>
    <p id="status">Verifying your E-Mail Address...</p>

    <script>
        // alert("Script!");
        const token = <?= json_encode($token) ?>;
        const status = document.getElementById('status');

        if (!token) {
            alert("No Token!");
            status.textContext = 'Verification link is missing its token';
        } else {
            //alert("Calling /api/verify with: " + token);
            fetch('/api/verify',
            {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ token: token })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    status.textContext = data.error.message || 'Verification Failed';
                } else {
                    status.textContext = 'Your E-Mail Address has been Verified';
                }
                //alert("Fetch Complete!");
            })
            .catch(error => {
                status.textContext = 'Unable to contact the verification service';
                console.error(error);
                alert("ERROR: " + error);
            });
        }
    </script>
</body>

</html>