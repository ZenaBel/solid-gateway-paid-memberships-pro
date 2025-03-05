<?php namespace SolidGate\API;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use SolidGate\API\DTO\FormInitDTO;
use SolidGate\API\DTO\FormUpdateDTO;
use SolidGate\API\DTO\FormResignDTO;
use Throwable;

class Api
{
    const BASE_SOLID_GATE_API_URI = 'https://pay.solidgate.com/api/v1/';
    const BASE_RECONCILIATION_API_URI = 'https://reports.solidgate.com/';

    const BASE_SOLID_SUBSCRIBE_GATE_API_URI = 'https://subscriptions.solidgate.com/api/v1/';

    const BASE_GATE_API_URI = 'https://gate.solidgate.com/api/v1/';
    const RECONCILIATION_ORDERS_PATH = 'api/v2/reconciliation/orders';
    const RECONCILIATION_CHARGEBACKS_PATH = 'api/v2/reconciliation/chargebacks';
    const RECONCILIATION_ALERTS_PATH = 'api/v2/reconciliation/chargeback-alerts';
    const RECONCILIATION_MAX_ATTEMPTS = 3;

    protected $solidGateApiClient;
    protected $reconciliationsApiClient;
    protected $solidSubscribeGateApiClient;
    protected $gateApiClient;

    protected $publicKey;
    protected $secretKey;
    protected $exception;

    public function __construct(
        string $publicKey,
        string $secretKey,
        string $baseSolidGateApiUri = self::BASE_SOLID_GATE_API_URI,
        string $baseReconciliationsApiUri = self::BASE_RECONCILIATION_API_URI,
        string $baseSolidSubscribeGateApiUri = self::BASE_SOLID_SUBSCRIBE_GATE_API_URI,
        string $baseGateApiUri = self::BASE_GATE_API_URI
    ) {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;

        $this->solidGateApiClient = new HttpClient(
            [
                'base_uri' => $baseSolidGateApiUri,
                'verify'   => true,
            ]
        );

        $this->reconciliationsApiClient = new HttpClient(
            [
                'base_uri' => $baseReconciliationsApiUri,
                'verify'   => true,
            ]
        );

        $this->solidSubscribeGateApiClient = new HttpClient(
            [
                'base_uri' => $baseSolidSubscribeGateApiUri,
                'verify'   => true,
            ]
        );
        $this->gateApiClient = new HttpClient(
            [
                'base_uri' => $baseGateApiUri,
                'verify'   => true,
            ]
        );
    }

    public function addProduct(array $attributes): string
    {
        return $this->sendRequestPOST('products', $attributes);
    }

    public function getProducts(array $attributes = []): string
    {
        $path = 'products?' . http_build_query($attributes);

        return $this->sendRequestGET($path, []);
    }

    public function updateProduct(string $productId, array $attributes): string
    {
        return $this->sendRequestPATCH('products/' . $productId, $attributes);
    }

    public function addPrice(string $productId, array $attributes): string
    {
        return $this->sendRequestPOST('products/' . $productId . '/prices', $attributes);
    }

    public function getPrices(string $productId, array $attributes = []): string
    {
        $path = 'products/' . $productId . '/prices?' . http_build_query($attributes);

        return $this->sendRequestGET($path, []);
    }

    public function updatePrice(string $productId, string $priceId, array $attributes): string
    {
        return $this->sendRequestPATCH('products/' . $productId . '/prices/' . $priceId, $attributes);
    }

    public function cancelSubscription(array $attributes): string
    {
        return $this->sendRequestPOST('subscription/cancel', $attributes);
    }

    public function getSubscriptionStatus(array $attributes): string
    {
        return $this->sendRequestPOST('subscription/status', $attributes);
    }

    public function pauseSchedule(string $subscription_id, array $attributes): string
    {
        return $this->sendRequestPOST("subscriptions/$subscription_id/pause-schedule", $attributes);
    }

    public function updatePauseSchedule(string $subscription_id, array $attributes): string
    {
        return $this->sendRequestPATCH("subscriptions/$subscription_id/pause-schedule", $attributes);
    }

    public function removePauseSchedule(string $subscription_id): string
    {
        return $this->sendRequestDELETE("subscriptions/$subscription_id/pause-schedule");
    }

    public function getProduct($product_uuid): string
    {
        return $this->sendRequestGET('products/' . $product_uuid, []);
    }

    public function switchProductSubscription(array $data): string
    {
        return $this->sendRequestPOST('subscription/switch-subscription-product', $data);
    }

