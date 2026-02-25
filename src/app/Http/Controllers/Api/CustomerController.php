<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;

final class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 10), 50));

        $paginator = Customer::query()
            ->latestFirst()
            ->paginate($perPage)
            ->appends($request->query());

        return CustomerResource::collection($paginator);
    }
}
