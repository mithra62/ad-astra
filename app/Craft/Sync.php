<?php

namespace App\Craft;

use App\Models\Commodity;
use App\Models\Organization;
use App\Models\Remittance;
use App\Models\Submission;
use App\Models\UsState;
use App\Traits\Errors;

class Sync
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
    public function sync(): void
    {
        $data = $this->getClient()->getSoybeanRemittances();
        if (!$this->hasErrors($data)) {
            $this->saveSoybeanRemittances($data);
        }

        $data = $this->getClient()->getCornRemittances();
        if (!$this->hasErrors($data)) {
            $this->saveCornRemittances($data);
        }

        $data = $this->getClient()->getSubmissions();
        if (!$this->hasErrors($data)) {
            $this->saveSubmissions($data);
        }
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
     * @param array $remittances
     * @return void
     */
    protected function saveSoybeanRemittances(array $remittances): void
    {
        $found = [];
        foreach($remittances['data'] AS $remittance_data)
        {
            $remittance_data['type'] = 'soybean';
            $found[] = $remittance_data['id'];
            $remittance = $this->saveRemittance($remittance_data);
            $meta = [
                'num_bushels_purchased' => $remittance_data['numberOfBushelsPurchased'],
                'num_bushels_assessed' => $remittance_data['numberOfBushelsAssessed'],
                'net_market_value_assessed_bushels' => $remittance_data['netMarketValueOfAssessedBushels'],
            ];

            $remittance->saveMeta($meta);
        }

        if($found) {
//            $check = Remittance::whereNotIn('id', $found)->get();
//            if($check->count() >= 1) {
//                foreach($check AS $remittance) {
//                    $remittance->delete();
//                }
//            }
        }
    }

    /**
     * @param array $remittances
     * @return void
     */
    protected function saveCornRemittances(array $remittances): void
    {
        $found = [];
        foreach($remittances['data'] AS $remittance_data)
        {
            $remittance_data['type'] = 'corn';
            $found[] = $remittance_data['id'];
            $remittance = $this->saveRemittance($remittance_data);
            $meta = [
                'bushels_purchased_as_second_purchaser' => $remittance_data['bushelsPurchasedAsASecondPurchaser'],
                'total_bushels_subject_to_checkoff' => $remittance_data['totalBushelsSubjectToCheckoff'],
                'bushels_sweet_corn_popcorn_seed_corn' => $remittance_data['bushelsOfSweetCornPopcornAndSeedCorn'],
            ];

            $remittance->saveMeta($meta);
        }

        if($found) {
//            $check = Remittance::whereNotIn('id', $found)->get();
//            if($check->count() >= 1) {
//                foreach($check AS $remittance) {
//                    $remittance->delete();
//                }
//            }
        }
    }

    /**
     * @param array $submissions
     * @return void
     */
    protected function saveSubmissions(array $submissions): void
    {
        $found = [];
        foreach($submissions['data'] AS $submission)
        {
            $found[] = $submission['id'];
            $this->saveCommodity($submission['commodity']);
            $states = $organizations = false;
            if(isset($submission['states']) && is_array($submission['states']) && $submission['states']) {
                $states = true;
                foreach($submission['states'] AS $state) {
                    $this->saveState($state);
                }
            }

            if(isset($submission['organizations']) && is_array($submission['organizations']) && $submission['organizations']) {
                $organizations = true;
                foreach($submission['organizations'] AS $state) {
                    $this->saveOrganization($state);
                }
            }

            $data = Submission::prepareApiData($submission);
            $check = Submission::where('id', $submission['id'])->first();
            if(!$check instanceof Submission) {
                $check = Submission::create($data);
            } else {
                $check->update($data);
                $check->us_state()->detach();
                $check->organization()->detach();
                $check->remittances()->detach();
            }

            if($states) {
                foreach($submission['states'] AS $state) {
                    $check->us_state()->attach($state['id']);
                }
            }

            if($organizations) {
                foreach($submission['organizations'] AS $org) {
                    $check->organization()->attach($org['id']);
                }
            }

            $remittances = $submission['commodityRemittances'] ?? [];
            if($remittances) {
                foreach($remittances AS $remittance) {
                    $check->remittances()->attach($remittance['id']);
                }
            }
        }

//        if($found) {
//            $check = Submission::whereNotIn('id', $found)->get();
//            if($check->count() >= 1) {
//                foreach($check AS $submission) {
//                    $submission->delete();
//                }
//            }
//        }
    }

    /**
     * @param array $commodity
     * @return $this
     */
    protected function saveCommodity(array $commodity): Sync
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
    protected function saveOrganization(? array $organization): Sync
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
    protected function saveState(array $state): Sync
    {
        $check = UsState::where('id', $state['id'])->first();
        if(!$check) {
            UsState::create($state);
        }

        return $this;
    }
}
