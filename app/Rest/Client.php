<?php

namespace App\Rest;

use App\Traits\Errors;

class Client
{
    use Errors;

    /**
     * @var string
     */
    protected string $token = '1|S88c33R0D4eisqQ5ffhTkPBdxg8iYAbWcNcS4Pc6207bc902';

    /**
     * @var string
     */
    protected string $end_point = 'http://eric.laravel-dev.com/api';

    /**
     * @return array
     */
    public function getCornRemittances(): array
    {
        return $this->getAll('remittances/corn');
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

    /**
     * @param string $path
     * @return array
     */
    public function get(string $path, array $params = [])
    {
        $headers = $this->headers();
        $curl = curl_init($this->getEndPoint());
        curl_setopt($curl, CURLOPT_URL, $this->getEndPoint() . '/' . $path);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($curl);
        curl_close($curl);
        if (!$resp) {
            $resp = [];
        }

        return json_decode($resp, true);
    }

    /**
     * @return string[]
     */
    protected function headers(): array
    {
        return [
            "client_name: Checkoff Pro",
            "Authorization: Bearer " . $this->getToken(),
            'Content-Type: application/json',
        ];
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

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
    public function getEndPoint(): string
    {
        return $this->end_point;
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
     * @return array
     */
    public function getSoybeanRemittances(): array
    {
        return $this->getAll('remittances/soybeans');
    }

    /**
     * @return array
     */
    public function getSubmissions(): array
    {
        return $this->getAll('submissions');
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
}
