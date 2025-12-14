<?php
namespace App\Import;

use App\Craft\Client;
use App\Models\Commodity;
use App\Models\Organization;
use App\Models\Remittance;
use App\Models\UsState;

abstract class AbstractDriver
{
    /**
     * @var array
     */
    protected array $data = [];

    /**
     * @var int
     */
    protected int $active_page = 1;

    /**
     * @var int
     */
    protected int $limit = 100;

    /**
     * @var Client
     */
    protected Client $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @return AbstractDriver
     */
    abstract public function import(): AbstractDriver;

    /**
     * @return bool
     */
    abstract public function save(): bool;

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @param array $commodity
     * @return $this
     */
    protected function saveCommodity(array $commodity): AbstractDriver
    {
        $check = Commodity::where('id', $commodity['id'])->first();
        if(!$check) {
            Commodity::create($commodity);
        }

        return $this;
    }

    /**
     * @param array|null $organization
     * @return $this
     */
    protected function saveOrganization(? array $organization): AbstractDriver
    {
        if(is_array($organization)) {
            $check = Organization::where('id', $organization['id'])->first();
            if(!$check) {
                Organization::create($organization);
            }
        }

        return $this;
    }

    /**
     * @param array $state
     * @return $this
     */
    protected function saveState(array $state): AbstractDriver
    {
        $check = UsState::where('id', $state['id'])->first();
        if(!$check) {
            UsState::create($state);
        }

        return $this;
    }

    /**
     * @param array $remittance
     * @return Remittance
     */
    protected function saveRemittance(array $remittance): Remittance
    {
        $this->saveState($remittance['state'])
            ->saveCommodity($remittance['commodity'])
            ->saveOrganization($remittance['organization']);

        $check = Remittance::where('id', $remittance['id'])->first();
        if(!$check instanceof Remittance) {
            $check = Remittance::create(
                Remittance::prepareApiData($remittance)
            );
        } else {
            $check->update(
                Remittance::prepareApiData($remittance)
            );
        }

        return $check;
    }

    /**
     * @return int
     */
    protected function nextPage(): int
    {
        $next_page = 1;
        $current_page = $this->data['meta']['pagination']['current_page'];
        $total_pages = $this->data['meta']['pagination']['total_pages'] ?? 1;
        if($current_page < $total_pages) {
            $next_page = $current_page + 1;
        }

        return $next_page;
    }
}
