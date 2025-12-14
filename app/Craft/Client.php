<?php

namespace App\Craft;

use App\Traits\Errors;

class Client
{
    use Errors;

    /**
     * @var string
     */
    protected string $token = '';

    /**
     * @var string
     */
    protected string $end_point = 'https://checkoffpro.com/api';

    /**
     * @param string $token
     * @return $this
     */
    public function setToken(string $token): Client
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $end_point
     * @return $this
     */
    public function setEndPoint(string $end_point): Client
    {
        $this->end_point = $end_point;
        return $this;
    }

    /**
     * @return string
     */
    public function getEndPoint(): string
    {
        return $this->end_point;
    }

    /**
     * @return array
     */
    public function getCornRemittances(int $limit, int $page): array
    {
        return $this->get('remittances/corn');
    }

    /**
     * @return array
     */
    public function getSoybeanRemittances(int $limit, int $page): array
    {
        return $this->getAll('remittances/soybeans');
    }

    /**
     * @return array
     */
    public function getSubmissions(int $limit, int $page): array
    {
        return $this->getAll('submissions');
    }

    /**
     * @return string[]
     */
    protected function headers(): array
    {
        return [
            "client_name: Checkoff Pro",
            "X-API-Key: " . $this->getToken(),
            'Content-Type: application/json',
        ];
    }

    /**
     * @param string $path
     * @return array
     */
    public function get(string $path, array $params = []): array
    {
        $url = $this->getEndPoint() . '/' . $path . '.json' . '?' . http_build_query($params);
        $headers = $this->headers();
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($curl);
        curl_close($curl);
        return json_decode($resp, true);
    }

    /**
     * @param string $path
     * @param array $payload
     * @return array
     */
    public function post(string $path, array $payload): array
    {
        $headers = $this->headers();
        $curl = curl_init($this->getEndPoint());
        curl_setopt($curl, CURLOPT_URL, $this->getEndPoint() . '/' . $path . '.json');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        $resp = curl_exec($curl);
        curl_close($curl);
        return json_decode($resp, true);
    }

    /**
     * @param string $path
     * @return array
     */
    public function getAll(string $path): array
    {
        $data = $this->get($path);
        if (!$this->hasErrors($data)) {
            //paginate!
        }

        return $data;
    }
}
