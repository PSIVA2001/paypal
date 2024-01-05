<?php

include 'config.php';
global $client_id, $client_secret, $authurl, $paymenturl;
$credentials = $client_id . ':' . $client_secret;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formErrors = validateForm($_POST);
    if (!empty($formErrors)) {
        $response = array('status' => 'error', 'errors' => $formErrors);
        echo json_encode($response);
        exit;
    } else {
        $token = handleCurl($credentials, $authurl, $_POST, true);
        $response = handleCurl($token, $paymenturl, $_POST);
        echo json_encode($response);
        exit;
    }
}
function handleCurl($credentials, $url, $formdata, $isauth = false)
{
    $postdata = $isauth ? http_build_query(['grant_type' => 'client_credentials'], '', '&') : getBody($formdata);
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $headers = [];
    $headers[] = $isauth ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json';
    if (!$isauth) {
        $headers[] = "Authorization: Bearer " . $credentials;
    } else {
        curl_setopt($curl, CURLOPT_USERPWD, $credentials);
    }
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postdata,
            CURLOPT_HTTPHEADER => $headers
        ]);
        $response = curl_exec($curl);
    if (curl_errno($curl)) {
        echo json_encode(array('status' => 'curlError', 'message' => curl_error($curl)));
        exit;
    }
    curl_close($curl);
    $data = json_decode($response, true);
    $error = handleError($data);
    if (!$error) {
        return $isauth ? $data['access_token'] : array('status' => 'success', 'url' => getLink($data));
    }
}

function handleError($data)
{
    if (isset($data['error'])) {
        // This case is used when the credentials error occurs
        echo json_encode(array('status' => 'curlError', 'message' => json_encode($data['error_description'])));
        exit;
    } else if(isset($data['name'])) {
        // this case is used when currency is not supported. example INR
        echo json_encode(array('status' => 'curlError', 'message' => json_encode($data['details'][0]['issue'])));
        exit;
    }
    return false;
}

function getBody($data)
{
    $payment_data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'amount' => [
                    'value' => $data['amount'],
                    'currency_code' => $data['currency']
                ]
            ]
        ],
        'payment_source' => [
            'paypal' => [
                'experience_context' => [
                    'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                    'user_action' => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                    'landing_page' => 'GUEST_CHECKOUT',
                    'return_url' => 'http://localhost/paypal/success.html',
                    'cancel_url' => 'http://localhost/paypal/error.html'
                ]
            ]
        ]
    ];
    return json_encode($payment_data);
}

function validateForm($formData)
{
    $errors = [];
    if (empty($formData['amount']) || !is_numeric($formData['amount'])) {
        $errors['amount'] = 'Invalid amount. Please enter a valid positive numeric value.';
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $formData['amount'])) {
        $errors['amount'] = 'Please Enter Valid amount format (numeric with up to two decimal places)';
    }

    if (empty($formData['currency'])) {
        $errors['currency'] = 'Currency is required.';
    }

    return $errors;
}

function getLink($data)
{
    $payerActionLink = null;
    foreach ($data['links'] as $link) {
        if ($link['rel'] === 'payer-action') {
            $payerActionLink = $link['href'];
            break;
        }
    }

    return $payerActionLink;
}






