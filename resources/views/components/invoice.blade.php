<div>
@foreach(\Condoedge\Finance\Models\InvoiceDetail::forInvoice($invoice->id)->get() as $detail)
<x-invoice-detail :detail="$detail" />
@endforeach
</div>