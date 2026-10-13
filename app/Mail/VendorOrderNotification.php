<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class VendorOrderNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;
    public Vendor $vendor;
    public Collection $vendorItems;
    public float $grossAmount;
    public float $commissionRate;
    public float $commissionGstRate;
    public float $commissionFee;
    public float $commissionGst;
    public float $totalDeduction;
    public float $netPayout;

    public function __construct(Order $order, Vendor $vendor, Collection $vendorItems)
    {
        $this->order = $order;
        $this->vendor = $vendor;
        $this->vendorItems = $vendorItems;

        $this->grossAmount = (float) $vendorItems->sum(function ($item) {
            return ($item->quantity ?? 1) * ($item->price ?? 0);
        });

        $this->commissionRate = $vendor->commission_rate;
        $this->commissionGstRate = $vendor->commission_gst_rate;
        $this->commissionFee = $vendor->calculateCommissionFee($this->grossAmount);
        $this->commissionGst = $vendor->calculateCommissionGst($this->grossAmount);
        $this->totalDeduction = $vendor->calculateTotalDeduction($this->grossAmount);
        $this->netPayout = $vendor->calculateVendorPayout($this->grossAmount);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Order Received for ' . ($this->vendor->vendor_name ?? 'Your Store') . ' - #' . $this->order->order_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vendor-order-notification',
        );
    }
}
