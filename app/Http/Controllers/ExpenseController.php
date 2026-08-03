<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Wedding;
use App\Repositories\ExpenseRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseRepository $expenses) {}

    public function index(Request $request, Wedding $wedding): AnonymousResourceCollection
    {
        return ExpenseResource::collection(
            $this->expenses->searchForWedding(
                $wedding,
                $request->query('status'),
                (int) $request->query('per_page', '15'),
            ),
        );
    }

    public function store(StoreExpenseRequest $request, Wedding $wedding): JsonResponse
    {
        $expense = $wedding->expenses()->create($request->validated());

        return ExpenseResource::make($expense)->response()->setStatusCode(201);
    }

    public function update(UpdateExpenseRequest $request, Wedding $wedding, Expense $expense): ExpenseResource
    {
        $expense->update($request->validated());

        return ExpenseResource::make($expense);
    }

    public function destroy(Wedding $wedding, Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    public function summary(Wedding $wedding): JsonResponse
    {
        return response()->json(['data' => $this->expenses->summaryForWedding($wedding)]);
    }
}
