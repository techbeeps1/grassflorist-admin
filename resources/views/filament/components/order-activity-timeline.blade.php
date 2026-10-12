@php
    $orderNumber = $order->order_number ?: $order->id;
    $customerName = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));
    $customerEmail = $order->email;
    $customerPhone = $order->customer_phone ?: ($order->recipient_phone ?? '');

    // 1. Fetch activities from order_activities table
    $dbActivities = \App\Models\OrderActivity::where('order_id', $order->id)->get();

    // 2. Fetch notifications from notification_logs (order_id match + historical fallback)
    $notifications = \App\Models\NotificationLog::where('order_id', $order->id)
        ->orWhere(function ($q) use ($order, $orderNumber, $customerEmail, $customerPhone) {
            $q->where(function ($sub) use ($customerEmail, $customerPhone) {
                if (!empty($customerEmail)) {
                    $sub->where('recipient', $customerEmail);
                }
                if (!empty($customerPhone)) {
                    $cleanPhone = substr(preg_replace('/\D/', '', $customerPhone), -9);
                    if (!empty($cleanPhone)) {
                        $sub->orWhere('recipient', 'like', "%{$cleanPhone}%");
                    }
                }
            })->where(function ($sub2) use ($orderNumber, $order) {
                $sub2->where('content', 'like', "%#{$orderNumber}%")
                     ->orWhere('content', 'like', "%#OR-{$order->id}%")
                     ->orWhere('content', 'like', "%#{$order->id}%")
                     ->orWhere('subject_or_sender', 'like', "%{$orderNumber}%");
            });
        })
        ->orderBy('created_at', 'asc')
        ->get();

    // 3. Fetch payment logs
    $paymentLogs = \App\Models\PaymentLog::where('order_id', $order->id)
        ->orWhere('order_number', (string)$orderNumber)
        ->orWhere('order_number', (string)$order->id)
        ->orderBy('created_at', 'asc')
        ->get();

    // Compile Unified Timeline Events
    $timeline = collect();

    $isPaypalOrder = strtolower((string)($order->payment_method ?? '')) === 'paypal';
    $calcRate = (float)($order->exchange_rate ?: 0.2667);
    $orderSarTotal = (float)($order->sar_amount ?: $order->total_amount);
    $orderUsdTotal = ($order->currency_amount && (float)$order->currency_amount < $orderSarTotal)
        ? (float)$order->currency_amount
        : round($orderSarTotal * $calcRate, 2);

    $formattedTotal = $isPaypalOrder
        ? '$' . number_format($orderUsdTotal, 2) . ' USD (' . number_format($orderSarTotal, 2) . ' SAR)'
        : number_format($orderSarTotal, 2) . ' SAR';

    // Milestone 1: Order Created
    if ($order->created_at) {
        $timeline->push([
            'id' => 'milestone-created',
            'type' => 'created',
            'category' => 'order',
            'badge' => 'Order Placed',
            'title' => "Order #{$orderNumber} Placed",
            'subtitle' => "Order submitted via storefront by " . ($customerName ?: 'Customer'),
            'actor' => 'Storefront Customer',
            'timestamp' => $order->created_at,
            'status' => 'completed',
            'details' => [
                'Customer' => ($customerName ?: 'Customer') . ($customerEmail ? " ({$customerEmail})" : ''),
                'Phone' => $customerPhone ?: 'N/A',
                'Total Amount' => $formattedTotal,
                'Language' => strtoupper((string)($order->order_language ?: 'AR')),
                'Payment Method' => strtoupper((string)($order->payment_method ?: 'N/A')),
                'Customer IP' => $order->customer_ip ?: 'N/A',
            ],
        ]);
    }

    // Milestone 2: Payment Received
    if ($order->date_paid || in_array(strtolower((string)$order->payment_status), ['paid', 'completed'])) {
        $paidTime = $order->date_paid ?: ($order->created_at ? $order->created_at->copy()->addSeconds(15) : now());
        $timeline->push([
            'id' => 'milestone-paid',
            'type' => 'paid',
            'category' => 'payment',
            'badge' => 'Payment Confirmed',
            'title' => "Payment Captured (" . strtoupper((string)($order->payment_method ?: 'ONLINE')) . ")",
            'subtitle' => "Full payment of {$formattedTotal} verified",
            'actor' => strtoupper((string)($order->payment_method ?: 'Payment Gateway')),
            'timestamp' => $paidTime,
            'status' => 'completed',
            'details' => [
                'Payment Status' => ucfirst((string)($order->payment_status ?: 'Paid')),
                'Method' => strtoupper((string)($order->payment_method ?: 'N/A')),
                'Amount' => $formattedTotal,
                'Transaction ID' => $order->razorpay_payment_id ?: ($order->razorpay_order_id ?: 'Verified'),
                'Paid Timestamp' => $paidTime->format('d M Y, h:i:s A'),
            ],
        ]);
    }

    // Milestone 3: DB Activities (status changes, manual notes, etc.)
    foreach ($dbActivities as $act) {
        if (in_array($act->type, ['created', 'paid'])) {
            continue; // Already synthesized cleanly above
        }

        $type = $act->type;
        $timeline->push([
            'id' => 'act-' . $act->id,
            'type' => $type,
            'category' => $type === 'status_change' ? 'status' : 'other',
            'badge' => ucfirst(str_replace('_', ' ', $type)),
            'title' => $act->title,
            'subtitle' => $act->description,
            'actor' => $act->user_name ?: 'Admin',
            'timestamp' => $act->created_at,
            'status' => 'completed',
            'details' => $act->properties ?? [],
        ]);
    }

    // Milestone 4: Notification Logs (Emails & SMS)
    foreach ($notifications as $notif) {
        $isEmail = $notif->channel === 'email';
        $timeline->push([
            'id' => 'notif-' . $notif->id,
            'type' => $isEmail ? 'email' : 'sms',
            'category' => $isEmail ? 'email' : 'sms',
            'badge' => $isEmail ? 'Email Alert' : 'SMS Alert',
            'title' => ($isEmail ? 'Email Sent: ' : 'SMS Sent: ') . ucfirst(str_replace('_', ' ', (string)$notif->event_key)),
            'subtitle' => $isEmail 
                ? "Subject: {$notif->subject_or_sender} • Recipient: {$notif->recipient}" 
                : "Sender: " . ($notif->subject_or_sender ?: 'GRASS') . " • Mobile: {$notif->recipient}",
            'actor' => $isEmail ? 'Mail Service' : 'Msegat Gateway',
            'timestamp' => $notif->created_at,
            'status' => $notif->status, // 'sent' or 'failed'
            'error_message' => $notif->error_message,
            'details' => [
                'Channel' => strtoupper($notif->channel),
                'Event' => $notif->event_key,
                'Recipient' => $notif->recipient,
                'Delivery Status' => ucfirst($notif->status),
                'Dispatched At' => $notif->created_at->format('d M Y, h:i:s A'),
            ],
            'content_preview' => $notif->content,
            'subject' => $notif->subject_or_sender,
            'raw_response' => $notif->response_payload,
        ]);
    }

    // Milestone 5: Payment Logs (callbacks/webhooks)
    foreach ($paymentLogs as $pl) {
        $timeline->push([
            'id' => 'paylog-' . $pl->id,
            'type' => 'payment_log',
            'category' => 'payment',
            'badge' => 'Gateway Webhook',
            'title' => "Payment Callback: " . strtoupper($pl->gateway) . " ({$pl->event_type})",
            'subtitle' => ($pl->message ?: "Payment event {$pl->event_type} registered") . " • Status: " . ucfirst($pl->status),
            'actor' => strtoupper($pl->gateway) . ' Server',
            'timestamp' => $pl->created_at,
            'status' => $pl->status === 'success' ? 'sent' : ($pl->status === 'failed' ? 'failed' : 'completed'),
            'details' => [
                'Gateway' => strtoupper($pl->gateway),
                'Event Type' => $pl->event_type,
                'Status' => $pl->status,
                'Amount' => $pl->amount ? number_format((float)$pl->amount, 2) : 'N/A',
                'Payment ID' => $pl->razorpay_payment_id ?: 'N/A',
            ],
        ]);
    }

    // CHRONOLOGICAL FLOW: Top (First/Oldest) ➔ Bottom (Last/Newest)
    $priority = ['created' => 0, 'paid' => 1, 'status_change' => 2, 'payment_log' => 3, 'email' => 4, 'sms' => 5];
    
    $chronologicalTimeline = $timeline->sort(function ($a, $b) use ($priority) {
        $timeA = $a['timestamp']->timestamp;
        $timeB = $b['timestamp']->timestamp;
        if ($timeA === $timeB) {
            $pA = $priority[$a['type']] ?? 9;
            $pB = $priority[$b['type']] ?? 9;
            return $pA <=> $pB;
        }
        return $timeA <=> $timeB;
    })->values();
