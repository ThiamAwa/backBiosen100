<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsController extends Controller
{
    public function sendSms(Request $request)
    {
        try {
            $request->validate([
                'recipients'         => 'required|array|min:1',
                'recipients.*.phone' => 'required|string',
                'recipients.*.name'  => 'required|string',
                'message'            => 'required|string|max:1000',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $e->errors(),
            ], 422);
        }

        $accountId = config('services.lafricamobile.api_key');
        $password  = config('services.lafricamobile.api_secret');
        $sender    = config('services.lafricamobile.sender_name', 'ISI_01');
        $apiUrl    = 'https://lamsms.lafricamobile.com/api';

        if (!$accountId || !$password) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration SMS manquante',
            ], 500);
        }

        $results      = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($request->recipients as $recipient) {
            try {
                $firstName = explode(' ', $recipient['name'])[0];
                $message   = "Bonjour {$firstName}, " . $request->message;
                $phone     = $this->formatPhoneNumber($recipient['phone']);

                // ── Format JSON selon la doc LAfricaMobile ──
                $postData = http_build_query([
                    'accountid' => $accountId,
                    'password'  => $password,
                    'sender'    => $sender,
                    'text'      => $message,
                    'to'        => $phone,
                    'ret_id'    => Str::uuid()->toString(),
                ]);

                Log::info('📤 Envoi SMS', [
                    'to'   => $phone,
                    'name' => $recipient['name'],
                ]);

                // ── cURL direct comme la doc PHP ──
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL            => $apiUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING       => '',
                    CURLOPT_MAXREDIRS      => 10,
                    CURLOPT_TIMEOUT        => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST  => 'POST',
                    CURLOPT_POSTFIELDS     => $postData,
                    CURLOPT_SSL_VERIFYPEER => false,  // ← SSL fix Windows
                    CURLOPT_SSL_VERIFYHOST => false,  // ← SSL fix Windows
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/x-www-form-urlencoded',
                    ],
                ]);

                $responseBody = trim(curl_exec($curl));
                $httpCode     = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $curlError    = curl_error($curl);
                curl_close($curl);

                Log::info('📨 Réponse API', [
                    'http_code' => $httpCode,
                    'body'      => $responseBody,
                    'curl_error'=> $curlError,
                ]);

                if (empty($curlError) && $httpCode == 200 && !empty($responseBody)){
                    $successCount++;
                    $results[] = [
                        'phone'      => $phone,
                        'name'       => $recipient['name'],
                        'status'     => 'success',
                        'message_id' => $responseBody,
                    ];
                    Log::info("✅ SMS envoyé à {$recipient['name']} - ID: {$responseBody}");
                } else {
                    $failureCount++;
                    $results[] = [
                        'phone'        => $phone,
                        'name'         => $recipient['name'],
                        'status'       => 'error',
                        'message'      => $curlError ?: ($responseBody ?: 'Erreur inconnue'),
                        'api_response' => $responseBody,
                    ];
                    Log::error("❌ Échec SMS à {$recipient['name']}: " . ($curlError ?: $responseBody));
                }

            } catch (\Exception $e) {
                $failureCount++;
                $results[] = [
                    'phone'   => $recipient['phone'],
                    'name'    => $recipient['name'],
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ];
            }

            usleep(200000);
        }

        return response()->json([
            'success' => $successCount > 0,
            'message' => "{$successCount} SMS envoyé(s)" . ($failureCount > 0 ? ", {$failureCount} échec(s)" : ""),
            'summary' => [
                'total'   => count($request->recipients),
                'success' => $successCount,
                'failure' => $failureCount,
            ],
            'details' => $results,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // SOLDE
    // ══════════════════════════════════════════════════════════
    public function getSmsBalance()
    {
        $accountId = config('services.lafricamobile.api_key');
        $password  = config('services.lafricamobile.api_secret');
        $apiUrl    = 'https://lamsms.lafricamobile.com/api';

        if (!$accountId || !$password) {
            return response()->json(['success' => false, 'message' => 'Configuration manquante'], 500);
        }

        try {
            // ── XML balance selon la doc officielle ──
            $xmlBody  = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
            $xmlBody .= '<push accountid="' . htmlspecialchars($accountId) . '" ';
            $xmlBody .= 'password="' . htmlspecialchars($password) . '">' . "\n";
            $xmlBody .= '  <balance/>' . "\n";
            $xmlBody .= '</push>';

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => $xmlBody,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/xml'],
            ]);

            $responseBody = trim(curl_exec($curl));
            $curlError    = curl_error($curl);
            curl_close($curl);

            Log::info('💰 Solde SMS brut', ['body' => $responseBody, 'error' => $curlError]);

            if ($curlError) {
                return response()->json(['success' => false, 'message' => $curlError], 500);
            }

            // Réponse peut être "SMS:9 VOCAL:10 USSD:10" ou un XML
            $balance = ['sms' => 0, 'vocal' => 0, 'ussd' => 0, 'raw' => $responseBody];

            // Format texte : "SMS:9 VOCAL:10 USSD:10"
            if (preg_match('/SMS[:\s]+(\d+)/i', $responseBody, $m)) {
                $balance['sms'] = (int)$m[1];
            }
            if (preg_match('/VOCAL[:\s]+(\d+)/i', $responseBody, $m)) {
                $balance['vocal'] = (int)$m[1];
            }
            if (preg_match('/USSD[:\s]+(\d+)/i', $responseBody, $m)) {
                $balance['ussd'] = (int)$m[1];
            }

            // Format XML
            if ($balance['sms'] === 0 && str_contains($responseBody, '<')) {
                $xml = @simplexml_load_string($responseBody);
                if ($xml) {
                    $balance['sms']   = (int)($xml->sms ?? 0);
                    $balance['vocal'] = (int)($xml->vocal ?? 0);
                    $balance['ussd']  = (int)($xml->ussd ?? 0);
                }
            }

            return response()->json(['success' => true, 'balance' => $balance]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ══════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════
    private function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') $phone = substr($phone, 1);
        $phone = ltrim($phone, '+');
        if (substr($phone, 0, 3) !== '221') $phone = '221' . $phone;
        return '00' . $phone;  // Format: 00221XXXXXXXXX
    }
}
