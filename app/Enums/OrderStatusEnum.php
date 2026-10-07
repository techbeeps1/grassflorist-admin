<?php 

namespace App\Enums;

enum OrderStatusEnum: string
{
    case NEW = 'new';
    case PENDING_PAYMENT = 'pending_payment';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PRINTED = 'printed';
    case SHIPPED = 'shipped';
    case ORDER_SHIPPED = 'order_shipped';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';
    case DECLINED = 'declined';
}