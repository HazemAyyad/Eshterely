<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;
use App\Support\AdminFulfillmentLabels;
use App\Support\AdminUserDisplay;
use Illuminate\Support\Str;

class OutboundShipmentsAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.shipments.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Shipment::query()
            ->with(['user', 'destinationAddress.city', 'destinationAddress.country', 'items.orderLineItem', 'payments']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return DataTables::eloquent($query)
            ->addColumn('customer', function (Shipment $s) {
                $u = $s->user;
                if (! $u) {
                    return '—';
                }
                $name = e(AdminUserDisplay::primaryName($u));
                $phone = $u->phone ? '<div class="text-muted small">'.e($u->phone).'</div>' : '';

                return '<div><a href="'.route('admin.users.show', $u).'" class="fw-semibold">'.$name.'</a></div>'.$phone;
            })
            ->addColumn('destination', function (Shipment $s) {
                $a = $s->destinationAddress;
                if (! $a) {
                    return '—';
                }
                $parts = array_filter([
                    $a->city?->name ?? null,
                    $a->country?->code ?? null,
                ]);
                $line = trim((string) ($a->address_line ?? $a->street_address ?? ''));

                return e(Str::limit(($line !== '' ? $line.' — ' : '').implode(', ', $parts), 48));
            })
            ->addColumn('items_count', fn (Shipment $s) => (string) $s->items->count())
            ->addColumn('payment', function (Shipment $s) {
                $paid = $s->payments->contains(fn ($p) => $p->status->value === 'paid');
                if ($paid) {
                    return '<span class="badge bg-success">'.e(__('admin.shipping_paid_badge')).'</span>';
                }
                $await = $s->status === Shipment::STATUS_AWAITING_PAYMENT;

                return $await
                    ? '<span class="badge bg-warning">'.e(__('admin.awaiting_shipping_payment_badge')).'</span>'
                    : '<span class="badge bg-secondary">'.e(__('admin.pending')).'</span>';
            })
            ->addColumn('status', function (Shipment $s) {
                $p = AdminFulfillmentLabels::outboundShipment($s->status);

                return '<span class="badge bg-'.$p['badge'].'">'.$p['label'].'</span>';
            })
            ->addColumn('carrier_tracking', function (Shipment $s) {
                $c = $s->carrier ? e(Str::limit($s->carrier, 12)) : '—';
                $t = $s->tracking_number ? e(Str::limit($s->tracking_number, 16)) : '—';

                return $c.' / '.$t;
            })
            ->addColumn('actions', function (Shipment $s) {
                $show = route('admin.shipments.show', $s);
                $pack = route('admin.shipments.pack-form', $s);
                $ship = route('admin.shipments.ship-form', $s);

                $btns = '<a href="'.$show.'" class="btn btn-sm btn-outline-primary">'.e(__('admin.view')).'</a> ';
                if (in_array($s->status, [Shipment::STATUS_PAID, Shipment::STATUS_PACKED], true)) {
                    $btns .= '<a href="'.$pack.'" class="btn btn-sm btn-primary">'.e(__('admin.pack_shipment')).'</a> ';
                }
                if ($s->status === Shipment::STATUS_PACKED) {
                    $btns .= '<a href="'.$ship.'" class="btn btn-sm btn-success">'.e(__('admin.mark_shipped')).'</a>';
                }

                return '<div class="d-flex flex-wrap gap-1">'.$btns.'</div>';
            })
            ->editColumn('id', fn (Shipment $s) => (string) $s->id)
            ->rawColumns(['customer', 'payment', 'status', 'actions'])
            ->toJson();
    }

    public function show(Shipment $shipment): View
    {
        $shipment->load([
            'user',
            'destinationAddress.city',
            'destinationAddress.country',
            'items.orderLineItem.shipment.order',
            'items.orderLineItem.cartItem',
            'items.orderLineItem.importedProduct',
            'items.orderLineItem.warehouseReceipts' => fn ($q) => $q->orderByDesc('received_at'),
            'payments',
        ]);

        return view('admin.shipments.show', compact('shipment'));
    }

    public function packForm(Shipment $shipment): View
    {
        $shipment->load(['items.orderLineItem']);

        return view('admin.shipments.pack', compact('shipment'));
    }

    public function shipForm(Shipment $shipment): View
    {
        return view('admin.shipments.ship', compact('shipment'));
    }
}
