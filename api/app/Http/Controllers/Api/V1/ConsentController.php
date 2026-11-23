<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Account\ConsentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConsentController extends Controller
{
    public function __construct(private readonly ConsentService $consents)
    {
    }

    /** GET /api/v1/me/consents — état des consentements. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['consents' => $this->consents->all((int) $request->user()->id)]);
    }

    /** PUT /api/v1/me/consents — accorder ou révoquer des consentements. */
    public function update(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $input = $request->input();
        $this->consents->update($userId, is_array($input) ? $input : []);

        return response()->json(['consents' => $this->consents->all($userId)]);
    }
}
