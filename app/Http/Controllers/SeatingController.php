<?php

namespace App\Http\Controllers;

use App\Http\Requests\Seating\AssignSeatRequest;
use App\Http\Requests\Seating\ImportTablesRequest;
use App\Http\Requests\Seating\StoreTableRequest;
use App\Http\Requests\Seating\UpdateTableRequest;
use App\Http\Resources\WeddingTableResource;
use App\Models\Wedding;
use App\Models\WeddingTable;
use App\Services\SeatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SeatingController extends Controller
{
    public function __construct(private readonly SeatingService $seatingService) {}

    public function tables(Wedding $wedding): AnonymousResourceCollection
    {
        return WeddingTableResource::collection($this->seatingService->tables($wedding));
    }

    public function storeTable(StoreTableRequest $request, Wedding $wedding): JsonResponse
    {
        $table = $this->seatingService->createTable($wedding, $request->validated());

        return WeddingTableResource::make($table)->response()->setStatusCode(201);
    }

    public function updateTable(UpdateTableRequest $request, Wedding $wedding, WeddingTable $table): WeddingTableResource
    {
        return WeddingTableResource::make($this->seatingService->updateTable($table, $request->validated()));
    }

    public function destroyTable(Wedding $wedding, WeddingTable $table): JsonResponse
    {
        $this->seatingService->deleteTable($table);

        return response()->json(['message' => 'Table deleted.']);
    }

    public function assign(AssignSeatRequest $request, Wedding $wedding): JsonResponse
    {
        $this->seatingService->assign(
            $wedding,
            (int) $request->validated('guest_id'),
            (int) $request->validated('wedding_table_id'),
            $request->validated('seat_number') !== null ? (int) $request->validated('seat_number') : null,
        );

        return response()->json(['message' => 'Guest seated.']);
    }

    public function unassign(Request $request, Wedding $wedding): JsonResponse
    {
        $request->validate(['guest_id' => ['required', 'integer']]);

        $this->seatingService->unassign($wedding, (int) $request->input('guest_id'));

        return response()->json(['message' => 'Guest unseated.']);
    }

    public function autoSeat(Wedding $wedding): JsonResponse
    {
        $result = $this->seatingService->autoSeat($wedding);

        return response()->json([
            'message' => "Auto-seated {$result['seated']} guests ({$result['unseated']} left without a seat).",
            'data' => $result,
        ]);
    }

    public function report(Wedding $wedding): JsonResponse
    {
        return response()->json(['data' => $this->seatingService->report($wedding)]);
    }

    public function import(ImportTablesRequest $request, Wedding $wedding): JsonResponse
    {
        $result = $this->seatingService->importCsv($wedding, $request->file('file'));

        return response()->json([
            'message' => "Imported {$result['imported']} tables ({$result['skipped']} skipped).",
            'data' => $result,
        ]);
    }

    public function export(Wedding $wedding): Response
    {
        $xlsx = $this->seatingService->exportExcel($wedding);

        $safeName = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $wedding->wedding_name ?? '');
        $safeName = trim(preg_replace('/\s+/', '_', $safeName));
        $filename = $safeName
            ? "seating-{$safeName}-{$wedding->wedding_code}.xlsx"
            : "seating-{$wedding->wedding_code}.xlsx";

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
