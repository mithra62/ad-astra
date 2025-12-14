<?php
namespace App;

use App\Craft\Client;
use App\Traits\Errors;
use App\Import\Drivers\SoybeanRemittances;
use App\Import\Drivers\Submissions;
use App\Import\Drivers\CornRemittances;

class Import
{
    use Errors;

    /**
     * @var Client
     */
    protected Client $client;

    /**
     * @var array
     */
    protected array $errors = [];

    /**
     * @var array|string[]
     */
    protected array $models = [
        CornRemittances::class,
        SoybeanRemittances::class,
        Submissions::class, //Submissions must be last due to foreign keys
    ];

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $client->setToken(env('CRAFT_API_TOKEN'));
        $this->client = $client;
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @return void
     */
    public function run()
    {
        foreach ($this->models as $model) {
            $driver = new $model($this->client);
            if($driver instanceof Import\AbstractDriver) {
                $driver->import()->save();
            }
        }
    }
}
