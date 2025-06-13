<?php
require_once 'connection.php';
try {
    $fm = getFileMakerConnection();
    $token = $fm->getToken();

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://172.16.8.104/fmi/data/vLatest/databases/OrderSysV3.fmp12/layouts/AddVendors/records",
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
    curl_close($curl);

    $responseData = json_decode($response, true);

    if (
        isset($responseData['messages'][0]['code']) &&
        $responseData['messages'][0]['code'] === "0"
    ) {
        $vendors = array_map(function ($item) {
            return [
                'VendorName' => $item['fieldData']['VendorName_t'] ?? '',
                'VendorEmail' => $item['fieldData']['VendorEmail_t'] ?? '',
                'VendorAddress' => $item['fieldData']['VendorCompanyAddresh_t'] ?? '',
                'MobileNo' => $item['fieldData']['MobileNo_t'] ?? ''
            ];
        }, $responseData['response']['data']);

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'vendors' => $vendors
        ]);
    } else {
        throw new Exception("Unexpected response from FileMaker");
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
