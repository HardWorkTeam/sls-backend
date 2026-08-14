<?php

namespace App\Http\Controllers;

use App\Http\Requests\Gift\StoreGiftRequest;
use App\Http\Requests\Gift\UpdateGiftRequest;
use App\Http\Resources\GiftResource;
use App\Models\Gift;
use App\Models\Wedding;
use App\Repositories\GiftRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use App\Support\Excel;

class GiftController extends Controller
{
    public function __construct(private readonly GiftRepository $gifts) {}

    public function export(Request $request, Wedding $wedding): Response
    {
        $gifts = $this->gifts->allForWedding(
            $wedding,
            $request->query('gift_type'),
            $request->query('search')
        );

        $typeLabels = [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'item' => 'Gift Item',
        ];

        $rows = [];
        foreach ($gifts as $gift) {
            $rows[] = [
                $gift->guest?->name ?? 'Anonymous',
                $typeLabels[$gift->gift_type] ?? $gift->gift_type,
                $gift->gift_type === 'item' ? ($gift->item_name ?? '—') : number_format((float) $gift->amount, 2),
                $gift->gift_type === 'item' ? '—' : $gift->currency,
                $gift->note ?? '—',
                $gift->received_at?->format('Y-m-d H:i:s') ?? '—',
            ];
        }

        $xlsx = Excel::build(
            ['Guest Name', 'Type', 'Amount / Item', 'Currency', 'Note', 'Received At'],
            $rows,
            'Gifts'
        );

        $safeName = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $wedding->wedding_name ?? '');
        $safeName = trim(preg_replace('/\s+/', '_', $safeName));
        $date = $wedding->wedding_date?->format('Y-m-d');

        $filename = implode('-', array_filter([
            'gifts',
            $safeName !== '' ? $safeName : $wedding->wedding_code,
            $date,
        ])).'.xlsx';

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            implode('-', array_filter(['gifts', $wedding->wedding_code, $date])).'.xlsx'
        );

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => $disposition,
        ]);
    }

    public function index(Request $request, Wedding $wedding): AnonymousResourceCollection
    {
        return GiftResource::collection(
            $this->gifts->searchForWedding(
                $wedding,
                $request->query('gift_type'),
                (int) $request->query('per_page', '15'),
                $request->query('search'),
            ),
        );
    }

    public function store(StoreGiftRequest $request, Wedding $wedding): JsonResponse
    {
        $gift = $wedding->gifts()->create($request->validated() + ['received_at' => $request->validated('received_at') ?? now()]);

        return GiftResource::make($gift->load('guest'))->response()->setStatusCode(201);
    }

    public function update(UpdateGiftRequest $request, Wedding $wedding, Gift $gift): GiftResource
    {
        $gift->update($request->validated());

        return GiftResource::make($gift->load('guest'));
    }

    public function destroy(Wedding $wedding, Gift $gift): JsonResponse
    {
        $gift->delete();

        return response()->json(['message' => 'Gift deleted.']);
    }

    public function summary(Wedding $wedding): JsonResponse
    {
        return response()->json(['data' => $this->gifts->summaryForWedding($wedding)]);
    }
}
