<?php
namespace App\Import\Drivers;

use App\Import\AbstractDriver;
use App\Models\Submission;

class Submissions extends AbstractDriver
{
    public function import(): AbstractDriver
    {
        $payload = [
            'page' => app('settings')->get('import.submissions.active_page', $this->active_page)
        ];

        $this->data = $this->getClient()->get('submissions', $payload);
        app('settings')->set('import.submissions.active_page', $this->nextPage())->save();
        return $this;
    }

    public function save(): bool
    {
        foreach($this->data['data'] AS $submission)
        {
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

        return true;
    }
}