    public function createPrice($product_uuid, array $data): string
    {
        return $this->sendRequestPOST("products/$product_uuid/prices", $data);
    }

    public function getProductPrices($uuid): string
    {
        return $this->sendRequestGET("products/$uuid/prices", []);
    }

    public function calculatePrice(array $data): string
    {
        return $this->sendRequestPOST('products/calculatePrice', $data);
    }

    public function reactivateSubscription(array $attributes): string
    {
        return $this->sendRequestPOST("subscription/restore", $attributes);
    }

    public function updateToken(array $data): string
    {
        return $this->sendRequestPOST('subscription/update-token', $data);
    }

    public function charge(array $attributes): string
    {
        return $this->sendRequest('charge', $attributes);
    }

    public function recurring(array $attributes): string
    {
        return $this->sendRequest('recurring', $attributes);
    }

    public function status(array $attributes): string
    {
        return $this->sendRequest('status', $attributes);
    }

    public function refund(array $attributes): string
    {
        return $this->sendRequest('refund', $attributes);
    }

    public function resign(array $attributes): string
    {
        return $this->sendRequest('resign', $attributes);
    }

    public function auth(array $attributes): string
    {
        return $this->sendRequest('auth', $attributes);
    }

    public function void(array $attributes): string
    {
        return $this->sendRequest('void', $attributes);
    }

    public function settle(array $attributes): string
    {
        return $this->sendRequest('settle', $attributes);
    }

    public function arnCode(array $attributes): string
    {
        return $this->sendRequest('arn-code', $attributes);
    }

    public function applePay(array $attributes): string
    {
        return $this->sendRequest('apple-pay', $attributes);
    }

    public function googlePay(array $attributes): string
    {
        return $this->sendRequest('google-pay', $attributes);
    }

    public function formMerchantData(array $attributes): FormInitDTO
    {
        $encryptedFormData = $this->generateEncryptedFormData($attributes);
        $signature = $this->generateSignature($encryptedFormData);

        return new FormInitDTO($encryptedFormData, $this->getPublicKey(), $signature);
    }

    public function formUpdate(array $attributes): FormUpdateDTO
    {
        $encryptedFormData = $this->generateEncryptedFormData($attributes);
        $signature = $this->generateSignature($encryptedFormData);

        return new FormUpdateDTO($encryptedFormData, $signature);
    }

    public function formResign(array $attributes): FormResignDTO
    {
        $encryptedFormData = $this->generateEncryptedFormData($attributes);
        $signature = $this->generateSignature($encryptedFormData);

        return new FormResignDTO($encryptedFormData, $this->getPublicKey(), $signature);
    }

    public function getUpdatedOrders(
        \DateTime $dateFrom,
        \DateTime $dateTo,
        int $maxAttempts = self::RECONCILIATION_MAX_ATTEMPTS
    ): \Generator {
        return $this->sendReconciliationsRequest($dateFrom, $dateTo, self::RECONCILIATION_ORDERS_PATH, $maxAttempts);
    }

    public function getUpdatedChargebacks(
        \DateTime $dateFrom,
        \DateTime $dateTo,
        int $maxAttempts = self::RECONCILIATION_MAX_ATTEMPTS
    ): \Generator {
        return $this->sendReconciliationsRequest($dateFrom, $dateTo, self::RECONCILIATION_CHARGEBACKS_PATH,
            $maxAttempts);
    }

