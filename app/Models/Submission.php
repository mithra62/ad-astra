<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Submission extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'id',
        'title',
        'type',
        'start_date',
        'end_date',
        'total_remittance',
        'submitted',
        'paid_by_check',
        'payment_date',
        'transaction_id',
        'invoice_number',
        'customer_code',
        'approval_code',
        'pdf_url',
        'us_state_id',
        'checkoff_organization_id',
        'status',
        'synced_at',
        'organization_id',
        'commodity_id',
    ];

    /**
     * @var string
     */
    protected $table = 'submissions';

    public static function prepareApiData(array $submission): array
    {
        $submission['total_remittance'] = $submission['totalRemittance'];
        $submission['paid_by_check'] = $submission['paidByCheck'];
        $submission['payment_date'] = $submission['paymentDate'];
        $submission['transaction_id'] = $submission['transactionId'];
        $submission['invoice_number'] = $submission['invoiceNumber'];
        $submission['customer_code'] = $submission['customerCode'];
        $submission['approval_code'] = $submission['approvalCode'];
        $submission['pdf_url'] = $submission['pdfUrl'];
        $submission['end_date'] = $submission['endDate'];
        $submission['start_date'] = $submission['startDate'];
        $submission['synced_at'] = now();

        $submission['status'] = $submission['status']['id'];
        $submission['checkoff_organization_id'] = $submission['checkoffOrganization']['id'] ?? null;
        $submission['commodity_id'] = $submission['commodity']['id'];
        $submission['created_at'] = $submission['dateCreated'];
        $submission['updated_at'] = $submission['dateUpdated'];
        //$remittance['organization'] = $remittance['organization']['id'] ?? null;

        return $submission;
    }

    /**
     * @return BelongsToMany
     */
    public function us_state(): BelongsToMany
    {
        return $this->belongsToMany(UsState::class)->withPivot('submission_id', 'us_state_id');
    }

    /**
     * @return BelongsToMany
     */
    public function organization(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)->withPivot('submission_id', 'organization_id');
    }

    /**
     * @return BelongsTo
     */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    /**
     * @return BelongsToMany
     */
    public function remittances(): BelongsToMany
    {
        return $this->belongsToMany(Remittance::class)->withPivot('remittance_id', 'submission_id');
    }
}
