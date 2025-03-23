<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\PaymentRequest;
use App\Models\Client;
use App\Models\Integration;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Invoice\GenerateInvoiceStatus;
use App\Services\Invoice\InvoiceCalculator;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class PaymentsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Payment $payment
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy(Payment $payment)
    {
        if (!auth()->user()->can('payment-delete')) {
            session()->flash('flash_message', __("You don't have permission to delete a payment"));
            return redirect()->back();
        }
        $api = Integration::initBillingIntegration();
        if ($api) {
            $api->deletePayment($payment);
        }

        $payment->delete();
        session()->flash('flash_message', __('Payment successfully deleted'));
        return redirect()->back();
    }

    public function addPayment(PaymentRequest $request, Invoice $invoice)
    {
        if (!$invoice->isSent()) {
            session()->flash('flash_message_warning', __("Can't add payment on Invoice"));
            return redirect()->route('invoices.show', $invoice->external_id);
        }

        $invoiceCalculator = new InvoiceCalculator($invoice);
        $newAmountDue = $invoiceCalculator->getAmountDue()->getAmount() - ($request->amount * 100);

        if ($newAmountDue < 0) {
            session()->flash('flash_message_warning', "Amount paid superior to the amount due");
            return redirect()->route('invoices.show', ['invoice' => $invoice->external_id]);
        }

        $payment = Payment::create([
            'external_id' => Uuid::uuid4()->toString(),
            'amount' => $request->amount * 100,
            'payment_date' => Carbon::parse($request->payment_date),
            'payment_source' => $request->source,
            'description' => $request->description,
            'invoice_id' => $invoice->id
        ]);
        $api = Integration::initBillingIntegration();
        if ($api && $invoice->integration_invoice_id) {
            $result = $api->createPayment($payment);
            $payment->integration_payment_id = $result["Guid"];
            $payment->integration_type = get_class($api);
            $payment->save();
        }
        app(GenerateInvoiceStatus::class, ['invoice' => $invoice])->createStatus();

        session()->flash('flash_message', __('Payment successfully added'));
        return redirect()->back();
    }

    public function getTotalPaymentsByClient() {
        $clients = Client::select('clients.id', 'clients.company_name')
            ->selectRaw('SUM(payments.amount) as total_paid')
            ->join('invoices', 'clients.id', '=', 'invoices.client_id')
            ->join('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->groupBy('clients.id', 'clients.company_name')
            ->get();

        $clients = $clients->map(function($client) {
            $client->total_paid = (string) $client->total_paid;
            return $client;
        });

        return response()->json($clients);
    }


//// si on veut client avec 0 paiement
//    public function getTotalPaymentsByClient() {
//        $clients = Client::select('clients.id', 'clients.company_name')
//            ->selectRaw('COALESCE(SUM(payments.amount), 0) as total_paid') // Si NULL, mettre 0
//            ->leftJoin('invoices', 'clients.id', '=', 'invoices.client_id')
//            ->leftJoin('payments', 'invoices.id', '=', 'payments.invoice_id')
//            ->groupBy('clients.id', 'clients.company_name')
//            ->get();
//
//        $clients = $clients->map(function($client) {
//            $client->total_paid = (string) $client->total_paid;
//            return $client;
//        });
//
//        return response()->json($clients);
//    }


    public function getPaymentsByClient($clientId) {
        $client = Client::where('id', $clientId)->first();

        if (!$client) {
            return response()->json(['error' => 'Client not found'], 404);
        }

        $payments = Payment::join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->join('invoice_lines', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.client_id', $clientId)
            ->groupBy('payments.id')
            ->get();

        return response()->json([
            'client' => [
                'id' => $client->id,
                'company_name' => $client->company_name
            ],
            'payments' => $payments
        ]);
    }



    public function getTotalPaymentsJson() {
        $sumPaid = Payment::sum('amount');

        $sumPrice = InvoiceLine::whereNotNull('offer_id')->sum('price');

        return response()->json([
            'sumPaid' => $sumPaid,
            'sumDue' => $sumPrice - $sumPaid
        ]);
    }


    public function getAllPaymentsByInvocieJson() {
        $payments = Payment::select('invoice_id')
            ->selectRaw('SUM(amount) as amount')
            ->groupBy('invoice_id')
            ->get();

        $paymentsData = $payments->map(function ($payment) {
            $invoice = $payment->invoice;

            if ($invoice) {
                $invoiceCalculator = new InvoiceCalculator($invoice);
                $newAmountDue = $invoiceCalculator->getAmountDue()->getAmount();
            } else {
                $newAmountDue = null; // Si pas de facture associée
            }

            return [
                'amount' => $payment->amount,
                'invoice_date' => $invoice->created_at,
                'status' => $invoice->status,
                'invoice_id' => $payment->invoice_id,
                'newAmountDue' => $newAmountDue,
            ];
        });

        return response()->json($paymentsData);
    }

    public function getByInvoiceJson($invoiceId)
    {
        $payments = Payment::where('invoice_id', $invoiceId)->get();

        return response()->json($payments);
    }

    public function getByIdJson($id)
    {
        $payments = Payment::where('id', $id)->get();

        return response()->json($payments);
    }

    public function updatePayment(Request $request, $id)
    {
        $payment = Payment::where('id', $id)->firstOrFail();

        if (!$payment->invoice->isSent()) {
            return response()->json([
                'message' => "Can't update payment on Invoice"
            ], 400);
        }

        $payment->update([
            'amount' => $request->amount,
            'description' => $request->description,
            'payment_source' => $request->payment_source,
            'payment_date' => Carbon::parse($request->payment_date),
            'integration_payment_id' => $request->integration_payment_id,
            'integration_type' => $request->integration_type,
        ]);

        // Mise à jour de l’intégration si nécessaire
        $api = Integration::initBillingIntegration();
        if ($api && $payment->invoice->integration_invoice_id) {
            $result = $api->updatePayment($payment);
            $payment->integration_payment_id = $result["Guid"];
            $payment->integration_type = get_class($api);
            $payment->save();
        }

        app(GenerateInvoiceStatus::class, ['invoice' => $payment->invoice])->createStatus();

        return response()->json([
            'message' => 'Payment successfully updated',
            'payment' => $payment
        ], 200);
    }

    public function deletePayment($id)
    {
        $payment = Payment::where('id', $id)->firstOrFail();
        $payment->delete();

        $api = Integration::initBillingIntegration();
        if ($api && $payment->integration_payment_id) {
            $api->deletePayment($payment->integration_payment_id);
        }

        app(GenerateInvoiceStatus::class, ['invoice' => $payment->invoice])->createStatus();

        return response()->json([
            'message' => 'Payment successfully deleted'
        ], 200);
    }
}
