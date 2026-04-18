<?php

namespace App\Observers;

use App\Models\Shipment;
use App\Models\User;
use App\Services\Activity\UserActivityLogger;
use App\Support\UserActivityAction;

class ShipmentObserver
{
    public function __construct(
        protected UserActivityLogger $activityLogger
    ) {}

    public function updated(Shipment $shipment): void
    {
        if (! $shipment->wasChanged('status')) {
            return;
        }

        $user = User::find($shipment->user_id);
        if ($user === null) {
            return;
        }

        $meta = ['shipment_id' => $shipment->id];

        if ($shipment->status === Shipment::STATUS_SHIPPED && $shipment->getOriginal('status') !== Shipment::STATUS_SHIPPED) {
            $this->activityLogger->log(
                $user,
                UserActivityAction::SHIPMENT_SHIPPED,
                'Shipment #'.$shipment->id.' marked as shipped',
                null,
                array_merge($meta, [
                    'carrier' => $shipment->carrier,
                    'tracking_number' => $shipment->tracking_number,
                ]),
                null
            );
        }

        if ($shipment->status === Shipment::STATUS_DELIVERED && $shipment->getOriginal('status') !== Shipment::STATUS_DELIVERED) {
            $this->activityLogger->log(
                $user,
                UserActivityAction::SHIPMENT_DELIVERED,
                'Shipment #'.$shipment->id.' delivered',
                null,
                $meta,
                null
            );
        }
    }
}
