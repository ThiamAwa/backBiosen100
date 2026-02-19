<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsController extends Controller
{
    /**
     * Envoyer un SMS via l'API LAFRICAMOBILE
     * Conforme à la documentation officielle: https://lamsms.lafricamobile.com/api
     */
    public function sendSms(Request $request)
    {
        Log::info('📤 SMS Send Request', [
            'recipients_count' => count($request->recipients ?? []),
            'message_length' => strlen($request->message ?? '')
        ]);

        try {
            $request->validate([
                'recipients' => 'required|array|min:1',
                'recipients.*.phone' => 'required|string',
                'recipients.*.name' => 'required|string',
                'message' => 'required|string|max:1000',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            $recipients = $request->recipients;
            $baseMessage = $request->message;

            $results = [];
            $successCount = 0;
            $failureCount = 0;

            $accountId = config('services.lafricamobile.api_key');
            $password = config('services.lafricamobile.api_secret');
            $senderName = config('services.lafricamobile.sender_name', 'BioSen100');
            $apiUrl = config('services.lafricamobile.api_url', 'https://lamsms.lafricamobile.com/api');

            if (!$accountId || !$password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration SMS manquante (API_KEY ou API_SECRET)'
                ], 500);
            }

            foreach ($recipients as $recipient) {
                try {
                    $firstName = explode(' ', $recipient['name'])[0];
                    $personalizedMessage = "Bonjour {$firstName}, " . $baseMessage;
                    $phone = $this->formatPhoneNumber($recipient['phone']);

                    Log::info('📞 Envoi SMS', [
                        'to' => $phone,
                        'name' => $recipient['name'],
                        'message_preview' => substr($personalizedMessage, 0, 50) . '...'
                    ]);

                    // Construction du XML selon la documentation LAfricaMobile
                    // IMPORTANT: Utiliser CDATA pour le texte et sender qui ne commence PAS par un chiffre
                    $xmlBody = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
                    $xmlBody .= '<push accountid="' . htmlspecialchars($accountId) . '" ';
                    $xmlBody .= 'password="' . htmlspecialchars($password) . '" ';
                    $xmlBody .= 'sender="' . htmlspecialchars($senderName) . '">' . "\n";
                    $xmlBody .= '  <message>' . "\n";
                    $xmlBody .= '    <text><![CDATA[' . $personalizedMessage . ']]></text>' . "\n";
                    $xmlBody .= '    <to>' . $phone . '</to>' . "\n";
                    $xmlBody .= '  </message>' . "\n";
                    $xmlBody .= '</push>';

                    Log::info('📨 XML envoyé:', ['xml' => $xmlBody]);

                    // Envoi via POST avec Content-Type: application/xml
                    $response = Http::timeout(30)
                        ->withHeaders([
                            'Content-Type' => 'application/xml',
                        ])
                        ->withBody($xmlBody, 'application/xml')
                        ->post($apiUrl);

                    $responseBody = trim($response->body());

                    Log::info('📨 SMS API Response', [
                        'status' => $response->status(),
                        'body' => $responseBody,
                        'recipient' => $recipient['name']
                    ]);

                    // La réponse est un ID numérique si succès (ex: "12345666")
                    if ($response->successful() && is_numeric($responseBody) && (int)$responseBody > 0) {
                        $successCount++;
                        $results[] = [
                            'phone' => $phone,
                            'name' => $recipient['name'],
                            'status' => 'success',
                            'message' => 'SMS envoyé avec succès',
                            'message_id' => $responseBody
                        ];

                        Log::info("✅ SMS envoyé à {$recipient['name']} - Message ID: {$responseBody}");
                    } else {
                        $failureCount++;
                        $errorMsg = $this->parseErrorResponse($responseBody);

                        $results[] = [
                            'phone' => $phone,
                            'name' => $recipient['name'],
                            'status' => 'error',
                            'message' => $errorMsg,
                            'api_response' => $responseBody
                        ];

                        Log::error("❌ Échec envoi SMS à {$recipient['name']}: {$errorMsg}", [
                            'response' => $responseBody,
                            'status_code' => $response->status()
                        ]);
                    }

                } catch (\Exception $e) {
                    $failureCount++;
                    $results[] = [
                        'phone' => $recipient['phone'],
                        'name' => $recipient['name'],
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                    Log::error("❌ Exception lors de l'envoi à {$recipient['name']}: " . $e->getMessage());
                }

                // Pause entre chaque envoi pour éviter le rate limiting
                usleep(200000); // 0.2 secondes
            }

            return response()->json([
                'success' => $successCount > 0,
                'message' => "{$successCount} SMS envoyé(s) avec succès" . ($failureCount > 0 ? ", {$failureCount} échec(s)" : ""),
                'summary' => [
                    'total' => count($recipients),
                    'success' => $successCount,
                    'failure' => $failureCount
                ],
                'details' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur générale envoi SMS', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi des SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer le solde SMS
     * Conforme à la documentation LAfricaMobile
     */
    public function getSmsBalance()
    {
        Log::info('🔍 Demande de vérification du solde SMS');

        $accountId = config('services.lafricamobile.api_key');
        $password  = config('services.lafricamobile.api_secret');
        $apiUrl    = config('services.lafricamobile.api_url');

        if (!$accountId || !$password) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration SMS manquante'
            ], 200);
        }

        // Construction du XML pour vérifier le solde
        $xmlBody = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xmlBody .= '<push accountid="' . htmlspecialchars($accountId) . '" ';
        $xmlBody .= 'password="' . htmlspecialchars($password) . '">' . "\n";
        $xmlBody .= '  <balance/>' . "\n";
        $xmlBody .= '</push>';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/xml'
            ])
                ->withBody($xmlBody, 'application/xml')
                ->timeout(30)
                ->post($apiUrl);

            $body = trim($response->body());

            Log::info('📨 Balance API Response', [
                'status' => $response->status(),
                'body' => $body
            ]);

            // LAfricaMobile renvoie souvent JUSTE UN NOMBRE
            if (is_numeric($body)) {
                return response()->json([
                    'success' => true,
                    'balance' => [
                        'sms' => (int) $body,
                        'vocal' => 0,
                        'ussd' => 0
                    ]
                ]);
            }

            // Sinon essayer de parser le XML
            $balance = $this->parseBalanceResponse($body);

            return response()->json([
                'success' => true,
                'balance' => $balance
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur solde SMS', ['msg' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Impossible de récupérer le solde'
            ], 200);
        }
    }

    /**
     * Formater le numéro de téléphone au format LAfricaMobile
     * Format attendu: 00221XXXXXXXXX (pour le Sénégal)
     */
    private function formatPhoneNumber($phone)
    {
        // Nettoyer le numéro (garder seulement les chiffres)
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Retirer le premier 0 si présent (numéro local sénégalais)
        if (substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }

        // Retirer le + s'il est présent
        $phone = ltrim($phone, '+');

        // Ajouter le code pays 221 si absent
        if (substr($phone, 0, 3) !== '221') {
            $phone = '221' . $phone;
        }

        // Format final: 00221XXXXXXXXX
        return '00' . $phone;
    }

    /**
     * Parser la réponse d'erreur de l'API
     */
    private function parseErrorResponse($response)
    {
        // Messages d'erreur courants
        $errorMessages = [
            'invalid credentials' => 'Identifiants invalides (accountid ou password incorrect)',
            'authentication failed' => 'Échec de l\'authentification',
            'insufficient credit' => 'Crédit insuffisant',
            'invalid phone number' => 'Numéro de téléphone invalide',
            'message too long' => 'Message trop long',
            'unauthorized sender' => 'Nom d\'expéditeur non autorisé (doit être validé par LAfricaMobile)',
            'invalid sender' => 'Expéditeur invalide - ne doit PAS commencer par un chiffre',
            'missing parameter' => 'Paramètre manquant dans la requête',
        ];

        $lowerResponse = strtolower($response);

        foreach ($errorMessages as $key => $message) {
            if (stripos($lowerResponse, $key) !== false) {
                return $message;
            }
        }

        return $response ?: 'Erreur inconnue lors de l\'envoi du SMS';
    }

    /**
     * Parser la réponse du solde
     */
    private function parseBalanceResponse($response)
    {
        $balance = [
            'sms' => 0,
            'vocal' => 0,
            'ussd' => 0
        ];

        // Essayer de parser le XML
        if (stripos($response, '<?xml') !== false || stripos($response, '<') !== false) {
            try {
                $xml = simplexml_load_string($response);
                if ($xml !== false) {
                    $balance['sms'] = (int)($xml->sms ?? $xml->SMS ?? 0);
                    $balance['vocal'] = (int)($xml->vocal ?? $xml->VOCAL ?? 0);
                    $balance['ussd'] = (int)($xml->ussd ?? $xml->USSD ?? 0);
                }
            } catch (\Exception $e) {
                Log::warning('Échec du parsing XML du solde');
            }
        }

        // Utiliser des regex si le XML n'a pas fonctionné
        if ($balance['sms'] == 0) {
            if (preg_match('/SMS[:\s]+(\d+)/i', $response, $m)) {
                $balance['sms'] = (int)$m[1];
            }
            if (preg_match('/VOCAL[:\s]+(\d+)/i', $response, $m)) {
                $balance['vocal'] = (int)$m[1];
            }
            if (preg_match('/USSD[:\s]+(\d+)/i', $response, $m)) {
                $balance['ussd'] = (int)$m[1];
            }
        }

        return $balance;
    }
}