    public function getUpdatedAlerts(
        \DateTime $dateFrom,
        \DateTime $dateTo,
        int $maxAttempts = self::RECONCILIATION_MAX_ATTEMPTS
    ): \Generator {
        return $this->sendReconciliationsRequest($dateFrom, $dateTo, self::RECONCILIATION_ALERTS_PATH, $maxAttempts);
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    public function generateSignature(string $data): string
    {
        return base64_encode(
            hash_hmac('sha512',
                $this->getPublicKey() . $data . $this->getPublicKey(),
                $this->getSecretKey())
        );
    }

    public function sendRequest(string $method, array $attributes): string
    {
        $request = $this->makeRequestPOST($method, $attributes);

        try {
            $response = $this->solidGateApiClient->send($request);

            return $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->exception = $e;
        }

        return '';
    }

    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    protected function base64UrlEncode(string $data): string
    {
        return strtr(base64_encode($data), '+/', '-_');
    }

    public function sendReconciliationsRequest(
        \DateTime $dateFrom,
        \DateTime $dateTo,
        string $url,
        int $maxAttempts
    ): \Generator {
        $nextPageIterator = null;
        do {
            $attributes = [
                'date_from' => $dateFrom->format('Y-m-d H:i:s'),
                'date_to'   => $dateTo->format('Y-m-d H:i:s'),
            ];

            if ($nextPageIterator) {
                $attributes['next_page_iterator'] = $nextPageIterator;
            }

            $request = $this->makeRequestPOST($url, $attributes);
            try {
                $responseArray = $this->sendReconciliationsRequestInternal($request, $maxAttempts);
                $nextPageIterator = ($responseArray['metadata'] ?? [])['next_page_iterator'] ?? null;

                foreach ($responseArray['orders'] as $order) {
                    yield $order;
                }
            } catch (Throwable $e) {
                $this->exception = $e;

                return;
            }
        } while ($nextPageIterator != null);
    }

    private function sendReconciliationsRequestInternal(Request $request, int $maxAttempts): array
    {
        $attempt = 0;
        $lastException = null;
        while ($attempt < $maxAttempts) {
            $attempt += 1;
            try {
                $response = $this->reconciliationsApiClient->send($request);
                $responseArray = json_decode($response->getBody()->getContents(), true);
                if (is_array($responseArray) && isset($responseArray['orders']) && is_array($responseArray['orders'])) {
                    return $responseArray;
                }
                $lastException = new \RuntimeException("Incorrect response structure. Need retry request");
            } catch (Throwable $e) {
                $lastException = $e;
            }
        }

        throw new $lastException;
    }

    protected function generateEncryptedFormData(array $attributes): string
    {
        $attributes = json_encode($attributes);
        $secretKey = substr($this->getSecretKey(), 0, 32);

        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLen);

        $encrypt = openssl_encrypt($attributes, 'aes-256-cbc', $secretKey, OPENSSL_RAW_DATA, $iv);

        return $this->base64UrlEncode($iv . $encrypt);
    }

    protected function makeRequestPOST(string $path, array $attributes): Request
    {
        $body = json_encode($attributes);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'Merchant'     => $this->getPublicKey(),
            'Signature'    => $this->generateSignature($body),
        ];

        return new Request('POST', $path, $headers, $body);
    }

    protected function makeRequestGET(string $path, array $attributes): Request
    {
        $body = json_encode($attributes);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'Merchant'     => $this->getPublicKey(),
            'Signature'    => $this->generateSignature($body),
        ];

        return new Request('GET', $path, $headers, $body);
    }

    protected function makeRequestPATCH(string $path, array $attributes): Request
    {
        $body = json_encode($attributes);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'Merchant'     => $this->getPublicKey(),
            'Signature'    => $this->generateSignature($body),
        ];

        return new Request('PATCH', $path, $headers, $body);
    }

    protected function makeRequestDELETE(string $path, array $attributes): Request
    {
        $body = json_encode($attributes);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'Merchant'     => $this->getPublicKey(),
            'Signature'    => $this->generateSignature($body),
        ];

        return new Request('DELETE', $path, $headers, $body);
    }

    protected function makeRequestGATE(string $path, array $attributes): Request
    {
        $body = json_encode($attributes);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'Merchant'     => $this->getPublicKey(),
            'Signature'    => $this->generateSignature($body),
        ];

        return new Request('POST', $path, $headers, $body);
    }

    protected function sendRequestGATE(string $string, array $attributes = []): string
    {
        $request = $this->makeRequestGATE($string, $attributes);

        try {
            $response = $this->solidGateApiClient->send($request);

            return $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->exception = $e;
        }

        return '';
    }

    public function checkOrderStatus(array $data): string
    {
        return $this->sendRequestGATE('status', $data);
    }

    public function sendRequestPOST(string $method, array $attributes): string
    {
        $request = $this->makeRequestPOST($method, $attributes);

        try {
            $response = $this->solidSubscribeGateApiClient->send($request);

            return $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->exception = $e;
        }

        return '';
    }

    public function sendRequestGET(string $method, array $attributes): string
    {
        $request = $this->makeRequestGET($method, $attributes);

        try {
            $response = $this->solidSubscribeGateApiClient->send($request);

            return $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->exception = $e;
        }

        return '';
    }

    public function sendRequestPATCH(string $string, array $attributes): string
    {
        $request = $this->makeRequestPATCH($string, $attributes);

        try {
            $response = $this->solidSubscribeGateApiClient->send($request);

            return $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->exception = $e;
        }

        return '';
    }

    public function sendRequestDELETE(string $string, array $attributes = []): string
    {
        $request = $this->makeRequestDELETE($string, $attributes);

        try {
            $response = $this->solidSubscribeGateApiClient->send($request);

            return $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->exception = $e;
        }

        return '';
    }
}
