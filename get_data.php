<?php
require_once 'connection.php';

try {
    $fm = getFileMakerConnection();
    $token = $fm->getToken();

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://172.16.8.104/fmi/data/vLatest/databases/OrderSysV3.fmp12/layouts/Order/records",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $token"
        ],
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_SSL_VERIFYPEER => false,  
        CURLOPT_SSL_VERIFYHOST => false  
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if (curl_errno($curl)) {
        throw new Exception("cURL error: " . curl_error($curl));
    }

    curl_close($curl);

    $responseData = json_decode($response, true);

    if (
        isset($responseData['messages'][0]['code']) &&
        $responseData['messages'][0]['code'] === "0"
    ) {
        header('Content-Type: application/json');
        echo json_encode([
            'token' => $token,
            'data' => $responseData['response']['data']
        ], JSON_PRETTY_PRINT);
    } else {
        throw new Exception("Unexpected response from FileMaker");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'messages' => $responseData['messages'][0]['message'] ?? 'No message returned',
        'status' => $httpCode ?? 0
    ]);
}
