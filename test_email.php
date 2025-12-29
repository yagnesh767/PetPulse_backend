<?php
require 'mailer.php';

if (sendOTP('yagneshbattu07@gmail.com', '123456')) {
    echo "EMAIL SENT SUCCESSFULLY";
} else {
    echo "EMAIL FAILED";
}
