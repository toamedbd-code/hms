<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Support\Str;

class ExpenseService
{
    protected $expense;

    public function __construct(Expense $expense)
    {
        $this->expense = $expense;
    }

    public function list()
    {
        return $this->expense->with('expenseHead')
            ->orderBy('created_at', 'desc');
    }

    public function create(array $data)
    {
        if (empty($data['bill_number'])) {
            $data['bill_number'] = $this->generateInvoiceNumber();
        }

        return $this->expense->create($data);
    }

    public function find($id)
    {
        return $this->expense->with('expenseHead')->findOrFail($id);
    }

    public function update(array $data, $id)
    {
        $expense = $this->find($id);
        $expense->fill($data);
        $expense->save();
        
        return $expense;
    }

    public function delete($id)
    {
        $expense = $this->find($id);
        $expense->status = 'Deleted';
        $expense->save();
        
        return $expense->delete();
    }

    public function changeStatus($id, $status)
    {
        $expense = $this->find($id);
        $expense->status = $status;
        $expense->save();
        
        return $expense;
    }

    private function generateInvoiceNumber()
    {
        do {
            $invoiceNumber = 'Bill' . date('Ymd') . strtoupper(Str::random(6));
        } while ($this->expense->where('bill_number', $invoiceNumber)->exists());

        return $invoiceNumber;
    }

    public function getByDateRange($startDate, $endDate)
    {
        return $this->expense->with('expenseHead')
            ->dateRange($startDate, $endDate)
            ->active()
            ->orderBy('date', 'desc')
            ->get();
    }

    public function getTotalByExpenseHead($expenseHeaderId, $startDate = null, $endDate = null)
    {
        $query = $this->expense->where('expense_header_id', $expenseHeaderId)
            ->active();

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        return $query->sum('amount');
    }

    public function getMonthlyExpenseSummary($year = null, $month = null)
    {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');

        return $this->expense->with('expenseHead')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->active()
            ->selectRaw('expense_header_id, SUM(amount) as total_amount, COUNT(*) as total_count')
            ->groupBy('expense_header_id')
            ->get();
    }

    public function search($searchTerm)
    {
        return $this->expense->with('expenseHead')
            ->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('bill_number', 'like', "%{$searchTerm}%")
                    ->orWhere('case_id', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            })
            ->orderBy('created_at', 'desc');
    }
}