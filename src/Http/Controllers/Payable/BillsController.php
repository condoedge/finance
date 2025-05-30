<?php

namespace Condoedge\Finance\Http\Controllers\Payable;

use Condoedge\Finance\Facades\BillDetailModel;
use Condoedge\Finance\Facades\BillModel;
use Condoedge\Finance\Models\Dto\Bills\CreateBillDto;
use Condoedge\Finance\Models\Dto\Bills\CreateOrUpdateBillDetail;
use Condoedge\Finance\Models\Dto\Bills\UpdateBillDto;
use Illuminate\Routing\Controller;

class BillsController extends Controller
{
    /**
     * @operationId Create bill
     */
    public function createBill(CreateBillDto $data)
    {
        BillModel::createBillFromDto($data);

        return response()->json([
            'message' => __('translate.bill-created'),
        ]);
    }

    /**
     * @operationId Update bill
     */
    public function updateBill(UpdateBillDto $data)
    {
        BillModel::updateBillFromDto($data);

        return response()->json([
            'message' => __('translate.bill-updated'),
        ]);
    }

    /**
     * @operationId Create or update bill detail
     */
    public function saveBillDetail(CreateOrUpdateBillDetail $data)
    {
        if ($data->id) {
            BillDetailModel::editBillDetail($data);
        } else {
            BillDetailModel::createBillDetail($data);
        }

        return response()->json([
            'message' => __('translate.bill-detail-created'),
        ]);
    }
}
