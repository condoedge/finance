@component('mail::message')
<p>You just received a new invoice to pay<p>

<x-invoice :invoice="$invoice" />

@endcomponent

