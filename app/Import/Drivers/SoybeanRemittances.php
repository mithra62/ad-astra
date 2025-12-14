<?php
namespace App\Import\Drivers;

use App\Import\AbstractDriver;

class SoybeanRemittances extends AbstractDriver
{
    /**
     * @return AbstractDriver
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function import(): AbstractDriver
    {
        $payload = [
            'page' => app('settings')->get('import.soybean-remittances.active_page', $this->active_page)
        ];

        $this->data = $this->getClient()->get('remittances/soybeans', $payload);
        app('settings')->set('import.soybean-remittances.active_page', $this->nextPage())->save();
        return $this;
    }

    /**
     * @return bool
     */
    public function save(): bool
    {
        foreach($this->data['data'] AS $remittance_data)
        {
            $remittance_data['type'] = 'soybean';
            $remittance = $this->saveRemittance($remittance_data);
            $meta = [
                'num_bushels_purchased' => $remittance_data['numberOfBushelsPurchased'],
                'num_bushels_assessed' => $remittance_data['numberOfBushelsAssessed'],
                'net_market_value_assessed_bushels' => $remittance_data['netMarketValueOfAssessedBushels'],
            ];

            $remittance->saveMeta($meta);
        }

        return true;
    }
}
