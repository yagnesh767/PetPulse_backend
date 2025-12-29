<?php
if (file_exists(__DIR__ . '/sendgrid/lib/SendGrid.php')) {
    echo "SendGrid library FOUND";
} else {
    echo "SendGrid library NOT FOUND";
}