@endphp

<style>
    /* Scoped Timeline Styles with Guaranteed Light & Dark Mode */
    .act-timeline-wrapper {
        position: relative;
        padding-top: 8px;
        padding-bottom: 8px;
        font-family: inherit;
    }
    .act-timeline-track {
        position: relative;
        padding-left: 24px;
    }
    [dir="rtl"] .act-timeline-track,
    .rtl .act-timeline-track {
        padding-left: 0;
        padding-right: 24px;
    }
    /* Vertical connecting spine */
    .act-timeline-track::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 24px;
        bottom: 24px;
        width: 2px;
        background: #e2e8f0;
        z-index: 1;
    }
    .dark .act-timeline-track::before {
        background: #334155;
    }
    [dir="rtl"] .act-timeline-track::before,
    .rtl .act-timeline-track::before {
        left: auto;
        right: 20px;
    }
    /* Timeline Item */
    .act-timeline-item {
        position: relative;
        margin-bottom: 22px;
        z-index: 2;
    }
    .act-timeline-item:last-child {
        margin-bottom: 0;
    }
    /* Step Circle Node */
    .act-node-icon {
        position: absolute;
        left: 0;
        top: 14px;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
        border: 3px solid #ffffff;
        z-index: 3;
    }
    .dark .act-node-icon {
        border-color: #0f172a;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    }
    [dir="rtl"] .act-node-icon,
    .rtl .act-node-icon {
        left: auto;
        right: 0;
    }
    .act-node-icon svg {
        width: 20px;
        height: 20px;
        stroke: #ffffff;
    }

    /* Node Color Schemes */
    .act-node-created {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    .act-node-paid {
        background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
    }
    .act-node-payment_log {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    }
    .act-node-status_change {
        background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
    }
    .act-node-email {
        background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    }
    .act-node-sms {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    .act-node-odoo_sync {
        background: linear-gradient(135deg, #86198f 0%, #581c87 100%);
    }
    .act-node-other {
        background: linear-gradient(135deg, #64748b 0%, #475569 100%);
    }

    /* Main Card Container */
    .act-card {
        margin-left: 56px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 18px 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    .dark .act-card {
        background: #111827;
        border-color: #1f2937;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
    }
    .act-card:hover {
        box-shadow: 0 8px 22px -4px rgba(0, 0, 0, 0.08);
        border-color: #cbd5e1;
    }
    .dark .act-card:hover {
        border-color: #374151;
        box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.45);
    }
    [dir="rtl"] .act-card,
    .rtl .act-card {
        margin-left: 0;
        margin-right: 56px;
    }

    /* Header Bar */
    .act-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .act-header-left {
        flex: 1 1 320px;
    }
    .act-header-right {
        text-align: right;
        flex-shrink: 0;
    }
    [dir="rtl"] .act-header-right,
    .rtl .act-header-right {
        text-align: left;
    }
    .act-badge-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
        flex-wrap: wrap;
    }
    .act-step-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 9px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    .act-badge-created {
        background: #ecfdf5;
        color: #065f46;
    }
    .dark .act-badge-created {
        background: rgba(16, 185, 129, 0.15);
        color: #6ee7b7;
    }
    .act-badge-paid {
        background: #f0fdfa;
        color: #115e59;
    }
    .dark .act-badge-paid {
        background: rgba(20, 184, 166, 0.15);
        color: #5eead4;
    }
    .act-badge-payment_log {
        background: #eef2ff;
        color: #3730a3;
    }
    .dark .act-badge-payment_log {
        background: rgba(99, 102, 241, 0.15);
        color: #a5b4fc;
    }
    .act-badge-status_change {
        background: #faf5ff;
        color: #581c87;
    }
    .dark .act-badge-status_change {
        background: rgba(168, 85, 247, 0.15);
        color: #d8b4fe;
    }
    .act-badge-email {
        background: #f0f9ff;
        color: #075985;
    }
    .dark .act-badge-email {
        background: rgba(14, 165, 233, 0.15);
        color: #7dd3fc;
    }
    .act-badge-sms {
        background: #fffbeb;
        color: #92400e;
    }
    .dark .act-badge-sms {
        background: rgba(245, 158, 11, 0.15);
        color: #fcd34d;
    }
    .act-badge-odoo_sync {
        background: #fdf4ff;
        color: #86198f;
    }
    .dark .act-badge-odoo_sync {
        background: rgba(134, 25, 143, 0.15);
        color: #f0abfc;
    }
    .act-badge-other {
        background: #f1f5f9;
        color: #334155;
    }
    .dark .act-badge-other {
        background: rgba(100, 116, 139, 0.2);
        color: #cbd5e1;
    }

    .act-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
    }
    .act-status-success {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
    }
    .dark .act-status-success {
        background: rgba(16, 185, 129, 0.12);
        color: #34d399;
        border-color: rgba(16, 185, 129, 0.3);
    }
    .act-status-failed {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }
    .dark .act-status-failed {
        background: rgba(239, 68, 68, 0.12);
        color: #f87171;
        border-color: rgba(239, 68, 68, 0.3);
    }

    .act-card-title {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 3px 0;
        line-height: 1.35;
    }
    .dark .act-card-title {
        color: #f8fafc;
    }
    .act-card-subtitle {
        font-size: 12px;
        color: #64748b;
        margin: 0;
        line-height: 1.45;
    }
    .dark .act-card-subtitle {
        color: #94a3b8;
    }
    .act-card-time {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        font-family: monospace;
    }
    .dark .act-card-time {
        color: #f8fafc;
    }
    .act-card-date {
        font-size: 11px;
        color: #64748b;
        margin-top: 3px;
    }
    .dark .act-card-date {
        color: #94a3b8;
    }
    .act-card-actor {
        font-weight: 600;
        color: #334155;
    }
    .dark .act-card-actor {
        color: #cbd5e1;
    }

    /* Structured Details Grid */
    .act-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 10px;
        margin-top: 14px;
    }
    .act-detail-card {
        padding: 9px 12px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .dark .act-detail-card {
        background: #1e293b;
        border-color: #334155;
    }
    .act-detail-card.act-detail-highlight {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    .dark .act-detail-card.act-detail-highlight {
        background: rgba(34, 197, 94, 0.1);
        border-color: rgba(34, 197, 94, 0.25);
    }
    .act-detail-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin-bottom: 3px;
    }
    .dark .act-detail-label {
        color: #94a3b8;
    }
    .act-detail-value {
        font-size: 12px;
        font-weight: 700;
        color: #0f172a;
        word-break: break-word;
        line-height: 1.35;
    }
    .dark .act-detail-value {
        color: #f1f5f9;
    }
    .act-detail-highlight .act-detail-value {
        color: #15803d;
    }
    .dark .act-detail-highlight .act-detail-value {
        color: #4ade80;
    }

    /* Status Transition Stepper */
    .act-status-transition {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin-top: 14px;
        padding: 8px 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }
    .dark .act-status-transition {
        background: #1e293b;
        border-color: #334155;
    }
    .act-status-chip-old {
        padding: 4px 10px;
        border-radius: 8px;
        background: #e2e8f0;
        color: #475569;
        font-size: 12px;
        font-weight: 600;
    }
    .dark .act-status-chip-old {
        background: #334155;
        color: #cbd5e1;
    }
    .act-status-arrow {
        color: #7c3aed;
        font-size: 14px;
        font-weight: 800;
    }
    .act-status-chip-new {
        padding: 4px 12px;
        border-radius: 8px;
        background: #ede9fe;
        color: #6d28d9;
        border: 1px solid #ddd6fe;
        font-size: 12px;
        font-weight: 700;
    }
    .dark .act-status-chip-new {
        background: rgba(124, 58, 237, 0.2);
        color: #c4b5fd;
        border-color: rgba(124, 58, 237, 0.4);
    }

    /* SMS Message Box */
    .act-sms-box {
        margin-top: 14px;
        padding: 12px 14px;
        border-radius: 12px;
        background: #fffbeb;
        border: 1px solid #fde68a;
    }
    .dark .act-sms-box {
        background: rgba(245, 158, 11, 0.08);
        border-color: rgba(245, 158, 11, 0.25);
    }
    .act-sms-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        font-weight: 700;
        color: #92400e;
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(245, 158, 11, 0.2);
        margin-bottom: 8px;
    }
    .dark .act-sms-header {
        color: #fcd34d;
        border-color: rgba(245, 158, 11, 0.2);
    }
    .act-sms-bubble {
        padding: 10px 12px;
        border-radius: 8px;
        background: #ffffff;
        border: 1px solid #fef3c7;
        font-size: 13px;
        line-height: 1.5;
        color: #1e293b;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }
    .dark .act-sms-bubble {
        background: #0f172a;
        border-color: #1e293b;
        color: #f1f5f9;
    }

    /* Email Box */
    .act-email-box {
        margin-top: 14px;
        padding: 12px 14px;
        border-radius: 12px;
        background: #f0f9ff;
        border: 1px solid #bae6fd;
    }
    .dark .act-email-box {
        background: rgba(14, 165, 233, 0.08);
        border-color: rgba(14, 165, 233, 0.25);
    }
    .act-email-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12px;
        color: #0369a1;
        gap: 12px;
        flex-wrap: wrap;
    }
    .dark .act-email-header {
        color: #7dd3fc;
    }
    .act-email-btn {
        background: #0284c7;
        color: #ffffff;
        border: none;
        border-radius: 6px;
        padding: 5px 12px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: background 0.15s;
    }
    .act-email-btn:hover {
        background: #0369a1;
    }
    .act-email-iframe-box {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #e0f2fe;
    }
    .dark .act-email-iframe-box {
        border-top-color: #1e293b;
    }

    /* Error Banner */
    .act-error-banner {
        margin-top: 12px;
        padding: 10px 14px;
        border-radius: 10px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        display: flex;
        align-items: flex-start;
        gap: 8px;
        font-size: 12px;
        color: #b91c1c;
    }
    .dark .act-error-banner {
        background: rgba(239, 68, 68, 0.1);
        border-color: rgba(239, 68, 68, 0.25);
        color: #fca5a5;
    }
</style>

<div class="act-timeline-wrapper" x-data="{ 
    expandedItem: null,
    toggle(id) { this.expandedItem = this.expandedItem === id ? null : id; }
}">

    @if($chronologicalTimeline->isEmpty())
        <div style="text-align: center; padding: 48px 16px; border-radius: 16px; border: 2px dashed #e2e8f0; background: #ffffff;" class="dark:border-gray-800 dark:bg-gray-900">
            <p style="font-size: 14px; font-weight: 600; color: #64748b;" class="dark:text-gray-400">No activity events recorded yet for this order.</p>
        </div>
    @else
        <div class="act-timeline-track">
            @foreach($chronologicalTimeline as $index => $event)
                @php
                    $stepNum = $index + 1;
                    $type = $event['type'];
                    $st = strtolower((string)($event['status'] ?? ''));
                    $isSuccess = in_array($st, ['sent', 'success', 'paid', 'completed']);
                @endphp

                <div class="act-timeline-item">

                    <!-- Continuous Stepper Circle Node -->
                    <div class="act-node-icon act-node-{{ $type }}">
                        @if($type === 'created')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        @elseif($type === 'paid')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        @elseif($type === 'payment_log')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        @elseif($type === 'status_change')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        @elseif($type === 'email')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        @elseif($type === 'sms')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        @elseif($type === 'odoo_sync')
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        @else
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        @endif
                    </div>

                    <!-- Step Container Card -->
                    <div class="act-card">
                        
                        <!-- Top Header: Step Badge + Title + Timestamp -->
                        <div class="act-card-header">
                            <div class="act-header-left">
                                <div class="act-badge-row">
                                    <span class="act-step-badge act-badge-{{ $type }}">
                                        Step #{{ $stepNum }} • {{ $event['badge'] }}
                                    </span>

                                    <!-- Delivery Status Pill -->
                                    @if(isset($event['status']))
                                        @if($isSuccess)
                                            <span class="act-status-pill act-status-success">
                                                <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                <span>{{ ucfirst($event['status']) }}</span>
                                            </span>
                                        @else
                                            <span class="act-status-pill act-status-failed">
                                                <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                                <span>Failed Delivery</span>
                                            </span>
                                        @endif
                                    @endif
                                </div>

                                <h4 class="act-card-title">
                                    {{ $event['title'] }}
                                </h4>

                                <p class="act-card-subtitle">
                                    {{ $event['subtitle'] }}
                                </p>
                            </div>

                            <!-- Timestamp & Actor -->
                            <div class="act-header-right">
                                <div class="act-card-time">
                                    {{ $event['timestamp']->format('h:i:s A') }}
                                </div>
                                <div class="act-card-date">
                                    {{ $event['timestamp']->format('d M Y') }} • <span class="act-card-actor">{{ $event['actor'] }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Failure Error Callout if any -->
                        @if(!empty($event['error_message']))
                            <div class="act-error-banner">
                                <svg style="width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <div>
                                    <strong style="font-weight: 700;">Gateway Delivery Error:</strong>
                                    <div style="font-family: monospace; font-size: 11px; margin-top: 2px;">{{ $event['error_message'] }}</div>
                                </div>
                            </div>
                        @endif

                        <!-- ========================================================= -->
                        <!-- RICH CONTENT DISPLAY PER EVENT TYPE -->
                        <!-- ========================================================= -->

                        <!-- A. Order Created Grid -->
                        @if($type === 'created' && !empty($event['details']))
                            <div class="act-detail-grid">
                                @foreach($event['details'] as $k => $v)
                                    @php $isTotal = strtolower($k) === 'total amount'; @endphp
                                    <div class="act-detail-card {{ $isTotal ? 'act-detail-highlight' : '' }}">
                                        <span class="act-detail-label">{{ $k }}</span>
                                        <span class="act-detail-value">{{ $v }}</span>
                                    </div>
                                @endforeach
                            </div>

                        <!-- B. Payment Verified Badge & Data -->
                        @elseif($type === 'paid' && !empty($event['details']))
                            <div class="act-detail-grid">
                                @foreach($event['details'] as $k => $v)
                                    @php $isHighlight = in_array(strtolower($k), ['amount', 'payment status']); @endphp
                                    <div class="act-detail-card {{ $isHighlight ? 'act-detail-highlight' : '' }}">
                                        <span class="act-detail-label">{{ $k }}</span>
                                        <span class="act-detail-value">{{ $v }}</span>
                                    </div>
                                @endforeach
                            </div>

                        <!-- C. Payment Logs (Callbacks/Webhooks) -->
                        @elseif($type === 'payment_log' && !empty($event['details']))
                            <div class="act-detail-grid">
                                @foreach($event['details'] as $k => $v)
                                    <div class="act-detail-card">
                                        <span class="act-detail-label">{{ $k }}</span>
                                        <span class="act-detail-value">{{ $v }}</span>
                                    </div>
                                @endforeach
                            </div>

                        <!-- D. Status Change Visual Stepper -->
                        @elseif($type === 'status_change')
                            @php
                                $from = $event['details']['from_status'] ?? ($event['details']['from'] ?? 'previous');
                                $to = $event['details']['to_status'] ?? ($event['details']['to'] ?? 'new');
                            @endphp
                            <div class="act-status-transition">
                                <span class="act-status-chip-old">
                                    {{ ucfirst(str_replace('_', ' ', (string)$from)) }}
                                </span>
                                <span class="act-status-arrow">➔</span>
                                <span class="act-status-chip-new">
                                    {{ ucfirst(str_replace('_', ' ', (string)$to)) }}
                                </span>
                            </div>

                        <!-- E. SMS Message Chat Bubble -->
                        @elseif($type === 'sms' && !empty($event['content_preview']))
                            <div class="act-sms-box">
                                <div class="act-sms-header">
                                    <span>📱 SMS Dispatch via مسجات (Msegat)</span>
                                    <span>Recipient: <strong style="font-family: monospace;">{{ $event['details']['Recipient'] ?? $customerPhone }}</strong></span>
                                </div>
                                <div class="act-sms-bubble"
                                     style="direction: {{ preg_match('/[\x{0600}-\x{06FF}]/u', $event['content_preview']) ? 'rtl' : 'ltr' }};">
                                    {{ $event['content_preview'] }}
                                </div>
                            </div>

                        <!-- F. Email Content Preview -->
                        @elseif($type === 'email' && !empty($event['content_preview']))
                            <div class="act-email-box">
                                <div class="act-email-header">
                                    <span style="font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                                        <span>✉️ Email Sent:</span>
                                        <strong style="color: inherit;">{{ $event['subject'] ?: 'Grass Florist Notification' }}</strong>
                                    </span>
                                    <button type="button" @click="toggle('{{ $event['id'] }}')" class="act-email-btn">
                                        <span x-text="expandedItem === '{{ $event['id'] }}' ? 'Close Preview ▲' : 'Open Email Preview ▼'"></span>
                                    </button>
                                </div>

                                <div x-show="expandedItem === '{{ $event['id'] }}'" x-collapse class="act-email-iframe-box">
                                    <div style="display: flex; justify-content: flex-end; margin-bottom: 8px;">
                                        <button type="button" 
                                                onclick="const w = window.open(); w.document.write(decodeURIComponent('{{ rawurlencode($event['content_preview']) }}')); w.document.close();" 
                                                style="font-size: 11px; font-weight: 700; color: #0284c7; background: transparent; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                            <span>Open in New Tab</span> ↗
                                        </button>
                                    </div>
                                    <iframe :srcdoc="@js($event['content_preview'])" 
                                            style="width: 100%; min-height: 480px; border: 1px solid #cbd5e1; border-radius: 8px; background: #ffffff;"></iframe>
                                </div>
                            </div>

                        <!-- G. Generic DB Activity Details -->
                        @elseif(!empty($event['details']) && is_array($event['details']))
                            <div class="act-detail-grid">
                                @foreach($event['details'] as $k => $v)
                                    <div class="act-detail-card">
                                        <span class="act-detail-label">{{ $k }}</span>
                                        <span class="act-detail-value">{{ is_array($v) ? json_encode($v) : $v }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
