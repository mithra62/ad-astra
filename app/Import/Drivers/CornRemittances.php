<?php
namespace App\Import\Drivers;

use App\Import\AbstractDriver;

class CornRemittances extends AbstractDriver
{
    public function import(): AbstractDriver
    {
        $payload = [
            'page' => app('settings')->get('import.corn-remittances.active_page', $this->active_page)
        ];

        $this->data = $this->getClient()->get('remittances/corn', $payload);
        app('settings')->set('import.corn-remittances.active_page', $this->nextPage())->save();

        return $this;
    }

    public function save(): bool
    {
        foreach($this->data['data'] AS $remittance_data)
        {
            $remittance_data['type'] = 'corn';
            $remittance = $this->saveRemittance($remittance_data);
            $meta = [
                'bushels_purchased_as_second_purchaser' => $remittance_data['bushelsPurchasedAsASecondPurchaser'],
                'total_bushels_subject_to_checkoff' => $remittance_data['totalBushelsSubjectToCheckoff'],
                'bushels_sweet_corn_popcorn_seed_corn' => $remittance_data['bushelsOfSweetCornPopcornAndSeedCorn'],
            ];

            $remittance->saveMeta($meta);
        }

        return true;
    }
}
